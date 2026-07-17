---
name: 04-laravel-oop-feature
description: Chuẩn hóa cách xây backend feature bằng PHP Laravel và OOP cho LazyManager MVP. Dùng khi implement hoặc review feature trong People Service hoặc Inventory Service, gồm layer architecture, request flow, DTO, UseCase, Repository, Domain rules, error handling, tests và Definition of Done.
---

# LazyManager MVP - Skill Laravel OOP Feature

## 1. Mục đích

Skill này cung cấp quy trình chung để xây một backend feature bằng Laravel và OOP trong LazyManager MVP.

Áp dụng cho cả People Service và Inventory Service.

Mục tiêu là giữ code đúng layer, controller mỏng, business rule nằm trong Use Case hoặc Domain Service, repository tách khỏi Eloquent khi cần và mỗi feature có test đủ để bảo vệ behavior quan trọng.

Luôn tuân thủ `skills/00-project-context/SKILL.md`. Không thêm pattern, abstraction hoặc service mới nếu không phục vụ trực tiếp use case MVP.

## 2. Kiến trúc bắt buộc

Mỗi Laravel service dùng cấu trúc:

```text
app/
├── Domain/
│   ├── Enums/
│   ├── Exceptions/
│   └── Services/
├── Application/
│   ├── DTOs/
│   ├── Interfaces/
│   └── UseCases/
├── Infrastructure/
│   └── Persistence/
└── Http/
    ├── Controllers/
    ├── Middleware/
    ├── Requests/
    └── Resources/
```

Ý nghĩa layer:

- `Http`: nhận request, validate, gọi Use Case, trả response.
- `Application`: chứa DTO, Interface và Use Case.
- `Domain`: chứa enum, exception, business rule, validator hoặc domain service.
- `Infrastructure`: chứa Eloquent repository implementation, persistence và importer.

## 3. Luồng request

Luồng request chuẩn:

```text
React
→ FormRequest
→ Controller
→ DTO
→ UseCase
→ Domain Service hoặc Validator
→ Repository Interface
→ Eloquent Repository
→ Database
→ Resource
```

Không bỏ qua backend validation hoặc backend authorization chỉ vì React đã kiểm tra.

## 4. Quy tắc Controller

Controller chỉ:

- Nhận dữ liệu đã validate.
- Tạo DTO.
- Gọi Use Case.
- Trả Resource hoặc HTTP response.

Controller không:

- Truy vấn Eloquent phức tạp.
- Kiểm tra business rule.
- Cập nhật tồn.
- Chứa DB transaction.
- Gửi nguyên Request vào Use Case.

Nếu controller bắt đầu có nhánh nghiệp vụ, query dài hoặc transaction, phải chuyển logic xuống Use Case hoặc Domain Service.

## 5. Quy tắc DTO

- Dùng `final readonly class` khi phù hợp.
- Có kiểu dữ liệu rõ ràng.
- Không chứa HTTP Request.
- Chỉ chứa dữ liệu use case cần.
- Không chứa validation rule.
- Không gọi Eloquent hoặc service khác.

DTO được tạo từ dữ liệu đã validate, thường trong Controller hoặc static factory nhỏ nếu codebase đã có pattern đó.

## 6. Quy tắc Use Case

- Một Use Case đại diện một hành động.
- Tên dạng động từ, ví dụ `CreateEmployeeUseCase`, `ConfirmDailySalesUseCase`.
- Điều phối repository và domain service.
- Không trả raw query builder.
- Không nhận nguyên HTTP Request.
- Không chứa response formatting.
- Nghiệp vụ tồn kho phải dùng DB transaction.
- Use Case phải là nơi rõ nhất để đọc luồng nghiệp vụ chính.

Use Case có thể:

- Kiểm tra quyền bổ sung nếu middleware chưa đủ.
- Gọi Domain Service hoặc Validator.
- Gọi Repository Interface.
- Mở DB transaction khi cần atomic behavior.
- Throw domain exception cho lỗi nghiệp vụ.

## 7. Quy tắc Repository

- Interface đặt ở `Application/Interfaces`.
- Eloquent implementation đặt ở `Infrastructure/Persistence`.
- Use Case phụ thuộc interface.
- Bind interface trong Service Provider.
- Repository trả dữ liệu hoặc model theo nhu cầu use case, nhưng không để Use Case phụ thuộc query builder.
- Không đặt business rule trong repository.

