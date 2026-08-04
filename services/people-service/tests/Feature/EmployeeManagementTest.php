<?php

namespace Tests\Feature;

use App\Domain\Enums\UserRole;
use App\Domain\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_list_employees(): void
    {
        $manager = $this->createUser(email: 'manager@example.com', role: UserRole::StoreManager);
        $this->createUser(email: 'staff@example.com', role: UserRole::Staff);

        $this->actingAsManager($manager)
            ->getJson('/api/v1/employees')
            ->assertOk()
            ->assertJsonPath('employees.0.email', 'manager@example.com')
            ->assertJsonPath('employees.1.email', 'staff@example.com')
            ->assertJsonMissingPath('employees.0.password')
            ->assertJsonMissingPath('employees.1.password');
    }

    public function test_staff_can_list_employees(): void
    {
        $staff = $this->createUser(email: 'staff@example.com', role: UserRole::Staff);

        $this->actingWithRole($staff, UserRole::Staff)
            ->getJson('/api/v1/employees')
            ->assertOk()
            ->assertJsonPath('employees.0.email', 'staff@example.com');
    }

    public function test_manager_can_create_employee(): void
    {
        $manager = $this->createUser(email: 'manager@example.com', role: UserRole::StoreManager);

        $this->actingAsManager($manager)
            ->withValidCsrf()
            ->postJson('/api/v1/employees', [
                'name' => 'Nhân viên mới',
                'email' => 'new.staff@example.com',
                'password' => 'password123',
                'role' => UserRole::Staff->value,
                'status' => UserStatus::Active->value,
            ])
            ->assertCreated()
            ->assertJsonPath('employee.name', 'Nhân viên mới')
            ->assertJsonPath('employee.email', 'new.staff@example.com')
            ->assertJsonPath('employee.role', UserRole::Staff->value)
            ->assertJsonPath('employee.status', UserStatus::Active->value)
            ->assertJsonMissingPath('employee.password');

        $created = User::query()->where('email', 'new.staff@example.com')->first();
        $this->assertNotNull($created);
        $this->assertTrue(Hash::check('password123', $created->password));
    }

    public function test_staff_cannot_create_employee(): void
    {
        $staff = $this->createUser(email: 'staff@example.com', role: UserRole::Staff);

        $this->actingWithRole($staff, UserRole::Staff)
            ->withValidCsrf()
            ->postJson('/api/v1/employees', [
                'name' => 'Nhân viên mới',
                'email' => 'new.staff@example.com',
                'password' => 'password123',
                'role' => UserRole::Staff->value,
                'status' => UserStatus::Active->value,
            ])
            ->assertForbidden();
    }

    public function test_locked_manager_cannot_list_employees(): void
    {
        $manager = $this->createUser(
            email: 'manager@example.com',
            role: UserRole::StoreManager,
            status: UserStatus::Locked,
        );

        $this->actingAsManager($manager)
            ->getJson('/api/v1/employees')
            ->assertUnauthorized();
    }

    public function test_create_employee_requires_csrf(): void
    {
        $manager = $this->createUser(email: 'manager@example.com', role: UserRole::StoreManager);

        $this->actingAsManager($manager)
            ->postJson('/api/v1/employees', [
                'name' => 'Nhân viên mới',
                'email' => 'new.staff@example.com',
                'password' => 'password123',
                'role' => UserRole::Staff->value,
                'status' => UserStatus::Active->value,
            ])
            ->assertStatus(419);
    }

    public function test_create_employee_rejects_duplicate_email(): void
    {
        $manager = $this->createUser(email: 'manager@example.com', role: UserRole::StoreManager);
        $this->createUser(email: 'taken@example.com', role: UserRole::Staff);

        $this->actingAsManager($manager)
            ->withValidCsrf()
            ->postJson('/api/v1/employees', [
                'name' => 'Nhân viên mới',
                'email' => 'taken@example.com',
                'password' => 'password123',
                'role' => UserRole::Staff->value,
                'status' => UserStatus::Active->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_manager_can_update_employee_without_password(): void
    {
        $manager = $this->createUser(email: 'manager@example.com', role: UserRole::StoreManager);
        $employee = $this->createUser(email: 'staff@example.com', role: UserRole::Staff);
        $oldPassword = $employee->password;

        $this->actingAsManager($manager)
            ->withValidCsrf()
            ->putJson('/api/v1/employees/'.$employee->id, [
                'name' => 'Nhân viên đã sửa',
                'email' => 'staff.renamed@example.com',
                'role' => UserRole::StoreManager->value,
                'status' => UserStatus::Locked->value,
            ])
            ->assertOk()
            ->assertJsonPath('employee.name', 'Nhân viên đã sửa')
            ->assertJsonPath('employee.email', 'staff.renamed@example.com')
            ->assertJsonPath('employee.role', UserRole::StoreManager->value)
            ->assertJsonPath('employee.status', UserStatus::Locked->value)
            ->assertJsonMissingPath('employee.password');

        $employee->refresh();
        $this->assertSame($oldPassword, $employee->password);
    }

    public function test_update_employee_allows_current_email(): void
    {
        $manager = $this->createUser(email: 'manager@example.com', role: UserRole::StoreManager);
        $employee = $this->createUser(email: 'staff@example.com', role: UserRole::Staff);

        $this->actingAsManager($manager)
            ->withValidCsrf()
            ->putJson('/api/v1/employees/'.$employee->id, [
                'email' => 'staff@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('employee.email', 'staff@example.com');
    }

    public function test_update_employee_rejects_duplicate_email(): void
    {
        $manager = $this->createUser(email: 'manager@example.com', role: UserRole::StoreManager);
        $employee = $this->createUser(email: 'staff@example.com', role: UserRole::Staff);
        $this->createUser(email: 'taken@example.com', role: UserRole::Staff);

        $this->actingAsManager($manager)
            ->withValidCsrf()
            ->putJson('/api/v1/employees/'.$employee->id, [
                'email' => 'taken@example.com',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_employee_can_change_password(): void
    {
        $manager = $this->createUser(email: 'manager@example.com', role: UserRole::StoreManager);
        $employee = $this->createUser(email: 'staff@example.com', role: UserRole::Staff);

        $this->actingAsManager($manager)
            ->withValidCsrf()
            ->putJson('/api/v1/employees/'.$employee->id, [
                'password' => 'new-password',
            ])
            ->assertOk();

        $employee->refresh();
        $this->assertTrue(Hash::check('new-password', $employee->password));
    }

    public function test_manager_delete_locks_employee(): void
    {
        $manager = $this->createUser(email: 'manager@example.com', role: UserRole::StoreManager);
        $employee = $this->createUser(email: 'staff@example.com', role: UserRole::Staff);

        $this->actingAsManager($manager)
            ->withValidCsrf()
            ->deleteJson('/api/v1/employees/'.$employee->id)
            ->assertNoContent();

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'status' => UserStatus::Locked->value,
        ]);
    }

    private function createUser(
        string $email,
        UserRole $role,
        UserStatus $status = UserStatus::Active,
    ): User {
        return User::factory()->create([
            'name' => 'Nhân viên test',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function actingAsManager(User $manager): self
    {
        return $this->actingWithRole($manager, UserRole::StoreManager);
    }

    private function actingWithRole(User $user, UserRole $role): self
    {
        /** @var \App\Application\Interfaces\JwtServiceInterface $jwtService */
        $jwtService = $this->app->make(\App\Application\Interfaces\JwtServiceInterface::class);

        return $this
            ->withCredentials()
            ->withUnencryptedCookie('lm_access_token', $jwtService->issueToken($user->id, $role)->token);
    }

    private function withValidCsrf(): self
    {
        return $this
            ->withUnencryptedCookie('lm_csrf_token', 'test-csrf-token')
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token');
    }
}
