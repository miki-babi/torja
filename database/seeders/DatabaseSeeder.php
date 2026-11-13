<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call(LanguageSeeder::class);
        $this->call(TranslationSeeder::class);
        $this->call(FoodDatabaseSeeder::class);
        $this->call(ExerciseDatabaseSeeder::class);
        $this->call(RolesAndPermissionsSeeder::class);

        $staff = User::updateOrCreate(
            ['email' => 'staff@example.com'],
            [
                'name' => 'Staff',
                'password' => 'password',
                'role' => 'staff',
                'email_verified_at' => now(),
            ],
        );
        $staff->assignRole('super-admin');

        $client = User::updateOrCreate(
            ['email' => 'client@example.com'],
            [
                'name' => 'Test Client',
                'password' => 'password',
                'role' => 'client',
                'email_verified_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'role' => 'client',
                'email_verified_at' => now(),
            ],
        );

        $trainer = User::updateOrCreate(
            ['email' => 'trainer@example.com'],
            [
                'name' => 'Test Trainer',
                'password' => 'password',
                'role' => 'trainer',
                'email_verified_at' => now(),
            ],
        )->ensureTrainer();

        // A live offer so the demo public page has something to sell.
        $trainer->plans()->firstOrCreate(
            ['name' => 'Monthly 1:1 Coaching'],
            [
                'description' => 'Custom program, weekly check-ins and chat access.',
                'kind' => 'one_on_one',
                'price_etb' => 2000,
                'duration_days' => 30,
            ],
        );

        $secondTrainer = User::updateOrCreate(
            ['email' => 'trainer2@example.com'],
            [
                'name' => 'Second Trainer',
                'password' => 'password',
                'role' => 'trainer',
                'email_verified_at' => now(),
            ],
        )->ensureTrainer();

        // One client coached by two trainers exercises the many-to-many link.
        $trainer->clients()->syncWithoutDetaching([$client->id]);
        $secondTrainer->clients()->syncWithoutDetaching([$client->id]);
    }
}
