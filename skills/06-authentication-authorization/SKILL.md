---
name: 06-authentication-authorization
description: Chuẩn hóa Authentication và Authorization cho LazyManager MVP. Dùng khi implement hoặc review UC-01, UC-02, login, JWT, middleware xác thực, role STORE_MANAGER/STAFF, ManagerOnly policy, React auth flow, 401/403 handling và auth tests.
---

# LazyManager MVP - Skill Authentication & Authorization

## 1. Mục đích

Skill này hướng dẫn triển khai đăng nhập, phát JWT, xác thực request và kiểm tra hai role trong LazyManager MVP.

Skill bao phủ:

- `UC-01`: Đăng nhập.
- `UC-02`: Đăng xuất.

People Service chịu trách nhiệm phát JWT. Cả People Service và Inventory Service phải xác thực JWT ở backend. React chỉ hỗ trợ trải nghiệm người dùng, không phải nguồn bảo mật.

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

## 3. Yêu cầu JWT

JWT chứa:

- `sub`.
- `role`.
- `iss`.
- `aud`.
- `iat`.
- `exp`.

Quy tắc:

- People Service phát JWT sau khi login thành công.
- Inventory Service xác minh JWT bằng cùng secret trong môi trường demo.
- JWT secret phải nằm trong environment.
- Không hard-code secret.
- Token hết hạn phải bị từ chối với HTTP `401`.
- Token sai chữ ký phải bị từ chối với HTTP `401`.
- Không tin role do React gửi trong request body.

## 4. Component backend

Các component backend cần có:

- `UserRole` enum.
- `LoginData` DTO.
- `LoginUseCase`.
- `AuthController`.
- `LoginRequest`.
- `JwtService` interface.
- JWT implementation.
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
8. Phát JWT qua `JwtService`.
9. Trả user và token.

Response nên trả tối thiểu:

```json
{
  "user": {
    "id": 1,
    "email": "manager@example.com",
    "role": "STORE_MANAGER"
  },
  "token": "jwt-token",
  "expires_at": "..."
}
```

Không trả password hash.

Không log password hoặc JWT.

## 6. Quy tắc phân quyền

- Chỉ `STORE_MANAGER` được `POST`, `PUT`, `DELETE` employees.
- `STAFF` gọi các endpoint đó nhận `403`.
- Mọi endpoint bảo vệ phải xác thực ở backend.
- Inventory Service không tin role do React gửi trong body.
- Role phải lấy từ JWT đã xác minh hoặc `CurrentUser` context sinh ra từ JWT.
- Frontend có thể ẩn nút theo role, nhưng backend vẫn phải enforce.

Employee mutation endpoints bắt buộc bảo vệ:

- `POST /api/people/v1/employees`.
- `PUT /api/people/v1/employees/{id}`.
- `DELETE /api/people/v1/employees/{id}`.

## 7. Logout trong MVP

Logout trong MVP:

- React xóa token.
- Có thể không làm refresh token phức tạp.
- Không tạo token blacklist nếu không cần trong MVP.
- Tài liệu phải nói rõ giới hạn này.

Giới hạn MVP:

- Token còn hạn vẫn hợp lệ cho đến khi hết hạn nếu bị copy trước logout.
- Giảm rủi ro bằng thời gian sống token hợp lý và không log token.
- Refresh token, blacklist, token rotation đưa vào backlog tương lai nếu cần.

## 8. Luồng frontend

Frontend auth flow:

- Login page.
- Auth context hoặc state.
- Protected route.
- Axios interceptor.
- `401` logout và redirect về login.
- `403` hiển thị lỗi không có quyền.
- Lưu token theo cách nhất quán và giải thích lựa chọn.

Token storage:

- Chọn một cách lưu token nhất quán trong MVP.
- Nếu dùng `localStorage`, ghi rõ tradeoff: dễ implement, tồn tại sau refresh, nhưng cần tránh XSS và không lưu dữ liệu nhạy cảm khác.
- Nếu dùng memory state, ghi rõ tradeoff: an toàn hơn với persistence, nhưng mất token khi refresh.

Không trộn nhiều cách lưu token.

Không gửi role trong request body để backend tin tưởng.

## 9. Test bắt buộc

Backend tests bắt buộc:

- Manager login thành công.
- Staff login thành công.
- Sai password trả `401`.
- User `LOCKED` trả `403`.
- Không token trả `401`.
- Token sai chữ ký trả `401`.
- Token hết hạn trả `401`.
- Staff `POST /api/people/v1/employees` trả `403`.
- Manager `POST /api/people/v1/employees` đi qua middleware.

Frontend tests nên có:

- Login page render.
- Login validation error.
- Login success lưu token và chuyển trang.
- `401` xóa token và redirect login.
- `403` hiển thị lỗi quyền.
- `STAFF` không thấy employee mutation buttons.

## 10. Quy tắc bảo mật

- Password phải hash bằng API chuẩn của Laravel.
- Không log password.
- Không log JWT.
- JWT secret nằm trong environment.
- Không hard-code secret.
- CORS giới hạn phù hợp với frontend origin của MVP.
- Validate mọi request.
- Không trả stack trace ra response production-like.
- Không lưu secret trong git.
- Không trả password hash trong API response.
- Không dùng frontend role check thay cho backend authorization.

## 11. Hành động bị cấm

Không được:

- Xây role/permission builder.
- Thêm OAuth.
- Thêm social login.
- Thêm refresh token phức tạp.
- Thêm token blacklist nếu chưa cần trong MVP.
- Chỉ kiểm tra role trên React.
- Tin role từ request body.
- Hard-code JWT secret.
- Log password hoặc JWT.
- Đặt auth business logic trong Controller.
- Gửi nguyên HTTP Request vào `LoginUseCase`.

## 12. Tiêu chí hoàn thành

Auth feature chỉ được xem là Done khi:

- People Service phát JWT đúng.
- People Service xác minh token cho protected endpoints.
- Inventory Service xác minh token đúng bằng cùng demo secret.
- Manager và Staff có hành vi đúng.
- Test authorization pass.
- React xử lý `401` đúng.
- React xử lý `403` đúng.
- Employee mutation endpoints chặn `STAFF` ở backend.
- Password được hash.
- JWT secret nằm trong environment.
- Tài liệu nói rõ logout MVP chỉ xóa token phía React.

## 13. Định dạng output

Chỉ trả nội dung `SKILL.md`.
