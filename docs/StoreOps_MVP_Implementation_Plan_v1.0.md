> Tài liệu nguồn đã lưu trữ. Tên dự án hiện tại là `LazyManager`. Dùng `docs/scope.md`, `docs/rules.md`, `docs/architecture.md`, `docs/decisions.md`, `docs/api.md` và `docs/setup-plan.md` làm nguồn sự thật.

**STOREOPS MVP**

**KẾ HOẠCH TRIỂN KHAI & TODO**

Kế hoạch triển khai 1 tháng cho dự án React + PHP Laravel + OOP + Microservice

| **Tên dự án** | StoreOps MVP - Quản lý nhân viên, lịch làm, tồn kho và kiểm kho |
| **Phiên bản** | 1.0 |
| **Thời gian triển khai** | 20 ngày làm việc / 4 tuần |
| **Kiến trúc** | React + 2 Laravel Microservices + PostgreSQL + Docker + Nginx |
| **Vai trò hệ thống** | Store Manager, Staff |
| **Mục tiêu** | Hoàn thành một sản phẩm chạy được thật và dùng làm portfolio intern/fresher |
| **Trạng thái** | SẴN SÀNG TRIỂN KHAI |

# 1. Mục tiêu của kế hoạch triển khai

Tài liệu này biến tài liệu yêu cầu dự án thành các công việc có thể bắt tay vào làm ngay. Trọng tâm là logic, code, kiến trúc và kết quả chạy được; không yêu cầu sử dụng nhiều thuật ngữ BA.

- Giữ phạm vi đủ nhỏ để một sinh viên năm 4 có thể hoàn thành trong 1 tháng.
- Giữ OOP và microservice ở mức thực tế, không chia quá nhiều service.
- Ưu tiên làm theo từng luồng hoàn chỉnh từ React đến database.
- Mỗi tuần đều phải có một phiên bản có thể chạy và demo.
- Mọi tính năng mới ngoài phạm vi được đưa vào backlog tương lai, không chen vào MVP.

# 2. Phạm vi MVP đã khóa

## 2.1 Hai microservice

React Web App
↓
Nginx API Gateway
├── People Service → đăng nhập, nhân viên, lịch làm
└── Inventory Service → sản phẩm, tồn kho, bán, mượn, kiểm kho

- People Service có database people\_db riêng.
- Inventory Service có database inventory\_db riêng.
- Hai service giao tiếp bằng REST API trong MVP.
- Không dùng RabbitMQ, Redis, AI hoặc Notification Service trong tháng đầu.

## 2.2 Hai quyền

| **Chức năng** | **Store Manager** | **Staff** |
| Đăng nhập, xem dữ liệu | Có | Có |
| Xem nhân viên | Có | Có |
| Thêm/sửa/xóa nhân viên | Có | Không |
| Xếp lịch | Có | Có |
| CRUD sản phẩm/SKU | Có | Có |
| Import tồn kho | Có | Có |
| Nhập bán hàng | Có | Có |
| Mượn/trả hàng | Có | Có |
| Kiểm kho, CSV | Có | Có |

## 2.3 Không làm trong MVP

- AI
- RabbitMQ/Redis
- Check-in/check-out
- Đi sớm/đi trễ
- Nghỉ phép/availability
- Đổi ca
- Multi-tenant
- Email thông báo
- Dashboard nâng cao
- App mobile
- Dự báo tồn kho

# 3. Quy tắc nghiệp vụ cần code đúng

## 3.1 Nhân viên và quyền

- Chỉ Store Manager được tạo, sửa và xóa mềm nhân viên.
- Staff gọi API tạo/sửa/xóa nhân viên phải nhận HTTP 403.
- Nhân viên bị xóa mềm hoặc inactive không được xếp vào ca mới.
- Quyền phải được kiểm tra ở backend, không chỉ ẩn nút trên React.

## 3.2 Lịch làm

- Mỗi ngày có 2 ca: MORNING và AFTERNOON.
- Mỗi ca tối đa 2 nhân viên.
- Không thêm cùng một nhân viên hai lần vào cùng ca.
- Có thể xóa nhân viên khỏi ca và gán người khác.

## 3.3 Tồn kho

current\_balance = imported\_stock - sold\_quantity - borrowed\_out + returned\_quantity

- Mỗi thay đổi tồn phải tạo một inventory\_transaction.
- Không cập nhật balance trực tiếp từ controller.
- Các loại transaction MVP: IMPORT\_SYNC, SALE, SALE\_REVERSAL, BORROW\_OUT, BORROW\_RETURN.
- Xác nhận bán hàng phải chạy trong database transaction; lỗi một dòng thì rollback toàn bộ.
- Một phiếu bán đã xác nhận không được trừ lại lần thứ hai.

