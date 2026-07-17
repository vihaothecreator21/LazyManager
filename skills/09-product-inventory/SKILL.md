---
name: 09-product-inventory
description: Chuẩn hóa Product, SKU, Inventory Balance và Transaction Ledger cho LazyManager MVP Inventory Service. Dùng khi implement hoặc review UC-10 CRUD sản phẩm/SKU và UC-12 xem tồn, lịch sử giao dịch, InventoryBalanceService, ledger, Laravel backend và React UI.
---

# LazyManager MVP - Skill sản phẩm và tồn kho

## 1. Mục đích

Skill này hướng dẫn xây và review Product, SKU, Inventory Balance và Transaction Ledger trong Inventory Service.

Áp dụng cho:

- UC-10 CRUD sản phẩm và SKU.
- UC-12 Xem tồn và lịch sử giao dịch.

Mục tiêu là giữ master data sản phẩm rõ ràng, mọi thay đổi tồn có ledger giải thích được, không cập nhật balance trực tiếp từ Controller và không mở rộng scope ngoài MVP.

Luôn tuân thủ:

- `skills/00-project-context/SKILL.md`.
- `skills/03-database-design/SKILL.md`.
- `skills/04-laravel-oop-feature/SKILL.md`.
- `skills/05-react-feature/SKILL.md`.

## 2. Data model

### `products`

- `product_code`.
- `name`.
- `active`.
- soft delete.

### `product_skus`

- `product_id`.
- `sku_code`.
- `size`.
- `active`.
- soft delete.

### `inventory_balances`

- `sku_id` unique.
- `quantity`.

### `inventory_transactions`

- `sku_id`.
- `type`.
- `quantity_change`.
- `quantity_before`.
- `quantity_after`.
- `reference_type`.
- `reference_id`.
- `reason`.
- `created_by`.
- `created_at`.

## 3. Quy tắc nghiệp vụ

- `product_code` unique.
- `sku_code` unique.
- Một `product` có nhiều `SKU` theo `size`.
- `SKU` có transaction history không được hard delete.
- `product` có `SKU` hoặc transaction history liên quan không được hard delete.
- `Balance` không được chỉnh trực tiếp từ Controller.
- Mọi thay đổi balance phải đi qua `InventoryBalanceService`.
- Mọi thay đổi balance phải tạo `inventory_transaction`.
- `quantity`, `quantity_change`, `quantity_before` và `quantity_after` dùng integer.
- `inventory_balances.quantity` không được âm.
- `quantity_after = quantity_before + quantity_change`.
- Tạo `SKU` mới phải tạo hoặc chuẩn bị được `inventory_balances` với `quantity = 0` theo pattern hiện tại.
- Deactivate `product` không tự động xóa transaction history.
- Deactivate `SKU` không xóa transaction history.
- `product` inactive hoặc deleted không nên cho tạo `SKU` mới.
- `SKU` inactive hoặc deleted không được dùng cho nghiệp vụ tạo thay đổi tồn mới, trừ truy vấn lịch sử.

## 4. Loại transaction

Dùng enum `InventoryTransactionType`:

- `IMPORT_SYNC`.
- `SALE`.
- `SALE_REVERSAL`.
- `BORROW_OUT`.
- `BORROW_RETURN`.

Không thêm transaction type mới nếu task không thuộc MVP hoặc chưa được cập nhật trong project context.

## 5. Use Cases bắt buộc

Backend cần các Use Case tối thiểu:

- `ListProductsUseCase`.
- `CreateProductUseCase`.
- `UpdateProductUseCase`.
- `DeactivateProductUseCase`.
- `CreateSkuUseCase`.
- `UpdateSkuUseCase`.
- `DeactivateSkuUseCase`.
- `GetInventoryBalanceUseCase`.
- `GetInventoryTransactionsUseCase`.

Nếu codebase đã có tên khác nhưng cùng trách nhiệm, ưu tiên giữ pattern hiện tại và không tạo trùng.

## 6. Trách nhiệm của `InventoryBalanceService`

`InventoryBalanceService` là điểm duy nhất được thay đổi `inventory_balances`.

Service này phải chịu trách nhiệm:

- Increase.
- Decrease.
- Synchronize.
- Lock balance khi cần.
- Kiểm tra tồn.
- Tạo transaction ledger.
- Trả `quantity_before`, `quantity_change`, `quantity_after`.
- Không cho thay đổi một nửa.
- Chạy trong DB transaction khi có thay đổi tồn.
- Rollback toàn bộ nếu balance update hoặc ledger insert lỗi.
- Không cho `quantity_after` âm.

Các Use Case như import, sale, borrow và return phải gọi `InventoryBalanceService`, không tự update `inventory_balances`.

## 7. API

Các endpoint thuộc Inventory Service:

- `GET /api/inventory/v1/products`.
- `POST /api/inventory/v1/products`.
- `GET /api/inventory/v1/products/{id}`.
- `PUT /api/inventory/v1/products/{id}`.
- `DELETE /api/inventory/v1/products/{id}`.
- `POST /api/inventory/v1/products/{id}/skus`.
- `PUT /api/inventory/v1/skus/{id}`.
- `DELETE /api/inventory/v1/skus/{id}`.
- `GET /api/inventory/v1/inventory`.
- `GET /api/inventory/v1/inventory/{skuId}/transactions`.

Quy tắc API:

- Tất cả endpoint bảo vệ phải xác thực JWT ở backend.
- React gọi API qua Nginx, không gọi thẳng port service.
- List products hỗ trợ search tối thiểu theo `product_code`, `sku_code` hoặc `name`.
- Inventory list trả đủ dữ liệu để hiển thị `product_code`, `name`, `sku_code`, `size`, `quantity`, `active`.
- Transaction history sắp xếp mới nhất trước.
- Không trả raw exception hoặc stack trace.

HTTP status gợi ý:

- `200` cho list/detail/update/delete thành công.
- `201` cho create thành công.
- `401` khi chưa xác thực.
- `404` khi không tìm thấy `product` hoặc `SKU`.
- `409` cho `product_code` hoặc `sku_code` trùng nếu dùng conflict.
- `422` cho validation hoặc business rule không hợp lệ.

## 8. React

Frontend cần các phần tối thiểu:

- Product list.
- Product form.
- SKU management.
- Inventory balance list.
- Transaction history modal hoặc page.
- Search theo `product_code`, `sku_code` hoặc `name`.

Quy tắc React:

- Dùng TypeScript type cho `Product`, `ProductSku`, `InventoryBalance`, `InventoryTransaction`.
- Dùng enum hoặc union cho `InventoryTransactionType`.
- API functions đặt trong feature API layer.
- Dùng TanStack Query cho list/detail/history.
- Mutation thành công phải invalidate product, SKU, inventory hoặc transaction query liên quan.
- Form dùng React Hook Form và Zod.
- Có loading, empty, API error, validation error, unauthorized và forbidden state.
- Không lưu server state trùng trong `useState`.
- Không tạo dashboard nâng cao cho inventory trong MVP.

## 9. Test bắt buộc

Backend tests tối thiểu:

- `product_code` unique.
- `sku_code` unique.
- Product create thành công.
- Product update thành công.
- SKU create thành công.
- SKU update thành công.
- SKU có transaction chỉ soft delete hoặc deactivate, không hard delete.
- Balance query đúng.
- Transaction history sắp xếp mới nhất.
- Không cập nhật balance ngoài `InventoryBalanceService`.
- `InventoryBalanceService` tạo `inventory_transactions` khi increase.
- `InventoryBalanceService` tạo `inventory_transactions` khi decrease.
- Decrease quá tồn trả lỗi và rollback.
- Balance update lỗi phải rollback ledger.

Frontend tests tối thiểu:

- Product list render.
- Product form validation.
- Mutation success invalidate đúng query.
- Inventory list loading và empty state.
- Transaction history hiển thị đúng thứ tự từ API.
- API error hiển thị rõ.

## 10. Hành động bị cấm

Không được:

- Thêm category tree.
- Thêm price, promotion hoặc image management.
- Gom `product` và `SKU` thành một bảng.
- Lưu nhiều size trong một string.
- Xóa transaction history.
- Hard delete `SKU` đã có transaction history.
- Cập nhật `inventory_balances` từ Controller.
- Cập nhật `inventory_balances` mà không tạo `inventory_transactions`.
- Tạo transaction type ngoài MVP khi chưa có task rõ.
- Dùng JSON để lưu danh sách SKU hoặc ledger.
- Thêm `stores`, `suppliers`, `purchase_orders` hoặc barcode module nếu task không yêu cầu trong MVP.
- Gọi thẳng database của service khác.
- Tạo service thứ ba.

## 11. Tiêu chí hoàn thành

UC-10 và UC-12 chỉ được xem là Done khi:

- Product CRUD chạy end-to-end.
- SKU CRUD chạy end-to-end.
- Inventory balance list chạy end-to-end.
- Transaction history chạy end-to-end.
- Ledger có thể giải thích mọi thay đổi tồn.
- Mọi thay đổi balance đi qua `InventoryBalanceService`.
- Balance update và ledger insert nằm trong cùng DB transaction.
- Test pass.
- API hoạt động qua Nginx.
- Backend validation và authorization hoạt động.
- React không có TypeScript error.
- React không có console error.
- API docs cập nhật.

## 12. Định dạng output

Khi dùng skill này:

- Trả lời bằng tiếng Việt.
- Giữ nguyên tên class, API, database, enum, table, column và folder bằng tiếng Anh.
- Chỉ đề xuất thay đổi trong phạm vi UC-10 và UC-12.
- Nếu task yêu cầu tạo hoặc sửa skill, chỉ trả nội dung hoàn chỉnh của `SKILL.md`.
