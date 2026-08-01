# Schedule Management UC-07/08/09 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Xây backend Schedule Management cho lịch tuần giống bảng mẫu: 7 ngày, ca sáng, ca chiều, và hàng OFF theo từng ngày.

**Architecture:** People Service lưu lịch bằng hai bảng nhỏ: `shift_assignments` cho người đi làm theo ca và `schedule_day_offs` cho người nghỉ theo ngày. API trả về cấu trúc đã gom sẵn theo tuần để frontend dựng bảng giống ảnh mẫu mà không phải tự ráp dữ liệu phức tạp.

**Tech Stack:** PHP 8.3, Laravel 13, PostgreSQL, PHPUnit feature tests, Docker Compose.

## Global Constraints

- Tất cả text tiếng Việt trong code response, docs, message backend phải có dấu đầy đủ.
- Không thêm dependency mới.
- Không tạo bảng `employees` trong task này; dùng `users` làm nhân viên theo Employee Management hiện tại.
- Không làm frontend trong task này.
- Không làm nghỉ phép, đổi ca, publish lịch, version lịch, check-in/check-out.
- API ghi dữ liệu phải dùng cookie auth và CSRF hiện có.
- Rule đã chốt: nhân viên đã OFF trong một ngày không được gán ca ngày đó; nhân viên đã có ca trong ngày không được đánh OFF ngày đó.

---

## API Target

### Xem lịch tuần

```text
GET /api/people/v1/schedules?week_start=2026-07-27
```

Response:

```json
{
  "title": "Lịch làm việc (27/07/2026 - 02/08/2026)",
  "week_start": "2026-07-27",
  "week_end": "2026-08-02",
  "days": [
    {
      "date": "2026-07-27",
      "weekday_label": "Thứ 2",
      "morning": {
        "label": "SÁNG (8:30 - 15:30)",
        "assignments": [
          {
            "id": 1,
            "employee": {
              "id": 2,
              "name": "Vy",
              "email": "vy@example.com"
            },
            "note": "out 18h"
          }
        ]
      },
      "afternoon": {
        "label": "CHIỀU (15:00 - 22:00)",
        "assignments": []
      },
      "offs": [
        {
          "id": 1,
          "employee": {
            "id": 3,
            "name": "Thoại",
            "email": "thoai@example.com"
          },
          "note": null
        }
      ]
    }
  ]
}
```

### Gán nhân viên vào ca

```text
POST /api/people/v1/schedules/assignments
```

Request:

```json
{
  "work_date": "2026-07-27",
  "shift_type": "MORNING",
  "employee_id": 2,
  "note": "out 18h"
}
```

Response `201`:

```json
{
  "assignment": {
    "id": 1,
    "work_date": "2026-07-27",
    "shift_type": "MORNING",
    "employee": {
      "id": 2,
      "name": "Vy",
      "email": "vy@example.com"
    },
    "note": "out 18h"
  }
}
```

### Gỡ nhân viên khỏi ca

```text
DELETE /api/people/v1/schedules/assignments/{id}
```

Response `204`.

### Đánh OFF

```text
POST /api/people/v1/schedules/day-offs
```

Request:

```json
{
  "off_date": "2026-07-27",
  "employee_id": 3,
  "note": null
}
```

Response `201`:

```json
{
  "day_off": {
    "id": 1,
    "off_date": "2026-07-27",
    "employee": {
      "id": 3,
      "name": "Thoại",
      "email": "thoai@example.com"
    },
    "note": null
  }
}
```

### Xóa OFF

```text
DELETE /api/people/v1/schedules/day-offs/{id}
```

Response `204`.

---

## Data Model

### `shift_assignments`

```text
id bigint primary key
work_date date not null
shift_type varchar(32) not null
employee_id bigint not null foreign key users.id
note varchar(255) nullable
created_at timestamp nullable
updated_at timestamp nullable
unique(work_date, shift_type, employee_id)
index(work_date, shift_type)
```

### `schedule_day_offs`

```text
id bigint primary key
off_date date not null
employee_id bigint not null foreign key users.id
note varchar(255) nullable
created_at timestamp nullable
updated_at timestamp nullable
unique(off_date, employee_id)
index(off_date)
```

### Enum `ShiftType`

