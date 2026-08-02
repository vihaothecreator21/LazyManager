# Báo cáo tiến độ LazyManager MVP

> Cập nhật: 2026-08-02 | Branch: `codex/product-inventory-uc-10-12`

---

## Tổng quan nhanh

| Hạng mục | Hoàn thành | Tổng | % |
|---|---|---|---|
| Use Cases (UC) | **12 / 20** | 20 | **60%** |
| Backend APIs | **~22 / ~36** | ~36 route | **~61%** |
| React Pages (route) | **6 / 14** | 14 route | **43%** |
| Backend tests | **40 passed, 133 assertions** | - | Tốt |
| Infra/Docker | Hoàn chỉnh | - | **100%** |

---

## Chi tiết 20 Use Cases

### Nhóm People Service

| UC | Tên | Backend | Frontend | Tình trạng |
|---|---|---|---|---|
| UC-01 | Đăng nhập | ✅ | ✅ `/login` | **DONE** |
| UC-02 | Đăng xuất | ✅ | ✅ nút nav | **DONE** |
| UC-03 | Xem nhân viên | ✅ | ✅ `/employees` | **DONE** |
| UC-04 | Thêm nhân viên | ✅ Manager only | ✅ form | **DONE** |
| UC-05 | Sửa nhân viên | ✅ | ✅ form | **DONE** |
| UC-06 | Xóa mềm nhân viên | ✅ | ✅ | **DONE** |
| UC-07 | Xem lịch tuần | ✅ | ✅ `/schedule` | **DONE** |
| UC-08 | Gán nhân viên vào ca | ✅ rule 2 người | ✅ | **DONE** |
| UC-09 | Xóa nhân viên khỏi ca | ✅ | ✅ | **DONE** |

### Nhóm Inventory Service

| UC | Tên | Backend | Frontend | Tình trạng |
|---|---|---|---|---|
| UC-10 | CRUD sản phẩm và SKU | ✅ | ✅ `/products` | **DONE** (Task 8) |
| UC-11 | Import tồn kho | Khai báo api.md, code chưa có | ❌ UI chưa | **CHƯA** |
| UC-12 | Xem tồn và lịch sử giao dịch | ✅ | ✅ `/inventory` | **DONE** (Task 9) |
| UC-13 | Tạo/import phiếu bán hôm qua | ❌ | ❌ | **CHƯA** |
| UC-14 | Xác nhận phiếu bán và trừ tồn | ❌ | ❌ | **CHƯA** |
| UC-15 | Hủy phiếu bán đã xác nhận | ❌ | ❌ | **CHƯA** |
| UC-16 | Ghi nhận sản phẩm cho mượn | ❌ | ❌ | **CHƯA** |
| UC-17 | Ghi nhận sản phẩm đã trả | ❌ | ❌ | **CHƯA** |
| UC-18 | Tạo phiên kiểm kho | ❌ | ❌ | **CHƯA** |
| UC-19 | Xuất CSV kiểm kho | ❌ | ❌ | **CHƯA** |
| UC-20 | Nhập số lượng thực tế và xem chênh lệch | ❌ | ❌ | **CHƯA** |

> **Lưu ý UC-11:** `api.md` đã liệt kê `POST /stock-imports` và `POST /stock-imports/{id}/confirm` nhưng code backend chưa implement. UI `/inventory/import` chưa có.

---

## Chi tiết React Pages (14 route theo MVP doc)

| Route | Màn hình | Tình trạng |
|---|---|---|
| `/login` | Đăng nhập | ✅ DONE |
| `/` (dashboard) | Landing + nav links | ✅ DONE |
| `/employees` | Danh sách + CRUD nhân viên | ✅ DONE (dùng form inline) |
| `/schedule` | Bảng lịch tuần 7 ngày | ✅ DONE |
| `/products` | CRUD sản phẩm + SKU | ✅ DONE |
| `/inventory` | Tồn kho + lịch sử giao dịch | ✅ DONE |
| `/employees/new` | Form riêng tạo nhân viên | Xử lý inline, không có route riêng |
| `/employees/:id` | Chi tiết/sửa nhân viên | Xử lý inline, không có route riêng |
| `/inventory/import` | Upload + preview + confirm file | ❌ CHƯA |
| `/daily-sales` | Danh sách phiếu bán | ❌ CHƯA |
| `/daily-sales/new` | Upload/paste sản phẩm bán | ❌ CHƯA |
| `/borrowed` | Danh sách mượn/đã trả | ❌ CHƯA |
| `/stock-counts` | Danh sách phiên kiểm | ❌ CHƯA |
| `/stock-counts/:id` | Nhập actual, variance, export CSV | ❌ CHƯA |

---

## Database - những bảng còn thiếu

Theo MVP doc mục 6.2, `inventory_db` cần 10 bảng. Hiện tại đã có 4:

| Bảng | Tình trạng |
|---|---|
| `products` | ✅ |
| `product_skus` | ✅ |
| `inventory_balances` | ✅ |
| `inventory_transactions` | ✅ |
| `stock_imports` | ❌ CHƯA |
| `daily_sales` + `daily_sale_lines` | ❌ CHƯA |
| `borrow_records` | ❌ CHƯA |
| `stock_counts` + `stock_count_lines` | ❌ CHƯA |

---

## Tiêu chí "Done" theo MVP doc (mục 12)

| Tiêu chí | Tình trạng |
|---|---|
| Docker Compose dựng được trên máy mới | ✅ |
| 20 UC chạy được theo golden path | ❌ 12/20 |
| Backend kiểm tra quyền (không chỉ ẩn nút) | ✅ |
| Mọi thay đổi tồn có `inventory_transaction` | ✅ ledger atomic |
| Không trừ kho 2 lần / không trả 2 lần | ✅ |
| 12-15 test backend rule quan trọng | ✅ 40 tests, 133 assertions |
| README hướng dẫn + demo script | Chưa kiểm tra kỹ |
| Swagger / Postman collection | ❌ CHƯA |

---

## Đề xuất 4 task tiếp theo

```
Task 10: Stock Import (UC-11)
  Backend: stock_imports table, ImportStockUseCase, preview + confirm
  Frontend: /inventory/import (upload CSV, preview table, confirm)

Task 11: Daily Sales (UC-13/14/15)
  Backend: daily_sales + daily_sale_lines, ConfirmDailySalesUseCase, CancelDailySalesUseCase
  Frontend: /daily-sales, /daily-sales/new

Task 12: Borrow/Return (UC-16/17)
  Backend: borrow_records, BorrowProductUseCase, ReturnBorrowedProductUseCase
  Frontend: /borrowed

Task 13: Stock Count (UC-18/19/20)
  Backend: stock_counts + stock_count_lines, GenerateStockCountUseCase, ExportStockCountCsvUseCase
  Frontend: /stock-counts, /stock-counts/:id

Task 14: Quality + Delivery
  README hoàn chỉnh + tài khoản demo
  Swagger/OpenAPI hoặc Postman collection
  Seed demo data end-to-end
  Quay video demo ngắn
```

---

## Tóm tắt

Dự án đã xây xong **nền tảng vững** (infra, auth, RBAC, ledger atomic) và hoàn thành **5/5 domain People** + **2/11 domain Inventory** (UC-10, UC-12).

Còn lại 8 UC inventory tập trung vào 4 luồng nghiệp vụ: import tồn, bán hàng, mượn hàng, kiểm kho. Đây là phần tạo ra "demo value" thật sự của sản phẩm portfolio.
