---
name: 06-authentication-authorization
description: Chuẩn hóa Authentication và Authorization cho LazyManager MVP. Dùng khi implement hoặc review UC-01, UC-02, login, JWT, middleware xác thực, role STORE_MANAGER/STAFF, ManagerOnly policy, React auth flow, 401/403 handling và auth tests.
---

# LazyManager MVP - Skill Authentication & Authorization

## 1. Mục đích

Skill này hướng dẫn triển khai đăng nhập, phát access JWT cookie, refresh token hash backend, xác thực request và kiểm tra hai role trong LazyManager MVP.

Skill bao phủ:

- `UC-01`: Đăng nhập.
- `UC-02`: Đăng xuất.

People Service chịu trách nhiệm phát access JWT và refresh token. Cả People Service và Inventory Service phải xác thực access JWT ở backend. React chỉ hỗ trợ trải nghiệm người dùng, không phải nguồn bảo mật.

Luôn tuân thủ:

- `skills/00-project-context/SKILL.md`.
- `skills/04-laravel-oop-feature/SKILL.md`.
- `skills/05-react-feature/SKILL.md`.

## 2. Vai trò

LazyManager MVP chỉ có hai role:

- `STORE_MANAGER`.
- `STAFF`.

Quy tắc:

- Cả hai role được login.
- Cả hai role được thao tác các module chính trong MVP.
- Chỉ `STORE_MANAGER` được `POST`, `PUT`, `DELETE` employees.
- `STAFF` gọi employee mutation endpoints phải nhận HTTP `403`.

Không xây role/permission builder trong MVP.

## 3. Yêu cầu Auth

Phương án B bắt buộc:

- Access token là JWT ngắn hạn.
- Access JWT nằm trong cookie HttpOnly `lm_access_token`.
- Refresh token là opaque random token.
- Refresh token raw nằm trong cookie HttpOnly `lm_refresh_token`.
- People Service chỉ lưu hash của refresh token trong `people_db`.
- Không lưu raw refresh token.
- Không trả access token hoặc refresh token trong JSON response.
- React không lưu JWT trong `localStorage`, `sessionStorage` hoặc memory state.
- React không gắn `Authorization: Bearer`.
- Protected requests gửi cookie qua same-origin Nginx gateway.
- Cookie dùng `HttpOnly`, `SameSite=Lax`, `Secure` ở HTTPS; local Docker có thể tắt `Secure`.
- State-changing requests phải có CSRF protection phù hợp với cookie auth.

Access JWT chứa:

- `sub`.
- `role`.
- `iss`.
- `aud`.
- `iat`.
- `exp`.

Quy tắc:

- People Service phát access JWT sau khi login thành công.
- Inventory Service xác minh access JWT từ cookie bằng cùng secret trong môi trường demo.
- JWT secret phải nằm trong environment.
- Không hard-code secret.
- Token hết hạn phải bị từ chối với HTTP `401`.
- Token sai chữ ký phải bị từ chối với HTTP `401`.
- Không tin role do React gửi trong request body.
- Refresh token thiếu, sai hash, hết hạn hoặc bị revoke phải bị từ chối với HTTP `401`.

## 4. Component backend

Các component backend cần có:

- `UserRole` enum.
- `LoginData` DTO.
- `LoginUseCase`.
- `AuthController`.
- `LoginRequest`.
- `JwtService` interface.
- JWT implementation.
- `RefreshToken` model hoặc persistence component tương đương.
- Migration lưu refresh token hash.
- `RefreshTokenService` hoặc use case quản lý issue, hash, verify, rotate và revoke refresh token.
- `AuthenticateJwt` middleware.
- `ManagerOnly` middleware hoặc Policy.
- `CurrentUser` context nếu cần.

Vị trí theo kiến trúc:

- Enum và business exceptions đặt trong `Domain`.
- DTO, Use Case và interface đặt trong `Application`.
- JWT implementation đặt trong `Infrastructure` hoặc service implementation phù hợp.
- Controller, Request và Middleware đặt trong `Http`.

## 5. Luồng login

Luồng login chuẩn:

1. Nhận `email` và `password`.
2. Validate bằng `LoginRequest`.
3. Tạo `LoginData`.
4. `AuthController` gọi `LoginUseCase`.
5. `LoginUseCase` tìm user theo email.
6. Kiểm tra password bằng password hashing API của Laravel.
7. Kiểm tra `users.status = ACTIVE`.
8. Phát access JWT qua `JwtService`.
9. Phát refresh token opaque.
10. Hash refresh token và lưu hash active trong database.
11. Set cookie `lm_access_token` và `lm_refresh_token`.
12. Trả user, không trả token.

Response nên trả tối thiểu:

```json
{
  "user": {
    "id": 1,
    "email": "manager@example.com",
    "role": "STORE_MANAGER"
  }
}
```

Không trả password hash.

Không log password, JWT hoặc raw refresh token.

## 6. Luồng refresh

Luồng refresh chuẩn:

1. Nhận request `POST /api/people/v1/auth/refresh`.
2. Đọc cookie `lm_refresh_token`.
3. Hash raw refresh token từ cookie.
4. Tìm refresh token hash active, chưa hết hạn, chưa revoke.
5. Kiểm tra user còn `ACTIVE`.
6. Revoke hash cũ.
7. Phát access JWT mới.
8. Phát refresh token mới, hash và lưu.
9. Set lại cả hai cookies.
10. Trả user hoặc `204`.

Nếu refresh token thiếu, sai, hết hạn hoặc đã revoke:

