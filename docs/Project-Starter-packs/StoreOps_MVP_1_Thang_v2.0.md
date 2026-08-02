> Tài liệu nguồn đã lưu trữ. Tên dự án hiện tại là `LazyManager`. Dùng `docs/scope.md`, `docs/rules.md`, `docs/architecture.md`, `docs/decisions.md`, `docs/api.md` và `docs/setup-plan.md` làm nguồn sự thật.

**STOREOPS**

_Kế hoạch MVP 1 tháng - React + PHP Laravel + OOP + Microservice_

**Phiên bản 2.0 - Giảm phạm vi**

| **Thông tin** | **Nội dung** |
| --- | --- |
| Mục tiêu | Xây một website chạy được thật trong 1 tháng, giải quyết kiểm kho hằng ngày và xếp lịch nhân viên. |
| Đối tượng | Sinh viên năm 4, làm dự án cá nhân để học và phỏng vấn intern/fresher. |
| Frontend | React + TypeScript + Vite |
| Backend | PHP 8.x + Laravel, tổ chức code theo OOP |
| Kiến trúc | 2 microservice, REST API, 2 database riêng |
| Vai trò | STORE\_MANAGER và STAFF |
| Thời gian | 4 tuần / khoảng 20 ngày làm việc |

| **Nguyên tắc quan trọng**   MVP này ưu tiên hoàn thành luồng nghiệp vụ thật. Không thêm công nghệ chỉ để dự án trông phức tạp. Mỗi chức năng phải có lý do rõ ràng và có thể demo end-to-end. |

# 1. Vì sao phải giảm phạm vi?

Tài liệu v1.0 đang chứa nhiều phần phù hợp cho một dự án dài hơn: ba service chính, Notification Service, AI Service, RabbitMQ, Redis, chấm công, nghỉ phép, nhiều trạng thái mượn hàng, dashboard và kế hoạch 10 tuần. Với một sinh viên làm cá nhân trong 1 tháng, nguy cơ lớn nhất là làm dở nhiều phần nhưng không có luồng nào hoàn chỉnh.

## 1.1 Mục tiêu mới

- Hoàn thành được một website có thể chạy bằng Docker Compose.
- Giải quyết đúng hai bài toán thật: kiểm kho buổi sáng và xếp lịch làm.
- Giữ kiến trúc microservice nhưng chỉ tách hai service có ranh giới rõ.
- Giữ OOP trong từng service để thể hiện cách tổ chức code chuyên nghiệp.
- Có CRUD, phân quyền, import/export file, nghiệp vụ cập nhật tồn và test quan trọng.

## 1.2 Những phần bỏ khỏi MVP 1 tháng

| **Phần bị lược** | **Lý do** | **Khi nào làm sau** |
| --- | --- | --- |
| RabbitMQ, Redis, DLQ, Outbox | Tăng nhiều công việc hạ tầng, chưa cần cho luồng cốt lõi. | Sau khi REST API và nghiệp vụ ổn định. |
| Notification Service | Không trực tiếp giải quyết kiểm kho hoặc xếp lịch. | Phiên bản 2. |
| AI Service | Có giá trị demo nhưng dễ làm trễ tiến độ. | Thêm AI mapping cột sau MVP. |
| Check-in/check-out, sớm/trễ | Là một domain riêng, cần nhiều rule và test. | Mở rộng Workforce Service. |
| Nghỉ phép, availability, đổi ca | Không bắt buộc để xếp lịch cơ bản. | Phiên bản 2. |
| Workflow duyệt mượn nhiều bước | Quá nhiều trạng thái cho bài toán hiện tại. | Khi có nhiều cửa hàng dùng thật. |
| Multi-tenant, nhiều công ty | Không cần cho dự án cá nhân. | Khi phát triển SaaS. |
| Dashboard phân tích nâng cao | Tốn thời gian UI và truy vấn. | Sau khi MVP chạy ổn. |

# 2. Phạm vi MVP chốt

## 2.1 Hai vai trò

| **Chức năng** | **STORE\_MANAGER** | **STAFF** |
| --- | --- | --- |
| Đăng nhập / đăng xuất | Có | Có |
| Xem danh sách và chi tiết nhân viên | Có | Có |
| Thêm / sửa / xóa mềm nhân viên | Có | Không |
| Xem và xếp lịch làm | Có | Có |
| CRUD sản phẩm / SKU | Có | Có |
| Import tồn kho | Có | Có |
| Nhập và xác nhận sản phẩm đã bán | Có | Có |
| Ghi nhận sản phẩm cho mượn / trả lại | Có | Có |
| Tạo phiếu kiểm kho, xuất CSV, nhập thực tế | Có | Có |
| Xem lịch sử giao dịch kho | Có | Có |

