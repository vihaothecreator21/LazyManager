---
name: 00-project-context
description: Bối cảnh nền tảng và quy tắc bắt buộc cho mọi coding agent làm việc trong dự án LazyManager MVP.
---

# LazyManager MVP - Skill bối cảnh dự án

## 1. Mục đích

Skill này cung cấp bối cảnh chung cho toàn bộ dự án LazyManager MVP. Mọi skill, coding agent hoặc tác vụ sinh code trong dự án phải đọc và tuân thủ nội dung này trước khi thiết kế, sửa code, viết test hoặc cập nhật tài liệu.

Mục tiêu chính là giữ dự án đúng phạm vi MVP 20 ngày làm việc, tránh việc coding agent tự ý mở rộng scope, thêm service, thêm công nghệ hoặc tạo abstraction không phục vụ trực tiếp các use case đã chốt.

Nếu có xung đột giữa đề xuất kỹ thuật và tài liệu dự án, ưu tiên giữ scope nhỏ, chạy được, test được và demo được end-to-end.

## 2. Tổng quan dự án

LazyManager là website quản lý nhân viên, lịch làm, tồn kho và kiểm kho cho cửa hàng.

Tên cũ trong tài liệu nguồn có thể là `StoreOps`; mọi code, docs mới, README, Docker project và UI phải dùng `LazyManager`.

Thời gian MVP: 20 ngày làm việc.

Mục tiêu: xây dựng dự án cá nhân cho sinh viên năm 4, dùng làm portfolio khi ứng tuyển intern/fresher.

Dự án phải ưu tiên luồng nghiệp vụ thật, chạy được bằng Docker Compose, có backend authorization, có validation, có test cho business rules quan trọng và có tài liệu đủ để người khác clone về chạy.

## 3. Công nghệ sử dụng

- Frontend: React + TypeScript + Vite.
- Backend: PHP Laravel.
- Database: PostgreSQL.
- Runtime orchestration: Docker Compose.
- API Gateway: Nginx API Gateway.
- Communication: REST API.

Không thêm công nghệ khác vào MVP nếu không có yêu cầu rõ ràng từ người dùng và không được ghi nhận trong scope.

## 4. Kiến trúc

LazyManager MVP dùng kiến trúc 2 microservice:

```text
Browser
  |
  | http://localhost:8080
  v
Nginx API Gateway
  |-- /                     -> frontend:5173
  |-- /api/people/*         -> people-service:8000
  |-- /api/inventory/*      -> inventory-service:8000
  |
  |-- /api/people/v1/*      -> People Service /api/v1/*
  |-- /api/inventory/v1/*   -> Inventory Service /api/v1/*
```

Trong MVP, mỗi Laravel service chạy HTTP bằng:

```text
php artisan serve --host=0.0.0.0 --port=8000
```

Không dựng thêm Nginx + PHP-FPM riêng cho từng Laravel service trong MVP.

### People Service

People Service chịu trách nhiệm:

- authentication.
- employee.
- schedule.

People Service sở hữu database `people_db`.

### Inventory Service

Inventory Service chịu trách nhiệm:

- product.
- SKU.
- stock.
- sales.
- borrow.
- stock count.

Inventory Service sở hữu database `inventory_db`.

### Quyền sở hữu database

- `people_db` và `inventory_db` là hai database riêng.
- Không service nào được truy cập trực tiếp database của service khác.
- Service chỉ được đọc/ghi database mà nó sở hữu.
- Nếu cần giao tiếp giữa service, dùng REST API trong MVP.

### Xác thực

- People Service phát access JWT ngắn hạn sau khi login thành công.
- Access JWT nằm trong cookie HttpOnly `lm_access_token`.
- Refresh token là opaque random token, nằm trong cookie HttpOnly `lm_refresh_token`.
- People Service chỉ lưu hash của refresh token trong `people_db`, không lưu raw refresh token.
- JWT chứa `sub`, `role`, `iss`, `aud`, `iat` và `exp`.
- Inventory Service xác thực access JWT từ cookie: signature, issuer, audience, expiration và role bằng cùng secret trong môi trường demo.
- Refresh token chỉ thuộc People Service và được rotate khi refresh.
- Frontend không quyết định quyền. Backend luôn phải kiểm tra quyền lại.
- React không lưu JWT trong `localStorage` hoặc memory state và không tự gắn `Authorization: Bearer`.
- Logout gọi backend để revoke refresh token hash và clear cookies.
- Cookie auth phải có CSRF protection cho state-changing requests.