```php
enum ShiftType: string
{
    case Morning = 'MORNING';
    case Afternoon = 'AFTERNOON';
}
```

---

## Business Rules

1. Mỗi ngày có đúng 2 ca: `MORNING` và `AFTERNOON`.
2. Mỗi ca tối đa 2 nhân viên.
3. Một nhân viên không được gán hai lần vào cùng một ca.
4. Một nhân viên được làm cả sáng và chiều trong cùng ngày nếu không bị đánh OFF.
5. Nhân viên `LOCKED` không được gán ca.
6. Nhân viên `LOCKED` không được đánh OFF.
7. Nếu nhân viên đã OFF trong ngày, không được gán ca ngày đó.
8. Nếu nhân viên đã có ca trong ngày, không được đánh OFF ngày đó.
9. Ghi dữ liệu phải có CSRF.
10. Cả `STORE_MANAGER` và `STAFF` đều được xem lịch, gán ca, gỡ ca, đánh OFF, xóa OFF trong MVP.

---

## File Map

Create:

- `services/people-service/app/Domain/Enums/ShiftType.php`
  - Chứa enum `MORNING` và `AFTERNOON`.

- `services/people-service/app/Models/ShiftAssignment.php`
  - Eloquent model cho bảng `shift_assignments`.
  - Cast `work_date` thành `date`.
  - Cast `shift_type` thành `ShiftType`.
  - Relation `employee(): BelongsTo`.

- `services/people-service/app/Models/ScheduleDayOff.php`
  - Eloquent model cho bảng `schedule_day_offs`.
  - Cast `off_date` thành `date`.
  - Relation `employee(): BelongsTo`.

- `services/people-service/app/Http/Requests/StoreShiftAssignmentRequest.php`
  - Validate payload gán ca.

- `services/people-service/app/Http/Requests/StoreScheduleDayOffRequest.php`
  - Validate payload đánh OFF.

- `services/people-service/app/Http/Controllers/ScheduleController.php`
  - `index()`: xem lịch tuần.
  - `storeAssignment()`: gán ca.
  - `destroyAssignment()`: gỡ ca.
  - `storeDayOff()`: đánh OFF.
  - `destroyDayOff()`: xóa OFF.

- `services/people-service/database/migrations/2026_07_31_000000_create_shift_assignments_table.php`
  - Tạo bảng `shift_assignments`.

- `services/people-service/database/migrations/2026_07_31_000001_create_schedule_day_offs_table.php`
  - Tạo bảng `schedule_day_offs`.

- `services/people-service/tests/Feature/ScheduleManagementTest.php`
  - Feature tests cho UC-07/08/09 và OFF.

Modify:

- `services/people-service/routes/api.php`
  - Thêm routes schedule dưới `auth.jwt`.
  - Các POST/DELETE dùng `csrf.double_submit`.

- `docs/api.md`
  - Cập nhật API schedule mới.

- `README.md`
  - Cập nhật milestone Schedule backend nếu task hoàn tất.

---

## Task 1: RED Tests For Weekly Schedule Read API

**Files:**
- Create: `services/people-service/tests/Feature/ScheduleManagementTest.php`

**Interfaces:**
- Consumes: current auth JWT helper pattern from `EmployeeManagementTest`.
- Produces: expected response contract for `GET /api/v1/schedules`.

- [ ] **Step 1: Write failing tests**

Create `ScheduleManagementTest` with these tests:

```php
<?php

namespace Tests\Feature;

use App\Domain\Enums\UserRole;
use App\Domain\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
```

- [ ] **Step 2: Run RED**

Run:

```powershell
docker compose exec -T people-service php artisan test --filter=ScheduleManagementTest
```

Expected: fails because `/api/v1/schedules` route does not exist.

---

## Task 2: GREEN Weekly Schedule Read API

**Files:**
- Create: `services/people-service/app/Domain/Enums/ShiftType.php`
- Create: `services/people-service/app/Http/Controllers/ScheduleController.php`
- Modify: `services/people-service/routes/api.php`
- Test: `services/people-service/tests/Feature/ScheduleManagementTest.php`

**Interfaces:**
- Produces: `ScheduleController::index(): JsonResponse`.
- Produces: route `GET /api/v1/schedules`.

- [ ] **Step 1: Create enum**

