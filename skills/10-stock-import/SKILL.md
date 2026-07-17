---
name: 10-stock-import
description: Chuẩn hóa UC-11 Import tồn kho cho LazyManager MVP Inventory Service. Dùng khi implement hoặc review upload CSV/XLSX, parse file, preview import, validate từng dòng, confirm import, đồng bộ inventory_balances, tạo IMPORT_SYNC inventory_transactions, chống duplicate file hash và rollback atomic.
---

# LazyManager MVP - Skill nhập tồn kho

## 1. Mục đích

Skill này hướng dẫn xây và review UC-11 Import tồn kho trong Inventory Service.

Mục tiêu là upload, parse, preview và xác nhận file tồn kho theo template cố định, đảm bảo preview an toàn, confirm atomic, chống confirm trùng và mọi thay đổi tồn đều có `IMPORT_SYNC` ledger.

Luôn tuân thủ:

- `skills/00-project-context/SKILL.md`.
- `skills/04-laravel-oop-feature/SKILL.md`.
- `skills/09-product-inventory/SKILL.md`.

## 2. Input được hỗ trợ

Hỗ trợ:

- CSV.
- XLSX.

Template cố định gồm đúng các cột:

- `sku_code`.
- `quantity`.

Không thêm AI column mapping, không đoán cột, không hỗ trợ nhiều template trong MVP.

## 3. Quy trình

Thực hiện theo thứ tự:

1. Upload file.
2. Validate type và size.
3. Tính SHA-256 hash.
4. Parse dữ liệu.
5. Validate từng dòng.
6. Gom hoặc phát hiện SKU trùng trong file theo rule đã chọn.
7. Hiển thị preview.
8. Không cập nhật kho ở bước preview.
9. Người dùng bấm Confirm.
10. Chạy database transaction.
11. Lock balance liên quan.
12. Đồng bộ balance.
13. Tạo `IMPORT_SYNC` transaction.
14. Đánh dấu import `CONFIRMED`.

Preview chỉ được tạo dữ liệu nháp như `stock_imports` và preview rows nếu schema hiện tại có hỗ trợ. Preview không được thay đổi `inventory_balances` hoặc tạo `inventory_transactions`.

## 4. Kiến trúc

Các component backend cần cân nhắc:

- `StockImporter` interface.
- `CsvStockImporter`.
- `XlsxStockImporter`.
- `StockImporterFactory` nếu thực sự có hai loại file.
- `ImportStockPreviewUseCase`.
- `ConfirmStockImportUseCase`.
- `ImportRowData` DTO.
- `ImportValidationResult`.
- `DuplicateStockImportException`.

Quy tắc layer:

- File parsing nằm trong `Infrastructure`.
- DTO và Use Case nằm trong `Application`.
- Rule duplicate, validation nghiệp vụ và exception nằm trong `Domain`.
- Controller chỉ nhận upload, tạo DTO, gọi Use Case và trả response.
- Confirm phải gọi `InventoryBalanceService`, không tự update `inventory_balances`.

## 5. Validation

Phải validate các lỗi sau:

- Thiếu header.
- Header sai.
- SKU không tồn tại.
- Quantity không phải integer.
- Quantity âm.
- Dòng rỗng.
- SKU trùng trong file.
- File hash đã confirmed.
- File vượt kích thước.

Quy tắc validation:

- Lỗi theo dòng phải trả kèm row number.
- Header phải khớp template `sku_code`, `quantity`.
- `quantity` là integer và `quantity >= 0`.
- Dòng rỗng có thể bỏ qua nếu toàn bộ cell rỗng, nhưng phải nhất quán và có test.
- SKU trùng trong file phải được xử lý bằng một rule rõ ràng: chặn như lỗi blocking hoặc gom quantity. Ưu tiên chặn để dễ review trong MVP.
- Nếu có lỗi blocking, không cho Confirm.

## 6. Quy tắc đồng bộ

Khi file quantity khác current balance:

- `quantity_change = imported_quantity - current_quantity`.
- `quantity_before = current_quantity`.
- `quantity_after = imported_quantity`.
- Tạo `IMPORT_SYNC` transaction.
- Không chỉ ghi đè balance mà không có ledger.

Khi file quantity bằng current balance:

- Có thể không tạo `inventory_transaction` nếu không có thay đổi.
- Vẫn được đánh dấu dòng là no-op trong preview.
- Quyết định no-op phải nhất quán trong API response và test.

Tất cả dòng thay đổi phải được xử lý trong cùng DB transaction khi Confirm.

## 7. Idempotency

- File đã `CONFIRMED` không được confirm lại.
- Hai request confirm đồng thời không được cập nhật hai lần.
- Dùng `status`, unique `file_hash` hoặc lock phù hợp.
- `stock_imports.file_hash` phải unique khi đã confirmed.
- Confirm phải kiểm tra lại trạng thái import trong transaction.
- Nếu import đã `CONFIRMED`, trả `409` và không tạo thêm ledger.

