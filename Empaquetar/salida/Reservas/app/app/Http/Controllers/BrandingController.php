<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandingController extends Controller
{
    private const DIRECTORY = 'branding';

    /** Public: the login page needs the brand before authenticating. */
    public function show(): JsonResponse
    {
        return response()->json(BusinessSetting::current()->toBrandArray());
    }

    /** Public: logo or favicon file. */
    public function asset(string $type): BinaryFileResponse
    {
        $path = BusinessSetting::current()->assetFilePath($type);
        abort_unless($path, 404);

        return response()->file($path, ['Cache-Control' => 'public, max-age=604800']);
    }

    public function update(Request $request): JsonResponse
    {
        if (is_string($request->input('palette'))) {
            $request->merge(['palette' => json_decode($request->input('palette'), true)]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:80',
            'tagline' => 'nullable|string|max:120',
            'theme_preset' => 'nullable|string|max:40',
            'palette' => 'required|array',
            'palette.*' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:2048',
            'favicon' => 'nullable|file|mimes:png,ico,jpg,jpeg,webp|max:512',
            'remove_logo' => 'nullable|boolean',
            'remove_favicon' => 'nullable|boolean',
        ], [
            'name.required' => 'El nombre del negocio es obligatorio.',
            'name.max' => 'El nombre no puede tener más de 80 caracteres.',
            'tagline.max' => 'El eslogan no puede tener más de 120 caracteres.',
            'palette.*.regex' => 'Cada color debe tener el formato #RRGGBB.',
            'logo.mimes' => 'El logo debe ser PNG, JPG o WEBP.',
            'logo.max' => 'El logo no puede pesar más de 2 MB.',
            'favicon.mimes' => 'El favicon debe ser PNG, ICO, JPG o WEBP.',
            'favicon.max' => 'El favicon no puede pesar más de 512 KB.',
        ]);

        $unknown = array_diff(array_keys($data['palette']), array_keys(BusinessSetting::DEFAULT_PALETTE));
        if ($unknown) {
            throw ValidationException::withMessages([
                'palette' => 'Colores no reconocidos: '.implode(', ', $unknown),
            ]);
        }

        $setting = BusinessSetting::current();

        $setting->fill([
            'name' => trim($data['name']),
            'tagline' => $request->exists('tagline') ? (trim((string) ($data['tagline'] ?? '')) ?: null) : $setting->tagline,
            'theme_preset' => $data['theme_preset'] ?? 'custom',
            'palette' => array_merge(BusinessSetting::DEFAULT_PALETTE, array_map('strtolower', $data['palette'])),
        ]);

        $setting->logo_path = $this->resolveFile($request->file('logo'), $setting->logo_path, $request->boolean('remove_logo'), 'logo');
        $setting->favicon_path = $this->resolveFile($request->file('favicon'), $setting->favicon_path, $request->boolean('remove_favicon'), 'favicon');

        $setting->save();

        return response()->json([
            'message' => 'Identidad del negocio actualizada.',
            'brand' => $setting->fresh()->toBrandArray(),
        ]);
    }

    private function resolveFile(?UploadedFile $file, ?string $current, bool $remove, string $prefix): ?string
    {
        if (! $file && ! $remove) {
            return $current;
        }

        if ($current && Storage::disk('public')->exists($current)) {
            Storage::disk('public')->delete($current);
        }

        if (! $file) {
            return null;
        }

        $name = $prefix.'-'.Str::random(8).'.'.strtolower($file->getClientOriginalExtension());

        return $file->storeAs(self::DIRECTORY, $name, 'public');
    }
}
