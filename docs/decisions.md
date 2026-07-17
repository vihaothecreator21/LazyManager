# Quyết định LazyManager

## ADR-000: Dùng LazyManager làm tên dự án

Quyết định: dùng `LazyManager` làm tên repository, product, README, Docker project và UI.

Lý do: workspace và hướng dự án đã thống nhất dưới tên `LazyManager`. Tài liệu nguồn cũ hơn vẫn có thể nhắc `StoreOps` như tên làm việc trước đây.

## ADR-001: Dùng monorepo

Quyết định: giữ frontend, gateway, services và docs trong cùng một repository.

Lý do: dự án là MVP cá nhân 1 tháng. Monorepo giúp setup, Docker Compose và review đơn giản hơn.

## ADR-002: Dùng hai Laravel services

Quyết định: tách backend thành `people-service` và `inventory-service`.

Lý do: đây là các ranh giới nghiệp vụ rõ. Nhiều service hơn sẽ làm chậm MVP.

## ADR-003: Mỗi service sở hữu database riêng

Quyết định: `people-service` dùng `people_db`; `inventory-service` dùng `inventory_db`.

Lý do: giữ ranh giới microservice thật trong khi vẫn đủ đơn giản cho MVP.

## ADR-004: Dùng REST trong MVP

Quyết định: frontend gọi services qua Nginx bằng REST APIs.

Lý do: REST đủ cho các luồng MVP. Async messaging không cần trong tháng 1.

## ADR-005: Không dùng RabbitMQ hoặc Redis trong MVP

Quyết định: bỏ RabbitMQ, Redis, outbox, DLQ và background queues.

Lý do: chúng thêm việc hạ tầng mà không mở khóa demo scheduling và inventory cốt lõi.

## ADR-006: Lưu roles bằng enum values

Quyết định: dùng fixed role enum values: `STORE_MANAGER` và `STAFF`.

Lý do: dynamic permissions nằm ngoài phạm vi. Enum roles dễ test và dễ giải thích hơn.

## ADR-007: Xây walking skeleton trước deep features

Quyết định: đầu tiên làm đường đi end-to-end qua React, Nginx, cả hai services và cả hai databases.

Lý do: hạ tầng chạy thật giúp lộ vấn đề setup sớm và giữ tài liệu bám vào code chạy được.

## ADR-008: Gateway route prefixes

Quyết định: public API routes đi qua Nginx bằng service prefixes:

- `/api/people/v1/*` map đến People Service `/api/v1/*`.
- `/api/inventory/v1/*` map đến Inventory Service `/api/v1/*`.
- `/api/people/health` map đến People Service `/health`.
- `/api/inventory/health` map đến Inventory Service `/health`.
- `/api/people/ready` map đến People Service `/ready`.
- `/api/inventory/ready` map đến Inventory Service `/ready`.

Lý do: React dùng một origin, quyền sở hữu service thể hiện rõ trong URL và Laravel routes nội bộ vẫn có version.

## ADR-009: Laravel development runtime

Quyết định: trong MVP, mỗi Laravel service chạy `php artisan serve --host=0.0.0.0 --port=8000`.

Lý do: đủ cho portfolio MVP local 20 ngày và tránh setup thêm Nginx + PHP-FPM cho từng service.

## ADR-010: Schedule dùng hai bảng

Quyết định: dùng `schedules` và `shift_assignments`.

Lý do: ca làm là một domain object rõ ràng, API `/schedules/{id}/assignments` tự nhiên và dễ hiển thị ca trống.

## ADR-011: Employee delete dùng SoftDeletes

Quyết định: `employees` dùng Laravel SoftDeletes qua `deleted_at`; không thêm `employees.active` trong MVP.

Lý do: soft delete giữ lịch sử lịch làm. `users.status` tách riêng kiểm soát đăng nhập bằng `ACTIVE` hoặc `LOCKED`.

## ADR-012: Logout chỉ ở frontend trong MVP

Quyết định: UC-02 Logout xóa JWT trong React và chuyển về login. Không tạo backend logout endpoint trong MVP.

Lý do: JWT là stateless và MVP không dùng Redis hoặc token blacklist tables.

## ADR-013: JWT claims bắt buộc

Quyết định: JWT phải gồm `sub`, `role`, `iss`, `aud`, `iat` và `exp`.

Lý do: Inventory Service có thể xác minh chữ ký, issuer, audience, thời hạn và role mà không gọi People Service.

## ADR-014: IMPORT_SYNC là đồng bộ tuyệt đối

Quyết định: `IMPORT_SYNC` đặt balance bằng quantity trong file import, không phải cộng thêm vào balance hiện tại.

Quy tắc:

```text
quantity_before = current balance
quantity_after = imported quantity
quantity_change = quantity_after - quantity_before
```

Lý do: nhiều lần stock import vẫn đúng và ledger vẫn giải thích được mọi thay đổi.

## ADR-015: Lưu preview stock import

Quyết định: lưu metadata file import đã upload trong `stock_imports` và đọc lại file đã lưu khi confirm. Không tạo `stock_import_lines` trong MVP trừ khi triển khai chứng minh là cần.

Các field gợi ý:

- `original_file_name`.
- `stored_file_path`.
- `file_hash`.
- `status`.
- `validation_errors` JSONB.
- `confirmed_at`.

Lý do: giữ schema nhỏ hơn nhưng vẫn hỗ trợ preview và confirm.

## ADR-016: Thứ tự setup

Quyết định: tạo application skeletons trước khi verify Docker Compose.

Thứ tự:

1. Kiểm tra workspace.
2. Khóa docs.
3. Tạo monorepo folders.
4. Tạo Laravel và React skeletons.
5. Tạo Dockerfiles.
6. Tạo Docker Compose.
7. Tạo Nginx routes.
8. Tạo health và readiness endpoints.
9. Tạo React health page.
10. Tạo env docs và README.
11. Clean rebuild.
12. Commit walking skeleton.

Lý do: Docker Compose chỉ có thể được verify có ý nghĩa sau khi applications tồn tại.
