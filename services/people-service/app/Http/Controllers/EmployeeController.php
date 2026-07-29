<?php

namespace App\Http\Controllers;

use App\Domain\Enums\UserStatus;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class EmployeeController extends Controller
{
    public function index(): JsonResponse
    {
        $employees = User::query()
            ->orderBy('id')
            ->get()
            ->map(fn (User $user): array => $this->employeeResource($user))
            ->values();

        return response()->json(['employees' => $employees]);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        /** @var array{name: string, email: string, password: string, role: string, status: string} $data */
        $data = $request->validated();

        $employee = User::query()->create($data);

        return response()->json([
            'employee' => $this->employeeResource($employee),
        ], 201);
    }

    public function update(UpdateEmployeeRequest $request, int $id): JsonResponse
    {
        /** @var array{name?: string, email?: string, password?: string, role?: string, status?: string} $data */
        $data = $request->validated();
        $employee = User::query()->findOrFail($id);

        $employee->forceFill($data)->save();

        return response()->json([
            'employee' => $this->employeeResource($employee->refresh()),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $employee = User::query()->findOrFail($id);

        $employee->forceFill(['status' => UserStatus::Locked])->save();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function employeeResource(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'status' => $user->status->value,
            'created_at' => $user->created_at?->toJSON(),
            'updated_at' => $user->updated_at?->toJSON(),
        ];
    }
}