## 3.4 Kiểm kho

- Khi tạo phiên kiểm kho, expected\_quantity được snapshot.
- expected\_quantity không tự đổi dù tồn hiện tại thay đổi.
- actual\_quantity phải là số nguyên không âm.
- variance = actual\_quantity - expected\_quantity.

# 4. Danh sách Use Case đầy đủ

| **ID** | **Use case** | **Người dùng** | **Kết quả** |
| UC-01 | Đăng nhập | Manager, Staff | Đăng nhập và nhận access token |
| UC-02 | Đăng xuất | Manager, Staff | Thu hồi phiên đăng nhập |
| UC-03 | Xem nhân viên | Manager, Staff | Danh sách và chi tiết |
| UC-04 | Tạo nhân viên | Manager | Tạo tài khoản/hồ sơ nhân viên |
| UC-05 | Sửa nhân viên | Manager | Cập nhật hồ sơ |
| UC-06 | Xóa mềm nhân viên | Manager | Không mất dữ liệu lịch sử |
| UC-07 | Xem lịch tuần | Manager, Staff | Hiển thị 2 ca/ngày |
| UC-08 | Gán nhân viên vào ca | Manager, Staff | Tối đa 2 người/ca |
| UC-09 | Xóa nhân viên khỏi ca | Manager, Staff | Giải phóng vị trí trong ca |
| UC-10 | CRUD sản phẩm và SKU | Manager, Staff | Quản lý mã sản phẩm, size |
| UC-11 | Import tồn kho | Manager, Staff | Upload, preview, confirm |
| UC-12 | Xem tồn và lịch sử | Manager, Staff | Balance + transactions |
| UC-13 | Tạo phiếu bán hằng ngày | Manager, Staff | Nhập SKU và số lượng |
| UC-14 | Xác nhận phiếu bán | Manager, Staff | Trừ tồn đúng một lần |
| UC-15 | Hủy phiếu bán | Manager, Staff | Tạo SALE\_REVERSAL |
| UC-16 | Ghi nhận cho mượn | Manager, Staff | Trừ tồn bằng BORROW\_OUT |
| UC-17 | Ghi nhận trả hàng | Manager, Staff | Cộng tồn bằng BORROW\_RETURN |
| UC-18 | Tạo phiên kiểm kho | Manager, Staff | Snapshot expected |
| UC-19 | Xuất CSV kiểm kho | Manager, Staff | File danh sách cần kiểm |
| UC-20 | Nhập thực tế và tính lệch | Manager, Staff | actual và variance |

# 5. Thiết kế trước khi code

## 5.1 Cấu trúc repository

storeops/
├── frontend/
├── services/
│ ├── people-service/
│ └── inventory-service/
├── gateway/
│ └── nginx.conf
├── docs/
├── docker-compose.yml
├── .env.example
└── README.md

## 5.2 Cấu trúc OOP bên trong mỗi Laravel service

app/
├── Domain/
│ ├── Entities/
│ ├── Enums/
│ ├── Exceptions/
│ └── Services/
├── Application/
│ ├── DTOs/
│ ├── UseCases/
│ └── Interfaces/
├── Infrastructure/
│ └── Persistence/
└── Http/
├── Controllers/
├── Requests/
├── Resources/
└── Middleware/

- Controller chỉ nhận request, gọi use case và trả response.
- Business rule nằm trong UseCase/Domain Service.
- Repository interface tách business logic khỏi Eloquent.
- Enum dùng cho role, status, transaction type và shift type.

# 6. Database tối giản

## 6.1 people\_db

| **Bảng** | **Mục đích** | **Constraint chính** |
| users | Đăng nhập và role | email unique |
| employees | Hồ sơ nhân viên | employee\_code unique; soft delete |
| schedules | Một ca cụ thể theo ngày | unique(work\_date, shift\_type) |
| shift\_assignments | Nhân viên trong ca | unique(schedule\_id, employee\_id) |

## 6.2 inventory\_db

| **Bảng** | **Mục đích** | **Ghi chú** |
| products | Thông tin sản phẩm | product\_code unique |
| product\_skus | SKU theo size | sku\_code unique |
| inventory\_balances | Tồn hiện tại | unique(sku\_id) |
| inventory\_transactions | Lịch sử tăng/giảm | before/change/after |
| stock\_imports | Phiên import | file\_hash |
| daily\_sales | Phiếu bán ngày | status DRAFT/CONFIRMED/CANCELLED |
| daily\_sale\_lines | Dòng SKU đã bán | quantity > 0 |
| borrow\_records | Cho mượn/trả | status BORROWED/RETURNED |
| stock\_counts | Phiên kiểm kho | count\_date |
| stock\_count\_lines | Expected/actual/variance | snapshot expected |

