---
name: 07-employee-management
description: Chuẩn hóa Employee Management cho LazyManager MVP People Service. Dùng khi implement hoặc review UC-03, UC-04, UC-05, UC-06: xem nhân viên, tạo nhân viên, sửa nhân viên, xóa mềm/deactivate, đúng role STORE_MANAGER/STAFF, giữ lịch sử schedule, Laravel OOP backend và React pages.
---

# LazyManager MVP - Skill quản lý nhân viên

## 1. Mục đích

Skill này hướng dẫn xây CRUD nhân viên trong People Service đúng quyền và không mất lịch sử.

Skill bao phủ:

- `UC-03`: Xem nhân viên.
- `UC-04`: Thêm nhân viên.
- `UC-05`: Sửa nhân viên.
- `UC-06`: Xóa mềm nhân viên.

Luôn tuân thủ:

- `skills/00-project-context/SKILL.md`.
- `skills/04-laravel-oop-feature/SKILL.md`.
- `skills/05-react-feature/SKILL.md`.
- `skills/06-authentication-authorization/SKILL.md`.

## 2. Vai trò

- `STORE_MANAGER` và `STAFF` được xem danh sách và chi tiết nhân viên.
- Chỉ `STORE_MANAGER` được thêm, sửa, xóa mềm nhân viên.
- `STAFF` gọi employee mutation API phải nhận HTTP `403`.
- React có thể ẩn mutation buttons với `STAFF`, nhưng backend vẫn là nơi kiểm tra quyền thật.

## 3. Field của Employee

Employee tối thiểu gồm:

- `id`.
- `user_id` nullable.
- `employee_code`.
- `full_name`.
- `phone`.
- `created_at`.
- `updated_at`.
- `deleted_at`.

Không thêm field HR ngoài MVP như salary, department, leave hoặc attendance.

## 4. Quy tắc nghiệp vụ

- `employee_code` unique.
- `full_name` bắt buộc.
- Nhân viên bị soft delete không được xếp lịch mới.
- Xóa là soft delete bằng `deleted_at`.
- Không dùng `employees.active` trong MVP.
- Lịch sử phân công cũ phải còn.
- Xóa nhân viên không tự động xóa schedule history.
- `STAFF` gọi mutation API nhận `403`.
- Không hard delete employee có lịch sử.
- Nếu employee không tồn tại, trả `404`.
- Nếu `employee_code` trùng, trả `409` hoặc `422` theo API convention của project.

## 5. Backend Use Cases

Các Use Case cần có:

- `ListEmployeesUseCase`.
- `GetEmployeeDetailUseCase`.
- `CreateEmployeeUseCase`.
- `UpdateEmployeeUseCase`.
- `DeactivateEmployeeUseCase`.

Mỗi Use Case đại diện một hành động rõ ràng. Không gộp toàn bộ CRUD vào một service lớn.

## 6. Component backend bắt buộc

Backend components bắt buộc:

- Employee DTOs.
- `EmployeeRepositoryInterface`.
- `EloquentEmployeeRepository`.
- `EmployeeController`.
- `StoreEmployeeRequest`.
- `UpdateEmployeeRequest`.
- `EmployeeResource`.
- `EmployeeCodeAlreadyExistsException`.
- `EmployeeNotFoundException`.
- Manager authorization.

Manager authorization có thể dùng `ManagerOnly` middleware hoặc Policy, nhưng phải test được HTTP `403`.

Repository interface đặt trong `Application/Interfaces`.

Eloquent implementation đặt trong `Infrastructure/Persistence`.

Request, Controller, Resource đặt trong `Http`.

Exception nghiệp vụ đặt trong `Domain/Exceptions`.

## 7. API

Employee API:

- `GET /api/people/v1/employees`.
- `POST /api/people/v1/employees`.
- `GET /api/people/v1/employees/{id}`.
- `PUT /api/people/v1/employees/{id}`.
- `DELETE /api/people/v1/employees/{id}`.

Authorization:

- `GET` endpoints: `STORE_MANAGER`, `STAFF`.
- `POST`, `PUT`, `DELETE`: chỉ `STORE_MANAGER`.

Error status:

- `401`: chưa xác thực.
- `403`: không có quyền.
- `404`: employee không tồn tại.
- `409` hoặc `422`: `employee_code` trùng hoặc validation/business rule không hợp lệ.

## 8. Tính năng danh sách

Employee list hỗ trợ:

- Search theo `employee_code` hoặc `full_name`.
- Filter current/soft-deleted nếu task thật sự cần.
- Pagination nếu cần.

Không overbuild advanced filtering.

Không thêm filter ngoài MVP như department, salary, attendance hoặc leave.

## 9. Trang React

React pages cần có:

- Employee list.
- Employee detail.
- Employee create form.
- Employee edit form.
- Deactivate confirmation.
- Staff chỉ thấy chức năng xem.

Quy tắc frontend:

- API đi qua Nginx.
- Không gọi thẳng port People Service.
- Dùng React Hook Form và Zod cho forms.
- Dùng TanStack Query cho list/detail.
- Mutation success phải invalidate employee queries.
- List page có loading, empty, success, API error, unauthorized và forbidden state.
- Staff không thấy nút create/edit/delete.
- Nếu backend trả `403`, hiển thị thông báo không có quyền.

## 10. Test bắt buộc

Backend tests bắt buộc:

- Manager tạo được employee.
- Staff tạo bị `403`.
- Mã trùng bị `409` hoặc `422`.
- Manager sửa được.
- Staff sửa bị `403`.
- Xóa mềm không xóa lịch sử.
- Soft-deleted employee không xuất hiện trong lựa chọn xếp lịch mới.
- Detail không tồn tại trả `404`.

Frontend tests nên có:

- Employee list render.
- Loading state.
- Empty state.
- API error state.
- Create form validation.
- Mutation success cập nhật lại UI.
- `STAFF` không thấy employee mutation buttons.

## 11. Hành động bị cấm

Không được:

- Tạo HR module.
- Thêm salary, department, leave hoặc attendance.
- Hard delete employee có lịch sử.
- Đặt authorization chỉ ở React.
- Xóa schedule history khi xóa employee.
- Tạo role/permission builder.
- Thêm dynamic permissions.
- Đặt business logic trong Controller.
- Gửi nguyên HTTP Request vào Use Case.
- Overbuild advanced employee filters ngoài MVP.

## 12. Tiêu chí hoàn thành

Employee Management chỉ được xem là Done khi:

- `UC-03` đến `UC-06` chạy end-to-end.
- Backend role behavior đúng.
- Frontend role behavior đúng.
- `STAFF` bị chặn bởi backend khi gọi mutation API.
- Employee list/detail hoạt động.
- Create/update/deactivate hoạt động với `STORE_MANAGER`.
- Xóa mềm không xóa schedule history.
- Soft-deleted employee không được chọn cho lịch mới.
- Test pass.
- API docs cập nhật.
- React không có console error.

## 13. Định dạng output

Chỉ trả nội dung `SKILL.md`.
