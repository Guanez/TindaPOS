<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Store Owner',
                'username' => 'owner',
                'email' => 'owner@tindapos.ph',
                'password' => 'owner123',
                'role' => 'owner',
            ],
            [
                'name' => 'Store Admin',
                'username' => 'admin',
                'email' => 'admin@tindapos.ph',
                'password' => 'owner123',
                'role' => 'admin',
            ],
            [
                'name' => 'Juan Cashier',
                'username' => 'cashier',
                'email' => 'cashier@tindapos.ph',
                'password' => 'owner123',
                'role' => 'cashier',
            ],
        ];

        foreach ($users as $userData) {
            User::create($userData); // password is auto-hashed via cast
        }
    }
}
