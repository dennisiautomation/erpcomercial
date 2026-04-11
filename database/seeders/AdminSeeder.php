<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@ia365.com.br'],
            [
                'name' => 'Admin IA365',
                'password' => Hash::make('admin123'),
                'perfil' => 'admin',
                'is_admin' => true,
                'status' => 'ativo',
            ]
        );
    }
}
