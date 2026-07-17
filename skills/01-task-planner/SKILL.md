---
name: 01-task-planner
description: Phân tích task code cho LazyManager MVP trước khi implement. Dùng khi cần chuyển task, use case, bug hoặc feature nhỏ thành kế hoạch code rõ ràng, đúng scope, có file cần sửa, API, database, business rules, tests và Definition of Done.
---

# LazyManager MVP - Skill lập kế hoạch task

## 1. Mục đích

Skill này dùng để chuyển một task hoặc use case của LazyManager MVP thành kế hoạch code nhỏ, rõ và có thể thực hiện.

Không trực tiếp code trước khi hoàn thành phân tích. Trước khi viết code, phải xác định task thuộc service nào, use case nào, có nằm trong MVP không, cần sửa database/API/file nào, cần bảo vệ business rules nào và cần test gì.

Skill này phải tuân thủ `skills/00-project-context/SKILL.md`. Nếu task có dấu hiệu vượt scope, phải chặn sớm thay vì tự mở rộng thiết kế.

## 2. Input bắt buộc

Trước khi lập kế hoạch, thu thập hoặc xác nhận các input sau:

- Task description.
- Use case ID.
- Service liên quan.
- Code hiện tại.
- Database schema.
- API contract.
- Quy tắc nghiệp vụ.
- Skill nghiệp vụ liên quan.

Nếu thiếu input, đọc code và tài liệu hiện có trước. Chỉ hỏi người dùng khi không thể suy luận an toàn từ repository hoặc tài liệu dự án.

## 3. Quy trình lập kế hoạch

Khi nhận một task, thực hiện đúng thứ tự sau.

### Bước 1: Xác định service

Xác định task thuộc:

- People Service: authentication, employee, schedule.
- Inventory Service: product, SKU, stock, sales, borrow, stock count.

Nếu task chạm cả frontend và backend, vẫn phải xác định backend service chính trước.

### Bước 2: Xác định use case

Gắn task với một hoặc nhiều use case MVP:

- `UC-01`: Đăng nhập.
- `UC-02`: Đăng xuất.
- `UC-03`: Xem nhân viên.
- `UC-04`: Tạo nhân viên.
- `UC-05`: Sửa nhân viên.
- `UC-06`: Xóa mềm nhân viên.
- `UC-07`: Xem lịch tuần.
- `UC-08`: Gán nhân viên vào ca.
- `UC-09`: Xóa nhân viên khỏi ca.
- `UC-10`: CRUD sản phẩm và SKU.
- `UC-11`: Import tồn kho.
- `UC-12`: Xem tồn và lịch sử.
- `UC-13`: Tạo phiếu bán hằng ngày.
- `UC-14`: Xác nhận phiếu bán.
- `UC-15`: Hủy phiếu bán.
- `UC-16`: Ghi nhận cho mượn.
- `UC-17`: Ghi nhận trả hàng.
- `UC-18`: Tạo phiên kiểm kho.
- `UC-19`: Xuất CSV kiểm kho.
- `UC-20`: Nhập thực tế và tính lệch.

Nếu không tìm được use case phù hợp, đánh dấu cần kiểm tra scope.

### Bước 3: Kiểm tra MVP scope

Kiểm tra task có nằm trong MVP không.

Nếu task thuộc một trong các mục sau, trả trạng thái `OUT_OF_SCOPE` và đưa vào Future Backlog:

- RabbitMQ.
- Redis.
- Notification Service.
- AI Service.
- Check-in/check-out.
- Nghỉ phép.
- Availability.
- Đổi ca.
- Multi-tenant.
- App mobile.
- Dashboard nâng cao.
- Forecasting.
- Dynamic role/permission system.
- Service thứ ba.
- Shared database giữa services.

Không lập kế hoạch implement cho task `OUT_OF_SCOPE` trừ khi người dùng yêu cầu đổi scope rõ ràng.

### Bước 4: Tóm tắt quy tắc nghiệp vụ

Liệt kê các quy tắc nghiệp vụ cần bảo vệ trong task.

Ví dụ:

- Staff không được thêm, sửa, xóa mềm nhân viên.
- Mỗi ca tối đa 2 nhân viên.
- Không gán cùng nhân viên hai lần vào một ca.
- Nhân viên bị soft delete không được xếp lịch mới.
- Mọi thay đổi tồn phải tạo `inventory_transaction`.
- Phiếu bán `DRAFT` không thay đổi tồn.
- Phiếu bán chỉ được confirm một lần.
- Confirm sales phải atomic và rollback toàn bộ nếu có lỗi.
- Borrow làm giảm tồn.
- Return làm tăng tồn.
- `expected_quantity` của stock count là snapshot.
- `variance = actual - expected`.

### Bước 5: Liệt kê database changes

Nêu rõ task có cần thay đổi database không:

- Migration mới.
- Constraint.
- Index.
- Enum.
- Transaction requirement.

Task liên quan duplicate phải có unique constraint, idempotency rule hoặc cả hai.

Task liên quan tồn kho phải nêu rõ database transaction, row locking nếu cần và rollback condition.

### Bước 6: Liệt kê API changes

Liệt kê API cần tạo hoặc sửa:

- Method.
- URL.
- Request body.
- Response.
- Authorization.
- Error status.

HTTP status phải phù hợp:

- `401` cho unauthenticated.
- `403` cho không đủ quyền.
- `404` cho không tìm thấy resource.
- `409` cho conflict hoặc duplicate business action.
- `422` cho validation error.
- `500` chỉ cho lỗi ngoài dự kiến.

### Bước 7: Liệt kê backend files

Liệt kê file backend cần tạo hoặc sửa theo kiến trúc OOP:

- Request.
- DTO.
- UseCase.
- Domain Service hoặc Validator.
- Repository Interface.
- Eloquent Repository.
- Controller.
- Resource.
- Exception.
- Test.

Không tạo file giả định nếu chưa kiểm tra code hiện tại. Nếu repository chưa có cấu trúc tương ứng, kế hoạch phải ghi rõ bước tạo cấu trúc tối thiểu.

### Bước 8: Liệt kê React files

Liệt kê file React cần tạo hoặc sửa:

- API client.
- Type.
- Schema.
- Hook.
- Component.
- Page.
- Route.

Ưu tiên UI đơn giản bằng form/table để hoàn thành luồng end-to-end. Không đề xuất drag-and-drop, dashboard nâng cao hoặc animation phức tạp trong MVP.

### Bước 9: Liệt kê tests

Liệt kê test cần có:

- Happy path.
- Authorization.
- Validation.
- Business rule.
- Duplicate request.
- Rollback nếu có thay đổi tồn.

Task liên quan quyền phải có test HTTP 403.

Task liên quan tồn kho phải có test ledger/transaction và rollback.

Task liên quan duplicate phải test unique constraint hoặc idempotency rule.

### Bước 10: Chia implementation steps

Chia task thành các bước nhỏ, mỗi bước từ 30 phút đến 4 giờ.

Mỗi bước phải có kết quả rõ ràng:

- File nào thay đổi.
- API nào chạy được.
- Test nào pass.
- UI nào gọi được API.

Ưu tiên thứ tự backend rule trước, API sau, React sau, test/regression song song theo từng lát nhỏ.

## 4. Template output bắt buộc

Khi lập kế hoạch, luôn dùng template sau:

```text
Task:
Use case:
Service:
Scope status:

Quy tắc nghiệp vụ:
Database changes:
API changes:
Backend files:
Frontend files:
Tests:
Implementation steps:
Risks:
Tiêu chí hoàn thành:
```

Trong `Scope status`, dùng một trong các giá trị:

- `IN_SCOPE`.
- `OUT_OF_SCOPE`.
- `NEEDS_CLARIFICATION`.

## 5. Quy tắc lập kế hoạch

- Không tạo file giả định nếu chưa kiểm tra code hiện tại.
- Không đề xuất RabbitMQ, Redis hoặc service mới.
- Không tạo pattern chỉ để làm code phức tạp hơn.
- Luôn ưu tiên luồng end-to-end từ React đến database.
- Task liên quan tồn kho phải nêu rõ transaction và rollback.
- Task liên quan quyền phải có test HTTP 403.
- Task liên quan duplicate phải có unique constraint hoặc idempotency rule.
- Không đặt business logic trong Controller.
- Không cập nhật `inventory_balances` nếu không tạo `inventory_transaction`.
- Không cho service truy cập trực tiếp database của service khác.
- Không dùng frontend authorization thay cho backend authorization.
- Không mở rộng bảng, enum hoặc route ngoài use case đang xử lý nếu không cần thiết.

## 6. Tiêu chí hoàn thành

Một kế hoạch chỉ hoàn thành khi developer biết rõ:

- Sửa file nào.
- Viết API nào.
- Business rule nào cần bảo vệ.
- Test gì cần viết.
- Khi nào task được xem là Done.

Nếu kế hoạch còn thiếu code context, database schema hoặc API contract, không kết luận sẵn. Ghi `NEEDS_CLARIFICATION` hoặc thêm bước đọc code cụ thể trước khi implement.

## 7. Định dạng output

Khi sử dụng skill này:

- Chỉ lập kế hoạch, không code.
- Trả lời bằng tiếng Việt.
- Giữ nguyên tên class, API, database, enum và thư mục bằng tiếng Anh.
- Dùng đúng template ở mục 4.
- Nếu task ngoài MVP, trả `Scope status: OUT_OF_SCOPE` và ghi Future Backlog item.