## 5. Vai trò

Dự án chỉ có 2 role trong MVP:

- `STORE_MANAGER`.
- `STAFF`.

Quy tắc quyền:

- Cả `STORE_MANAGER` và `STAFF` được thao tác mọi module chính trong MVP.
- Chỉ `STORE_MANAGER` được thêm, sửa, xóa mềm nhân viên.
- `STAFF` gọi API thêm, sửa, xóa mềm nhân viên phải nhận HTTP 403.
- Quyền luôn phải được kiểm tra ở backend bằng middleware, policy hoặc use case guard.
- Không được chỉ ẩn nút trên React để thay thế backend authorization.

## 6. Quy tắc OOP

Mỗi Laravel service phải tổ chức code theo các lớp trách nhiệm rõ ràng:

```text
app/
  Domain/
    Entities/
    Enums/
    Exceptions/
    Services/
  Application/
    DTOs/
    UseCases/
    Interfaces/
  Infrastructure/
    Persistence/
  Http/
    Controllers/
    Requests/
    Resources/
    Middleware/
```

### Http

- Nhận request.
- Validate input qua Request object hoặc validator phù hợp.
- Gọi Use Case.
- Trả response hoặc Resource.
- Không chứa business logic.

### Application

- Chứa DTO.
- Chứa Use Case.
- Điều phối một hành động nghiệp vụ hoàn chỉnh.
- Gọi Domain Service và Repository interface.

### Domain

- Chứa quy tắc nghiệp vụ.
- Chứa enum.
- Chứa exception nghiệp vụ.
- Chứa Domain Service như `ScheduleValidator` hoặc `InventoryBalanceService` nếu rule cần tái sử dụng.

### Infrastructure

- Chứa Eloquent repository implementation.
- Chứa database persistence.
- Chứa file importer như CSV/XLSX importer.
- Không để domain rule phụ thuộc trực tiếp vào Eloquent.

### Quy tắc Controller

Controller phải mỏng:

1. Nhận dữ liệu đã validate.
2. Tạo DTO nếu cần.
3. Gọi Use Case.
4. Trả Resource hoặc JSON response.

Business logic không nằm trong Controller.

Repository interface phải tách khỏi Eloquent implementation.

Ưu tiên composition hơn inheritance. Chỉ dùng inheritance khi Laravel framework yêu cầu hoặc khi lợi ích thật sự rõ ràng.

## 7. Quy tắc nghiệp vụ quan trọng

### Schedule

- Mỗi ngày có 2 shift type: `MORNING` và `AFTERNOON`.
- Schedule dùng 2 bảng: `schedules` và `shift_assignments`.
- `schedules` unique trên `(work_date, shift_type)`.
- `shift_assignments` unique trên `(schedule_id, employee_id)`.
- Mỗi ca tối đa 2 nhân viên.
- Không gán cùng một nhân viên hai lần vào cùng một ca.
- Employee dùng Laravel SoftDeletes qua `deleted_at`.
- Không thêm `employees.active` trong MVP.
- `users.status` dùng `ACTIVE` hoặc `LOCKED` để kiểm soát đăng nhập.
- Nhân viên bị soft delete không được xếp lịch mới.
- Lịch sử cũ vẫn được giữ khi nhân viên bị xóa mềm.

### Inventory

- Mọi thay đổi tồn phải tạo `inventory_transaction`.
- Không cập nhật `inventory_balances` trực tiếp từ Controller.
- Các thay đổi tồn phải đi qua Use Case hoặc Domain Service phù hợp.
- Các transaction type trong MVP gồm:
  - `IMPORT_SYNC`.
  - `SALE`.
  - `SALE_REVERSAL`.
  - `BORROW_OUT`.
  - `BORROW_RETURN`.
