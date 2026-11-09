<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ayuda;
use App\Models\AyudaDefault;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Controller that handles CRUD and restore operations for system help (ayuda).
 */
class AyudaController extends Controller
{
    /**
     * Return help entries for a given path (query param `path` or `ruta`).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $path = $request->query('path', $request->query('ruta'));
        $show_all = $request->query('all') === '1';
        $user_id = $this->get_authenticated_help_user_id();

        $query = Ayuda::query();
        if ($path) {
            $this->apply_path_filter($query, $path);
        }

        if (! $show_all && $user_id !== null) {
            $query->where('id_user', $user_id);
        }

        $results = $query
            ->orderBy('ruta')
            ->orderBy('path')
            ->orderBy('id_ayuda')
            ->get();

        if (! $show_all && $results->isEmpty() && $path) {
            $results = $this->sync_default_help_for_user($path, $user_id);
        }

        return response()->json($this->build_help_response_payload($results));
    }

    /**
     * Store a new help entry.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->get_help_validation_rules());
        $data = $this->normalize_help_data($data);

        $help = Ayuda::create($data);
        return response()->json($help, 201);
    }

    /**
     * Update an existing help entry.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $help = $this->find_help_or_fail($id, $request->boolean('all'));
        $this->assert_help_is_mutable($help);
        $data = $request->validate($this->get_help_validation_rules());
        $data = $this->normalize_help_data($data, $help);

        $help->update($data);
        return response()->json($help);
    }

    /**
     * Delete a help entry.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        request()->validate([
            'all' => 'nullable',
        ]);

        $help = $this->find_help_or_fail($id, request()->boolean('all'));
        $this->assert_help_is_mutable($help);
        $help->delete();
        return response()->json(['deleted' => true]);
    }

    /**
     * Build API payload for help entries adding mutability flags.
     *
     * @param Collection<int, Ayuda|AyudaDefault> $results
     * @return array<int, array<string, mixed>>
     */
    private function build_help_response_payload(Collection $results): array
    {
        return $results->map(function ($help): array {
            $is_system_default = $help instanceof Ayuda && $this->is_system_default_help($help);

            return [
                ...$help->toArray(),
                'is_system_default' => $is_system_default,
                'can_edit' => ! $is_system_default,
                'can_delete' => ! $is_system_default,
            ];
        })->all();
    }

    /**
     * Restore the ayuda table from ayuda_default.
     * Expects `path`/`ruta` for route restore or `all=1` for full restore.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function restore_default(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path' => 'nullable|string',
            'ruta' => 'nullable|string',
            'all' => 'nullable',
        ]);
        $restore_all = $request->boolean('all');
        $path = $data['path'] ?? $data['ruta'] ?? null;
        $user_id = $this->get_authenticated_help_user_id();

        if ($restore_all) {
            $restored_count = $this->restore_all_default_help($user_id);
            return response()->json(['restored' => $restored_count]);
        }

        if (! $path) {
            return response()->json(['error' => 'path is required'], 422);
        }

        $defaults = $this->get_default_help_query($path)->get();

        $this->delete_restored_help_scope($path, $user_id);

        $inserted = [];
        foreach ($defaults as $d) {
            $inserted[] = Ayuda::create([
                'topico' => $d->topico,
                'subtopico' => $d->subtopico,
                'contenido' => $d->contenido,
                'ruta' => $d->ruta,
                'path' => $d->path,
                'id_user' => $user_id ?? $d->id_user,
            ]);
        }

        return response()->json(['restored' => count($inserted)]);
    }

    /**
     * Restore all help entries from ayuda_default.
     *
     * @param int|null $user_id
     * @return int
     */
    private function restore_all_default_help(?int $user_id): int
    {
        $this->delete_restored_help_scope(null, $user_id);

        $defaults = AyudaDefault::query()
            ->orderBy('ruta')
            ->orderBy('path')
            ->orderBy('id_ayuda')
            ->get();

        $inserted_count = 0;

        foreach ($defaults as $default_help) {
            Ayuda::create([
                'topico' => $default_help->topico,
                'subtopico' => $default_help->subtopico,
                'contenido' => $default_help->contenido,
                'ruta' => $default_help->ruta,
                'path' => $default_help->path,
                'id_user' => $user_id ?? $default_help->id_user,
            ]);

            $inserted_count++;
        }

        return $inserted_count;
    }

