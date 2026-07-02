<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Initial admin account — credentials MUST be changed after first login.
        User::updateOrCreate(
            ['email' => 'admin@lajur.id'],
            [
                'name' => 'Administrator Lajur',
                'password' => Hash::make('password'),
                'is_admin' => true,
            ]
        );

        $this->call([
            CarSeeder::class,
            TestimonialSeeder::class,
        ]);
    }
}