| **Cách làm quyền đơn giản nhưng mở rộng được**   Trong MVP chỉ dùng enum ROLE\_STORE\_MANAGER và ROLE\_STAFF. Backend vẫn kiểm tra quyền bằng Policy/Middleware. Sau này có thể chuyển sang bảng roles/permissions mà không cần viết lại toàn bộ nghiệp vụ. |

## 2.2 Các module bắt buộc

| **Module** | **Chức năng giữ lại** | **Chức năng bỏ** |
| --- | --- | --- |
| Tài khoản & nhân viên | Đăng nhập; refresh phiên; đăng xuất; xem nhân viên; manager CRUD nhân viên. | Role CRUD, permission builder, OAuth/social login. |
| Lịch làm | Bảng tuần; 2 ca/ngày; tối đa 2 người/ca; thêm/sửa/xóa phân công. | Publish/version, nghỉ phép, đổi ca, attendance. |
| Sản phẩm | CRUD sản phẩm và SKU theo size; tìm kiếm. | Danh mục cây, màu sắc phức tạp, giá bán. |
| Tồn kho | Import file mẫu; balance hiện tại; transaction history. | AI mapping, background queue, nhiều kiểu import. |
| Bán hằng ngày | Import/paste sản phẩm bán hôm qua; preview; confirm; cancel. | Đọc API POS, xử lý nhiều nguồn bán. |
| Hàng mượn | Ghi nhận mượn ra; trả lại; ghi chú nơi mượn. | Approve/reject/receive/complete nhiều bước. |
| Kiểm kho | Tạo danh sách cần kiểm; CSV; nhập thực tế; variance. | Submit/approve nhiều cấp, adjustment workflow. |

# 3. Kiến trúc đơn giản nhưng đúng microservice

| React Web App   \|   v   Nginx API Gateway   \|---------------------------\|   v v   People Service Inventory Service   Auth + Employee + Schedule Product + Stock + Sales + Borrow + Count   people\_db inventory\_db |

## 3.1 People Service

- Đăng nhập và phát access JWT cookie.
- Phát refresh token opaque, chỉ lưu hash trong people_db.
- Lưu user, role, employee.
- Quản lý lịch tuần, ca sáng, ca chiều và phân công nhân viên.
- Kiểm tra STORE\_MANAGER khi thêm, sửa hoặc xóa nhân viên.

## 3.2 Inventory Service

- Quản lý sản phẩm, SKU/size và số lượng hiện tại.
- Import file tồn kho theo template cố định.
- Nhập sản phẩm bán hôm qua và trừ kho khi xác nhận.
- Ghi nhận hàng cho mượn và hàng trả lại.
- Tạo phiếu kiểm kho, xuất CSV, nhập số thực tế và tính chênh lệch.

## 3.3 Cách xác thực giữa hai service

People Service phát access JWT ngắn hạn trong cookie HttpOnly `lm_access_token` sau khi đăng nhập. People Service phát refresh token opaque trong cookie HttpOnly `lm_refresh_token` và chỉ lưu hash trong people_db. JWT chứa user\_id và role. Inventory Service kiểm tra access JWT từ cookie bằng cùng secret trong môi trường demo. Frontend không lưu token, không quyết định quyền; backend luôn kiểm tra lại role.

| **Không dùng RabbitMQ trong tháng đầu**   Hai service chưa có luồng bắt buộc phải giao tiếp bất đồng bộ. REST + JWT là đủ để hoàn thành MVP. Đây vẫn là microservice vì hai service có codebase, API và database riêng. |

## 3.4 Cấu trúc repository

| storeops/   frontend/   services/   people-service/   inventory-service/   gateway/   nginx.conf   docs/   docker-compose.yml   README.md |

# 4. OOP bên trong từng service

| app/   Domain/ Quy tắc nghiệp vụ   Application/ Các Use Case   Infrastructure/ Database, đọc CSV/XLSX   Http/ Controller, Request, Resource, Middleware |

| **Phần** | **Hiểu đơn giản** | **Ví dụ** |
| --- | --- | --- |
| Http | Nhận request từ React và trả JSON. | EmployeeController, StoreEmployeeRequest |
| Application | Thực hiện một hành động hoàn chỉnh. | ConfirmDailySalesUseCase |
| Domain | Chứa quy tắc không được vi phạm. | Một ca tối đa 2 người; tồn không âm. |
| Infrastructure | Làm việc với PostgreSQL và file. | EloquentProductRepository, CsvSalesImporter |

## 4.1 Các class quan trọng

| **Service** | **Use Case / Class** |
| --- | --- |
| People | LoginUseCase, CreateEmployeeUseCase, UpdateEmployeeUseCase, DeactivateEmployeeUseCase, AssignEmployeeToShiftUseCase, RemoveShiftAssignmentUseCase, ScheduleValidator |
| Inventory | ImportStockUseCase, ConfirmDailySalesUseCase, CancelDailySalesUseCase, BorrowProductUseCase, ReturnBorrowedProductUseCase, GenerateStockCountUseCase, ExportStockCountCsvUseCase, InventoryBalanceService |

