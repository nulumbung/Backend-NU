<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class RolesAndUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'superadmin@nulumbung.or.id'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('@Pat1muan'),
                'role' => 'superadmin',
                'avatar' => null,
                'auth_provider' => 'email',
            ]
        );
    }
}