Trạng thái gợi ý cho `stock_imports`:

- `PREVIEWED`.
- `CONFIRMED`.
- `FAILED` nếu codebase cần ghi nhận lỗi hệ thống.

Không thêm workflow phức tạp ngoài nhu cầu preview và confirm.

## 8. API

Endpoint tối thiểu:

- `POST /api/inventory/v1/stock-imports`.
- `GET /api/inventory/v1/stock-imports/{id}/preview` nếu tách endpoint.
- `POST /api/inventory/v1/stock-imports/{id}/confirm`.

Hành vi API:

- `POST /api/inventory/v1/stock-imports` upload file, parse, validate và trả preview.
- Preview response phải có dòng hợp lệ, dòng lỗi, tổng số dòng, số dòng thay đổi và trạng thái có thể confirm hay không.
- `POST /api/inventory/v1/stock-imports/{id}/confirm` chỉ chạy khi preview hợp lệ.
- Tất cả endpoint phải xác thực JWT ở backend.
- Cả `STORE_MANAGER` và `STAFF` được import tồn kho trong MVP.

HTTP status gợi ý:

- `201` khi upload preview được tạo.
- `200` khi lấy preview hoặc confirm thành công.
- `400` cho file type không hỗ trợ nếu không dùng validation response.
- `401` khi chưa xác thực.
- `404` khi import không tồn tại.
- `409` khi file hash đã confirmed hoặc confirm lặp.
- `422` khi file sai template hoặc dữ liệu dòng không hợp lệ.

## 9. React Import Wizard

React cần wizard tối thiểu:

- Chọn file.
- Upload.
- Hiển thị preview.
- Hiển thị lỗi từng dòng.
- Disable Confirm nếu có lỗi blocking.
- Confirm.
- Hiển thị kết quả cập nhật.

Quy tắc UI:

- Gọi API qua Nginx.
- Không gọi thẳng port Inventory Service.
- Không cho submit lặp khi upload hoặc confirm đang chạy.
- Hiển thị loading, empty, validation error, API error, unauthorized và forbidden state.
- Preview phải phân biệt dòng sẽ update, dòng no-op và dòng lỗi.
- Sau Confirm thành công, invalidate inventory balance và transaction history query liên quan.
- Không thêm AI mapping UI trong MVP.

## 10. Test bắt buộc

Backend tests tối thiểu:

- File hợp lệ preview thành công.
- Preview không thay đổi balance.
- SKU không tồn tại báo đúng dòng.
- Quantity âm bị lỗi.
- Quantity không phải integer bị lỗi.
- Header thiếu hoặc sai bị lỗi.
- SKU trùng trong file bị chặn hoặc được gom đúng theo rule đã chọn.
- File hash trùng bị chặn.
- Confirm cập nhật balance và ledger.
- Confirm tạo `IMPORT_SYNC` với `quantity_before`, `quantity_change`, `quantity_after` đúng.
- Lỗi một dòng rollback toàn bộ.
- Confirm hai lần không thay đổi lần hai.
- Hai request confirm đồng thời không tạo ledger hai lần.

Frontend tests tối thiểu:

- Upload file hiển thị preview.
- Preview có lỗi disable Confirm.
- API validation error hiển thị theo dòng.
- Confirm success hiển thị kết quả.
- Confirm success invalidate inventory query.

## 11. Hành động bị cấm

Không được:

- Thêm AI column mapping.
- Dùng background queue.
- Tự sửa dữ liệu lỗi.
- Confirm nếu chưa preview hợp lệ.
- Ghi đè balance thiếu ledger.
- Tạo `IMPORT_SYNC` ngoài `InventoryBalanceService` nếu service đã là điểm cập nhật balance.
- Bỏ qua file hash duplicate.
- Cho confirm lại file đã `CONFIRMED`.
- Update một phần dòng rồi bỏ qua dòng lỗi.
- Thêm Redis, RabbitMQ hoặc service mới.
- Hỗ trợ template động trong MVP.

## 12. Tiêu chí hoàn thành

UC-11 chỉ được xem là Done khi:

- Import chạy end-to-end.
- CSV và XLSX được xử lý theo template cố định.
- Preview an toàn và không thay đổi tồn.
- Confirm atomic.
- Duplicate protection hoạt động.
- Balance được đồng bộ đúng.
- Mỗi thay đổi tồn có `IMPORT_SYNC` transaction.
- Rollback hoạt động khi có lỗi.
- Test pass.
- API hoạt động qua Nginx.
- React không có TypeScript error hoặc console error.
- API docs cập nhật.

## 13. Định dạng output

Chỉ trả nội dung `SKILL.md`.
