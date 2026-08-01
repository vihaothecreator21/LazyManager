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
        $adminEmail = env('ADMIN_EMAIL', 'manager@example.com');
        $adminPassword = env('ADMIN_PASSWORD');

        if (empty($adminPassword)) {
            $adminPassword = \Illuminate\Support\Str::random(12);
            $this->command->warn("ADMIN_PASSWORD is not set. Generating a random one...");
        }

        $this->command->info("Manager Email: $adminEmail");
        $this->command->info("Manager Password: $adminPassword");

        $demoUsers = [
            [
                'name' => 'Quản lý demo',
                'email' => $adminEmail,
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
            $password = ($user['email'] === $adminEmail) ? $adminPassword : 'password';

            User::query()->firstOrCreate(
                ['email' => $user['email']],
                [
                    ...$user,
                    'password' => Hash::make($password),
                ],
            );
        }
    }
}