# 7. API cần triển khai

| **Service** | **Endpoint** | **Mục đích** |
| People | POST /api/v1/auth/login | Đăng nhập |
| People | POST /api/v1/auth/logout | Đăng xuất |
| People | GET /api/v1/employees | Danh sách nhân viên |
| People | POST /api/v1/employees | Tạo nhân viên - Manager |
| People | GET /api/v1/employees/{id} | Chi tiết nhân viên |
| People | PUT /api/v1/employees/{id} | Sửa - Manager |
| People | DELETE /api/v1/employees/{id} | Xóa mềm - Manager |
| People | GET /api/v1/schedules | Lịch tuần |
| People | POST /api/v1/schedules | Tạo ca |
| People | POST /api/v1/schedules/{id}/assignments | Gán nhân viên |
| People | DELETE /api/v1/schedules/{id}/assignments/{employeeId} | Xóa khỏi ca |
| Inventory | GET/POST /api/v1/products | List/tạo sản phẩm |
| Inventory | GET/PUT/DELETE /api/v1/products/{id} | Chi tiết/sửa/xóa |
| Inventory | POST /api/v1/products/{id}/skus | Tạo SKU |
| Inventory | GET /api/v1/inventory | Xem tồn |
| Inventory | POST /api/v1/stock-imports | Upload/preview |
| Inventory | POST /api/v1/stock-imports/{id}/confirm | Xác nhận import |
| Inventory | POST /api/v1/daily-sales | Tạo phiếu bán |
| Inventory | POST /api/v1/daily-sales/{id}/confirm | Trừ tồn |
| Inventory | POST /api/v1/daily-sales/{id}/cancel | Hoàn tồn |
| Inventory | POST /api/v1/borrow-records | Cho mượn |
| Inventory | POST /api/v1/borrow-records/{id}/return | Trả hàng |
| Inventory | POST /api/v1/stock-counts | Tạo phiên kiểm |
| Inventory | GET /api/v1/stock-counts/{id}/export-csv | Xuất CSV |
| Inventory | PUT /api/v1/stock-counts/{id}/lines | Nhập actual |

# 8. Kế hoạch 20 ngày

| **Ngày** | **Công việc** | **Kết quả bắt buộc** |
| Ngày 1 | Khóa scope, quy tắc nghiệp vụ, danh sách màn hình | scope.md, rules.md, screen-list.md |
| Ngày 2 | Vẽ ERD 2 database và chốt constraint | ERD people\_db + inventory\_db |
| Ngày 3 | Chốt API, tạo backlog, Definition of Done | api-list.md, GitHub Project |
| Ngày 4 | Tạo monorepo, Docker, Nginx, React, 2 Laravel | docker compose up chạy được |
| Ngày 5 | Login, JWT, role enum, middleware | UC-01, UC-02 |
| Ngày 6 | Employee migrations, repository, list/detail | UC-03 |
| Ngày 7 | Create/update/delete employee + policy tests | UC-04 đến UC-06 |
| Ngày 8 | Schedule tables và API xem lịch tuần | UC-07 |
| Ngày 9 | Assign/remove employee, max 2 validation | UC-08, UC-09 |
| Ngày 10 | React employee + schedule pages, hoàn thiện tuần 2 | Demo People Service |
| Ngày 11 | Product và SKU CRUD backend | UC-10 |
| Ngày 12 | React product pages + inventory balance list | UC-10, UC-12 |
| Ngày 13 | Import tồn kho: upload, parse, preview | UC-11 phần 1 |
| Ngày 14 | Confirm import, transaction ledger, duplicate protection | UC-11 hoàn chỉnh |
| Ngày 15 | Daily sales draft + preview before/after | UC-13 |
| Ngày 16 | Confirm/cancel sales, atomic transaction | UC-14, UC-15 |
| Ngày 17 | Borrow out và return | UC-16, UC-17 |
| Ngày 18 | Stock count snapshot + CSV | UC-18, UC-19 |
| Ngày 19 | Actual, variance, frontend stock-count flow | UC-20 |
| Ngày 20 | Regression test, Swagger, README, seed, demo script | Release v1.0-demo |

# 9. TODO chi tiết theo Epic

## EPIC-01 Nền tảng

\[ \] Tạo repository và branch develop

\[ \] Tạo Docker Compose