```php
<?php

namespace App\Domain\Enums;

enum ShiftType: string
{
    case Morning = 'MORNING';
    case Afternoon = 'AFTERNOON';
}
```

- [ ] **Step 2: Create minimal controller index**

```php
<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $weekStart = CarbonImmutable::parse($request->query('week_start', now()->toDateString()))->startOfWeek();
        $weekEnd = $weekStart->addDays(6);

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->addDays($i);
            $days[] = [
                'date' => $date->toDateString(),
                'weekday_label' => $this->weekdayLabel($i),
                'morning' => [
                    'label' => 'SÁNG (8:30 - 15:30)',
                    'assignments' => [],
                ],
                'afternoon' => [
                    'label' => 'CHIỀU (15:00 - 22:00)',
                    'assignments' => [],
                ],
                'offs' => [],
            ];
        }

        return response()->json([
            'title' => sprintf(
                'Lịch làm việc (%s - %s)',
                $weekStart->format('d/m/Y'),
                $weekEnd->format('d/m/Y'),
            ),
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'days' => $days,
        ]);
    }

    private function weekdayLabel(int $offset): string
    {
        return match ($offset) {
            0 => 'Thứ 2',
            1 => 'Thứ 3',
            2 => 'Thứ 4',
            3 => 'Thứ 5',
            4 => 'Thứ 6',
            5 => 'Thứ 7',
            default => 'Chủ nhật',
        };
    }
}
```

- [ ] **Step 3: Add route**

In `routes/api.php`:

```php
use App\Http\Controllers\ScheduleController;
```

Inside `Route::middleware('auth.jwt')->group(...)`:

```php
Route::get('/schedules', [ScheduleController::class, 'index']);
```

- [ ] **Step 4: Run GREEN**

Run:

```powershell
docker compose exec -T people-service php artisan test --filter=ScheduleManagementTest
```

Expected: schedule read tests pass.

---

## Task 3: RED Tests For Assigning Employees To Shifts

**Files:**
- Modify: `services/people-service/tests/Feature/ScheduleManagementTest.php`

**Interfaces:**
- Consumes route `POST /api/v1/schedules/assignments`.
- Produces expected validation/business behavior for assignments.

- [ ] **Step 1: Add tests**

Append tests:

```php
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
```

- [ ] **Step 2: Run RED**

Run:

```powershell
docker compose exec -T people-service php artisan test --filter=ScheduleManagementTest
```

Expected: fails because assignment route/model/table do not exist.

---

## Task 4: GREEN Assignments

**Files:**
- Create: `services/people-service/database/migrations/2026_07_31_000000_create_shift_assignments_table.php`
- Create: `services/people-service/app/Models/ShiftAssignment.php`
- Create: `services/people-service/app/Http/Requests/StoreShiftAssignmentRequest.php`
- Modify: `services/people-service/app/Http/Controllers/ScheduleController.php`
- Modify: `services/people-service/routes/api.php`
- Test: `services/people-service/tests/Feature/ScheduleManagementTest.php`

**Interfaces:**
- Produces: `ShiftAssignment` model.
- Produces: `ScheduleController::storeAssignment(StoreShiftAssignmentRequest $request): JsonResponse`.
- Produces: route `POST /api/v1/schedules/assignments`.

- [ ] **Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_assignments', function (Blueprint $table): void {
            $table->id();
            $table->date('work_date');
            $table->string('shift_type', 32);
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['work_date', 'shift_type', 'employee_id']);
            $table->index(['work_date', 'shift_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
    }
};
```

- [ ] **Step 2: Create model**

```php
<?php

namespace App\Models;

use App\Domain\Enums\ShiftType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['work_date', 'shift_type', 'employee_id', 'note'])]
final class ShiftAssignment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'shift_type' => ShiftType::class,
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
```

- [ ] **Step 3: Create request**

```php
<?php

namespace App\Http\Requests;

use App\Domain\Enums\ShiftType;
use App\Domain\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreShiftAssignmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'work_date' => ['required', 'date_format:Y-m-d'],
            'shift_type' => ['required', Rule::enum(ShiftType::class)],
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('status', UserStatus::Active->value),
            ],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 4: Add assignment methods to controller**

Add imports:

```php
use App\Domain\Enums\ShiftType;
use App\Http\Requests\StoreShiftAssignmentRequest;
use App\Models\ScheduleDayOff;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
```

