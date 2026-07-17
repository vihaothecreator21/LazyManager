---
name: 02-walking-skeleton-setup
description: Chuẩn hóa setup walking skeleton cho LazyManager MVP. Dùng khi tạo hoặc review monorepo, Docker Compose, Nginx API Gateway, React Vite skeleton, hai Laravel services, hai PostgreSQL databases, Laravel artisan serve runtime, gateway route prefixes, health/ready endpoints và clean rebuild verification.
---

# LazyManager MVP - Skill setup Walking Skeleton

## 1. Mục đích

Skill này hướng dẫn dựng nền tảng chạy xuyên suốt trước khi code nghiệp vụ.

Mục tiêu là chứng minh đường đi tối thiểu hoạt động:

```text
React
  -> Nginx
    -> People Service
      -> people_db

React
  -> Nginx
    -> Inventory Service
      -> inventory_db
```

Không implement business feature trước khi walking skeleton chạy được.

## 2. Input bắt buộc

Trước khi setup phải đọc:

- `docs/scope.md`.
- `docs/rules.md`.
- `docs/architecture.md`.
- `docs/decisions.md`.
- `docs/api.md`.
- `docs/setup-plan.md`.
- `skills/00-project-context/SKILL.md`.
- `skills/01-task-planner/SKILL.md`.

## 3. Cấu trúc repository

Tạo hoặc kiểm tra cấu trúc:

```text
LazyManager/
├── frontend/
│   ├── Dockerfile
│   ├── .env.example
│   └── src/
├── services/
│   ├── people-service/
│   │   ├── Dockerfile
│   │   └── app/
│   └── inventory-service/
│       ├── Dockerfile
│       └── app/
├── gateway/
│   └── nginx.conf
├── docs/
├── skills/
├── docker-compose.yml
├── .env.example
├── .gitignore
├── .dockerignore
└── README.md
```

## 4. Hợp đồng Gateway

Public URL:

```text
http://localhost:8080
```

Nginx routes:

```text
/                       -> frontend:5173
/api/people/*           -> people-service:8000
/api/inventory/*        -> inventory-service:8000
/api/people/v1/*        -> People Service /api/v1/*
/api/inventory/v1/*     -> Inventory Service /api/v1/*
```

Health routes:

```text
/api/people/health      -> people-service /health
/api/people/ready       -> people-service /ready
/api/inventory/health   -> inventory-service /health
/api/inventory/ready    -> inventory-service /ready
```

React không được gọi thẳng port nội bộ của service.

## 5. Laravel Runtime

Trong MVP, mỗi Laravel service chạy:

```text
php artisan serve --host=0.0.0.0 --port=8000
```

Không dựng thêm Nginx + PHP-FPM riêng cho từng Laravel service trong MVP.

## 6. Docker Compose

Container bắt buộc:

- `gateway`.
- `frontend`.
- `people-service`.
- `inventory-service`.
- `people-db`.
- `inventory-db`.

Network:

- `lazymanager-network`.

Volumes:

- `people-db-data`.
- `inventory-db-data`.

Host port:

- Chỉ expose `gateway` mặc định: `8080:80`.

Không expose database port mặc định. Chỉ mở tạm khi cần debug bằng pgAdmin hoặc DBeaver.

## 7. Health và readiness

Mỗi Laravel service phải có:

```text
GET /health
GET /ready
```

`/health` chỉ kiểm tra service đang chạy.

Response:

```json
{
  "status": "ok",
  "service": "people-service"
}
```

`/ready` kiểm tra service kết nối được database riêng.

Response:

```json
{
  "status": "ready",
  "service": "people-service",
  "database": "connected"
}
```

Inventory Service dùng `service = inventory-service`.

## 8. Quy trình setup

Luôn thực hiện theo thứ tự:

1. Kiểm tra workspace hiện tại.
2. Đọc và khóa docs nền.
3. Tạo monorepo folders.
4. Tạo React Vite skeleton.
5. Tạo People Laravel skeleton.
6. Tạo Inventory Laravel skeleton.
7. Tạo Dockerfile cho từng app.
8. Tạo `docker-compose.yml`.
9. Tạo `gateway/nginx.conf`.
10. Tạo `/health` và `/ready` cho hai service.
11. Tạo React health page.
12. Tạo `.env.example`.
13. Tạo README setup.
14. Chạy build/up.
15. Chạy clean rebuild.
16. Commit nền.

## 9. Lệnh verification

Các lệnh verification tối thiểu:

```text
docker compose build
docker compose up
docker compose down -v
docker compose up --build
```

Kiểm tra URL:

```text
http://localhost:8080/
http://localhost:8080/api/people/health
http://localhost:8080/api/people/ready
http://localhost:8080/api/inventory/health
http://localhost:8080/api/inventory/ready
```

## 10. Hành động bị cấm

Không được:

- Code business feature trước khi skeleton chạy.
- Tạo service thứ ba.
- Dùng RabbitMQ hoặc Redis.
- Thêm Nginx + PHP-FPM riêng cho từng Laravel service trong MVP.
- Cho React gọi trực tiếp `people-service:8000`, `inventory-service:8000`, `localhost:8001` hoặc `localhost:8002`.
- Cho service truy cập database của service khác.
- Expose nhiều port ra host khi chưa cần.

## 11. Tiêu chí hoàn thành

Walking skeleton chỉ Done khi:

- React mở được qua `http://localhost:8080/`.
- Nginx route đúng đến frontend, People Service và Inventory Service.
- People Service `/ready` kết nối được `people_db`.
- Inventory Service `/ready` kết nối được `inventory_db`.
- React health page hiển thị cả hai service là `ready`.
- Docker Compose clean rebuild thành công.
- README có lệnh chạy rõ ràng.
- Không có business logic nào bị code trước skeleton.

## 12. Định dạng output

Khi dùng skill này:

- Trả lời bằng tiếng Việt.
- Giữ tên class, API, service, database, container và folder bằng tiếng Anh.
- Luôn plan trước khi tạo hoặc sửa file.