## 4.2 Quy tắc controller mỏng

| React -> Controller -> Use Case -> Domain rule -> Repository -> Database      Controller chỉ:   1\. Nhận dữ liệu đã validate   2\. Tạo DTO   3\. Gọi Use Case   4\. Trả Resource |

# 5. Logic nghiệp vụ cần làm đúng

## 5.1 Tồn kho

| current\_quantity = imported\_quantity   \- confirmed\_sales   \- active\_borrow\_out   \+ returned\_borrow   +/- manual\_correction |

- Không sửa số lượng mà không lưu lý do. Mỗi thay đổi tạo một inventory\_transaction.
- Phiếu bán ở DRAFT chưa trừ kho. Chỉ khi CONFIRMED mới trừ.
- Một phiếu bán của cùng ngày không được confirm hai lần.
- Ghi nhận cho mượn sẽ trừ kho ngay; đánh dấu trả lại sẽ cộng lại đúng số lượng.
- Import tồn kho mới có thể dùng để đồng bộ lại số lượng, nhưng phải preview trước khi xác nhận.

## 5.2 Lịch làm

- Mỗi ngày có đúng hai loại ca: MORNING và AFTERNOON.
- Mỗi ca tối đa hai nhân viên.
- Không được gán cùng một nhân viên hai lần vào cùng ca.
- Một nhân viên có thể làm cả sáng và chiều trong MVP vì hai ca không giao nhau.
- Khi xóa mềm nhân viên, không được gán người đó vào lịch mới; lịch sử cũ vẫn giữ.

## 5.3 Phiếu kiểm kho

- Danh sách mặc định gồm các SKU đã bán hôm qua; có thể thêm SKU đang được mượn để dễ đối chiếu.
- expected\_quantity được chụp lại tại thời điểm tạo phiếu và không tự đổi sau đó.
- variance = actual\_quantity - expected\_quantity.
- MVP chỉ hiển thị chênh lệch, chưa tự động điều chỉnh kho theo variance.

# 6. Mô hình dữ liệu tối giản

## 6.1 people\_db

| **Bảng** | **Mục đích** | **Trường quan trọng** |
| --- | --- | --- |
| users | Tài khoản đăng nhập | id, email, password, role, status |
| refresh_tokens | Refresh token backend | id, user_id, token_hash, expires_at, revoked_at |
| employees | Hồ sơ nhân viên | id, user\_id nullable, code, full\_name, phone, active |
| shift\_assignments | Lịch làm | id, work\_date, shift\_type, employee\_id |

## 6.2 inventory\_db

| **Bảng** | **Mục đích** | **Trường quan trọng** |
| --- | --- | --- |
| products | Sản phẩm | id, product\_code, name, active |
| product\_skus | SKU theo size | id, product\_id, sku\_code, size |
| inventory\_balances | Số lượng hiện tại | sku\_id unique, quantity |
| inventory\_transactions | Lịch sử tăng giảm | type, change, before, after, reference\_id |
| stock\_imports | Lần nhập tồn | file\_name, file\_hash, status, imported\_at |
| daily\_sales | Phiếu bán theo ngày | sales\_date, status, file\_hash |
| daily\_sale\_lines | SKU đã bán | daily\_sale\_id, sku\_id, quantity |
| borrow\_records | Sản phẩm cho mượn | sku\_id, quantity, borrowed\_to, status |
| stock\_counts | Phiên kiểm kho | count\_date, status |
| stock\_count\_lines | Dòng kiểm kho | sku\_id, expected, actual, variance, note |

| **Không tạo quá nhiều bảng**   MVP không cần categories, stores, leave\_requests, attendance\_records, permissions, role\_permissions, notification\_logs hoặc AI tables. |

# 7. Danh sách use case đầy đủ của MVP

