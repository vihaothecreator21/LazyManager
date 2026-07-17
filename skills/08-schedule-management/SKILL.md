---
name: 08-schedule-management
description: Chuẩn hóa Schedule Management cho LazyManager MVP People Service. Dùng khi implement hoặc review UC-07, UC-08, UC-09: xem lịch tuần 7 ngày x 2 ca, gán nhân viên vào ca, xóa nhân viên khỏi ca, ScheduleValidator, concurrency, Laravel backend và React schedule UI.
---

# LazyManager MVP - Skill quản lý lịch làm

## 1. Mục đích

Skill này hướng dẫn quản lý bảng lịch 7 ngày với 2 ca mỗi ngày trong People Service.

Skill bao phủ:

- `UC-07`: Xem lịch tuần.
- `UC-08`: Gán nhân viên vào ca.
- `UC-09`: Xóa nhân viên khỏi ca.

Luôn tuân thủ:

- `skills/00-project-context/SKILL.md`.
- `skills/04-laravel-oop-feature/SKILL.md`.
- `skills/05-react-feature/SKILL.md`.

Mục tiêu là có luồng lịch làm chạy end-to-end, đúng rule tối đa 2 nhân viên/ca, không gán trùng và không xếp nhân viên bị soft delete vào lịch mới.

## 2. Data model

### `schedules`

- `id`.
- `work_date`.
- `shift_type`.

### `shift_assignments`

- `id`.
- `schedule_id`.
- `employee_id`.

Ràng buộc bắt buộc:

- `schedules` unique trên `(work_date, shift_type)`.
- `shift_assignments` unique trên `(schedule_id, employee_id)`.

Không thêm bảng ngoài MVP như leave, availability, attendance, schedule_versions hoặc shift_swap_requests.

## 3. Enums

`ShiftType`:

- `MORNING`.
- `AFTERNOON`.

Lưu bằng string column hoặc enum strategy nhất quán với project. Không dùng magic string rải rác trong code.

## 4. Quy tắc nghiệp vụ

- Mỗi ngày chỉ có `MORNING` và `AFTERNOON`.
- `schedules` phải unique trên `(work_date, shift_type)`.
- Mỗi ca tối đa 2 nhân viên.
- Không gán cùng employee hai lần vào một ca.
- Chỉ gán employee chưa bị soft delete.
- Employee bị soft delete không được xếp lịch mới.
- Một employee có thể làm cả `MORNING` và `AFTERNOON` trong MVP.
- `STORE_MANAGER` và `STAFF` đều được xếp lịch.
- Xóa assignment không xóa employee.
- Xóa employee không được xóa schedule history.

## 5. Backend Use Cases

Các Use Case cần có:

- `GetWeeklyScheduleUseCase`.
- `GetOrCreateScheduleUseCase` nếu cần.
- `AssignEmployeeToShiftUseCase`.
- `RemoveEmployeeFromShiftUseCase`.

Use Case phải điều phối repository và `ScheduleValidator`. Không đặt rule max 2 hoặc duplicate assignment trong Controller.

## 6. Component Domain

Domain components bắt buộc hoặc khuyến nghị:

- `ScheduleValidator`.
- `ShiftFullException`.
- `EmployeeAlreadyAssignedException`.
- `DeletedEmployeeException` hoặc exception tương đương theo codebase.

`ScheduleValidator` chịu trách nhiệm kiểm tra:

- Shift còn chỗ.
- Employee chưa có trong shift.
- Employee chưa bị soft delete.
- Shift type hợp lệ.

Map lỗi nghiệp vụ:

- Shift full: `409`.
- Employee already assigned: `409`.
- Employee đã bị soft delete: `422`.
- Assignment không tồn tại khi delete: `404`.

## 7. API

Schedule API:

- `GET /api/people/v1/schedules?week_start=YYYY-MM-DD`.
- `POST /api/people/v1/schedules`.
- `POST /api/people/v1/schedules/{id}/assignments`.
- `DELETE /api/people/v1/schedules/{id}/assignments/{employeeId}`.

Authorization:

- `STORE_MANAGER` được xem/gán/xóa assignment.
- `STAFF` được xem/gán/xóa assignment.
- Mọi endpoint vẫn phải xác thực JWT ở backend.

Request/response phải qua Nginx API Gateway từ React.