Add method:

```php
public function storeAssignment(StoreShiftAssignmentRequest $request): JsonResponse
{
    /** @var array{work_date: string, shift_type: string, employee_id: int, note?: string|null} $data */
    $data = $request->validated();

    $assignment = DB::transaction(function () use ($data): ShiftAssignment {
        $existingCount = ShiftAssignment::query()
            ->where('work_date', $data['work_date'])
            ->where('shift_type', $data['shift_type'])
            ->lockForUpdate()
            ->count();

        $alreadyAssigned = ShiftAssignment::query()
            ->where('work_date', $data['work_date'])
            ->where('shift_type', $data['shift_type'])
            ->where('employee_id', $data['employee_id'])
            ->exists();

        if ($alreadyAssigned) {
            throw ValidationException::withMessages([
                'employee_id' => 'Nhân viên đã được gán vào ca này.',
            ]);
        }

        if ($existingCount >= 2) {
            throw ValidationException::withMessages([
                'shift_type' => 'Mỗi ca chỉ được có tối đa 2 nhân viên.',
            ]);
        }

        return ShiftAssignment::query()->create($data);
    });

    return response()->json([
        'assignment' => $this->assignmentResource($assignment->load('employee')),
    ], 201);
}
```

Add resource:

```php
private function assignmentResource(ShiftAssignment $assignment): array
{
    /** @var User $employee */
    $employee = $assignment->employee;

    return [
        'id' => $assignment->id,
        'work_date' => $assignment->work_date->toDateString(),
        'shift_type' => $assignment->shift_type->value,
        'employee' => $this->employeeSummary($employee),
        'note' => $assignment->note,
    ];
}

private function employeeSummary(User $employee): array
{
    return [
        'id' => $employee->id,
        'name' => $employee->name,
        'email' => $employee->email,
    ];
}
```

- [ ] **Step 5: Add route**

Inside `Route::middleware(['csrf.double_submit'])->group(...)` under `auth.jwt`:

```php
Route::post('/schedules/assignments', [ScheduleController::class, 'storeAssignment']);
```

- [ ] **Step 6: Run migration + GREEN**

Run:

```powershell
docker compose exec -T people-service php artisan migrate
docker compose exec -T people-service php artisan test --filter=ScheduleManagementTest
```

Expected: assignment tests pass except OFF-related tests not written yet.

---

## Task 5: RED Tests For OFF Row

**Files:**
- Modify: `services/people-service/tests/Feature/ScheduleManagementTest.php`

**Interfaces:**
- Consumes route `POST /api/v1/schedules/day-offs`.
- Consumes route `DELETE /api/v1/schedules/day-offs/{id}`.

- [ ] **Step 1: Add OFF tests**

Append tests:

```php
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
```

- [ ] **Step 2: Run RED**

Run:

```powershell
docker compose exec -T people-service php artisan test --filter=ScheduleManagementTest
```

Expected: fails because OFF route/model/table do not exist.

---

## Task 6: GREEN OFF Row

**Files:**
- Create: `services/people-service/database/migrations/2026_07_31_000001_create_schedule_day_offs_table.php`
- Create: `services/people-service/app/Models/ScheduleDayOff.php`
- Create: `services/people-service/app/Http/Requests/StoreScheduleDayOffRequest.php`
- Modify: `services/people-service/app/Http/Controllers/ScheduleController.php`
- Modify: `services/people-service/routes/api.php`
- Test: `services/people-service/tests/Feature/ScheduleManagementTest.php`

**Interfaces:**
- Produces: `ScheduleDayOff` model.
- Produces: `ScheduleController::storeDayOff(StoreScheduleDayOffRequest $request): JsonResponse`.
- Produces: route `POST /api/v1/schedules/day-offs`.

- [ ] **Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_day_offs', function (Blueprint $table): void {
            $table->id();
            $table->date('off_date');
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['off_date', 'employee_id']);
            $table->index('off_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_day_offs');
    }
};
```

- [ ] **Step 2: Create model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['off_date', 'employee_id', 'note'])]
final class ScheduleDayOff extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'off_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
```

- [ ] **Step 3: Create request**

