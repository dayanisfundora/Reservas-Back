<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Global business identity (name, logo, theme palette).
 */
class BusinessSetting extends Model
{
    private const CACHE_KEY = 'business_settings.current';

    public const DEFAULT_PRESET = 'vino';

    /** Editable palette keys; must match PALETTE_KEYS in Front/src/assets/js/util/theme.js. */
    public const DEFAULT_PALETTE = [
        'wine-deep' => '#4a111b',
        'wine' => '#7a1f2b',
        'wine-light' => '#8e2a36',
        'ink' => '#2a1418',
        'ink-2' => '#3b1a20',
        'gold' => '#b8893b',
        'gold-light' => '#d4af6a',
        'gold-pale' => '#efe0bf',
        'ivory' => '#faf6f0',
        'ivory-2' => '#f3ece1',
        'dark' => '#241a1c',
        'dark-page' => '#1a1214',
    ];

    protected $fillable = [
        'name',
        'tagline',
        'logo_path',
        'favicon_path',
        'theme_preset',
        'palette',
    ];

    protected function casts(): array
    {
        return [
            'palette' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget(self::CACHE_KEY);
        });
    }

    public static function current(): self
    {
        $id = Cache::rememberForever(self::CACHE_KEY, function () {
            $row = static::query()->first();

            if ($row) {
                return $row->id;
            }

            return static::query()->create(self::defaults())->id;
        });

        $setting = static::query()->find($id);
        if (! $setting) {
            Cache::forget(self::CACHE_KEY);
            $setting = static::query()->create(self::defaults());
        }

        return $setting;
    }

    public static function defaults(): array
    {
        return [
            'name' => 'Reservas',
            'tagline' => 'Gestión de hospedaje',
            'theme_preset' => self::DEFAULT_PRESET,
            'palette' => self::DEFAULT_PALETTE,
        ];
    }

    /** Palette with defaults filled in for any missing key. */
    public function fullPalette(): array
    {
        return array_merge(self::DEFAULT_PALETTE, array_intersect_key($this->palette ?? [], self::DEFAULT_PALETTE));
    }

    public function logoUrl(): ?string
    {
        return $this->assetUrl('logo', $this->logo_path);
    }

    public function faviconUrl(): ?string
    {
        return $this->assetUrl('favicon', $this->favicon_path);
    }

    /** Absolute filesystem path of the logo or favicon, or null if missing. */
    public function assetFilePath(string $type): ?string
    {
        $path = $type === 'favicon' ? $this->favicon_path : $this->logo_path;
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->path($path);
    }

    public function toBrandArray(): array
    {
        return [
            'name' => $this->name,
            'tagline' => $this->tagline,
            'logo_url' => $this->logoUrl(),
            'favicon_url' => $this->faviconUrl(),
            'theme_preset' => $this->theme_preset,
            'palette' => $this->fullPalette(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** Served through the API so it works without `php artisan storage:link`. */
    private function assetUrl(string $type, ?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return url('api/branding/'.$type).'?v='.($this->updated_at?->timestamp ?? 0);
    }
}
