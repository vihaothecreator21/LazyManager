<?php

namespace Database\Seeders;

use App\Domain\Enums\UserRole;
use App\Domain\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $demoUsers = [
            [
                'name' => 'Quản lý demo',
                'email' => 'manager@example.com',
                'role' => UserRole::StoreManager,
                'status' => UserStatus::Active,
            ],
            [
                'name' => 'Nhân viên demo',
                'email' => 'staff@example.com',
                'role' => UserRole::Staff,
                'status' => UserStatus::Active,
            ],
            [
                'name' => 'Tài khoản bị khóa',
                'email' => 'locked@example.com',
                'role' => UserRole::Staff,
                'status' => UserStatus::Locked,
            ],
        ];

        foreach ($demoUsers as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    ...$user,
                    'password' => Hash::make('password'),
                ],
            );
        }
    }
}
