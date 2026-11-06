<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        // Demo data only for development; production installs (desktop package) get the admin user only
        $this->call(app()->environment(['local', 'testing']) ? DemoReservationSeeder::class : AdminUserSeeder::class);
    }
}