```php
<?php

namespace App\Http\Requests;

use App\Domain\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreScheduleDayOffRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'off_date' => ['required', 'date_format:Y-m-d'],
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('status', UserStatus::Active->value),
            ],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 4: Add OFF methods**

Add imports:

```php
use App\Http\Requests\StoreScheduleDayOffRequest;
```

Add method:

```php
public function storeDayOff(StoreScheduleDayOffRequest $request): JsonResponse
{
    /** @var array{off_date: string, employee_id: int, note?: string|null} $data */
    $data = $request->validated();

    $dayOff = DB::transaction(function () use ($data): ScheduleDayOff {
        $alreadyOff = ScheduleDayOff::query()
            ->where('off_date', $data['off_date'])
            ->where('employee_id', $data['employee_id'])
            ->lockForUpdate()
            ->exists();

        if ($alreadyOff) {
            throw ValidationException::withMessages([
                'employee_id' => 'Nhân viên đã được đánh OFF ngày này.',
            ]);
        }

        return ScheduleDayOff::query()->create($data);
    });

    return response()->json([
        'day_off' => $this->dayOffResource($dayOff->load('employee')),
    ], 201);
}
```

Add resource:

```php
private function dayOffResource(ScheduleDayOff $dayOff): array
{
    /** @var User $employee */
    $employee = $dayOff->employee;

    return [
        'id' => $dayOff->id,
        'off_date' => $dayOff->off_date->toDateString(),
        'employee' => $this->employeeSummary($employee),
        'note' => $dayOff->note,
    ];
}
```

- [ ] **Step 5: Add route**

```php
Route::post('/schedules/day-offs', [ScheduleController::class, 'storeDayOff']);
```

- [ ] **Step 6: Run migration + GREEN**

Run:

```powershell
docker compose exec -T people-service php artisan migrate
docker compose exec -T people-service php artisan test --filter=ScheduleManagementTest
```

Expected: OFF tests pass except conflict/delete/read-with-data tests not written yet.

---

## Task 7: RED Tests For Shift/OFF Conflicts And Deletes

**Files:**
- Modify: `services/people-service/tests/Feature/ScheduleManagementTest.php`

**Interfaces:**
- Consumes existing assignment and OFF routes.
- Produces conflict behavior.

- [ ] **Step 1: Add conflict and delete tests**

Append tests:

```php
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
```

- [ ] **Step 2: Run RED**

Run:

```powershell
docker compose exec -T people-service php artisan test --filter=ScheduleManagementTest
```

Expected: conflict/delete tests fail.

---

## Task 8: GREEN Conflicts, Deletes, And Read With Data

**Files:**
- Modify: `services/people-service/app/Http/Controllers/ScheduleController.php`
- Modify: `services/people-service/routes/api.php`
- Modify: `services/people-service/tests/Feature/ScheduleManagementTest.php`

**Interfaces:**
- Produces: `ScheduleController::destroyAssignment(int $id): JsonResponse`.
- Produces: `ScheduleController::destroyDayOff(int $id): JsonResponse`.
- Produces: populated `index()` response.

- [ ] **Step 1: Add conflict checks**

In `storeAssignment()`, before create:

```php
$isOff = ScheduleDayOff::query()
    ->where('off_date', $data['work_date'])
    ->where('employee_id', $data['employee_id'])
    ->lockForUpdate()
    ->exists();

if ($isOff) {
    throw ValidationException::withMessages([
        'employee_id' => 'Nhân viên đang OFF ngày này.',
    ]);
}
```

In `storeDayOff()`, before create:

```php
$hasShift = ShiftAssignment::query()
    ->where('work_date', $data['off_date'])
    ->where('employee_id', $data['employee_id'])
    ->lockForUpdate()
    ->exists();

if ($hasShift) {
    throw ValidationException::withMessages([
        'employee_id' => 'Nhân viên đã có ca trong ngày này.',
    ]);
}
```

- [ ] **Step 2: Add delete methods**

```php
public function destroyAssignment(int $id): JsonResponse
{
    ShiftAssignment::query()->findOrFail($id)->delete();

    return response()->json(null, 204);
}