| **ID** | **Use case** | **STORE\_MANAGER** | **STAFF** |
| --- | --- | --- | --- |
| UC-01 | Đăng nhập | Có | Có |
| UC-02 | Đăng xuất | Có | Có |
| UC-03 | Xem danh sách/chi tiết nhân viên | Có | Có |
| UC-04 | Thêm nhân viên | Có | Không |
| UC-05 | Sửa nhân viên | Có | Không |
| UC-06 | Xóa mềm nhân viên | Có | Không |
| UC-07 | Xem lịch tuần | Có | Có |
| UC-08 | Gán nhân viên vào ca | Có | Có |
| UC-09 | Xóa nhân viên khỏi ca | Có | Có |
| UC-10 | CRUD sản phẩm và SKU | Có | Có |
| UC-11 | Import tồn kho | Có | Có |
| UC-12 | Xem tồn và lịch sử giao dịch | Có | Có |
| UC-13 | Tạo/import phiếu bán hôm qua | Có | Có |
| UC-14 | Xác nhận phiếu bán và trừ tồn | Có | Có |
| UC-15 | Hủy phiếu bán đã xác nhận | Có | Có |
| UC-16 | Ghi nhận sản phẩm cho mượn | Có | Có |
| UC-17 | Ghi nhận sản phẩm đã trả | Có | Có |
| UC-18 | Tạo phiên kiểm kho | Có | Có |
| UC-19 | Xuất CSV kiểm kho | Có | Có |
| UC-20 | Nhập số lượng thực tế và xem chênh lệch | Có | Có |

### UC-01 - Đăng nhập

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER, STAFF |
| Quyền | Cả hai vai trò |
| Điều kiện trước | Tài khoản đang hoạt động. |
| Kết quả | Người dùng có phiên đăng nhập hợp lệ. |

**Luồng chính**

1\. Người dùng nhập email và mật khẩu.

2\. People Service kiểm tra thông tin.

3\. Nếu đúng, People Service set cookie `lm_access_token` chứa access JWT và cookie `lm_refresh_token` chứa refresh token opaque.

4\. People Service lưu hash refresh token trong database.

5\. React lưu user/session state không nhạy cảm và chuyển vào trang chính.

**Trường hợp lỗi / ngoại lệ**

- Sai mật khẩu: trả 401.
- Tài khoản bị khóa: trả 403.

### UC-02 - Đăng xuất

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER, STAFF |
| Quyền | Cả hai vai trò |
| Điều kiện trước | Đang đăng nhập. |
| Kết quả | Phiên phía trình duyệt kết thúc. |

**Luồng chính**

1\. Người dùng bấm Đăng xuất.

2\. React gọi backend logout.

3\. People Service revoke refresh token hash và clear auth cookies.

4\. React xóa user/session state và chuyển về trang đăng nhập.

### UC-03 - Xem nhân viên

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER, STAFF |
| Quyền | Cả hai vai trò |
| Điều kiện trước | Đã đăng nhập. |
| Kết quả | Danh sách và chi tiết được hiển thị. |

**Luồng chính**

1\. Mở trang Nhân viên.

2\. People Service trả danh sách nhân viên đang hoạt động và đã ngừng.

3\. Người dùng tìm theo mã hoặc tên và mở chi tiết.

### UC-04 - Thêm nhân viên

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER |
| Quyền | Chỉ STORE\_MANAGER |
| Điều kiện trước | Đã đăng nhập với role STORE\_MANAGER. |
| Kết quả | Nhân viên mới được lưu. |

**Luồng chính**

1\. Manager nhập mã, họ tên, điện thoại và trạng thái.

2\. Backend validate mã không trùng.

3\. CreateEmployeeUseCase tạo nhân viên.

4\. Trả nhân viên mới cho React.

**Trường hợp lỗi / ngoại lệ**

- STAFF gọi API: trả 403.
- Mã trùng: trả 409/422.

### UC-05 - Sửa nhân viên

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER |
| Quyền | Chỉ STORE\_MANAGER |
| Điều kiện trước | Nhân viên tồn tại. |
| Kết quả | Thông tin nhân viên được cập nhật. |

**Luồng chính**

1\. Manager mở hồ sơ và chỉnh sửa.

2\. Backend kiểm tra quyền và dữ liệu.

3\. UpdateEmployeeUseCase lưu thay đổi.

**Trường hợp lỗi / ngoại lệ**

- STAFF gọi API: 403.
- Không tìm thấy nhân viên: 404.

### UC-06 - Xóa mềm nhân viên

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER |
| Quyền | Chỉ STORE\_MANAGER |
| Điều kiện trước | Nhân viên tồn tại. |
| Kết quả | Nhân viên không còn được chọn cho lịch mới. |

**Luồng chính**

1\. Manager bấm Ngừng hoạt động.

2\. DeactivateEmployeeUseCase đặt active=false.

3\. Lịch sử cũ vẫn được giữ.

**Trường hợp lỗi / ngoại lệ**

- Nhân viên đã ngừng: không xử lý lặp.

### UC-07 - Xem lịch tuần

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER, STAFF |
| Quyền | Cả hai vai trò |
| Điều kiện trước | Đã đăng nhập. |
| Kết quả | Lịch tuần được hiển thị. |

**Luồng chính**

1\. Chọn tuần cần xem.

2\. People Service trả các phân công theo ngày và ca.

3\. React hiển thị bảng 7 ngày x 2 ca.

