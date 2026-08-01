# LazyManager

LazyManager là MVP portfolio cho bài toán quản lý lịch làm nhân viên và tồn kho cửa hàng.

Giai đoạn hiện tại: đã có walking skeleton, cookie auth, refresh/logout, CSRF, phân quyền cơ bản và Employee Management. Task kế tiếp là Schedule Management.

## Kiến Trúc

```text
Browser
  |
  | http://localhost:8080
  v
Nginx API Gateway
  |-- /                    -> frontend:80
  |-- /api/people/*        -> people-service:8000
  |-- /api/inventory/*     -> inventory-service:8000

People Service
  -> people_db

Inventory Service
  -> inventory_db
```

## Containers

Docker Compose bật 6 containers:

```text
gateway
frontend
people-service
inventory-service
people-db
inventory-db
```

Mặc định chỉ `gateway` được expose ra máy host.

## Yêu Cầu

- Docker Desktop
- Node.js, chỉ cần khi chạy `frontend` ngoài Docker
- PHP/Composer, chỉ cần khi chạy Laravel services ngoài Docker

Với workflow local bình thường, chỉ cần Docker Desktop.

## Chạy Nhanh

Tạo file môi trường local:

```powershell
Copy-Item .env.example .env
```

Điền các biến bắt buộc trong `.env`:

```text
PEOPLE_APP_KEY=
INVENTORY_APP_KEY=
JWT_SECRET=
```

Bật toàn bộ hệ thống:

```powershell
docker compose up -d --build
```

Mở:

```text
http://localhost:8080
```

## Demo Seeders

Mặc định container không tự seed demo data. Nếu cần tài khoản demo, đặt:

```text
RUN_DEMO_SEEDERS=true
ADMIN_EMAIL=manager@example.com
ADMIN_PASSWORD=Tangvihao211
```

Nếu không đặt `ADMIN_PASSWORD`, seeder sẽ dùng mật khẩu mặc định là `Tangvihao211`.


## Health Checks

Kiểm tra qua gateway:

```text
http://localhost:8080/api/people/health
http://localhost:8080/api/people/ready
http://localhost:8080/api/inventory/health
http://localhost:8080/api/inventory/ready
```

Response readiness mong đợi:

```json
{
  "status": "ready",
  "service": "people-service",
  "database": "connected"
}
```

`inventory-service` trả cùng cấu trúc, khác `service`.

## Port 8080 Bị Chiếm

Nếu project khác đang dùng port `8080`, chạy gateway bằng port khác:

```powershell
$env:GATEWAY_PORT="8081"
docker compose up -d
```

Mở:

```text
http://localhost:8081
```

## Tắt Hệ Thống

Tắt containers và giữ database volumes:

```powershell
docker compose down
```

Dùng lệnh này sau phiên code/học để giải phóng RAM Docker/WSL.

Tắt containers và xóa database volumes:

```powershell
docker compose down -v
```

Chỉ dùng `-v` khi chủ động muốn reset database sạch.

## Verification

Build frontend:

```powershell
cd frontend
npm run build
```

Chạy backend tests:

```powershell
docker compose exec -T people-service php artisan test
docker compose exec -T inventory-service php artisan test
```

Validate Docker Compose:

```powershell
docker compose config
```

Build images:

```powershell
docker compose build
```

Chạy hệ thống:

```powershell
docker compose up -d
```

Kiểm tra readiness:

```powershell
Invoke-RestMethod http://localhost:8080/api/people/ready
Invoke-RestMethod http://localhost:8080/api/inventory/ready
```

## Phạm Vi MVP

Trong phạm vi:

- React web app
- Nginx API Gateway
- People Service với `people_db`
- Inventory Service với `inventory_db`
- REST APIs
- Backend kiểm tra role
- Mỗi service có PostgreSQL database riêng

Ngoài phạm vi MVP:

- Redis
- RabbitMQ
- AI service
- Notification service
- Mobile app
- Multi-tenant SaaS
- Advanced dashboard

## Milestones

1. `UC-01`: đăng nhập bằng access JWT cookie và refresh token hash backend. Đã có.
2. `UC-02`: refresh/logout, cookie auth, CSRF protection và role checks. Đã có.
3. `UC-03` đến `UC-06`: Employee Management. Đã có.
4. `UC-07` đến `UC-09`: Schedule Management. Tiếp theo.
5. `UC-10` trở đi: Product, Inventory, Stock Import, Sales, Borrow, Stock Count.