Không tạo repository nếu feature chỉ có query cực đơn giản mà không cần abstraction. Khi bỏ repository, phải giải thích quyết định trong plan hoặc review note.

Ví dụ nên dùng repository:

- Query được dùng lại.
- Cần transaction/locking rõ ràng.
- Use Case không nên biết chi tiết Eloquent.
- Cần fake/mock trong test use case.

Ví dụ có thể không cần repository:

- Health check.
- Lookup rất nhỏ, không có business rule, không tái sử dụng.

## 8. Quy tắc Domain

- Dùng enum cho status và type.
- Dùng exception riêng cho lỗi nghiệp vụ.
- Dùng Validator hoặc Domain Service cho rule tái sử dụng.
- Không lạm dụng Entity hoặc Value Object khi không cần.
- Không đưa logic nghiệp vụ chính vào Model observer.

Ví dụ domain classes hợp lệ:

- `UserRole`.
- `ShiftType`.
- `InventoryTransactionType`.
- `DailySalesStatus`.
- `ScheduleValidator`.
- `InventoryBalanceService`.
- `InsufficientStockException`.
- `DuplicateShiftAssignmentException`.

Domain rule phải dễ test độc lập khi có thể.

## 9. Xử lý lỗi

Map lỗi sang HTTP status phù hợp:

- `401`: chưa xác thực.
- `403`: không có quyền.
- `404`: không tìm thấy.
- `409`: xung đột hoặc thao tác lặp.
- `422`: dữ liệu hoặc business rule không hợp lệ.
- `500`: lỗi hệ thống không mong đợi.

Không trả `500` cho lỗi nghiệp vụ đã biết.

Domain exception nên được map trong exception handler hoặc tại boundary phù hợp để response nhất quán.

## 10. Testing

Mỗi feature cần test:

- Happy path.
- Validation error.
- Authorization error.
- Business rule error.
- Duplicate hoặc idempotency nếu liên quan.
- Rollback nếu liên quan tồn kho.

Quy tắc test bắt buộc:

- Task liên quan quyền phải có test HTTP 403.
- Task liên quan schedule phải test max 2 employees và không gán trùng nếu có assign.
- Task liên quan tồn kho phải test `inventory_transactions` và rollback.
- Task liên quan sales phải test không confirm hai lần.
- Task liên quan borrow phải test không return hai lần.
- Task liên quan stock count phải test `expected_quantity` snapshot nếu có tạo phiên kiểm.

## 11. Quy trình implementation

Khi xây một backend feature, thực hiện theo thứ tự:

1. Đọc code hiện tại.
2. Viết plan file cần sửa.
3. Tạo hoặc cập nhật migration.
4. Tạo enum và exception.
5. Tạo DTO.
6. Tạo repository interface.
7. Tạo implementation.
8. Bind interface trong Service Provider.
9. Tạo Use Case.
10. Tạo Request.
11. Tạo Controller.
12. Tạo Resource.
13. Tạo route.
14. Viết test.
15. Cập nhật OpenAPI.

Nếu task không cần một bước nào, ghi rõ lý do. Ví dụ: không tạo repository vì endpoint chỉ là health check.

## 12. Hành động bị cấm

Không được:

- Fat controller.
- Business logic trong Model observer.
- Cập nhật tồn thiếu transaction ledger.
- Dùng static helper cho nghiệp vụ chính.
- Tạo `BaseService` hoặc `BaseRepository` quá chung.
- Thêm pattern không phục vụ task.
- Gửi nguyên HTTP Request vào Use Case.
- Dùng frontend authorization thay cho backend authorization.
- Cập nhật `inventory_balances` ngoài `InventoryBalanceService`.
- Tạo service thứ ba hoặc shared database.
- Thêm RabbitMQ, Redis, AI hoặc Notification Service vào MVP.

## 13. Tiêu chí hoàn thành

Feature backend chỉ được xem là Done khi:

- Code đúng layer.
- Test pass.
- API hoạt động qua Nginx.
- HTTP response đúng.
- Documentation được cập nhật.
- Validation chạy ở backend.
- Authorization chạy ở backend.
- Business logic không nằm trong Controller.
- Nghiệp vụ tồn kho có DB transaction và ledger nếu có thay đổi tồn.
- Response không lộ raw exception hoặc stack trace.

## 14. Định dạng output

Chỉ trả nội dung `SKILL.md`.
