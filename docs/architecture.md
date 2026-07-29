# Kiến trúc LazyManager

## Walking Skeleton

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

## Ranh giới service

### People Service

Sở hữu:

- Xác thực.
- Người dùng và vai trò.
- Nhân viên.
- Lịch làm.
- Phân công ca.

Database: `people_db`

### Inventory Service

Sở hữu:

- Sản phẩm và SKU.
- Số dư tồn kho.
- Giao dịch tồn kho.
- Nhập tồn kho.
- Bán hàng hằng ngày.
- Bản ghi mượn hàng.
- Phiên kiểm kho.

Database: `inventory_db`

## Xác thực

- People Service phát access JWT sau khi đăng nhập và set vào cookie HttpOnly `lm_access_token`.
- People Service phát refresh token opaque, set vào cookie HttpOnly `lm_refresh_token` và chỉ lưu hash trong `people_db`.
- JWT chứa `sub`, `role`, `iss`, `aud`, `iat` và `exp`.
- Inventory Service xác thực access JWT từ cookie bằng chữ ký, issuer, audience, thời hạn và role bằng demo secret dùng chung.
- Refresh token chỉ thuộc People Service; Inventory Service không đọc hoặc xác minh refresh token.
- React không lưu JWT trong `localStorage`, không gửi token trong response body và không gắn `Authorization: Bearer`.
- Refresh token được rotate khi gọi refresh. Logout backend revoke refresh token hash và clear cookies.
- Cookie auth dùng double-submit CSRF: backend set readable `lm_csrf_token`; frontend gửi lại bằng `X-CSRF-TOKEN` cho state-changing requests; backend so header với cookie.
- Frontend xử lý `401` bằng refresh-once retry: refresh session một lần, retry request cũ đúng một lần, refresh fail thì clear session và về `/login`.
- Backend services thực thi quy tắc phân quyền.

## Laravel Runtime trong Docker

- Trong MVP, mỗi Laravel service phục vụ HTTP bằng `php artisan serve --host=0.0.0.0 --port=8000`.
- Nginx proxy HTTP requests đến `people-service:8000` và `inventory-service:8000`.
- Không thêm container Nginx + PHP-FPM riêng cho từng Laravel service trong MVP.
- Sau MVP, production có thể thay bằng Nginx + PHP-FPM cho từng service.

## Gateway Routes

Public routes qua Nginx:

```text
GET  /api/people/health
GET  /api/people/ready
POST /api/people/v1/auth/login
POST /api/people/v1/auth/refresh
POST /api/people/v1/auth/logout
GET  /api/people/v1/employees

GET  /api/inventory/health
GET  /api/inventory/ready
GET  /api/inventory/v1/products
POST /api/inventory/v1/stock-imports
```

Laravel routes nội bộ giữ nguyên:

```text
/health
/ready
/api/v1/*
```

## Health và readiness

- `GET /health` kiểm tra Laravel đang chạy.
- `GET /ready` kiểm tra Laravel kết nối được database riêng của service.
- Walking skeleton chỉ được xem là Done khi React hiển thị cả hai service là `ready`.

## Cấu trúc repository

```text
LazyManager/
  frontend/
    Dockerfile
    .env.example
    src/
  services/
    people-service/
      Dockerfile
      app/
    inventory-service/
      Dockerfile
      app/
  gateway/
    nginx.conf
  docs/
  docker-compose.yml
  .env.example
  .gitignore
  .dockerignore
  README.md
```

## Quyết định hình dạng database

Dùng hai bảng schedule:

```text
schedules
  id
  work_date
  shift_type
  timestamps

shift_assignments
  id
  schedule_id
  employee_id
  timestamps
```

Ràng buộc:

- `schedules` unique trên `(work_date, shift_type)`.
- `shift_assignments` unique trên `(schedule_id, employee_id)`.

Xóa nhân viên:

- `employees.deleted_at` xử lý soft delete.
- `users.status` xử lý khóa đăng nhập.
- Không lặp lại ý nghĩa đó bằng `employees.active`.

## Cấu trúc Laravel Service

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

## Quy tắc Controller

Controllers chỉ nên:

1. Nhận dữ liệu request đã validate.
2. Tạo DTO.
3. Gọi use case.
4. Trả resource hoặc JSON response.

Quy tắc nghiệp vụ nằm trong use cases hoặc domain services.
