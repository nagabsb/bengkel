<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $shouldForceDemoSeed = filter_var(
            (string) env('SEED_DEMO_DATA', 'false'),
            FILTER_VALIDATE_BOOLEAN,
        );

        // Safety default: do not overwrite existing tenant data on migrate --seed.
        if (! $shouldForceDemoSeed && Schema::hasTable('users') && User::query()->exists()) {
            $this->command?->warn('DatabaseSeeder: dilewati (sudah ada data user). Gunakan SEED_DEMO_DATA=true jika ingin seed demo.');

            return;
        }

        $this->call([
            SaasDemoSeeder::class,
        ]);
    }
}