### UC-08 - Gán nhân viên vào ca

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER, STAFF |
| Quyền | Cả hai vai trò |
| Điều kiện trước | Nhân viên đang hoạt động; ca còn chỗ. |
| Kết quả | Phân công mới xuất hiện trên lịch. |

**Luồng chính**

1\. Chọn ngày, ca MORNING/AFTERNOON và nhân viên.

2\. ScheduleValidator kiểm tra ca chưa đủ 2 người và chưa gán trùng.

3\. AssignEmployeeToShiftUseCase lưu phân công.

**Trường hợp lỗi / ngoại lệ**

- Ca đã đủ 2 người: 409.
- Nhân viên đã có trong ca: 409.
- Nhân viên inactive: 422.

### UC-09 - Xóa nhân viên khỏi ca

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER, STAFF |
| Quyền | Cả hai vai trò |
| Điều kiện trước | Phân công tồn tại. |
| Kết quả | Nhân viên được gỡ khỏi ca. |

**Luồng chính**

1\. Người dùng chọn phân công cần xóa.

2\. RemoveShiftAssignmentUseCase xóa bản ghi.

3\. React cập nhật lịch.

**Trường hợp lỗi / ngoại lệ**

- Không tìm thấy phân công: 404.

### UC-10 - CRUD sản phẩm và SKU

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER, STAFF |
| Quyền | Cả hai vai trò |
| Điều kiện trước | Đã đăng nhập. |
| Kết quả | Dữ liệu sản phẩm sẵn sàng cho import và bán hàng. |

**Luồng chính**

1\. Tạo hoặc chọn sản phẩm.

2\. Nhập product\_code, tên sản phẩm.

3\. Thêm SKU với sku\_code và size.

4\. Có thể sửa hoặc xóa mềm sản phẩm/SKU.

**Trường hợp lỗi / ngoại lệ**

- Mã sản phẩm hoặc SKU trùng: 409.
- SKU có lịch sử giao dịch: chỉ xóa mềm.

### UC-11 - Import tồn kho

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER, STAFF |
| Quyền | Cả hai vai trò |
| Điều kiện trước | File CSV/XLSX đúng template. |
| Kết quả | Tồn kho được đồng bộ với file. |

**Luồng chính**

1\. Upload file gồm sku\_code và quantity.

2\. ImportStockUseCase đọc file và hiển thị preview.

3\. Người dùng sửa lỗi file nếu có.

4\. Bấm Xác nhận.

5\. Hệ thống cập nhật inventory\_balances và tạo transaction IMPORT\_SYNC.

**Trường hợp lỗi / ngoại lệ**

- SKU không tồn tại: báo lỗi theo dòng.
- Quantity âm/không phải số: báo lỗi.
- File hash trùng: cảnh báo và chặn xác nhận.

### UC-12 - Xem tồn và lịch sử giao dịch

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER, STAFF |
| Quyền | Cả hai vai trò |
| Điều kiện trước | Đã có sản phẩm. |
| Kết quả | Người dùng biết số lượng hiện tại và lý do thay đổi. |

**Luồng chính**

1\. Tìm theo SKU, mã hoặc tên.

2\. Inventory Service trả quantity hiện tại.

3\. Mở chi tiết để xem các transaction IMPORT\_SYNC, SALE, BORROW\_OUT, BORROW\_RETURN, REVERSAL.

### UC-13 - Tạo/import phiếu bán hôm qua

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER, STAFF |
| Quyền | Cả hai vai trò |
| Điều kiện trước | Đã có SKU và tồn kho. |
| Kết quả | Phiếu bán nháp được tạo, chưa ảnh hưởng tồn. |

**Luồng chính**

1\. Chọn sales\_date.

2\. Upload file hoặc dán các dòng sku\_code, quantity.

3\. Hệ thống gom các dòng cùng SKU.

4\. Hiển thị preview: tồn trước, số bán, tồn sau.

5\. Lưu phiếu ở trạng thái DRAFT.

**Trường hợp lỗi / ngoại lệ**

- SKU không tồn tại: báo lỗi.
- Quantity <= 0: báo lỗi.
- Đã có phiếu confirmed cùng ngày: chặn tạo mới.

### UC-14 - Xác nhận phiếu bán và trừ tồn

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER, STAFF |
| Quyền | Cả hai vai trò |
| Điều kiện trước | Phiếu ở DRAFT. |
| Kết quả | Tồn hiện tại giảm đúng một lần. |

**Luồng chính**

1\. Người dùng kiểm tra preview và bấm Xác nhận.

2\. ConfirmDailySalesUseCase khóa các dòng tồn liên quan.

3\. Kiểm tra từng SKU đủ số lượng.

4\. Tạo transaction SALE và giảm balance trong một DB transaction.

5\. Đặt phiếu thành CONFIRMED.

**Trường hợp lỗi / ngoại lệ**

