<?php

namespace App\Http\Controllers;

use App\Domain\Enums\ShiftType;
use App\Http\Requests\StoreScheduleDayOffRequest;
use App\Http\Requests\StoreShiftAssignmentRequest;
use App\Models\ScheduleDayOff;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $weekStart = CarbonImmutable::parse($request->query('week_start', now()->toDateString()))->startOfWeek();
        $weekEnd = $weekStart->addDays(6);

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

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->addDays($i);
            $dateString = $date->toDateString();
            $morningKey = $dateString.'|'.ShiftType::Morning->value;
            $afternoonKey = $dateString.'|'.ShiftType::Afternoon->value;

            $days[] = [
                'date' => $dateString,
                'weekday_label' => $this->weekdayLabel($i),
                'morning' => [
                    'label' => 'SÁNG (8:30 - 15:30)',
                    'assignments' => $assignments->get($morningKey, collect())
                        ->map(fn (ShiftAssignment $assignment): array => $this->assignmentResource($assignment))
                        ->values()
                        ->all(),
                ],
                'afternoon' => [
                    'label' => 'CHIỀU (15:00 - 22:00)',
                    'assignments' => $assignments->get($afternoonKey, collect())
                        ->map(fn (ShiftAssignment $assignment): array => $this->assignmentResource($assignment))
                        ->values()
                        ->all(),
                ],
                'offs' => $dayOffs->get($dateString, collect())
                    ->map(fn (ScheduleDayOff $dayOff): array => $this->dayOffResource($dayOff))
                    ->values()
                    ->all(),
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

    public function storeAssignment(StoreShiftAssignmentRequest $request): JsonResponse
    {
        /** @var array{work_date: string, shift_type: string, employee_id: int, note?: string|null} $data */
        $data = $request->validated();

        $assignment = DB::transaction(function () use ($data): ShiftAssignment {
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

            $alreadyAssigned = ShiftAssignment::query()
                ->where('work_date', $data['work_date'])
                ->where('shift_type', $data['shift_type'])
                ->where('employee_id', $data['employee_id'])
                ->lockForUpdate()
                ->exists();

            if ($alreadyAssigned) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Nhân viên đã được gán vào ca này.',
                ]);
            }

            $existingCount = ShiftAssignment::query()
                ->where('work_date', $data['work_date'])
                ->where('shift_type', $data['shift_type'])
                ->lockForUpdate()
                ->get()
                ->count();

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

    public function destroyAssignment(int $id): JsonResponse
    {
        ShiftAssignment::query()->findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    public function storeDayOff(StoreScheduleDayOffRequest $request): JsonResponse
    {
        /** @var array{off_date: string, employee_id: int, note?: string|null} $data */
        $data = $request->validated();

        $dayOff = DB::transaction(function () use ($data): ScheduleDayOff {
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

    public function destroyDayOff(int $id): JsonResponse
    {
        ScheduleDayOff::query()->findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array{id: int, name: string, email: string}
     */
    private function employeeSummary(User $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
        ];
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