    /**
     * Delete the help entries that must be replaced during a restore operation.
     *
     * @param string|null $path
     * @param int|null $user_id
     * @return void
     */
    private function delete_restored_help_scope(?string $path, ?int $user_id): void
    {
        $current_help_query = Ayuda::query();

        if ($path !== null) {
            $this->apply_path_filter($current_help_query, $path);
        }

        $current_help_query->where(function (Builder $restore_query) use ($user_id): void {
            $restore_query->whereNull('id_user');

            if ($user_id !== null) {
                $restore_query->orWhere('id_user', $user_id);
            }
        });

        $current_help_query->delete();
    }

    /**
     * Return the shared validation rules for help create and update operations.
     *
     * @return array<string, mixed>
     */
    private function get_help_validation_rules(): array
    {
        return [
            'topico' => 'nullable|string',
            'subtopico' => 'nullable|string',
            'contenido' => 'nullable|string',
            'ruta' => 'nullable|string',
            'path' => 'nullable|string',
            'id_user' => 'nullable|integer',
        ];
    }

    /**
     * Normalize help payload data before persisting it.
     *
     * @param array<string, mixed> $data
     * @param Ayuda|null $help
     * @return array<string, mixed>
     */
    private function normalize_help_data(array $data, ?Ayuda $help = null): array
    {
        $data['ruta'] = $data['ruta'] ?? $data['path'] ?? $help?->ruta;
        $data['path'] = $data['path'] ?? $data['ruta'] ?? $help?->path;
        $data['id_user'] = $this->get_authenticated_help_user_id() ?? $data['id_user'] ?? $help?->id_user;

        return $data;
    }

    /**
     * Apply the route path filter to a query using both `ruta` and `path`.
     *
     * @param Builder $query
     * @param string $path
     * @return void
     */
    private function apply_path_filter(Builder $query, string $path): void
    {
        $query->where(function (Builder $nested_query) use ($path): void {
            $nested_query
                ->where('ruta', $path)
                ->orWhere('path', $path);
        });
    }

    /**
     * Return the default help query for a given route path.
     *
     * @param string $path
     * @return Builder
     */
    private function get_default_help_query(string $path): Builder
    {
        $query = AyudaDefault::query();
        $this->apply_path_filter($query, $path);

        return $query->orderBy('id_ayuda');
    }

    /**
     * Provision the authenticated user help from defaults when no managed help exists yet.
     *
     * @param string $path
     * @param int|null $user_id
     * @return Collection<int, Ayuda|AyudaDefault>
     */
    private function sync_default_help_for_user(string $path, ?int $user_id): Collection
    {
        $defaults = $this->get_default_help_query($path)->get();

        if ($user_id === null || $defaults->isEmpty()) {
            return $defaults;
        }

        return $defaults->map(function (AyudaDefault $default_help) use ($user_id): Ayuda {
            return Ayuda::create([
                'topico' => $default_help->topico,
                'subtopico' => $default_help->subtopico,
                'contenido' => $default_help->contenido,
                'ruta' => $default_help->ruta,
                'path' => $default_help->path,
                'id_user' => $user_id,
            ]);
        });
    }

    /**
     * Find a managed help entry for the authenticated user or globally.
     *
     * @param int $id
     * @param bool $allow_global
     * @return Ayuda
     */
    private function find_help_or_fail(int $id, bool $allow_global = false): Ayuda
    {
        $query = Ayuda::query()->where('id_ayuda', $id);
        $user_id = $this->get_authenticated_help_user_id();

        if (! $allow_global && $user_id !== null) {
            $query->where('id_user', $user_id);
        }

        return $query->firstOrFail();
    }

    /**
     * Ensure default seeded help entries cannot be edited or deleted.
     *
     * @param Ayuda $help
     * @return void
     */
    private function assert_help_is_mutable(Ayuda $help): void
    {
        if ($this->is_system_default_help($help)) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'Default help entries cannot be edited or deleted.'
                ], 403)
            );
        }
    }

    /**
     * Determine whether a managed help entry comes from seeded default content.
     *
     * @param Ayuda $help
     * @return bool
     */
    private function is_system_default_help(Ayuda $help): bool
    {
        return AyudaDefault::query()
            ->where('ruta', $help->ruta)
            ->where('path', $help->path)
            ->where('topico', $help->topico)
            ->where('subtopico', $help->subtopico)
            ->where('contenido', $help->contenido)
            ->exists();
    }

    /**
     * Resolve the authenticated help user id from the local Sanctum user.
     *
     * @return int|null
     */
    private function get_authenticated_help_user_id(): ?int
    {
        $local_user_id = Auth::id();

        return $local_user_id !== null ? (int) $local_user_id : null;
    }
}
