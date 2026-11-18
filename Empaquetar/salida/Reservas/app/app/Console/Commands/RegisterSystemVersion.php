<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SystemVersion;
use Illuminate\Console\Command;

/**
 * Register a system version in the system_versions table.
 *
 * With --if-different nothing is inserted when the latest registered version is already the given one
 * (the desktop package launcher runs it on every start).
 *
 * @example php artisan system:version 1 0 0 0 --description="Paquete 1.0.0.0" --if-different
 */
class RegisterSystemVersion extends Command
{
    protected $signature = 'system:version {major} {minor} {app_change} {db_change} {--description=} {--if-different}';

    protected $description = 'Register a new system version in the system_versions table';

    public function handle(): int
    {
        $major = (int) $this->argument('major');
        $minor = (int) $this->argument('minor');
        $app_change = (int) $this->argument('app_change');
        $db_change = (int) $this->argument('db_change');
        $description = (string) ($this->option('description') ?? '');

        if ($this->option('if-different')) {
            $latest = SystemVersion::query()->orderByDesc('applied_at')->orderByDesc('id')->first();

            if ($latest !== null
                && (int) $latest->major === $major
                && (int) $latest->minor === $minor
                && (int) $latest->app_change === $app_change
                && (int) $latest->db_change === $db_change
            ) {
                $this->info('La versión ' . $latest->getVersionString() . ' ya está registrada.');

                return self::SUCCESS;
            }
        }

        $version = SystemVersion::query()->create([
            'major' => $major,
            'minor' => $minor,
            'app_change' => $app_change,
            'db_change' => $db_change,
            'description' => $description,
            'applied_at' => now(),
        ]);

        $this->info('Nueva versión registrada: ' . $version->getVersionString());

        return self::SUCCESS;
    }
}
