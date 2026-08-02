# Kế hoạch setup Walking Skeleton LazyManager

## Quy tắc

Luôn lập kế hoạch trước khi code.

Không bắt đầu bước implement nào cho đến khi task có:

- Trạng thái scope.
- Skill liên quan.
- File cần tạo hoặc sửa.
- Tác động API và DB.
- Bước test hoặc verification.
- Tiêu chí hoàn thành.

## Ánh xạ skill

| Phase | Task | Skill |
| --- | --- | --- |
| 0 | Context, scope, rules | `00-project-context` |
| 0 | Plan before each task | `01-task-planner` |
| 0 | Monorepo, Docker, Nginx, health | `02-walking-skeleton-setup` |
| 1 | Database design | `03-database-design` |
| 2 | Laravel OOP feature | `04-laravel-oop-feature` |
| 3 | React feature | `05-react-feature` |
| 4 | Auth and authorization | `06-authentication-authorization` |
| 5 | Employee | `07-employee-management` |
| 6 | Schedule | `08-schedule-management` |
| 7 | Product and inventory | `09-product-inventory` |
| 8 | Stock import | `10-stock-import` |
| 9 | Daily sales | `11-daily-sales` cần tạo sau |
| 10 | Borrow and return | `12-borrow-return` cần tạo sau |
| 11 | Stock count | `13-stock-count` cần tạo sau |
| 12 | Test, docs, release | `14-quality-delivery` cần tạo sau |

## Kiến trúc đã khóa

```text
Browser
  |
  | http://localhost:8080
  v
Nginx Gateway
  |-- /                     -> frontend:5173
  |-- /api/people/*         -> people-service:8000
  |-- /api/inventory/*      -> inventory-service:8000

People Service
  |-- Auth
  |-- Employee
  |-- Schedule
  v
people_db

Inventory Service
  |-- Product/SKU
  |-- Inventory Ledger
  |-- Stock Import
  |-- Daily Sales
  |-- Borrow/Return
  |-- Stock Count
  v
inventory_db
```

## Cấu trúc repository

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
│   ├── scope.md
│   ├── rules.md
│   ├── architecture.md
│   ├── decisions.md
│   ├── api.md
│   └── setup-plan.md
├── skills/
├── docker-compose.yml
├── .env.example
├── .gitignore
├── .dockerignore
└── README.md
```

## Kế hoạch thực thi

| Bước | Task | Hoàn thành khi |
| --- | --- | --- |
| 0 | Check workspace | Biết repo đang trống hay đã có code |
| 1 | Lock docs | `scope.md`, `rules.md`, `architecture.md`, `decisions.md`, `api.md`, `setup-plan.md` đã cập nhật |
| 2 | Create monorepo folders | Cấu trúc thư mục khớp kế hoạch này |
| 3 | Create Laravel and React skeletons | `frontend`, `people-service`, `inventory-service` tồn tại và có thể chạy riêng |
| 4 | Create Dockerfiles | Mỗi app image build được |
| 5 | Create Docker Compose | `gateway`, `frontend`, `people-service`, `inventory-service`, `people-db`, `inventory-db` được định nghĩa |
| 6 | Create Nginx routes | Frontend và APIs route qua `localhost:8080` |
| 7 | Create health and readiness | Cả hai services expose `/health` và `/ready` |
| 8 | Create React health page | React hiển thị cả hai services là `ready` qua Nginx |
| 9 | Create env and README | Developer khác có thể chạy setup |
| 10 | Clean rebuild | `docker compose down -v` rồi rebuild thành công |
| 11 | Commit foundation | Commit message `feat: setup walking skeleton` |

## Hình dạng Docker Compose

Containers:

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

Host ports:

- Mặc định chỉ expose Nginx: `8080:80`.
- Không expose database ports trừ khi debug bằng pgAdmin hoặc DBeaver.

## Laravel Runtime

Trong MVP:

```text
php artisan serve --host=0.0.0.0 --port=8000
```

Dùng lệnh này trong cả hai Laravel service containers.

Không thêm Nginx + PHP-FPM riêng ở cấp service trong MVP.

## Tiêu chí hoàn thành của Walking Skeleton

Walking skeleton được xem là Done khi:

- `http://localhost:8080/` mở React.
- React gọi `GET /api/people/ready` qua Nginx.
- React gọi `GET /api/inventory/ready` qua Nginx.
- People Service chỉ kết nối `people_db`.
- Inventory Service chỉ kết nối `inventory_db`.
- Không React call nào dùng port service trực tiếp.
- Không cần CORS workaround.
- README có một lệnh run rõ ràng.
- Clean rebuild pass.