- Số bán lớn hơn tồn: rollback toàn bộ và báo lỗi.
- Confirm lại phiếu cũ: không trừ lần hai.

### UC-15 - Hủy phiếu bán đã xác nhận

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER, STAFF |
| Quyền | Cả hai vai trò |
| Điều kiện trước | Phiếu đang CONFIRMED. |
| Kết quả | Tồn được hoàn lại nhưng lịch sử vẫn còn. |

**Luồng chính**

1\. Người dùng bấm Hủy và nhập lý do.

2\. CancelDailySalesUseCase tạo transaction REVERSAL cho từng dòng.

3\. Cộng lại tồn và đặt trạng thái CANCELLED.

**Trường hợp lỗi / ngoại lệ**

- Phiếu đã hủy: không xử lý lặp.

### UC-16 - Ghi nhận sản phẩm cho mượn

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER, STAFF |
| Quyền | Cả hai vai trò |
| Điều kiện trước | SKU có đủ tồn. |
| Kết quả | Tồn vật lý dự kiến giảm và có ghi chú rõ. |

**Luồng chính**

1\. Chọn SKU, số lượng, nơi mượn và ngày mượn.

2\. BorrowProductUseCase kiểm tra tồn.

3\. Tạo borrow\_record trạng thái ACTIVE.

4\. Tạo BORROW\_OUT transaction và giảm balance.

**Trường hợp lỗi / ngoại lệ**

- Số lượng mượn lớn hơn tồn: rollback và báo lỗi.

### UC-17 - Ghi nhận sản phẩm đã trả

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER, STAFF |
| Quyền | Cả hai vai trò |
| Điều kiện trước | Borrow record đang ACTIVE. |
| Kết quả | Hàng trở lại tồn hiện tại. |

**Luồng chính**

1\. Mở danh sách đang mượn.

2\. Chọn bản ghi và bấm Đã trả.

3\. ReturnBorrowedProductUseCase tạo BORROW\_RETURN transaction.

4\. Cộng tồn và chuyển trạng thái RETURNED.

**Trường hợp lỗi / ngoại lệ**

- Đã trả trước đó: không cộng lần hai.

### UC-18 - Tạo phiên kiểm kho

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER, STAFF |
| Quyền | Cả hai vai trò |
| Điều kiện trước | Đã xác nhận bán hôm qua hoặc có dữ liệu kho. |
| Kết quả | Phiên kiểm kho cố định được tạo. |

**Luồng chính**

1\. Chọn ngày kiểm.

2\. GenerateStockCountUseCase lấy các SKU đã bán ngày trước và SKU đang có borrow record ACTIVE.

3\. Lấy current\_quantity làm expected\_quantity.

4\. Lưu snapshot vào stock\_count\_lines.

**Trường hợp lỗi / ngoại lệ**

- Đã có phiên cùng ngày: mở phiên cũ thay vì tạo trùng.

### UC-19 - Xuất CSV kiểm kho

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER, STAFF |
| Quyền | Cả hai vai trò |
| Điều kiện trước | Phiên kiểm kho tồn tại. |
| Kết quả | Người dùng có một file duy nhất để đi kiểm. |

**Luồng chính**

1\. Mở phiên kiểm kho.

2\. Bấm Xuất CSV.

3\. ExportStockCountCsvUseCase tạo file gồm sku\_code, product\_name, size, expected, actual, variance, note.

### UC-20 - Nhập thực tế và xem chênh lệch

| **Mục** | **Nội dung** |
| --- | --- |
| Người thực hiện | STORE\_MANAGER, STAFF |
| Quyền | Cả hai vai trò |
| Điều kiện trước | Phiên kiểm kho đang OPEN. |
| Kết quả | Có kết quả chênh lệch rõ ràng cho từng SKU. |

**Luồng chính**

1\. Nhập actual\_quantity trên web cho từng SKU.

2\. Hệ thống tính variance = actual - expected.

3\. Lưu ghi chú nếu có lệch.

4\. Đánh dấu phiên COMPLETED khi nhập xong.

**Trường hợp lỗi / ngoại lệ**

- Actual âm hoặc không phải số: 422.
- Phiên COMPLETED: không sửa trừ khi mở lại thủ công.

# 8. API tối thiểu

