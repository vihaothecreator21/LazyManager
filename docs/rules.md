# Quy tắc nghiệp vụ LazyManager

## Vai trò

- Chỉ `STORE_MANAGER` được tạo, cập nhật hoặc xóa mềm nhân viên.
- `STAFF` phải nhận HTTP 403 khi gọi API ghi dữ liệu nhân viên.
- Backend phải kiểm tra quyền. React có thể ẩn nút, nhưng đó không phải bảo mật.

## Nhân viên

- Mã nhân viên phải unique.
- Employees dùng Laravel SoftDeletes qua `deleted_at`.
- Không thêm `employees.active` trong MVP.
- Nhân viên đã soft delete không được gán vào ca mới.
- Lịch sử lịch làm hiện có phải được giữ sau khi nhân viên bị soft delete.
- `users.status` kiểm soát đăng nhập và dùng `ACTIVE` hoặc `LOCKED`.

## Lịch làm

- Mỗi ngày có đúng 2 loại ca: `MORNING` và `AFTERNOON`.
- Mỗi ca có tối đa 2 nhân viên.
- Cùng một nhân viên không được gán hai lần vào cùng một ca.
- Một nhân viên có thể làm cả sáng và chiều trong MVP.

## Tồn kho

- Current balance không được cập nhật trực tiếp từ controllers.
- Mọi thay đổi số lượng tồn kho phải tạo một `inventory_transaction`.
- `inventory_balances.quantity` là trạng thái hiện tại.
- Transaction types trong MVP:
  - `IMPORT_SYNC`
  - `SALE`
  - `SALE_REVERSAL`
  - `BORROW_OUT`
  - `BORROW_RETURN`

## Nhập tồn kho

- Import phải preview các dòng trước khi xác nhận.
- Dòng không hợp lệ phải hiển thị cho người dùng.
- File hash trùng phải bị chặn hoặc bị từ chối rõ ràng.
- Confirm import phải cập nhật balances và transactions một cách atomic.
- `IMPORT_SYNC` nghĩa là đồng bộ tuyệt đối, không phải cộng dồn.
- Khi confirm:
  - `quantity_before = current balance`.
  - `quantity_after = imported quantity`.
  - `quantity_change = quantity_after - quantity_before`.
- Không set balance trực tiếp khi thiếu ledger `IMPORT_SYNC`.

## Bán hàng hằng ngày

- Daily sales draft không làm thay đổi tồn kho.
- Chỉ daily sales record đã confirmed mới làm giảm tồn kho.
- Confirm sales phải chạy trong database transaction.
- Nếu một dòng lỗi, toàn bộ confirmation phải rollback.
- Sales record đã confirmed không được làm giảm tồn kho lần hai.
- Hủy confirmed sales phải tạo reversal transactions.

## Mượn hàng

- Mượn sản phẩm làm giảm tồn kho ngay.
- Trả sản phẩm đã mượn làm tăng tồn kho.
- Một borrow record không được return hai lần.

## Kiểm kho

- `expected_quantity` là snapshot khi tạo stock count session.
- `expected_quantity` không được tự động thay đổi sau các thay đổi tồn kho về sau.
- `actual_quantity` phải là số nguyên không âm.
- `variance = actual_quantity - expected_quantity`.
- MVP chỉ hiển thị variance. Không tự động điều chỉnh tồn kho từ variance.
