<?php

namespace Tests\Feature;

use App\Domain\Enums\UserRole;
use App\Domain\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_week_schedule_with_seven_days_and_two_shifts(): void
    {
        $manager = $this->createUser('manager@example.com', UserRole::StoreManager);

        $this->actingWithRole($manager, UserRole::StoreManager)
            ->getJson('/api/v1/schedules?week_start=2026-07-27')
            ->assertOk()
            ->assertJsonPath('title', 'Lịch làm việc (27/07/2026 - 02/08/2026)')
            ->assertJsonPath('week_start', '2026-07-27')
            ->assertJsonPath('week_end', '2026-08-02')
            ->assertJsonCount(7, 'days')
            ->assertJsonPath('days.0.weekday_label', 'Thứ 2')
            ->assertJsonPath('days.0.morning.label', 'SÁNG (8:30 - 15:30)')
            ->assertJsonPath('days.0.afternoon.label', 'CHIỀU (15:00 - 22:00)')
            ->assertJsonPath('days.6.weekday_label', 'Chủ nhật');
    }

    public function test_staff_can_view_week_schedule(): void
    {
        $staff = $this->createUser('staff@example.com', UserRole::Staff);

        $this->actingWithRole($staff, UserRole::Staff)
            ->getJson('/api/v1/schedules?week_start=2026-07-27')
            ->assertOk();
    }

    public function test_schedule_requires_authentication(): void
    {
        $this->getJson('/api/v1/schedules?week_start=2026-07-27')
            ->assertUnauthorized();
    }

    public function test_manager_can_assign_employee_to_morning_shift(): void
    {
        $manager = $this->createUser('manager@example.com', UserRole::StoreManager, name: 'Quản lý');
        $employee = $this->createUser('vy@example.com', UserRole::Staff, name: 'Vy');

        $this->actingWithRole($manager, UserRole::StoreManager)
            ->withValidCsrf()
            ->postJson('/api/v1/schedules/assignments', [
                'work_date' => '2026-07-27',
                'shift_type' => 'MORNING',
                'employee_id' => $employee->id,
                'note' => 'out 18h',
            ])
            ->assertCreated()
            ->assertJsonPath('assignment.work_date', '2026-07-27')
            ->assertJsonPath('assignment.shift_type', 'MORNING')
            ->assertJsonPath('assignment.employee.name', 'Vy')
            ->assertJsonPath('assignment.note', 'out 18h');
    }

    public function test_assigning_employee_uses_database_lock_for_shift_capacity(): void
    {
        $manager = $this->createUser('manager@example.com', UserRole::StoreManager);
        $employee = $this->createUser('vy@example.com', UserRole::Staff, name: 'Vy');
        $queries = [];

        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->actingWithRole($manager, UserRole::StoreManager)
            ->withValidCsrf()
            ->postJson('/api/v1/schedules/assignments', [
                'work_date' => '2026-07-27',
                'shift_type' => 'MORNING',
                'employee_id' => $employee->id,
            ])
            ->assertCreated();

        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->assertTrue(
                collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'pg_advisory_xact_lock')),
            );
        } else {
            $this->assertFalse(
                collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'pg_advisory_xact_lock')),
            );
        }
    }

    public function test_staff_can_assign_employee_to_shift(): void
    {
        $staff = $this->createUser('staff@example.com', UserRole::Staff);
        $employee = $this->createUser('hao@example.com', UserRole::Staff, name: 'Hảo');

        $this->actingWithRole($staff, UserRole::Staff)
            ->withValidCsrf()
            ->postJson('/api/v1/schedules/assignments', [
                'work_date' => '2026-07-27',
                'shift_type' => 'AFTERNOON',
                'employee_id' => $employee->id,
            ])
            ->assertCreated();
    }

    public function test_assign_shift_requires_csrf(): void
    {
        $manager = $this->createUser('manager@example.com', UserRole::StoreManager);
        $employee = $this->createUser('vy@example.com', UserRole::Staff, name: 'Vy');

        $this->actingWithRole($manager, UserRole::StoreManager)
            ->postJson('/api/v1/schedules/assignments', [
                'work_date' => '2026-07-27',
                'shift_type' => 'MORNING',
                'employee_id' => $employee->id,
            ])
            ->assertStatus(419);
    }

    public function test_cannot_assign_more_than_two_employees_to_same_shift(): void
    {
        $manager = $this->createUser('manager@example.com', UserRole::StoreManager);
        $first = $this->createUser('vy@example.com', UserRole::Staff, name: 'Vy');
        $second = $this->createUser('na@example.com', UserRole::Staff, name: 'Na');
        $third = $this->createUser('hao@example.com', UserRole::Staff, name: 'Hảo');

        foreach ([$first, $second] as $employee) {
            $this->actingWithRole($manager, UserRole::StoreManager)
                ->withValidCsrf()
                ->postJson('/api/v1/schedules/assignments', [
                    'work_date' => '2026-07-27',
                    'shift_type' => 'MORNING',
                    'employee_id' => $employee->id,
                ])
                ->assertCreated();
        }

        $this->actingWithRole($manager, UserRole::StoreManager)
            ->withValidCsrf()
            ->postJson('/api/v1/schedules/assignments', [
                'work_date' => '2026-07-27',
                'shift_type' => 'MORNING',
                'employee_id' => $third->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Mỗi ca chỉ được có tối đa 2 nhân viên.');
    }

    public function test_cannot_assign_same_employee_twice_to_same_shift(): void
    {
        $manager = $this->createUser('manager@example.com', UserRole::StoreManager);
        $employee = $this->createUser('vy@example.com', UserRole::Staff, name: 'Vy');

        for ($i = 0; $i < 2; $i++) {
            $response = $this->actingWithRole($manager, UserRole::StoreManager)
                ->withValidCsrf()
                ->postJson('/api/v1/schedules/assignments', [
                    'work_date' => '2026-07-27',
                    'shift_type' => 'MORNING',
                    'employee_id' => $employee->id,
                ]);
        }

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Nhân viên đã được gán vào ca này.');
    }

    public function test_employee_can_work_morning_and_afternoon_on_same_day(): void
    {
        $manager = $this->createUser('manager@example.com', UserRole::StoreManager);
        $employee = $this->createUser('vy@example.com', UserRole::Staff, name: 'Vy');

        foreach (['MORNING', 'AFTERNOON'] as $shiftType) {
            $this->actingWithRole($manager, UserRole::StoreManager)
                ->withValidCsrf()
                ->postJson('/api/v1/schedules/assignments', [
                    'work_date' => '2026-07-27',
                    'shift_type' => $shiftType,
                    'employee_id' => $employee->id,
                ])
                ->assertCreated();
        }
    }

    public function test_cannot_assign_locked_employee_to_shift(): void
    {
        $manager = $this->createUser('manager@example.com', UserRole::StoreManager);
        $employee = $this->createUser('locked@example.com', UserRole::Staff, UserStatus::Locked);

        $this->actingWithRole($manager, UserRole::StoreManager)
            ->withValidCsrf()
            ->postJson('/api/v1/schedules/assignments', [
                'work_date' => '2026-07-27',
                'shift_type' => 'MORNING',
                'employee_id' => $employee->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);
    }

    public function test_manager_can_mark_employee_off_for_day(): void
    {
        $manager = $this->createUser('manager@example.com', UserRole::StoreManager);
        $employee = $this->createUser('thoai@example.com', UserRole::Staff, name: 'Thoại');

        $this->actingWithRole($manager, UserRole::StoreManager)
            ->withValidCsrf()
            ->postJson('/api/v1/schedules/day-offs', [
                'off_date' => '2026-07-27',
                'employee_id' => $employee->id,
            ])
            ->assertCreated()
            ->assertJsonPath('day_off.off_date', '2026-07-27')
            ->assertJsonPath('day_off.employee.name', 'Thoại');
    }

    public function test_staff_can_mark_employee_off_for_day(): void
    {
        $staff = $this->createUser('staff@example.com', UserRole::Staff);
        $employee = $this->createUser('na@example.com', UserRole::Staff, name: 'Na');

        $this->actingWithRole($staff, UserRole::Staff)
            ->withValidCsrf()
            ->postJson('/api/v1/schedules/day-offs', [
                'off_date' => '2026-07-28',
                'employee_id' => $employee->id,
            ])
            ->assertCreated();
    }

    public function test_cannot_mark_same_employee_off_twice_for_same_day(): void
    {
        $manager = $this->createUser('manager@example.com', UserRole::StoreManager);
        $employee = $this->createUser('thoai@example.com', UserRole::Staff, name: 'Thoại');

        for ($i = 0; $i < 2; $i++) {
            $response = $this->actingWithRole($manager, UserRole::StoreManager)
                ->withValidCsrf()
                ->postJson('/api/v1/schedules/day-offs', [
                    'off_date' => '2026-07-27',
                    'employee_id' => $employee->id,
                ]);
        }

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Nhân viên đã được đánh OFF ngày này.');
    }

    public function test_cannot_mark_locked_employee_off(): void
    {
        $manager = $this->createUser('manager@example.com', UserRole::StoreManager);
        $employee = $this->createUser('locked@example.com', UserRole::Staff, UserStatus::Locked);

        $this->actingWithRole($manager, UserRole::StoreManager)
            ->withValidCsrf()
            ->postJson('/api/v1/schedules/day-offs', [
                'off_date' => '2026-07-27',
                'employee_id' => $employee->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);
    }

    public function test_day_off_requires_csrf(): void
    {
        $manager = $this->createUser('manager@example.com', UserRole::StoreManager);
        $employee = $this->createUser('thoai@example.com', UserRole::Staff);

        $this->actingWithRole($manager, UserRole::StoreManager)
            ->postJson('/api/v1/schedules/day-offs', [
                'off_date' => '2026-07-27',
                'employee_id' => $employee->id,
            ])
            ->assertStatus(419);
    }

    public function test_cannot_assign_employee_when_employee_is_off_that_day(): void
    {
        $manager = $this->createUser('manager@example.com', UserRole::StoreManager);
        $employee = $this->createUser('vy@example.com', UserRole::Staff, name: 'Vy');

        $this->actingWithRole($manager, UserRole::StoreManager)
            ->withValidCsrf()
            ->postJson('/api/v1/schedules/day-offs', [
                'off_date' => '2026-07-27',
                'employee_id' => $employee->id,
            ])
            ->assertCreated();

        $this->actingWithRole($manager, UserRole::StoreManager)
            ->withValidCsrf()
            ->postJson('/api/v1/schedules/assignments', [
                'work_date' => '2026-07-27',
                'shift_type' => 'MORNING',
                'employee_id' => $employee->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Nhân viên đang OFF ngày này.');
    }

    public function test_cannot_mark_employee_off_when_employee_has_shift_that_day(): void
    {
        $manager = $this->createUser('manager@example.com', UserRole::StoreManager);
        $employee = $this->createUser('vy@example.com', UserRole::Staff, name: 'Vy');

        $this->actingWithRole($manager, UserRole::StoreManager)
            ->withValidCsrf()
            ->postJson('/api/v1/schedules/assignments', [
                'work_date' => '2026-07-27',
                'shift_type' => 'MORNING',
                'employee_id' => $employee->id,
            ])
            ->assertCreated();

        $this->actingWithRole($manager, UserRole::StoreManager)
            ->withValidCsrf()
            ->postJson('/api/v1/schedules/day-offs', [
                'off_date' => '2026-07-27',
                'employee_id' => $employee->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Nhân viên đã có ca trong ngày này.');
    }

    public function test_can_delete_assignment(): void
    {
        $manager = $this->createUser('manager@example.com', UserRole::StoreManager);
        $employee = $this->createUser('vy@example.com', UserRole::Staff, name: 'Vy');

        $assignmentId = $this->actingWithRole($manager, UserRole::StoreManager)
            ->withValidCsrf()
            ->postJson('/api/v1/schedules/assignments', [
                'work_date' => '2026-07-27',
                'shift_type' => 'MORNING',
                'employee_id' => $employee->id,
            ])
            ->json('assignment.id');

        $this->actingWithRole($manager, UserRole::StoreManager)
            ->withValidCsrf()
            ->deleteJson('/api/v1/schedules/assignments/'.$assignmentId)
            ->assertNoContent();
    }

    public function test_can_delete_day_off(): void
    {
        $manager = $this->createUser('manager@example.com', UserRole::StoreManager);
        $employee = $this->createUser('na@example.com', UserRole::Staff, name: 'Na');

        $dayOffId = $this->actingWithRole($manager, UserRole::StoreManager)
            ->withValidCsrf()
            ->postJson('/api/v1/schedules/day-offs', [
                'off_date' => '2026-07-27',
                'employee_id' => $employee->id,
            ])
            ->json('day_off.id');

        $this->actingWithRole($manager, UserRole::StoreManager)
            ->withValidCsrf()
            ->deleteJson('/api/v1/schedules/day-offs/'.$dayOffId)
            ->assertNoContent();
    }

    public function test_week_schedule_includes_assignments_and_day_offs(): void
    {
        $manager = $this->createUser('manager@example.com', UserRole::StoreManager);
        $vy = $this->createUser('vy@example.com', UserRole::Staff, name: 'Vy');
        $thoai = $this->createUser('thoai@example.com', UserRole::Staff, name: 'Thoại');

        $this->actingWithRole($manager, UserRole::StoreManager)
            ->withValidCsrf()
            ->postJson('/api/v1/schedules/assignments', [
                'work_date' => '2026-07-27',
                'shift_type' => 'MORNING',
                'employee_id' => $vy->id,
                'note' => 'out 18h',
            ])
            ->assertCreated();

        $this->actingWithRole($manager, UserRole::StoreManager)
            ->withValidCsrf()
            ->postJson('/api/v1/schedules/day-offs', [
                'off_date' => '2026-07-27',
                'employee_id' => $thoai->id,
            ])
            ->assertCreated();

        $this->actingWithRole($manager, UserRole::StoreManager)
            ->getJson('/api/v1/schedules?week_start=2026-07-27')
            ->assertOk()
            ->assertJsonPath('days.0.morning.assignments.0.employee.name', 'Vy')
            ->assertJsonPath('days.0.morning.assignments.0.note', 'out 18h')
            ->assertJsonPath('days.0.offs.0.employee.name', 'Thoại');
    }

    private function createUser(
        string $email,
        UserRole $role,
        UserStatus $status = UserStatus::Active,
        string $name = 'Nhân viên test',
    ): User {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => $status,
        ]);
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