\[ \] Tạo Nginx routes

\[ \] Tạo React + TypeScript + Vite

\[ \] Tạo People Laravel service

\[ \] Tạo Inventory Laravel service

\[ \] Tạo 2 PostgreSQL databases

\[ \] Tạo /health cho hai service

\[ \] Tạo .env.example

## EPIC-02 Authentication & Employee

\[ \] Migration users/employees

\[ \] UserRole enum

\[ \] LoginUseCase

\[ \] JWT middleware

\[ \] ManagerOnly middleware/policy

\[ \] ListEmployeeUseCase

\[ \] CreateEmployeeUseCase

\[ \] UpdateEmployeeUseCase

\[ \] DeleteEmployeeUseCase

\[ \] React login page

\[ \] React employee list/form

\[ \] Test Staff nhận 403

## EPIC-03 Schedule

\[ \] Migration schedules/shift\_assignments

\[ \] ShiftType enum

\[ \] GetWeeklyScheduleUseCase

\[ \] AssignEmployeeToShiftUseCase

\[ \] RemoveEmployeeFromShiftUseCase

\[ \] Rule max 2 employees

\[ \] Rule active employee only

\[ \] React weekly schedule page

\[ \] Unit test quy tắc lịch làm

## EPIC-04 Product & Inventory

\[ \] Migration products/product\_skus

\[ \] Migration balances/transactions

\[ \] Product CRUD use cases

\[ \] SKU create/update use cases

\[ \] InventoryBalanceService

\[ \] InventoryTransactionRepository

\[ \] React product pages

\[ \] React inventory list

## EPIC-05 Stock Import

\[ \] File upload validation

\[ \] CSV/XLSX importer interface

\[ \] Preview rows and errors

\[ \] File hash duplicate check

\[ \] Confirm import use case

\[ \] Atomic update balance + ledger

\[ \] React import wizard

\[ \] Tests import duplicate/rollback

## EPIC-06 Daily Sales

\[ \] Migration daily\_sales/lines

\[ \] Create draft use case

\[ \] Preview before/after

\[ \] Confirm sales use case

\[ \] Idempotency check

\[ \] Cancel/reversal use case

\[ \] React sales pages

\[ \] Tests not enough stock/confirm twice

## EPIC-07 Borrow

\[ \] Migration borrow\_records

\[ \] Create borrow use case

\[ \] Borrow out transaction

\[ \] Return use case

\[ \] Prevent return twice

\[ \] React borrow pages

\[ \] Unit tests

## EPIC-08 Stock Count

\[ \] Migration stock\_counts/lines

\[ \] Create snapshot use case

\[ \] CSV export service

\[ \] Update actual use case

\[ \] Calculate variance

\[ \] React stock count page

\[ \] Test expected remains immutable

## EPIC-09 Quality & Delivery

\[ \] Swagger/OpenAPI

\[ \] Seed demo data

\[ \] README run guide

\[ \] Demo account manager/staff

\[ \] Regression checklist

\[ \] Docker clean rebuild test

\[ \] Demo script 5-7 phút

\[ \] Tag release v1.0-demo

# 10. Tiêu chí hoàn thành

- Code đã chạy trên máy sạch bằng Docker Compose.
- API validate dữ liệu và trả HTTP status phù hợp.
- Quyền được kiểm tra ở backend.
- Business rule không nằm trong controller.
- Có xử lý lỗi và rollback cho nghiệp vụ tồn kho.
- Có ít nhất một test cho happy path và một test cho lỗi quan trọng.
- React gọi API thành công, không có console error.
- API document được cập nhật.
- Commit rõ nghĩa và merge qua feature branch.
- Tính năng đã được chạy lại theo acceptance checklist.

# 11. Quy trình Git và commit

main
└── develop
├── feature/auth-employee
├── feature/schedule
├── feature/product-inventory
├── feature/daily-sales
└── feature/stock-count

- Không code trực tiếp trên main.
- Mỗi branch chỉ tập trung một nhóm chức năng.
- Trước khi merge: chạy test, tự review diff, cập nhật tài liệu.
- Commit theo mẫu: feat:, fix:, test:, docs:, refactor:.

# 12. Checklist kiểm thử nghiệp vụ

