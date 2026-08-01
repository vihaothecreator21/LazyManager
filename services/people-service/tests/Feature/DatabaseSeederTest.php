<?php

namespace Tests\Feature;

use App\Domain\Enums\UserRole;
use App\Domain\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_does_not_overwrite_existing_manager_password(): void
    {
        $manager = User::factory()->create([
            'name' => 'Quản lý đã sửa',
            'email' => 'manager@example.com',
            'password' => Hash::make('custom-password'),
            'role' => UserRole::StoreManager,
            'status' => UserStatus::Active,
        ]);

        $this->seed(DatabaseSeeder::class);

        $manager->refresh();
        $this->assertTrue(Hash::check('custom-password', $manager->password));
        $this->assertFalse(Hash::check('Tangvihao211', $manager->password));
    }
}
