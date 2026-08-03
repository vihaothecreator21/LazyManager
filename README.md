# LazyManager

LazyManager là ứng dụng quản lý lịch làm nhân viên và tồn kho cửa hàng.


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