| **Service** | **Method** | **Endpoint** | **Mục đích** |
| --- | --- | --- | --- |
| People | POST | /api/v1/auth/login | Đăng nhập |
| People | GET | /api/v1/employees | Danh sách nhân viên |
| People | POST | /api/v1/employees | Tạo nhân viên - manager |
| People | PUT | /api/v1/employees/{id} | Sửa nhân viên - manager |
| People | DELETE | /api/v1/employees/{id} | Xóa mềm - manager |
| People | GET | /api/v1/schedule?week= | Xem lịch tuần |
| People | POST | /api/v1/shift-assignments | Gán ca |
| People | DELETE | /api/v1/shift-assignments/{id} | Gỡ ca |
| Inventory | GET/POST | /api/v1/products | Danh sách/tạo sản phẩm |
| Inventory | PUT/DELETE | /api/v1/products/{id} | Sửa/xóa mềm sản phẩm |
| Inventory | POST | /api/v1/stock-imports | Upload và preview tồn |
| Inventory | POST | /api/v1/stock-imports/{id}/confirm | Xác nhận đồng bộ tồn |
| Inventory | GET | /api/v1/inventory | Xem tồn |
| Inventory | GET | /api/v1/inventory/{skuId}/transactions | Lịch sử giao dịch |
| Inventory | POST | /api/v1/daily-sales | Tạo phiếu bán |
| Inventory | POST | /api/v1/daily-sales/{id}/confirm | Xác nhận và trừ tồn |
| Inventory | POST | /api/v1/daily-sales/{id}/cancel | Hủy và hoàn tồn |
| Inventory | POST | /api/v1/borrow-records | Cho mượn |
| Inventory | POST | /api/v1/borrow-records/{id}/return | Trả hàng |
| Inventory | POST | /api/v1/stock-counts | Tạo phiên kiểm |
| Inventory | GET | /api/v1/stock-counts/{id}/export | Xuất CSV |
| Inventory | PUT | /api/v1/stock-counts/{id}/lines | Nhập thực tế |

# 9. Các trang React cần làm

| **Route** | **Màn hình** | **Mức độ** |
| --- | --- | --- |
| /login | Đăng nhập | Bắt buộc |
| /dashboard | Lịch hôm nay + các nút thao tác nhanh | Đơn giản |
| /employees | Danh sách nhân viên | Bắt buộc |
| /employees/new | Form thêm nhân viên; chỉ manager thấy | Bắt buộc |
| /employees/:id | Chi tiết/sửa nhân viên | Bắt buộc |
| /schedule | Bảng tuần 7 ngày, 2 ca/ngày | Bắt buộc |
| /products | Danh sách sản phẩm/SKU | Bắt buộc |
| /inventory | Tồn hiện tại + tìm kiếm | Bắt buộc |
| /inventory/import | Upload, preview, confirm file tồn | Bắt buộc |
| /daily-sales | Danh sách phiếu bán | Bắt buộc |
| /daily-sales/new | Upload/paste sản phẩm bán | Bắt buộc |
| /borrowed | Danh sách đang mượn và đã trả | Bắt buộc |
| /stock-counts | Danh sách phiên kiểm | Bắt buộc |
| /stock-counts/:id | Nhập actual, variance, export CSV | Bắt buộc |

| **UI lịch làm**   Dùng bảng tuần và dropdown chọn nhân viên. Không làm drag-and-drop trong tháng đầu vì dễ tốn nhiều thời gian mà không tăng giá trị nghiệp vụ. |

# 10. Kế hoạch thực hiện 1 tháng

## Tuần 1 - Nền tảng, auth và nhân viên

| **Ngày** | **Việc cần làm** | **Kết quả phải có** |
| --- | --- | --- |
| 1 | Chốt scope, tạo monorepo, Docker Compose, Nginx, 2 Laravel service, React. | Chạy được 3 app và 2 database. |
| 2 | Thiết kế migrations people\_db; seed 2 role và tài khoản demo. | Có users, employees, shift\_assignments. |
| 3 | LoginUseCase, JWT middleware, React login. | Đăng nhập được bằng manager/staff. |
| 4 | CRUD Employee backend + Policy manager-only. | API CRUD đúng quyền. |
| 5 | React employee pages + test quyền. | Manager CRUD được; staff chỉ xem. |

## Tuần 2 - Lịch làm và sản phẩm/tồn kho nền tảng

| **Ngày** | **Việc cần làm** | **Kết quả phải có** |
| --- | --- | --- |
| 6 | Schedule domain, validator, API xem tuần. | Có lịch 7 ngày x 2 ca. |
| 7 | API gán/gỡ nhân viên; rule tối đa 2 người. | Không gán trùng hoặc quá 2. |
| 8 | React schedule table. | Xếp lịch được trên website. |
| 9 | Product/SKU CRUD backend. | Có product\_code, sku\_code, size. |
| 10 | React products + inventory list. | Xem/tìm sản phẩm và tồn. |

## Tuần 3 - Import tồn, bán hôm qua và hàng mượn

