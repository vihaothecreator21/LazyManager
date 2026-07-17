---
name: 03-database-design
description: Thiết kế và review PostgreSQL schema cho LazyManager MVP. Dùng khi tạo hoặc kiểm tra Laravel migrations, database constraints, indexes, transaction safety, rollback, migrate:fresh và schema ownership cho people_db hoặc inventory_db.
---

# LazyManager MVP - Skill thiết kế database

## 1. Mục đích

Skill này hướng dẫn tạo và review PostgreSQL migrations, constraint và index cho hai database của LazyManager MVP.

Mục tiêu là giữ schema tối giản, đúng MVP, bảo vệ business rules ở database khi phù hợp, chạy được migration/rollback ổn định và không phá vỡ ranh giới giữa `people_db` và `inventory_db`.

Luôn tuân thủ `skills/00-project-context/SKILL.md`. Không mở rộng schema ngoài use case đang làm.

## 2. Quyền sở hữu database

### `people_db`

People Service sở hữu các bảng:

- `users`.
- `employees`.
- `schedules`.
- `shift_assignments`.

### `inventory_db`

Inventory Service sở hữu các bảng:

- `products`.
- `product_skus`.
- `inventory_balances`.
- `inventory_transactions`.
- `stock_imports`.
- `daily_sales`.
- `daily_sale_lines`.
- `borrow_records`.
- `stock_counts`.
- `stock_count_lines`.

## 3. Quy tắc chung

- Không tạo foreign key xuyên database.
- Mỗi service chỉ sở hữu database của mình.
- Không service nào được truy cập trực tiếp database của service khác.
- Dùng `bigint` hoặc UUID nhất quán trong toàn bộ project. Không trộn tùy tiện.
- Timestamp phải có `created_at` và `updated_at` khi phù hợp.
- Dùng soft delete cho `employees`, `products` và `product_skus` nếu record có thể có lịch sử.
- Quantity là integer, không dùng float.
- Không cho quantity âm nếu business rule không cho phép.
- Enum có thể dùng PHP enum và string column.
- Mỗi migration phải rollback được.
- Tên column dùng snake_case.
- Tên constraint/index nên rõ nghĩa, ưu tiên dễ debug.
- Không dùng JSON để né thiết kế quan hệ đơn giản.

## 4. Constraint bắt buộc

### `people_db`

- `users.email` unique.
- `employees.employee_code` unique.
- `schedules` unique trên `(work_date, shift_type)`.
- `shift_assignments` unique trên `(schedule_id, employee_id)`.

### `inventory_db`

- `products.product_code` unique.
- `product_skus.sku_code` unique.
- `inventory_balances.sku_id` unique.
- `stock_imports.file_hash` unique khi đã confirmed.
- `daily_sale_lines.quantity > 0`.
- Không tạo nhiều confirmed `daily_sales` cho cùng `sales_date`.
- `stock_count_lines` unique trên `(stock_count_id, sku_id)`.

### Constraint cho quantity

Áp dụng check constraint cho quantity khi phù hợp:

- `inventory_balances.quantity >= 0`.
- `daily_sale_lines.quantity > 0`.
- `borrow_records.quantity > 0`.
- `stock_count_lines.expected_quantity >= 0`.
- `stock_count_lines.actual_quantity >= 0` nếu không nullable.
- `stock_count_lines.variance` có thể âm.

## 5. Index bắt buộc

Tạo index cho các truy vấn MVP hay dùng:

- `employees.employee_code`.
- Không dùng `employees.active` trong MVP; dùng `employees.deleted_at` cho soft delete và `users.status` cho đăng nhập.
- `schedules.work_date`.
- `products.product_code`.
- `product_skus.sku_code`.
- `inventory_transactions.sku_id`.
- `inventory_transactions.created_at`.
- `daily_sales.sales_date`.
- `borrow_records.status`.
- `stock_counts.count_date`.

Nếu query thường lọc nhiều column cùng lúc, cân nhắc composite index nhưng không thêm index dự đoán quá sớm.

## 6. Xử lý đồng thời

- Khi cập nhật inventory balance phải dùng DB transaction.
- Có thể dùng `SELECT FOR UPDATE` cho các dòng `inventory_balances` liên quan.
- Không cho cập nhật balance ngoài `InventoryBalanceService`.
- Một thao tác lỗi phải rollback toàn bộ.
- Confirm sales, cancel sales, stock import confirm, borrow và return đều phải atomic.
- Luôn tạo `inventory_transactions` trong cùng transaction với cập nhật `inventory_balances`.
- Không tách ledger và balance update thành hai transaction riêng.

## 7. Quy trình review

Khi tạo hoặc review migration, thực hiện theo thứ tự:

1. Đọc schema hiện tại.
2. Kiểm tra migration có trùng chức năng không.
3. Kiểm tra nullable hợp lý.
4. Kiểm tra foreign key.
5. Kiểm tra unique constraint.
6. Kiểm tra index.
7. Kiểm tra rollback.
8. Chạy `migrate:fresh`.
9. Chạy rollback.
10. Chạy lại migration.

Nếu chưa có codebase hoặc migration hiện tại, ghi rõ assumption và chỉ đề xuất schema tối thiểu theo MVP.

## 8. Output bắt buộc khi dùng skill

Khi dùng skill này để thiết kế hoặc review database, output phải có:

- Schema change summary.
- Migration files.
- Constraints.
- Indexes.
- Risks.
- Migration commands.
- Tests hoặc verification steps.

Template gợi ý:

```text
Schema change summary:
Migration files:
Constraints:
Indexes:
Risks:
Migration commands:
Tests / verification steps:
```

## 9. Hành động bị cấm

Không được:

- Tạo bảng ngoài MVP nếu không có task rõ.
- Tạo `categories`, `stores`, `permissions`, `role_permissions`, `attendance_records`, `leave_requests`, `notification_logs` hoặc AI tables.
- Tạo service thứ ba để chứa database mới.
- Tạo foreign key xuyên database.
- Chia sẻ một database cho cả People Service và Inventory Service.
- Dùng JSON để thay thế quan hệ dữ liệu đơn giản.
- Lưu danh sách SKU trong một cột text hoặc JSON.
- Xóa cứng dữ liệu có lịch sử giao dịch.
- Cập nhật `inventory_balances` mà không tạo `inventory_transactions`.
- Dùng float cho quantity.
- Thêm index hàng loạt khi chưa có query cần bảo vệ.

## 10. Tiêu chí hoàn thành

Database task chỉ được xem là Done khi:

- Migration chạy được.
- Migration rollback được.
- Constraint bảo vệ được business rule liên quan.
- Schema khớp Use Case.
- Không có quan hệ chéo giữa hai database.
- Index phục vụ truy vấn MVP rõ ràng.
- Quantity rules đúng kiểu integer và không âm khi business rule yêu cầu.
- Các thao tác tồn kho có transaction và rollback path.
- Verification commands đã được chạy hoặc ghi rõ lý do chưa chạy được.

## 11. Định dạng output

Khi dùng skill này:

- Trả lời bằng tiếng Việt.
- Giữ nguyên tên database, table, column, enum, class và command bằng tiếng Anh.
- Chỉ đề xuất thay đổi trong phạm vi MVP.
- Nếu người dùng yêu cầu tạo skill, chỉ trả nội dung hoàn chỉnh của `SKILL.md`.