## 8. Response lịch tuần

Weekly response phải trả đủ dữ liệu cho React hiển thị 7 ngày x 2 ca.

Không để React phải gọi API riêng cho từng ngày.

Mỗi ngày nên có đủ:

- `date`.
- `MORNING`.
- `AFTERNOON`.

Mỗi ca nên có:

- `schedule_id`.
- `shift_type`.
- `employees`.

Mỗi ca chứa tối đa 2 employee.

Ví dụ shape tham khảo:

```json
{
  "week_start": "2026-07-13",
  "days": [
    {
      "date": "2026-07-13",
      "shifts": [
        {
          "schedule_id": 1,
          "shift_type": "MORNING",
          "employees": []
        },
        {
          "schedule_id": 2,
          "shift_type": "AFTERNOON",
          "employees": []
        }
      ]
    }
  ]
}
```

## 9. React UI

React schedule UI cần có:

- Chọn tuần.
- Bảng 7 cột ngày hoặc dạng phù hợp.
- Mỗi ngày có `MORNING` và `AFTERNOON`.
- Nút thêm nhân viên.
- Danh sách chọn chỉ gồm employee chưa bị soft delete.
- Nút xóa assignment.
- Hiển thị lỗi `409` rõ ràng.

Quy tắc frontend:

- Dùng TanStack Query cho weekly schedule.
- Query key chứa `week_start`.
- Mutation assign/remove phải invalidate schedule query.
- Không dùng drag-and-drop trong MVP nếu làm tăng scope.
- Không gọi API riêng cho từng ngày.
- Không gọi thẳng port People Service.
- Loading, empty, API error, unauthorized và forbidden states phải có.

## 10. Xử lý đồng thời

Khi gán employee:

- Kiểm tra số người trong transaction.
- Cần tránh hai request đồng thời tạo người thứ ba.
- Dùng row lock hoặc constraint kết hợp logic phù hợp.
- Unique `(schedule_id, employee_id)` phải bảo vệ duplicate assignment.
- Nếu race condition xảy ra, trả lỗi nghiệp vụ phù hợp, không tạo dữ liệu sai.

Khuyến nghị backend:

- Lock `schedules` row hoặc các assignment rows liên quan trong DB transaction.
- Đếm assignment hiện tại trong cùng transaction.
- Insert assignment trong cùng transaction.
- Catch unique violation nếu duplicate request xảy ra.

Không chỉ kiểm tra số lượng ở frontend.

## 11. Test bắt buộc

Backend tests bắt buộc:

- Xem lịch tuần.
- Gán người đầu tiên.
- Gán người thứ hai.
- Người thứ ba bị `409`.
- Gán trùng bị `409`.
- Employee đã bị soft delete bị `422`.
- Xóa assignment thành công.
- Xóa assignment không tồn tại trả `404`.

Nên có thêm:

- `week_start` invalid trả `422`.
- Employee deleted không được gán.
- `ShiftType` không hợp lệ bị từ chối.

Frontend tests nên có:

- Schedule page render.
- Loading state.
- API error state.
- Assign success cập nhật UI.
- Remove success cập nhật UI.
- Lỗi `409` hiển thị rõ.

## 12. Hành động bị cấm

Không được:

- Làm drag-and-drop nếu làm tăng scope.
- Thêm publish/version.
- Thêm leave hoặc availability.
- Thêm attendance.
- Thêm đổi ca.
- Thêm workflow approve lịch.
- Đặt rule max 2 chỉ ở React.
- Đặt business logic trong Controller.
- Xóa schedule history khi xóa employee.
- Cho phép employee đã bị soft delete xuất hiện trong danh sách chọn cho lịch mới.

## 13. Tiêu chí hoàn thành

Schedule Management chỉ được xem là Done khi:

- Lịch tuần chạy end-to-end.
- React hiển thị được 7 ngày x 2 ca.
- Không thể có người thứ ba trong một ca.
- Không thể gán trùng employee vào một ca.
- Employee đã bị soft delete không thể được gán lịch mới.
- `STORE_MANAGER` và `STAFF` đều thao tác lịch đúng MVP.
- Test quy tắc nghiệp vụ pass.
- API docs cập nhật.
- React không có console error.

## 14. Định dạng output

Chỉ trả nội dung `SKILL.md`.
