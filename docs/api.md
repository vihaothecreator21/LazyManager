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
POST   /api/people/v1/auth/refresh
POST   /api/people/v1/auth/logout
GET    /api/people/v1/employees
POST   /api/people/v1/employees
PUT    /api/people/v1/employees/{id}
DELETE /api/people/v1/employees/{id}
GET    /api/people/v1/schedules?week_start=YYYY-MM-DD
POST   /api/people/v1/schedules/assignments
DELETE /api/people/v1/schedules/assignments/{id}
POST   /api/people/v1/schedules/day-offs
DELETE /api/people/v1/schedules/day-offs/{id}
```

Auth dùng cookie HttpOnly. React không lưu JWT trong `localStorage` hoặc memory state.

Employee Management:

- `GET /employees` trả danh sách nhân viên cho `STORE_MANAGER`.
- `POST /employees` tạo nhân viên mới, yêu cầu CSRF và role `STORE_MANAGER`.
- `PUT /employees/{id}` cập nhật `name`, `email`, `role`, `status`, `password` tùy chọn.
- `DELETE /employees/{id}` khóa tài khoản bằng `status = LOCKED`, không xóa row.
- Response employee chỉ trả `id`, `name`, `email`, `role`, `status`, `created_at`, `updated_at`.

## API công khai của Inventory Service

```text
GET    /api/inventory/v1/products?search=
POST   /api/inventory/v1/products
GET    /api/inventory/v1/products/{id}
PUT    /api/inventory/v1/products/{id}
DELETE /api/inventory/v1/products/{id}
POST   /api/inventory/v1/products/{id}/skus
PUT    /api/inventory/v1/skus/{id}
DELETE /api/inventory/v1/skus/{id}
GET    /api/inventory/v1/inventory?search=
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

Product/SKU/Inventory MVP:

- `GET /products?search=` trả danh sách sản phẩm kèm SKU và tồn kho hiện tại. Tìm kiếm theo `product_code`, `name` hoặc `sku_code`.
- `POST /products` tạo sản phẩm, chuẩn hóa `product_code` bằng trim + uppercase.
- `PUT /products/{id}` cập nhật `product_code` và `name`; không cho cập nhật trực tiếp `active`.
- `DELETE /products/{id}` ngừng hoạt động sản phẩm, ngừng hoạt động và soft-delete toàn bộ SKU thuộc sản phẩm; giữ nguyên balance và transaction.
- `POST /products/{id}/skus` tạo SKU, chuẩn hóa `sku_code` bằng trim + uppercase, đồng thời tạo `inventory_balances.quantity = 0`.
- `PUT /skus/{id}` cập nhật `sku_code` và `size`; không cho cập nhật trực tiếp `active`.
- `DELETE /skus/{id}` ngừng hoạt động và soft-delete SKU; giữ nguyên balance và transaction.
- Trùng `product_code` hoặc `sku_code`, kể cả bản ghi đã soft-delete, trả `409`.
- `GET /inventory?search=` chỉ trả Product/SKU chưa soft-delete, dạng dòng phẳng để xem tồn kho. Tìm kiếm theo `product_code`, `product_name` hoặc `sku_code`.
- `GET /inventory/{skuId}/transactions` dùng binding có `withTrashed()`, nên vẫn đọc được lịch sử giao dịch của SKU đã soft-delete.
- Lịch sử giao dịch trả mới nhất trước theo `created_at desc, id desc`.
- Mutation `POST`, `PUT`, `DELETE` bắt buộc gửi `X-CSRF-TOKEN` khớp cookie `lm_csrf_token`; `GET` không cần CSRF.

Duplicate response:

```json
{
  "message": "Mã sản phẩm đã tồn tại."
}
```

Inventory business error response:

```json
{
  "message": "Tồn kho không đủ để thực hiện thao tác này."
}
```

## Yêu cầu Auth

Phương án B:

- Access token là JWT ngắn hạn, được set vào cookie HttpOnly `lm_access_token`.
- Refresh token là opaque random token, được set vào cookie HttpOnly `lm_refresh_token`.
- People Service chỉ lưu hash của refresh token trong `people_db`, không lưu raw token.
- Protected APIs đọc access JWT từ cookie, không nhận token từ JSON response và không yêu cầu React gắn `Authorization: Bearer`.
- Cookie dùng `HttpOnly`, `SameSite=Lax`, `Secure` ở môi trường HTTPS; local Docker có thể tắt `Secure`.
- State-changing requests phải có CSRF protection phù hợp với cookie auth.

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

## Auth endpoints

`POST /api/people/v1/auth/login`

Request:

```json
{
  "email": "manager@example.com",
  "password": "password"
}
```

Response `200` set cookies `lm_access_token`, `lm_refresh_token` và `lm_csrf_token`:

```json
{
  "user": {
    "id": 1,
    "email": "manager@example.com",
    "role": "STORE_MANAGER"
  }
}
```

`POST /api/people/v1/auth/refresh`

- Đọc `lm_refresh_token`.
- Yêu cầu `X-CSRF-TOKEN` trùng với cookie `lm_csrf_token`.
- Hash raw token từ cookie rồi so với hash đang active trong database.
- Nếu hợp lệ, rotate refresh token, set lại access/refresh/CSRF cookies và revoke hash cũ.
- Nếu thiếu, sai, hết hạn hoặc đã revoke, trả `401` và clear cookies.

`POST /api/people/v1/auth/logout`

- Đọc `lm_refresh_token`.
- Yêu cầu `X-CSRF-TOKEN` trùng với cookie `lm_csrf_token`.
- Revoke refresh token hash nếu tồn tại.
- Clear `lm_access_token`, `lm_refresh_token` và `lm_csrf_token`.
- Trả `204`.

## Cookie auth + CSRF flow

- Login: People Service verifies password, issues access JWT, stores refresh token hash, sets `lm_access_token` HttpOnly, `lm_refresh_token` HttpOnly, and readable `lm_csrf_token`.
- Protected GET: service reads `lm_access_token` cookie. `Authorization: Bearer` is ignored.
- Protected POST/PUT/PATCH/DELETE: service reads `lm_access_token`, then verifies double-submit CSRF by comparing `X-CSRF-TOKEN` with `lm_csrf_token`.
- Refresh: frontend sends cookies plus `X-CSRF-TOKEN`; backend revokes old refresh hash, stores new refresh hash, sets new access/refresh/CSRF cookies.
- Logout: frontend sends cookies plus `X-CSRF-TOKEN`; backend revokes refresh hash and clears all auth cookies.
- Frontend retry: first `401` calls `/api/people/v1/auth/refresh`, retries the original request once, then clears session and redirects to `/login` if refresh fails.
- Inventory compatibility: Inventory Service verifies the same access JWT from `lm_access_token` using shared `JWT_SECRET`, issuer, audience, expiry, and role.

## Phase 3 test checklist

- People auth tests: login cookies, no JSON token, refresh rotate, old refresh fails, logout clears cookies, CSRF missing `419`, Bearer ignored.
- Inventory auth tests: missing/wrong/expired cookie `401`, manager/staff role read from cookie JWT, CSRF missing `419`, CSRF valid pass.
- Frontend build: no localStorage JWT, no Authorization/Bearer header, credentials enabled, refresh-once retry path compiled.
- Gateway smoke: login `200`, CSRF cookie present, refresh `200`, inventory `/api/inventory/v1/auth/me` `200`.

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
