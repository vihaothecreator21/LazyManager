#  API Gateway LazyManager

## Base URL công khai

```text
http://localhost:8080
```

React chỉ được gọi origin này.

## Quy tắc route của Gateway

```text
/                       -> frontend:5173
/api/people/*           -> people-service:8000
/api/inventory/*        -> inventory-service:8000
```

Ánh xạ API có phiên bản:

```text
/api/people/v1/*        -> people-service /api/v1/*
/api/inventory/v1/*     -> inventory-service /api/v1/*
```

Ánh xạ health:

```text
/api/people/health      -> people-service /health
/api/people/ready       -> people-service /ready
/api/inventory/health   -> inventory-service /health
/api/inventory/ready    -> inventory-service /ready
```

## API công khai của People Service

```text
POST   /api/people/v1/auth/login
GET    /api/people/v1/employees
POST   /api/people/v1/employees
GET    /api/people/v1/employees/{id}
PUT    /api/people/v1/employees/{id}
DELETE /api/people/v1/employees/{id}
GET    /api/people/v1/schedules?week_start=YYYY-MM-DD
POST   /api/people/v1/schedules
POST   /api/people/v1/schedules/{id}/assignments
DELETE /api/people/v1/schedules/{id}/assignments/{employeeId}
```

MVP không có endpoint logout ở backend. React xóa JWT ở phía client.

## API công khai của Inventory Service

```text
GET    /api/inventory/v1/products
POST   /api/inventory/v1/products
GET    /api/inventory/v1/products/{id}
PUT    /api/inventory/v1/products/{id}
DELETE /api/inventory/v1/products/{id}
POST   /api/inventory/v1/products/{id}/skus
PUT    /api/inventory/v1/skus/{id}
DELETE /api/inventory/v1/skus/{id}
GET    /api/inventory/v1/inventory
GET    /api/inventory/v1/inventory/{skuId}/transactions
POST   /api/inventory/v1/stock-imports
GET    /api/inventory/v1/stock-imports/{id}/preview
POST   /api/inventory/v1/stock-imports/{id}/confirm
POST   /api/inventory/v1/daily-sales
POST   /api/inventory/v1/daily-sales/{id}/confirm
POST   /api/inventory/v1/daily-sales/{id}/cancel
POST   /api/inventory/v1/borrow-records
POST   /api/inventory/v1/borrow-records/{id}/return
POST   /api/inventory/v1/stock-counts
GET    /api/inventory/v1/stock-counts/{id}/export-csv
PUT    /api/inventory/v1/stock-counts/{id}/lines
```

## Yêu cầu JWT

JWT claims:

- `sub`.
- `role`.
- `iss`.
- `aud`.
- `iat`.
- `exp`.

Inventory Service phải xác thực:

- Chữ ký.
- Issuer.
- Audience.
- Thời hạn.
- Role.

## Response health

`GET /health`:

```json
{
  "status": "ok",
  "service": "people-service"
}
```

`GET /ready`:

```json
{
  "status": "ready",
  "service": "people-service",
  "database": "connected"
}
```

Dùng giá trị `service` tương ứng cho `inventory-service`.
