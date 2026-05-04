<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@oficina.com'],
            [
                'name' => 'Administrador',
                'email' => 'admin@oficina.com',
                'password' => Hash::make('password'),
            ]
        );
    }
}
