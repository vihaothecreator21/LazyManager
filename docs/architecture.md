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

- People Service phát JWT sau khi đăng nhập.
- JWT chứa `sub`, `role`, `iss`, `aud`, `iat` và `exp`.
- Inventory Service xác thực chữ ký, issuer, audience, thời hạn và role bằng demo secret dùng chung.
- Backend services thực thi quy tắc phân quyền.
- Logout trong MVP chỉ xử lý ở frontend: React xóa JWT và chuyển về trang đăng nhập.

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
