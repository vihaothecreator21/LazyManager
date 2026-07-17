# Phạm vi MVP LazyManager

## Mục tiêu

Xây một MVP LazyManager chạy được trong 1 tháng cho dự án portfolio cá nhân.

Hệ thống phải chạy local bằng Docker Compose và demo được các luồng end-to-end thật cho xếp lịch nhân viên và kiểm tra tồn kho.

## Trong phạm vi

- Ứng dụng web React.
- Nginx API gateway.
- People Service.
- Inventory Service.
- Mỗi service có PostgreSQL database riêng.
- REST API giữa frontend và backend services.
- Backend kiểm tra role.
- Import/export file cơ bản cho workflow tồn kho.
- Backend tests cho các quy tắc nghiệp vụ quan trọng.

## Tên dự án

- Tên repository và product: `LazyManager`.
- Tài liệu cũ hơn có thể nhắc `StoreOps`; xem đó là tên làm việc trước đây.
- Docs mới, code comments, README, Docker project name và UI labels nên dùng `LazyManager`.

## Vai trò

- `STORE_MANAGER`
- `STAFF`

## Services

### People Service

- Xác thực.
- Người dùng.
- Nhân viên.
- Lịch tuần.
- Phân công ca.

### Inventory Service

- Sản phẩm.
- SKU.
- Số dư tồn kho.
- Giao dịch tồn kho.
- Nhập tồn kho.
- Bán hàng hằng ngày.
- Bản ghi mượn hàng.
- Kiểm kho.

## Use Cases của MVP

1. Đăng nhập.
2. Đăng xuất bằng cách xóa JWT trên React.
3. Xem nhân viên.
4. Tạo nhân viên.
5. Cập nhật nhân viên.
6. Xóa mềm nhân viên.
7. Xem lịch tuần.
8. Gán nhân viên vào ca.
9. Xóa nhân viên khỏi ca.
10. CRUD sản phẩm và SKU.
11. Import tồn kho.
12. Xem tồn kho và lịch sử giao dịch.
13. Tạo daily sales draft.
14. Confirm daily sales.
15. Cancel confirmed daily sales.
16. Mượn sản phẩm.
17. Trả sản phẩm đã mượn.
18. Tạo stock count session.
19. Export stock count CSV.
20. Nhập số lượng thực tế và xem variance.

## Ngoài phạm vi

- RabbitMQ.
- Redis.
- AI service.
- Notification service.
- Check-in/check-out.
- Đi muộn/về sớm.
- Nghỉ phép.
- Availability.
- Đổi ca.
- Multi-tenant SaaS.
- Dashboard nâng cao.
- Mobile app.
- Dynamic role/permission builder.
