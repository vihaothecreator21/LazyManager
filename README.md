# LazyManager

LazyManager là MVP portfolio cho bài toán quản lý lịch làm nhân viên và tồn kho cửa hàng.

Giai đoạn hiện tại: walking skeleton. Chưa có tính năng nghiệp vụ thật.

## Kiến Trúc

```text
Browser
  |
  | http://localhost:8080
  v
Nginx API Gateway
  |-- /                    -> frontend:5173
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

Bật toàn bộ hệ thống:

```powershell
docker compose up -d --build
```

Mở:

```text
http://localhost:8080
```

Trang health dashboard của frontend nên hiển thị:

```text
People Service: ready
Inventory Service: ready
```

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
docker compose up -d --build
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

## Milestones Tiếp Theo

1. Implement `UC-01` login trong People Service.
2. Thêm JWT authentication và role checks.
3. Xây Employee Management.
4. Xây Schedule Management.
5. Xây Product, Inventory, Stock Import, Sales, Borrow, Stock Count.