- `IMPORT_SYNC` là đồng bộ tuyệt đối, không phải cộng dồn.
- Khi confirm import: `quantity_change = imported_quantity - current_quantity`.
- Không ghi đè `inventory_balances` mà thiếu ledger.

### Daily Sales

- Phiếu bán `DRAFT` không thay đổi tồn.
- Phiếu bán chỉ được confirm một lần.
- Confirm sales phải atomic.
- Nếu có lỗi ở bất kỳ dòng nào khi confirm sales, rollback toàn bộ.
- Confirm sales tạo `SALE` transaction và giảm tồn.
- Cancel confirmed sales tạo `SALE_REVERSAL` transaction và cộng tồn lại.

### Borrow

- Borrow làm giảm tồn.
- Return làm tăng tồn.
- Một borrow record không được return hai lần.

### Stock Count

- `expected_quantity` của stock count là snapshot tại thời điểm tạo phiên kiểm kho.
- `expected_quantity` không tự thay đổi dù tồn hiện tại thay đổi sau đó.
- `actual_quantity` phải là số nguyên không âm.
- `variance = actual - expected`.
- MVP chỉ hiển thị variance, không tự động điều chỉnh tồn theo variance.

## 8. Giới hạn phạm vi

Tuyệt đối không thêm vào MVP:

- RabbitMQ.
- Redis.
- Notification Service.
- AI Service.
- Check-in/check-out.
- Nghỉ phép.
- Availability.
- Đổi ca.
- Multi-tenant.
- App mobile.
- Dashboard nâng cao.
- Forecasting.

Nếu xuất hiện nhu cầu ngoài danh sách MVP, ghi vào Future Backlog hoặc tài liệu tương ứng. Không code vào MVP.

## 9. Nguyên tắc code

- Không overengineering.
- Không tạo class, layer, pattern hoặc abstraction không phục vụ use case cụ thể.
- Code phải có type rõ ràng.
- Dùng enum cho role, status, shift type và transaction type.
- Dùng database transaction khi thay đổi tồn.
- HTTP status phải phù hợp với kết quả nghiệp vụ.
- Validation phải nằm ở backend, không chỉ ở frontend.
- Mọi feature phải có test cho happy path và lỗi chính.
- Ưu tiên một luồng chạy xuyên suốt hơn nhiều chức năng dang dở.
- Ưu tiên code rõ, dễ đọc, dễ demo hơn pattern phức tạp.

## 10. Tiêu chí hoàn thành chung

Một feature chỉ được xem là xong khi:

- Chạy được bằng Docker Compose.
- API hoạt động qua Nginx.
- Backend kiểm tra quyền.
- Có validation.
- Có xử lý lỗi.
- Có test.
- React không có console error.
- Tài liệu API được cập nhật.
- Business logic không nằm trong Controller.
- Các nghiệp vụ tồn kho có transaction và rollback khi cần.
- Feature đã được chạy lại theo acceptance checklist.

## 11. Hành động bị cấm

Không được:

- Sửa scope MVP khi chưa có yêu cầu rõ ràng từ người dùng.
- Tạo service thứ ba.
- Chia sẻ database giữa service.
- Cho service truy cập trực tiếp database của service khác.
- Đặt business logic trong Controller.
- Cập nhật inventory balance mà không tạo transaction.
- Chỉ ẩn nút trên React để thay thế backend authorization.
- Thêm RabbitMQ, Redis, AI, Notification Service hoặc dashboard nâng cao vào MVP.
- Tạo dynamic role/permission system trong MVP.
- Thêm workflow phức tạp cho borrow ngoài borrow/return.
- Mở rộng tính năng khi walking skeleton hoặc use case hiện tại chưa chạy được.

## 12. Định dạng output

Khi skill này được dùng để định hướng coding agent:

- Trả lời bằng tiếng Việt.
- Giữ nguyên tên class, API, database, enum và thư mục bằng tiếng Anh.
- Nêu rõ nếu một yêu cầu có nguy cơ vượt scope MVP.
- Đề xuất phương án nhỏ nhất chạy được trước.
- Khi viết code, tuân thủ architecture, OOP rules, business rules và forbidden actions trong file này.
