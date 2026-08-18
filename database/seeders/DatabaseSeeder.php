<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Credenciales del usuario admin documentadas en README.md — solo para
     * entornos local/pruebas, nunca correr este seeder en producción.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin Donaciones Rolda',
            'email' => 'admin@donaciones-rolda.test',
            'password' => Hash::make('AdminRolda#2026'),
        ]);
    }
}
