<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([RoleSeeder::class, ContentSeeder::class]);

        User::firstOrCreate(
            ['email' => 'admin@backendjourney.test'],
            ['name' => 'Irwanda', 'password' => 'password123', 'locale' => 'id'],
        )->syncRoles(['admin', 'learner']);

        User::firstOrCreate(
            ['email' => 'learner@backendjourney.test'],
            ['name' => 'Learner', 'password' => 'password123', 'locale' => 'en'],
        )->syncRoles(['learner']);
    }
}