- Trả HTTP `401`.
- Clear `lm_access_token` và `lm_refresh_token`.

## 7. Luồng logout

Logout trong MVP:

1. Nhận request `POST /api/people/v1/auth/logout`.
2. Đọc cookie `lm_refresh_token`.
3. Hash raw refresh token và revoke bản ghi active nếu tồn tại.
4. Clear `lm_access_token` và `lm_refresh_token`.
5. Trả HTTP `204`.

Không cần blacklist access JWT trong MVP. Access JWT bị copy trước logout vẫn có thể hợp lệ đến khi hết hạn, nên access token phải ngắn hạn và không được log.

## 8. Quy tắc phân quyền

- Chỉ `STORE_MANAGER` được `POST`, `PUT`, `DELETE` employees.
- `STAFF` gọi các endpoint đó nhận `403`.
- Mọi endpoint bảo vệ phải xác thực ở backend.
- Inventory Service không tin role do React gửi trong body.
- Role phải lấy từ JWT đã xác minh hoặc `CurrentUser` context sinh ra từ JWT.
- JWT phải được lấy từ cookie HttpOnly `lm_access_token`.
- Frontend có thể ẩn nút theo role, nhưng backend vẫn phải enforce.

Employee mutation endpoints bắt buộc bảo vệ:

- `POST /api/people/v1/employees`.
- `PUT /api/people/v1/employees/{id}`.
- `DELETE /api/people/v1/employees/{id}`.

## 9. Luồng frontend

Frontend auth flow:

- Login page.
- Auth context hoặc state.
- Protected route.
- HTTP client cấu hình `withCredentials` khi cần.
- `401` logout và redirect về login.
- `403` hiển thị lỗi không có quyền.
- Không đọc hoặc lưu token phía React.
- Login success lưu user/session state không nhạy cảm.
- Refresh gọi `/auth/refresh`; nếu fail thì xóa user/session state và chuyển về login.
- Logout gọi `/auth/logout`; sau đó xóa user/session state và chuyển về login.

Token storage:

- Không lưu access JWT hoặc refresh token trong `localStorage`, `sessionStorage` hoặc memory state.
- Cookie HttpOnly là storage duy nhất cho token.

Không gửi role trong request body để backend tin tưởng.

## 10. Test bắt buộc

Backend tests bắt buộc:

- Manager login thành công.
- Staff login thành công.
- Sai password trả `401`.
- User `LOCKED` trả `403`.
- Login set cookie `lm_access_token` và `lm_refresh_token`.
- Login không trả token trong JSON.
- Refresh token hợp lệ rotate refresh token và set cookies mới.
- Refresh token sai hash trả `401` và clear cookies.
- Refresh token hết hạn hoặc revoked trả `401`.
- Logout revoke refresh token hash và clear cookies.
- Không access cookie trả `401`.
- Access token sai chữ ký trả `401`.
- Access token hết hạn trả `401`.
- Staff `POST /api/people/v1/employees` trả `403`.
- Manager `POST /api/people/v1/employees` đi qua middleware.

Frontend tests nên có:

- Login page render.
- Login validation error.
- Login success lưu user/session state và chuyển trang.
- `401` xóa user/session state và redirect login.
- `403` hiển thị lỗi quyền.
- `STAFF` không thấy employee mutation buttons.
- Logout gọi backend và chuyển về login.

## 11. Quy tắc bảo mật

- Password phải hash bằng API chuẩn của Laravel.
- Không log password.
- Không log JWT.
- Không log raw refresh token.
- Chỉ lưu hash refresh token.
- Rotate refresh token khi refresh.
- JWT secret nằm trong environment.
- Không hard-code secret.
- CORS và cookie credentials giới hạn phù hợp với frontend origin/gateway của MVP.
- CSRF protection bắt buộc cho state-changing requests khi dùng cookie auth.
- Validate mọi request.
- Không trả stack trace ra response production-like.
- Không lưu secret trong git.
- Không trả password hash trong API response.
- Không dùng frontend role check thay cho backend authorization.

## 12. Hành động bị cấm

Không được:

- Xây role/permission builder.
- Thêm OAuth.
- Thêm social login.
- Lưu token trong `localStorage` hoặc `sessionStorage`.
- Trả token trong JSON response.
- Gắn `Authorization: Bearer` từ React.
- Lưu raw refresh token trong database.
- Thêm Redis hoặc access-token blacklist nếu chưa cần trong MVP.
- Chỉ kiểm tra role trên React.
- Tin role từ request body.
- Hard-code JWT secret.
- Log password, JWT hoặc raw refresh token.
- Đặt auth business logic trong Controller.
- Gửi nguyên HTTP Request vào `LoginUseCase`.

## 13. Tiêu chí hoàn thành

Auth feature chỉ được xem là Done khi:

- People Service phát access JWT cookie đúng.
- People Service phát refresh token cookie và lưu hash backend.
- People Service refresh token rotation đúng.
- Logout revoke refresh token hash và clear cookies.
- People Service xác minh access cookie cho protected endpoints.
- Inventory Service xác minh access cookie đúng bằng cùng demo secret.
- Manager và Staff có hành vi đúng.
- Test authorization pass.
- React xử lý `401` đúng.
- React xử lý `403` đúng.
- Employee mutation endpoints chặn `STAFF` ở backend.
- Password được hash.
- JWT secret nằm trong environment.
- Không token nào được lưu trong `localStorage` hoặc trả trong JSON.

## 14. Định dạng output

Chỉ trả nội dung `SKILL.md`.