public function destroyDayOff(int $id): JsonResponse
{
    ScheduleDayOff::query()->findOrFail($id)->delete();

    return response()->json(null, 204);
}
```

- [ ] **Step 3: Add delete routes**

```php
Route::delete('/schedules/assignments/{id}', [ScheduleController::class, 'destroyAssignment']);
Route::delete('/schedules/day-offs/{id}', [ScheduleController::class, 'destroyDayOff']);
```

- [ ] **Step 4: Populate index response**

In `index()`, query both tables:

```php
$assignments = ShiftAssignment::query()
    ->with('employee')
    ->whereBetween('work_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
    ->orderBy('id')
    ->get()
    ->groupBy(fn (ShiftAssignment $assignment): string => $assignment->work_date->toDateString().'|'.$assignment->shift_type->value);

$dayOffs = ScheduleDayOff::query()
    ->with('employee')
    ->whereBetween('off_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
    ->orderBy('id')
    ->get()
    ->groupBy(fn (ScheduleDayOff $dayOff): string => $dayOff->off_date->toDateString());
```

Use keys:

```php
$morningKey = $date->toDateString().'|'.ShiftType::Morning->value;
$afternoonKey = $date->toDateString().'|'.ShiftType::Afternoon->value;
```

Map resources:

```php
'assignments' => $assignments->get($morningKey, collect())
    ->map(fn (ShiftAssignment $assignment): array => $this->assignmentResource($assignment))
    ->values()
    ->all(),
```

OFF:

```php
'offs' => $dayOffs->get($date->toDateString(), collect())
    ->map(fn (ScheduleDayOff $dayOff): array => $this->dayOffResource($dayOff))
    ->values()
    ->all(),
```

- [ ] **Step 5: Add read-with-data test**

```php
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
```

- [ ] **Step 6: Run GREEN**

Run:

```powershell
docker compose exec -T people-service php artisan test --filter=ScheduleManagementTest
```

Expected: all schedule tests pass.

---

## Task 9: Docs And Full Verification

**Files:**
- Modify: `docs/api.md`
- Modify: `README.md`
- Test: full people-service tests.

**Interfaces:**
- Produces: updated docs for future frontend Schedule UI.

- [ ] **Step 1: Update `docs/api.md` schedule endpoints**

Replace old schedule route block with:

```text
GET    /api/people/v1/schedules?week_start=YYYY-MM-DD
POST   /api/people/v1/schedules/assignments
DELETE /api/people/v1/schedules/assignments/{id}
POST   /api/people/v1/schedules/day-offs
DELETE /api/people/v1/schedules/day-offs/{id}
```

Add short note:

```text
Schedule Management:
- Response trả đủ 7 ngày, mỗi ngày có ca sáng, ca chiều và danh sách OFF.
- Mỗi ca tối đa 2 nhân viên.
- Nhân viên đã OFF trong ngày không được gán ca.
- Nhân viên đã có ca trong ngày không được đánh OFF.
```

- [ ] **Step 2: Update `README.md` milestone**

Change:

```text
4. `UC-07` đến `UC-09`: Schedule Management. Tiếp theo.
```

to:

```text
4. `UC-07` đến `UC-09`: Schedule Management backend. Đã có.
```

- [ ] **Step 3: Run full verification**

Run:

```powershell
docker compose exec -T people-service php artisan test
docker compose exec -T inventory-service php artisan test
npm run build
git diff --check
```

Expected:

```text
people-service tests pass
inventory-service tests pass
frontend build exits 0
git diff --check exits 0
```

- [ ] **Step 4: Commit**

Run:

```powershell
git add docs/TaskImplementDetailPlan/ScheduleManagementUC-07-08-09plan.md docs/api.md README.md services/people-service/app services/people-service/database/migrations services/people-service/routes/api.php services/people-service/tests/Feature/ScheduleManagementTest.php
git commit -m "feat: add schedule management backend"
```

---

## Self-Review

- Spec coverage: UC-07 xem lịch tuần, UC-08 gán ca, UC-09 gỡ ca, và yêu cầu bổ sung OFF đều có task.
- Placeholder scan: không dùng TBD/TODO/implement later.
- Type consistency: `ShiftType`, `ShiftAssignment`, `ScheduleDayOff`, `storeAssignment`, `storeDayOff`, `destroyAssignment`, `destroyDayOff` được định nghĩa trước khi dùng.
- Scope: chỉ backend People Service + docs. Không frontend, không bảng `employees`, không feature ngoài MVP.