| **Ngày** | **Việc cần làm** | **Kết quả phải có** |
| --- | --- | --- |
| 11 | ImportStockUseCase đọc template cố định. | Upload và preview được. |
| 12 | Confirm import + inventory transaction. | Balance cập nhật atomic. |
| 13 | Daily sales draft + preview. | Gom SKU và xem tồn trước/sau. |
| 14 | Confirm/cancel daily sales + tests. | Trừ tồn một lần; hủy hoàn tồn. |
| 15 | Borrow/return use cases + UI. | Mượn trừ tồn, trả cộng lại. |

## Tuần 4 - Kiểm kho, test và demo

| **Ngày** | **Việc cần làm** | **Kết quả phải có** |
| --- | --- | --- |
| 16 | GenerateStockCountUseCase. | Tạo snapshot expected. |
| 17 | CSV export + nhập actual/variance. | Luồng kiểm kho hoàn chỉnh. |
| 18 | Test domain và API golden path. | Test các rule tồn, sales, borrow, schedule. |
| 19 | Swagger/OpenAPI, README, seed demo, Docker cleanup. | Reviewer chạy được bằng một lệnh. |
| 20 | Chạy demo end-to-end, sửa lỗi, quay video ngắn. | Bản portfolio hoàn chỉnh. |

# 11. Test tối thiểu phải có

| **Nhóm** | **Test bắt buộc** |
| --- | --- |
| Quyền | STAFF không thể create/update/delete employee; STORE\_MANAGER có thể. |
| Lịch | Không quá 2 người/ca; không gán trùng cùng nhân viên trong một ca. |
| Import | File sai cột bị từ chối; file hash trùng bị chặn; confirm cập nhật đủ tất cả dòng. |
| Bán hàng | DRAFT không trừ; CONFIRMED trừ một lần; thiếu tồn rollback; CANCELLED hoàn tồn. |
| Mượn | Mượn giảm tồn; trả tăng tồn; không trả hai lần. |
| Kiểm kho | Expected snapshot không đổi; variance tính đúng. |

## 11.1 Luồng chuẩn để demo

1\. Đăng nhập bằng STORE\_MANAGER và tạo 4 nhân viên.

2\. Xếp 2 người ca sáng, 2 người ca chiều; thử gán người thứ 3 và nhận lỗi.

3\. Tạo sản phẩm và SKU theo size.

4\. Import file tồn kho ban đầu.

5\. Import sản phẩm bán hôm qua và xác nhận để trừ tồn.

6\. Ghi nhận một SKU đang cho cửa hàng khác mượn.

7\. Tạo phiên kiểm kho và xuất CSV.

8\. Nhập số lượng thực tế và xem variance.

9\. Đăng nhập bằng STAFF; chứng minh không thể sửa nhân viên nhưng vẫn làm các thao tác kho/lịch.

# 12. Khi nào dự án được xem là hoàn thành?

- Docker Compose dựng được frontend, gateway, 2 service và 2 database trên máy mới.
- 20 use case trong tài liệu chạy được theo golden path.
- Backend kiểm tra quyền, không chỉ ẩn nút trên React.
- Mọi thay đổi tồn đều có inventory\_transaction.
- Không có lỗi trừ kho hai lần hoặc trả hàng hai lần.
- Có ít nhất 12-15 test backend tập trung vào rule quan trọng.
- Có README hướng dẫn chạy, tài khoản demo, file mẫu và kịch bản demo.
- Có Swagger hoặc collection API để nhà tuyển dụng xem nhanh.

| **Ưu tiên cuối cùng**   Một luồng kiểm kho hoàn chỉnh và đáng tin cậy quan trọng hơn việc có AI, RabbitMQ hoặc giao diện quá đẹp. Khi MVP đã ổn, AI Excel Mapper là tính năng mở rộng hợp lý nhất. |

# 13. Thứ tự bắt đầu ngay ngày đầu tiên

1\. Tạo repository theo cấu trúc ở mục 3.4.

2\. Tạo docker-compose với React, Nginx, people-service, inventory-service và 2 PostgreSQL database.

3\. Viết migrations tối giản đúng mục 6, chưa thêm bảng ngoài scope.

4\. Seed hai role cố định và hai tài khoản demo.

5\. Viết UC-01 đến UC-06 trước, sau đó mới làm lịch và kho.

6\. Mỗi use case hoàn thành phải có API, UI cơ bản và ít nhất một test.

7\. Không mở thêm feature mới trong 4 tuần; ghi vào backlog tương lai thay vì code ngay.

# 14. Backlog tương lai sau MVP

| **Ưu tiên** | **Tính năng mở rộng** |
| --- | --- |
| P2 | AI gợi ý mapping cột Excel; notification lịch làm; attendance/check-in-out. |
| P2 | Nghỉ phép, availability, publish lịch và version lịch. |
| P3 | RabbitMQ, Notification Service, audit log đầy đủ. |
| P3 | Nhiều cửa hàng thật, workflow mượn approve/receive/return. |
| P3 | Role/permission động thay cho hai role cố định. |
