# LazyManager

LazyManager là MVP portfolio cho bài toán quản lý lịch làm nhân viên và tồn kho cửa hàng.


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



## Yêu Cầu

- Docker Desktop
- Node.js, chỉ cần khi chạy `frontend` ngoài Docker
- PHP/Composer, chỉ cần khi chạy Laravel services ngoài Docker



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