| **ID** | **Tình huống** | **Kết quả mong đợi** | **Trạng thái** |
| AUTH-01 | Staff không thể POST/PUT/DELETE employees | PASS theo quy tắc | \[ \] |
| EMP-01 | Không tạo employee\_code trùng | PASS theo quy tắc | \[ \] |
| EMP-02 | Xóa mềm không mất lịch sử | PASS theo quy tắc | \[ \] |
| SCH-01 | Ca thứ 3 bị từ chối | PASS theo quy tắc | \[ \] |
| SCH-02 | Một người không được thêm hai lần cùng ca | PASS theo quy tắc | \[ \] |
| INV-01 | Import tạo balance và transaction | PASS theo quy tắc | \[ \] |
| INV-02 | Import lỗi một dòng rollback toàn bộ khi confirm | PASS theo quy tắc | \[ \] |
| SALE-01 | DRAFT không làm giảm tồn | PASS theo quy tắc | \[ \] |
| SALE-02 | CONFIRMED chỉ trừ một lần | PASS theo quy tắc | \[ \] |
| SALE-03 | Thiếu tồn thì toàn phiếu rollback | PASS theo quy tắc | \[ \] |
| SALE-04 | Cancel tạo reversal và cộng tồn | PASS theo quy tắc | \[ \] |
| BOR-01 | Borrow làm giảm tồn | PASS theo quy tắc | \[ \] |
| BOR-02 | Return làm tăng tồn | PASS theo quy tắc | \[ \] |
| BOR-03 | Không return hai lần | PASS theo quy tắc | \[ \] |
| CNT-01 | Expected được snapshot | PASS theo quy tắc | \[ \] |
| CNT-02 | Variance đúng công thức | PASS theo quy tắc | \[ \] |
| CNT-03 | Actual âm bị từ chối | PASS theo quy tắc | \[ \] |
| CSV-01 | CSV UTF-8 và đúng header | PASS theo quy tắc | \[ \] |

# 13. Rủi ro và cách xử lý

| **Rủi ro** | **Tác động** | **Cách xử lý** |
| Scope tăng giữa tháng | Không kịp hoàn thành | Đưa vào future-backlog.md; không code trong MVP |
| Mất quá nhiều thời gian làm UI | Backend chưa xong | Dùng layout đơn giản, ưu tiên form/table |
| Microservice gây khó debug | Chậm tiến độ | Chỉ 2 service; REST; log rõ request\_id |
| Import Excel phức tạp | Tắc tuần 3 | Bắt đầu CSV template trước, XLSX là bổ sung |
| Logic tồn sai | Dữ liệu demo không đáng tin | Ledger + DB transaction + unit test |
| Thiếu thời gian cuối tháng | Không có portfolio hoàn chỉnh | Ngày 18 phải code freeze phần mới; ngày 19-20 chỉ fix/test/docs |

# 14. Kịch bản demo cuối tháng

1. Đăng nhập bằng Store Manager và tạo một nhân viên mới.
2. Đăng nhập bằng Staff và chứng minh Staff bị chặn khi tạo nhân viên.
3. Mở lịch tuần, gán hai nhân viên vào ca sáng và thử gán người thứ ba để thấy validation.
4. Tạo sản phẩm và các SKU theo size.
5. Import file tồn kho và xem lịch sử IMPORT\_SYNC.
6. Tạo phiếu bán hôm qua, xem tồn trước/sau, xác nhận và xem SALE transaction.
7. Ghi nhận một sản phẩm cho mượn rồi ghi nhận trả.
8. Tạo phiên kiểm kho, xuất CSV, nhập số thực tế và xem variance.
9. Mở Swagger, test report và Docker Compose để chứng minh chất lượng kỹ thuật.

# 15. Tiêu chí hoàn thành dự án

- 20 use case hoạt động đúng.
- Hai microservice chạy độc lập và có database riêng.
- React gọi được cả hai service qua Nginx.
- OOP thể hiện qua UseCase, DTO, Repository, Enum và Domain Service.
- Luồng tồn kho có ledger, transaction và rollback.
- Có test cho các business rule quan trọng.
- Có README, Swagger, ERD, seed data và demo script.
- Một người khác có thể clone repo và chạy bằng Docker Compose.

# 16. Theo dõi tiến độ

| **Tuần** | **Mục tiêu** | **Đã lên kế hoạch** | **Đã xong** | **Bị chặn** | **Ghi chú** |
| Tuần 1 | Nền tảng + Auth + Employee | \[ \] | \[ \] | \[ \] |  |
| Tuần 2 | Schedule + Product | \[ \] | \[ \] | \[ \] |  |
| Tuần 3 | Import + Sales + Borrow | \[ \] | \[ \] | \[ \] |  |
| Tuần 4 | Stock count + Test + Docs | \[ \] | \[ \] | \[ \] |  |

**Nguyên tắc quan trọng nhất:** ưu tiên hoàn thành một luồng chạy xuyên suốt hơn là tạo nhiều chức năng dang dở.
