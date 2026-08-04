# Daily Sales UC-13-15 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Xây luồng Daily Sales UC-13/14/15: upload CSV bán hàng hôm qua, lưu preview DRAFT, confirm để trừ tồn đúng một lần, cancel để hoàn tồn bằng giao dịch đảo.

**Architecture:** Inventory Service sở hữu toàn bộ dữ liệu Daily Sales trong `inventory_db`. Controller chỉ validate request, gọi use case và trả resource; workflow nằm trong Application Use Cases; mọi thay đổi tồn kho bắt buộc đi qua `InventoryBalanceService` trong DB transaction. Frontend dùng một trang danh sách `/daily-sales` và một trang tạo `/daily-sales/new`, cùng pattern API client hiện có.

**Tech Stack:** PHP 8.3/8.4 runtime hiện có, Laravel 13, PostgreSQL, PHPUnit feature tests, React + TypeScript + Vite, Docker Compose.

## Global Constraints

- Tất cả text tiếng Việt trong code response, docs, backend messages và frontend text phải có dấu đầy đủ.
- Không thêm dependency mới.
- CSV MVP dùng đúng header `sku_code,quantity_sold`.
- Chuẩn hóa `sku_code` bằng `trim` + `uppercase`.
- `quantity_sold` phải là số nguyên `> 0`.
- `sales_date` phải có dạng `YYYY-MM-DD` và không được sau ngày hiện tại.
- Parser phải hỗ trợ UTF-8 BOM, bỏ qua dòng trống hoàn toàn, báo lỗi file không có dòng dữ liệu, và giới hạn tối đa 5000 dòng dữ liệu.
- Daily Sales theo plan này dùng CSV upload, không làm paste text trong UC-13/14/15.
- Hệ thống gom các dòng cùng SKU trong một file thành một dòng preview.
- Preview DRAFT không ảnh hưởng tồn kho.
- Preview lưu snapshot bằng `preview_quantity_before` và `preview_quantity_after`; ledger mới là nguồn sự thật tồn kho thực tế lúc confirm/cancel.
- Confirm trừ tồn đúng một lần bằng transaction `SALE`.
- Cancel cộng trả tồn đúng một lần bằng transaction `SALE_REVERSAL`.
- Confirm và Cancel phải query lại SKU và balance tại thời điểm thực thi, không tin relation hoặc snapshot từ DRAFT.
- Mọi thay đổi balance phải đi qua `InventoryBalanceService`.
- Mọi thay đổi balance phải tạo `inventory_transactions`.
- Mọi thay đổi balance phải nằm trong DB transaction.
- Không gọi thẳng database service khác.
- API ghi dữ liệu dùng cookie auth và CSRF hiện có. GET không yêu cầu CSRF; mutation `POST`, `PUT`, `DELETE` bắt buộc CSRF token.
- Cả `STORE_MANAGER` và `STAFF` được thao tác Daily Sales trong MVP.
- Frontend/UI task phải dùng taste/design skill trước khi sửa UI code.
- UI LazyManager giữ vibe "Cozy" và "Lazy": thân thiện, bình tĩnh, dễ thao tác, ít ma sát, trạng thái rõ.

---

## Current Project State

Đã có nền inventory:

- Model: `Product`, `ProductSku`, `InventoryBalance`, `InventoryTransaction`.
- Enum: `InventoryTransactionType` đã có `Sale = 'SALE'` và `SaleReversal = 'SALE_REVERSAL'`.
- Service: `InventoryBalanceService::decrease()` và `increase()` đều dùng DB transaction, `lockForUpdate()` và ghi ledger.
- Exception: `InventoryBusinessException` hỗ trợ status tùy chỉnh; `InsufficientStockException` dùng khi tồn âm.
- API hiện có: Product/SKU CRUD, inventory list, transaction history, Stock Import preview/confirm.
- Frontend hiện có `/products`, `/inventory`, `/inventory/import`, API client cookie auth + CSRF.
- `docs/api.md` đang đánh dấu Daily Sales endpoints là future.

Nguồn sự thật:

- `docs/StoreOps_MVP_1_Thang_v2.0.md`: UC-13/14/15 Daily Sales.
- `docs/TaskImplementDetailPlan/StockImportUC-11plan.md`: pattern preview/confirm CSV.
- `docs/api.md`: route public gateway `/api/inventory/v1/*`.
- `docs/progress_report_2.8.26.md`: tiến trình hiện tại sau UC-11.

---

## Scope

In scope:

- Upload CSV bằng `multipart/form-data` field `file`.
- Request có `sales_date` dạng `YYYY-MM-DD`.
- CSV header cố định: `sku_code,quantity_sold`.
- Parse preview, gom dòng cùng SKU trong file, lưu raw input tóm tắt theo dòng đầu tiên và tổng quantity.
- Lưu `daily_sales` trạng thái `DRAFT`.
- Lưu `daily_sale_lines` gồm `sku_code`, `sku_id`, `quantity_sold`, `preview_quantity_before`, `preview_quantity_after`, `error_message`.
- Báo lỗi khi SKU không tồn tại, inactive, soft-deleted, quantity không phải số nguyên, quantity <= 0.
- Chặn tạo/confirm nếu đã có phiếu `CONFIRMED` cùng `sales_date`.
- Tính `file_hash = hash_file('sha256', uploaded file path)` và lưu để audit; không chặn duplicate file trong Daily Sales.
- Confirm chỉ chạy khi phiếu `DRAFT` và không có dòng lỗi.
- Confirm trừ tồn bằng `InventoryBalanceService::decrease(... Sale ...)`.
- Nếu bất kỳ SKU không đủ tồn lúc confirm, rollback toàn bộ.
- Cancel chỉ chạy khi phiếu `CONFIRMED`.
- Cancel cộng trả tồn bằng `InventoryBalanceService::increase(... SaleReversal ...)`.
- Cancel lưu `cancel_reason`, `cancelled_at`, `cancelled_by`.
- React `/daily-sales`: danh sách phiếu bán, xem trạng thái.
- React `/daily-sales/new`: chọn ngày, upload CSV, preview, confirm.

Out of scope:

- Paste text.
- XLSX parser.
- POS integration.
- Doanh thu, giá bán, tiền, hóa đơn.
- Background queue.
- Multi-store.
- Daily Sales edit after draft created.
- Borrow UC-16/17.
- Stock Count UC-18/19/20.

---

## API Target

Gateway public routes:

```text
GET  /api/inventory/v1/daily-sales?date_from=&date_to=&status=
POST /api/inventory/v1/daily-sales
GET  /api/inventory/v1/daily-sales/{id}
POST /api/inventory/v1/daily-sales/{id}/confirm
POST /api/inventory/v1/daily-sales/{id}/cancel
```

Service internal routes:

```text
GET  /api/v1/daily-sales
POST /api/v1/daily-sales
GET  /api/v1/daily-sales/{dailySale}
POST /api/v1/daily-sales/{dailySale}/confirm
POST /api/v1/daily-sales/{dailySale}/cancel
```

Upload request:

```text
Content-Type: multipart/form-data
sales_date: 2026-08-03
file: daily-sales.csv
```

CSV template:

```csv
sku_code,quantity_sold
AO-THUN-M,2
AO-THUN-L,1
AO-THUN-M,3
```

Preview response:

```json
{
  "daily_sale": {
    "id": 1,
    "sales_date": "2026-08-03",
    "file_name": "daily-sales.csv",
    "status": "DRAFT",
    "has_errors": false,
    "confirmed_at": null,
    "cancelled_at": null,
    "cancel_reason": null,
    "created_by": 10,
    "confirmed_by": null,
    "cancelled_by": null,
    "created_at": "2026-08-04T10:00:00.000000Z",
    "lines": [
      {
        "id": 1,
        "row_number": 2,
        "raw_sku_code": "AO-THUN-M",
        "raw_quantity_sold": "2",
        "sku_code": "AO-THUN-M",
        "sku_id": 1,
        "quantity_sold": 5,
        "preview_quantity_before": 10,
        "preview_quantity_after": 5,
        "error_message": null
      }
    ]
  }
}
```

Confirmed same day response:

```json
{
  "message": "Ngày bán này đã có phiếu được xác nhận."
}
```

Confirm with errors response:

```json
{
  "message": "Không thể xác nhận phiếu bán còn dòng lỗi."
}
```

Cancel response:

```json
{
  "daily_sale": {
    "status": "CANCELLED"
  }
}
```

---

## Database Design

### `daily_sales`

```text
id bigint primary key
sales_date date not null
confirmed_sales_date date nullable unique
file_name varchar(255) not null
file_hash varchar(64) not null
status varchar(32) not null default DRAFT
confirmed_at timestamp nullable
cancelled_at timestamp nullable
cancel_reason varchar(255) nullable
created_by bigint nullable
confirmed_by bigint nullable
cancelled_by bigint nullable
created_at timestamp nullable
updated_at timestamp nullable
index(sales_date)
index(status)
index(created_at)
```

`confirmed_sales_date` là deliberate simplification:

- Khi DRAFT: `null`.
- Khi CONFIRMED: bằng `sales_date`, unique DB constraint chặn race hai confirm cùng ngày.
- Khi CANCELLED: set lại `null`, cho phép tạo/confirm phiếu mới cùng ngày nếu cần.
- Không cần PostgreSQL partial unique index, nên test SQLite đơn giản hơn.

### `daily_sale_lines`

```text
id bigint primary key
daily_sale_id bigint not null foreign key daily_sales.id cascade delete
row_number integer not null
sku_code varchar(64) not null
sku_id bigint nullable foreign key product_skus.id restrict on delete
raw_sku_code varchar(255) nullable
raw_quantity_sold varchar(255) nullable
quantity_sold integer nullable
preview_quantity_before integer nullable
preview_quantity_after integer nullable
error_message varchar(255) nullable
created_at timestamp nullable
updated_at timestamp nullable
unique(daily_sale_id, row_number)
index(daily_sale_id)
index(sku_id)
```

Deliberate simplifications:

- Không lưu file gốc. Preview lines đủ audit cho MVP.
- Duplicate SKU được gom theo yêu cầu UC-13, không báo lỗi như Stock Import.
- Với duplicate SKU, `row_number`, `raw_sku_code`, `raw_quantity_sold` lưu giá trị dòng hợp lệ đầu tiên; `quantity_sold` là tổng.
- Preview snapshot không tự cập nhật nếu tồn kho thay đổi trước confirm; confirm dùng ledger thực tế để trừ tồn và rollback nếu thiếu.

---

## File Map

Create:

```text
services/inventory-service/database/migrations/2026_08_04_000300_create_daily_sales_tables.php
services/inventory-service/app/Domain/Enums/DailySaleStatus.php
services/inventory-service/app/Models/DailySale.php
services/inventory-service/app/Models/DailySaleLine.php
services/inventory-service/app/Application/DTOs/CreateDailySaleData.php
services/inventory-service/app/Application/UseCases/CreateDailySaleUseCase.php
services/inventory-service/app/Application/UseCases/ListDailySalesUseCase.php
services/inventory-service/app/Application/UseCases/GetDailySaleUseCase.php
services/inventory-service/app/Application/UseCases/ConfirmDailySaleUseCase.php
services/inventory-service/app/Application/UseCases/CancelDailySaleUseCase.php
services/inventory-service/app/Infrastructure/CsvDailySaleParser.php
services/inventory-service/app/Http/Controllers/DailySaleController.php
services/inventory-service/app/Http/Requests/StoreDailySaleRequest.php
services/inventory-service/app/Http/Requests/CancelDailySaleRequest.php
services/inventory-service/app/Http/Resources/DailySaleSummaryResource.php
services/inventory-service/app/Http/Resources/DailySaleResource.php
services/inventory-service/app/Http/Resources/DailySaleLineResource.php
services/inventory-service/tests/Feature/DailySaleApiTest.php
frontend/src/features/inventory/api/dailySalesApi.ts
frontend/src/features/inventory/components/DailySalePreviewTable.tsx
frontend/src/features/inventory/pages/DailySalesPage.tsx
frontend/src/features/inventory/pages/NewDailySalePage.tsx
frontend/src/dailySalesExperience.test.mjs
```

Modify:

```text
services/inventory-service/routes/api.php
frontend/src/App.tsx
frontend/src/features/health/pages/DashboardPage.tsx
frontend/src/features/inventory/pages/InventoryPage.tsx
frontend/src/features/inventory/pages/ProductsPage.tsx
frontend/src/App.css
docs/api.md
docs/progress_report_2.8.26.md
README.md
```

No repository interfaces:

- Existing Inventory Service uses Eloquent directly in use cases.
- Daily Sales is single-service persistence.
- Add repository only when later reports need shared complex query objects.

---

## Domain Flow

### Create Draft

```text
Client POST /daily-sales
  -> auth.jwt + csrf.double_submit
  -> StoreDailySaleRequest validates sales_date + CSV file
  -> CreateDailySaleUseCase
  -> CsvDailySaleParser validates header and rows
  -> Check no CONFIRMED daily sale for sales_date
  -> daily_sales row with DRAFT
  -> daily_sale_lines rows with grouped SKU totals and error_message per grouped row
  -> DailySaleResource
  -> JSON preview
```

### Confirm

```text
Client POST /daily-sales/{id}/confirm
  -> auth.jwt + csrf.double_submit
  -> ConfirmDailySaleUseCase
  -> DB transaction locks daily_sales row
  -> Block if status != DRAFT
  -> Block if another CONFIRMED row exists for sales_date
  -> Set confirmed_sales_date = sales_date; unique DB constraint handles race
  -> Block if any line error exists
  -> For each line, query ProductSku fresh by `sku_id` without trusting loaded relation.
If SKU is missing, inactive or soft-deleted, throw 409 "SKU {sku_code} đã ngừng hoạt động và không thể xác nhận.".
If balance is missing, throw 422 "Không tìm thấy dữ liệu tồn kho của SKU.".
For each line call InventoryBalanceService::decrease(... SALE ...)
  -> If any line insufficient stock, rollback all
  -> Set status CONFIRMED, confirmed_at, confirmed_by
  -> DailySaleResource
```

### Cancel

```text
Client POST /daily-sales/{id}/cancel
  -> auth.jwt + csrf.double_submit
  -> CancelDailySaleUseCase
  -> DB transaction locks daily_sales row
  -> Block if status != CONFIRMED
  -> For each line, query ProductSku fresh by `sku_id` without trusting loaded relation.
If SKU is missing, inactive or soft-deleted, throw 409 "SKU {sku_code} đã ngừng hoạt động và không thể hủy.".
If balance is missing, throw 422 "Không tìm thấy dữ liệu tồn kho của SKU.".
For each line call InventoryBalanceService::increase(... SALE_REVERSAL ...)
  -> Set status CANCELLED, cancelled_at, cancelled_by, cancel_reason
  -> Set confirmed_sales_date = null
  -> DailySaleResource
```

---

## Task 0: Verify Existing Inventory Infrastructure

**Files:**

- Inspect: `services/inventory-service/app/Domain/Enums/InventoryTransactionType.php`
- Inspect: `services/inventory-service/app/Domain/Services/InventoryBalanceService.php`
- Inspect: `services/inventory-service/app/Domain/Exceptions/InventoryBusinessException.php`
- Inspect: `services/inventory-service/app/Domain/Exceptions/InsufficientStockException.php`
- Inspect: `services/inventory-service/app/Models/ProductSku.php`
- Inspect: `services/inventory-service/app/Application/DTOs/VerifiedToken.php`
- Modify: `docs/TaskImplementDetailPlan/DailySalesUC-13-15plan.md`

**Interfaces:**

- Confirms Daily Sales can use `InventoryBalanceService::decrease()` and `increase()`.
- Confirms `InventoryTransactionType::Sale` and `SaleReversal` exist.
- Confirms `VerifiedToken->userId` is available for `created_by`, `confirmed_by`, `cancelled_by`.

- [x] **Step 1: Verify transaction enum**

Run:

```powershell
Get-Content services\inventory-service\app\Domain\Enums\InventoryTransactionType.php
```

Expected:

```text
Sale = 'SALE'
SaleReversal = 'SALE_REVERSAL'
```

- [x] **Step 2: Verify inventory mutation service**

Run:

```powershell
Get-Content services\inventory-service\app\Domain\Services\InventoryBalanceService.php
```

Expected:

```text
decrease() rejects quantity <= 0.
decrease() delegates to applyDelta().
increase() rejects quantity <= 0.
increase() delegates to applyDelta().
applyDelta() wraps mutation in DB::transaction().
applyDelta() locks balance with lockForUpdate().
applyDelta() throws InsufficientStockException and rolls back when quantityAfter < 0.
```

- [x] **Step 3: Verify exception mapping**

Run:

```powershell
Get-Content services\inventory-service\bootstrap\app.php
```

Expected:

```text
InventoryBusinessException maps status to JSON.
InsufficientStockException maps to Vietnamese message and 422.
```

- [x] **Step 4: Verify SKU policy**

Run:

```powershell
Get-Content services\inventory-service\app\Models\ProductSku.php
```

Expected:

```text
ProductSku uses SoftDeletes and active boolean.
Daily Sales only accepts active, non-soft-deleted SKU.
Inactive or soft-deleted SKU returns line error "SKU đã ngừng hoạt động.".
```

- [x] **Step 5: Commit preflight note**

After documenting result in this plan:

```powershell
git add docs/TaskImplementDetailPlan/DailySalesUC-13-15plan.md
git commit -m "docs: verify daily sales preflight"
```

Task 0 result, verified on 2026-08-04:

```text
InventoryTransactionType has Sale = 'SALE' and SaleReversal = 'SALE_REVERSAL'.
InventoryBalanceService::decrease() and increase() reject quantity <= 0, delegate to applyDelta(), use DB::transaction(), lock inventory_balances with lockForUpdate(), and create inventory_transactions.
InventoryBalanceService::decrease() throws InsufficientStockException and rolls back when quantityAfter < 0.
InventoryBusinessException supports custom HTTP status; bootstrap/app.php renders it as JSON for api/* requests.
InsufficientStockException extends InventoryBusinessException with the Vietnamese insufficient stock message and default 422 status.
ProductSku uses SoftDeletes and active boolean; Daily Sales must import/confirm/cancel only active, non-soft-deleted SKU.
VerifiedToken exposes userId and role; Daily Sales can use userId for created_by, confirmed_by and cancelled_by.
```

---

## Task 1: Daily Sales Schema And Models

**Files:**

- Create: `services/inventory-service/database/migrations/2026_08_04_000300_create_daily_sales_tables.php`
- Create: `services/inventory-service/app/Domain/Enums/DailySaleStatus.php`
- Create: `services/inventory-service/app/Models/DailySale.php`
- Create: `services/inventory-service/app/Models/DailySaleLine.php`
- Test: `services/inventory-service/tests/Feature/DailySaleApiTest.php`

**Interfaces:**

- Produces enum `DailySaleStatus` with `Draft = 'DRAFT'`, `Confirmed = 'CONFIRMED'`, `Cancelled = 'CANCELLED'`.
- Produces `DailySale::lines(): HasMany`.
- Produces `DailySaleLine::dailySale(): BelongsTo`.
- Produces `DailySaleLine::sku(): BelongsTo`.

- [x] **Step 1: Add failing schema tests**

Create `DailySaleApiTest.php` with:

```php
public function test_daily_sales_tables_exist(): void
{
    self::assertTrue(Schema::hasTable('daily_sales'));
    self::assertTrue(Schema::hasTable('daily_sale_lines'));
    self::assertTrue(Schema::hasColumns('daily_sales', [
        'sales_date',
        'confirmed_sales_date',
        'file_name',
        'file_hash',
        'status',
        'confirmed_at',
        'cancelled_at',
        'cancel_reason',
        'created_by',
        'confirmed_by',
        'cancelled_by',
    ]));
    self::assertTrue(Schema::hasColumns('daily_sale_lines', [
        'daily_sale_id',
        'row_number',
        'sku_code',
        'sku_id',
        'raw_sku_code',
        'raw_quantity_sold',
        'quantity_sold',
        'preview_quantity_before',
        'preview_quantity_after',
        'error_message',
    ]));
}
```

- [x] **Step 2: Run failing schema tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=daily_sales_tables_exist
```

Expected: FAIL because tables do not exist.

- [x] **Step 3: Add migration**

Create migration with:

```php
Schema::create('daily_sales', function (Blueprint $table): void {
    $table->id();
    $table->date('sales_date');
    $table->date('confirmed_sales_date')->nullable()->unique();
    $table->string('file_name');
    $table->string('file_hash', 64);
    $table->string('status', 32)->default('DRAFT');
    $table->timestamp('confirmed_at')->nullable();
    $table->timestamp('cancelled_at')->nullable();
    $table->string('cancel_reason')->nullable();
    $table->unsignedBigInteger('created_by')->nullable();
    $table->unsignedBigInteger('confirmed_by')->nullable();
    $table->unsignedBigInteger('cancelled_by')->nullable();
    $table->timestamps();
    $table->index('sales_date');
    $table->index('status');
    $table->index('created_at');
});

Schema::create('daily_sale_lines', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('daily_sale_id')->constrained('daily_sales')->cascadeOnDelete();
    $table->unsignedInteger('row_number');
    $table->string('sku_code', 64);
    $table->foreignId('sku_id')->nullable()->constrained('product_skus')->restrictOnDelete();
    $table->string('raw_sku_code')->nullable();
    $table->string('raw_quantity_sold')->nullable();
    $table->unsignedInteger('quantity_sold')->nullable();
    $table->integer('preview_quantity_before')->nullable();
    $table->integer('preview_quantity_after')->nullable();
    $table->string('error_message')->nullable();
    $table->timestamps();
    $table->unique(['daily_sale_id', 'row_number']);
    $table->index('daily_sale_id');
    $table->index('sku_id');
});
```

- [x] **Step 4: Add enum and models**

`DailySaleStatus.php`:

```php
enum DailySaleStatus: string
{
    case Draft = 'DRAFT';
    case Confirmed = 'CONFIRMED';
    case Cancelled = 'CANCELLED';
}
```

`DailySale.php` casts:

```php
protected $fillable = [
    'sales_date',
    'confirmed_sales_date',
    'file_name',
    'file_hash',
    'status',
    'confirmed_at',
    'cancelled_at',
    'cancel_reason',
    'created_by',
    'confirmed_by',
    'cancelled_by',
];

protected function casts(): array
{
    return [
        'sales_date' => 'date',
        'confirmed_sales_date' => 'date',
        'status' => DailySaleStatus::class,
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];
}
```

`DailySaleLine.php` fillable:

```php
protected $fillable = [
    'daily_sale_id',
    'row_number',
    'sku_code',
    'sku_id',
    'raw_sku_code',
    'raw_quantity_sold',
    'quantity_sold',
    'preview_quantity_before',
    'preview_quantity_after',
    'error_message',
];
```

- [x] **Step 5: Add relationship test**

Add:

```php
public function test_daily_sale_model_relationships_work(): void
{
    $sku = $this->createSkuWithBalance(quantity: 10);
    $dailySale = DailySale::query()->create([
        'sales_date' => '2026-08-03',
        'file_name' => 'daily-sales.csv',
        'file_hash' => str_repeat('a', 64),
        'status' => DailySaleStatus::Draft->value,
        'created_by' => 10,
    ]);
    DailySaleLine::query()->create([
        'daily_sale_id' => $dailySale->id,
        'row_number' => 2,
        'sku_code' => $sku->sku_code,
        'raw_sku_code' => $sku->sku_code,
        'raw_quantity_sold' => '2',
        'sku_id' => $sku->id,
        'quantity_sold' => 2,
        'preview_quantity_before' => 10,
        'preview_quantity_after' => 8,
    ]);

    self::assertSame('AO-THUN-M', $dailySale->lines()->first()->sku->sku_code);
    self::assertSame(DailySaleStatus::Draft, $dailySale->status);
}
```

- [x] **Step 6: Run schema/model tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=DailySaleApiTest
```

Expected: PASS for schema/model tests.

- [x] **Step 7: Commit**

```powershell
git add services/inventory-service/database/migrations services/inventory-service/app/Domain/Enums services/inventory-service/app/Models services/inventory-service/tests/Feature/DailySaleApiTest.php
git commit -m "feat(inventory): add daily sales schema"
```

---

## Task 2: Daily Sales CSV Parser And Draft API

**Files:**

- Create: `services/inventory-service/app/Application/DTOs/CreateDailySaleData.php`
- Create: `services/inventory-service/app/Infrastructure/CsvDailySaleParser.php`
- Create: `services/inventory-service/app/Application/UseCases/CreateDailySaleUseCase.php`
- Create: `services/inventory-service/app/Application/UseCases/ListDailySalesUseCase.php`
- Create: `services/inventory-service/app/Application/UseCases/GetDailySaleUseCase.php`
- Create: `services/inventory-service/app/Http/Controllers/DailySaleController.php`
- Create: `services/inventory-service/app/Http/Requests/StoreDailySaleRequest.php`
- Create: `services/inventory-service/app/Http/Resources/DailySaleResource.php`
- Create: `services/inventory-service/app/Http/Resources/DailySaleLineResource.php`
- Modify: `services/inventory-service/routes/api.php`
- Test: `services/inventory-service/tests/Feature/DailySaleApiTest.php`

**Interfaces:**

- `CreateDailySaleData::__construct(UploadedFile $file, string $salesDate, ?int $createdBy)`.
- `CsvDailySaleParser::parse(string $path): array<int, array{row_number:int,raw_sku_code:?string,raw_quantity_sold:?string,sku_code:string,quantity_sold:?int,error_message:?string}>`.
- `CreateDailySaleUseCase::execute(CreateDailySaleData $data): DailySale`.
- `ListDailySalesUseCase::execute(?string $dateFrom, ?string $dateTo, ?string $status, int $perPage = 20): LengthAwarePaginator`.
- `GetDailySaleUseCase::execute(DailySale $dailySale): DailySale`.

- [ ] **Step 1: Add failing parser tests**

Add tests:

```php
public function test_daily_sale_csv_parser_accepts_template_and_normalizes_sku_code(): void
public function test_daily_sale_csv_parser_rejects_wrong_header(): void
public function test_daily_sale_csv_parser_marks_invalid_quantity_rows(): void
public function test_daily_sale_csv_parser_handles_bom_blank_rows_and_groups_normalized_sku(): void
public function test_daily_sale_csv_parser_rejects_file_without_data_rows(): void
public function test_daily_sale_csv_parser_rejects_more_than_5000_data_rows(): void
```

Expected parser behavior:

```text
Header must be sku_code,quantity_sold.
Header may start with UTF-8 BOM.
Completely blank rows are ignored.
File with only header returns "File CSV phải có ít nhất một dòng dữ liệu."
More than 5000 data rows returns "File CSV chỉ được có tối đa 5000 dòng dữ liệu."
Quantity <= 0 returns "Số lượng bán phải lớn hơn 0."
Non-integer quantity returns "Số lượng bán phải là số nguyên."
```

- [ ] **Step 2: Implement parser**

Use stdlib `fopen`, `fgetcsv`, `strtoupper`, `trim`.
Strip UTF-8 BOM from the first header cell with `preg_replace('/^\xEF\xBB\xBF/', '', $header[0])`.
Ignore rows where every CSV cell is `null` or trims to `''`.
Count nonblank data rows; reject after 5000 rows.

Header error:

```php
throw new InventoryBusinessException('File CSV phải có đúng hai cột sku_code và quantity_sold.');
```

Row parse output:

```php
[
    'row_number' => $rowNumber,
    'raw_sku_code' => $rawSkuCode,
    'raw_quantity_sold' => $rawQuantitySold,
    'sku_code' => strtoupper(trim((string) $rawSkuCode)),
    'quantity_sold' => $quantitySold,
    'error_message' => $errorMessage,
]
```

- [ ] **Step 3: Add failing draft API tests**

Add tests:

```php
public function test_create_daily_sale_draft_creates_sale_and_grouped_lines(): void
public function test_create_daily_sale_marks_missing_sku_error_by_line(): void
public function test_create_daily_sale_marks_inactive_sku_error_by_line(): void
public function test_create_daily_sale_blocks_when_confirmed_sale_exists_for_same_date(): void
public function test_create_daily_sale_rejects_future_sales_date(): void
public function test_get_daily_sale_does_not_require_csrf(): void
public function test_list_daily_sales_does_not_require_csrf(): void
public function test_list_daily_sales_paginates_and_does_not_return_lines(): void
public function test_list_daily_sales_filters_by_date_range_and_status(): void
public function test_list_daily_sales_rejects_date_to_before_date_from(): void
public function test_staff_can_create_daily_sale_draft(): void
public function test_create_daily_sale_requires_csrf(): void
```

Grouped line assertion:

```php
->post('/api/v1/daily-sales', [
    'sales_date' => '2026-08-03',
    'file' => $this->csvUpload("sku_code,quantity_sold\nAO-THUN-M,2\nAO-THUN-M,3\n", 'daily-sales.csv'),
])
->assertCreated()
->assertJsonPath('daily_sale.status', 'DRAFT')
->assertJsonPath('daily_sale.file_hash', fn ($value): bool => is_string($value) && strlen($value) === 64)
->assertJsonPath('daily_sale.lines.0.quantity_sold', 5)
->assertJsonPath('daily_sale.lines.0.preview_quantity_before', 10)
->assertJsonPath('daily_sale.lines.0.preview_quantity_after', 5);
```

- [ ] **Step 4: Implement DTO, request and resources**

`StoreDailySaleRequest` rules:

```php
return [
    'sales_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
    'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
];
```

`DailySaleSummaryResource` keys for list:

```php
[
    'id',
    'sales_date',
    'file_name',
    'status',
    'has_errors',
    'lines_count',
    'confirmed_at',
    'cancelled_at',
    'created_at',
]
```

`DailySaleResource` keys for show/create/confirm/cancel:

```php
[
    'id',
    'sales_date',
    'file_name',
    'file_hash',
    'status',
    'has_errors',
    'confirmed_at',
    'cancelled_at',
    'cancel_reason',
    'created_by',
    'confirmed_by',
    'cancelled_by',
    'created_at',
    'lines',
]
```

- [ ] **Step 5: Implement create/list/get use cases**

Create behavior:

```text
Reject if DailySale exists with status CONFIRMED and same sales_date.
Compute `$fileHash = hash_file('sha256', $data->file->getRealPath())` and store it for audit only.
Parse file.
Group rows by sku_code only when row has no parser error.
For grouped SKU, quantity_sold = sum.
Find ProductSku without withTrashed().
If not found, check withTrashed(); if found inactive/deleted, error "SKU đã ngừng hoạt động.", otherwise "Không tìm thấy SKU.".
For active SKU, read InventoryBalance quantity.
preview_quantity_before = current balance.
preview_quantity_after = current balance - quantity_sold.
Do not reject insufficient stock at draft time; show negative preview_quantity_after so operator sees risk.
Store DRAFT and lines.
Do not use file_hash to block duplicate Daily Sales files.
```

List behavior:

```text
Return paginator with per_page default 20, max 100.
Order newest first by sales_date desc, id desc.
Optional filters date_from, date_to, status.
Reject date_to before date_from with 422 "Khoảng ngày lọc không hợp lệ."
Use withCount('lines') and withExists(['lines as has_errors' => fn ($query) => $query->whereNotNull('error_message')]).
Do not load full lines for list.
```

- [ ] **Step 6: Implement controller and routes**

Routes:

```php
Route::get('/daily-sales', [DailySaleController::class, 'index']);
Route::post('/daily-sales', [DailySaleController::class, 'store']);
Route::get('/daily-sales/{dailySale}', [DailySaleController::class, 'show']);
```

Controller:

```php
public function store(StoreDailySaleRequest $request, CreateDailySaleUseCase $useCase): JsonResponse
public function index(Request $request, ListDailySalesUseCase $useCase): JsonResponse
public function show(DailySale $dailySale, GetDailySaleUseCase $useCase): JsonResponse
```

- [ ] **Step 7: Run draft API tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=DailySaleApiTest
```

Expected: PASS for schema, parser, draft, list, show tests.

- [ ] **Step 8: Commit**

```powershell
git add services/inventory-service/app services/inventory-service/routes/api.php services/inventory-service/tests/Feature/DailySaleApiTest.php
git commit -m "feat(inventory): add daily sales draft API"
```

---

## Task 3: Confirm Daily Sales API

**Files:**

- Create: `services/inventory-service/app/Application/UseCases/ConfirmDailySaleUseCase.php`
- Modify: `services/inventory-service/app/Http/Controllers/DailySaleController.php`
- Modify: `services/inventory-service/routes/api.php`
- Test: `services/inventory-service/tests/Feature/DailySaleApiTest.php`

**Interfaces:**

- `ConfirmDailySaleUseCase::execute(DailySale $dailySale, ?int $confirmedBy): DailySale`.

- [ ] **Step 1: Add failing confirm tests**

Add tests:

```php
public function test_confirm_daily_sale_decreases_balances_and_writes_sale_transactions(): void
public function test_confirm_daily_sale_rolls_back_when_any_sku_has_insufficient_stock(): void
public function test_confirm_daily_sale_uses_current_balance_when_stock_changed_after_preview(): void
public function test_confirm_daily_sale_blocks_when_sku_is_deactivated_after_preview(): void
public function test_confirm_daily_sale_blocks_sale_with_line_errors(): void
public function test_confirm_daily_sale_cannot_run_twice(): void
public function test_confirm_daily_sale_blocks_when_another_sale_confirmed_same_date(): void
public function test_confirm_daily_sale_requires_csrf(): void
public function test_staff_can_confirm_daily_sale(): void
```

Success assertion:

```php
$this->assertDatabaseHas('inventory_transactions', [
    'sku_id' => $sku->id,
    'type' => 'SALE',
    'quantity_before' => 10,
    'quantity_change' => -5,
    'quantity_after' => 5,
    'reference_type' => 'daily_sale',
    'reference_id' => (string) $dailySale->id,
    'reason' => 'Trừ tồn từ phiếu bán hằng ngày.',
    'created_by' => 10,
]);
```

Rollback assertion for insufficient stock:

```php
$this->assertDatabaseHas('daily_sales', [
    'id' => $dailySale->id,
    'status' => 'DRAFT',
    'confirmed_sales_date' => null,
]);
$this->assertDatabaseMissing('inventory_transactions', [
    'reference_type' => 'daily_sale',
    'reference_id' => (string) $dailySale->id,
    'type' => 'SALE',
]);
```

- [ ] **Step 2: Implement confirm use case**

Behavior:

```text
Use DB::transaction().
Lock daily_sales row with lockForUpdate().
Reload lines ordered by row_number.
If status is CONFIRMED, throw 409 "Phiếu bán này đã được xác nhận.".
If status is CANCELLED, throw 409 "Phiếu bán này đã bị hủy.".
If any line has error_message, throw 422 "Không thể xác nhận phiếu bán còn dòng lỗi.".
If another daily_sales row has status CONFIRMED and same sales_date, throw 409 "Ngày bán này đã có phiếu được xác nhận.".
Set confirmed_sales_date = sales_date before balance mutation and save; catch unique violation and map to same 409.
For each line, query ProductSku fresh by `sku_id` without trusting loaded relation.
If SKU is missing, inactive or soft-deleted, throw 409 "SKU {sku_code} đã ngừng hoạt động và không thể xác nhận.".
If balance is missing, throw 422 "Không tìm thấy dữ liệu tồn kho của SKU.".
For each line call InventoryBalanceService::decrease(
  sku: $sku,
  quantity: $line->quantity_sold,
  type: InventoryTransactionType::Sale,
  referenceType: 'daily_sale',
  referenceId: (string) $dailySale->id,
  reason: 'Trừ tồn từ phiếu bán hằng ngày.',
  createdBy: $confirmedBy
).
Set status CONFIRMED, confirmed_at now, confirmed_by.
Return loaded daily sale with lines.
```

- [ ] **Step 3: Add controller method and route**

Route:

```php
Route::post('/daily-sales/{dailySale}/confirm', [DailySaleController::class, 'confirm']);
```

Controller:

```php
public function confirm(DailySale $dailySale, ConfirmDailySaleUseCase $useCase): JsonResponse
{
    /** @var VerifiedToken $verified */
    $verified = request()->attributes->get('verified_token');

    return response()->json([
        'daily_sale' => new DailySaleResource($useCase->execute($dailySale, $verified->userId)),
    ]);
}
```

- [ ] **Step 4: Run confirm tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=confirm_daily_sale
```

Expected: PASS.

- [ ] **Step 5: Commit**

```powershell
git add services/inventory-service/app services/inventory-service/routes/api.php services/inventory-service/tests/Feature/DailySaleApiTest.php
git commit -m "feat(inventory): confirm daily sales"
```

---

## Task 4: Cancel Daily Sales API

**Files:**

- Create: `services/inventory-service/app/Application/UseCases/CancelDailySaleUseCase.php`
- Create: `services/inventory-service/app/Http/Requests/CancelDailySaleRequest.php`
- Modify: `services/inventory-service/app/Http/Controllers/DailySaleController.php`
- Modify: `services/inventory-service/routes/api.php`
- Test: `services/inventory-service/tests/Feature/DailySaleApiTest.php`

**Interfaces:**

- `CancelDailySaleUseCase::execute(DailySale $dailySale, string $reason, ?int $cancelledBy): DailySale`.

- [ ] **Step 1: Add failing cancel tests**

Add tests:

```php
public function test_cancel_daily_sale_reverses_balances_and_writes_sale_reversal_transactions(): void
public function test_cancel_daily_sale_blocks_when_sku_is_deactivated_after_confirm(): void
public function test_cancel_daily_sale_rolls_back_when_any_line_cannot_be_reversed(): void
public function test_cancel_daily_sale_clears_confirmed_sales_date(): void
public function test_cancel_daily_sale_requires_confirmed_status(): void
public function test_cancel_daily_sale_cannot_run_twice(): void
public function test_cancel_daily_sale_requires_reason(): void
public function test_cancel_daily_sale_requires_csrf(): void
public function test_staff_can_cancel_daily_sale(): void
```

Success assertion:

```php
$this->assertDatabaseHas('inventory_transactions', [
    'sku_id' => $sku->id,
    'type' => 'SALE_REVERSAL',
    'quantity_before' => 5,
    'quantity_change' => 5,
    'quantity_after' => 10,
    'reference_type' => 'daily_sale',
    'reference_id' => (string) $dailySale->id,
    'reason' => 'Khách trả đơn lỗi nhập.',
    'created_by' => 10,
]);
```

- [ ] **Step 2: Implement cancel request**

Rules:

```php
return [
    'reason' => ['required', 'string', 'max:255'],
];
```

Messages:

```php
'reason.required' => 'Vui lòng nhập lý do hủy phiếu bán.',
```

- [ ] **Step 3: Implement cancel use case**

Behavior:

```text
Use DB::transaction().
Lock daily_sales row with lockForUpdate().
Reload lines ordered by row_number.
If status is DRAFT, throw 409 "Phiếu bán chưa xác nhận nên không thể hủy.".
If status is CANCELLED, throw 409 "Phiếu bán này đã bị hủy.".
For each line, query ProductSku fresh by `sku_id` without trusting loaded relation.
If SKU is missing, inactive or soft-deleted, throw 409 "SKU {sku_code} đã ngừng hoạt động và không thể hủy.".
If balance is missing, throw 422 "Không tìm thấy dữ liệu tồn kho của SKU.".
For each line call InventoryBalanceService::increase(
  sku: $sku,
  quantity: $line->quantity_sold,
  type: InventoryTransactionType::SaleReversal,
  referenceType: 'daily_sale',
  referenceId: (string) $dailySale->id,
  reason: $reason,
  createdBy: $cancelledBy
).
Set status CANCELLED, cancelled_at now, cancelled_by, cancel_reason.
Set confirmed_sales_date = null.
Return loaded daily sale with lines.
```

- [ ] **Step 4: Add controller method and route**

Route:

```php
Route::post('/daily-sales/{dailySale}/cancel', [DailySaleController::class, 'cancel']);
```

Controller:

```php
public function cancel(CancelDailySaleRequest $request, DailySale $dailySale, CancelDailySaleUseCase $useCase): JsonResponse
```

- [ ] **Step 5: Run cancel tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=cancel_daily_sale
```

Expected: PASS.

- [ ] **Step 6: Run full daily sales tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=DailySaleApiTest
```

Expected: PASS.

- [ ] **Step 7: Commit**

```powershell
git add services/inventory-service/app services/inventory-service/routes/api.php services/inventory-service/tests/Feature/DailySaleApiTest.php
git commit -m "feat(inventory): cancel daily sales"
```

---

## Task 5: Daily Sales React API And Pages

**Files:**

- Create: `frontend/src/features/inventory/api/dailySalesApi.ts`
- Create: `frontend/src/features/inventory/components/DailySalePreviewTable.tsx`
- Create: `frontend/src/features/inventory/pages/DailySalesPage.tsx`
- Create: `frontend/src/features/inventory/pages/NewDailySalePage.tsx`
- Create: `frontend/src/dailySalesExperience.test.mjs`
- Modify: `frontend/src/App.tsx`
- Modify: `frontend/src/features/health/pages/DashboardPage.tsx`
- Modify: `frontend/src/features/inventory/pages/InventoryPage.tsx`
- Modify: `frontend/src/features/inventory/pages/ProductsPage.tsx`
- Modify: `frontend/src/App.css`
- Modify: `frontend/package.json`

**Interfaces:**

- `listDailySales(): Promise<PaginatedDailySales>`.
- `createDailySale(input: { salesDate: string; file: File }): Promise<DailySale>`.
- `getDailySale(id: number): Promise<DailySale>`.
- `confirmDailySale(id: number): Promise<DailySale>`.
- `cancelDailySale(id: number, reason: string): Promise<DailySale>`.
- Routes: `/daily-sales`, `/daily-sales/new`.

- [ ] **Step 1: Use frontend design skill**

Run relevant taste/design skill before UI edits:

```text
Use design-taste-frontend or redesign-existing-projects for LazyManager Cozy/Lazy operator UI.
```

Expected design direction:

```text
Operational list page, no landing hero.
New page: sales date first, CSV upload, preview table, confirm action.
Cancel action requires reason and calm warning state.
Errors are visible per row but not loud.
```

- [ ] **Step 2: Add frontend route policy test**

Create `dailySalesExperience.test.mjs`:

```js
import fs from 'node:fs'
import assert from 'node:assert/strict'

const app = fs.readFileSync(new URL('./App.tsx', import.meta.url), 'utf8')
const dashboard = fs.readFileSync(new URL('./features/health/pages/DashboardPage.tsx', import.meta.url), 'utf8')
const api = fs.readFileSync(new URL('./features/inventory/api/dailySalesApi.ts', import.meta.url), 'utf8')

assert.match(app, /path="\/daily-sales"/)
assert.match(app, /path="\/daily-sales\/new"/)
assert.doesNotMatch(app, /<RequireManager>\s*<DailySalesPage/)
assert.doesNotMatch(app, /<RequireManager>\s*<NewDailySalePage/)
assert.match(dashboard, /to="\/daily-sales"/)
assert.match(api, /createDailySale/)
assert.match(api, /confirmDailySale/)
assert.match(api, /cancelDailySale/)

console.log('daily sales route policy ok')
```

- [ ] **Step 3: Create API client**

Types:

```ts
export type DailySaleStatus = 'DRAFT' | 'CONFIRMED' | 'CANCELLED'

export type DailySaleLine = {
  id: number
  row_number: number
  raw_sku_code: string | null
  raw_quantity_sold: string | null
  sku_code: string
  sku_id: number | null
  quantity_sold: number | null
  preview_quantity_before: number | null
  preview_quantity_after: number | null
  error_message: string | null
}

export type DailySale = {
  id: number
  sales_date: string
  file_name: string
  file_hash: string | null
  status: DailySaleStatus
  has_errors: boolean
  confirmed_at: string | null
  cancelled_at: string | null
  cancel_reason: string | null
  created_by: number | null
  confirmed_by: number | null
  cancelled_by: number | null
  created_at: string | null
  lines: DailySaleLine[]
}

export type DailySaleSummary = Omit<DailySale, 'file_hash' | 'cancel_reason' | 'created_by' | 'confirmed_by' | 'cancelled_by' | 'lines'> & {
  lines_count: number
}

export type PaginatedDailySales = {
  data: DailySaleSummary[]
  meta: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}
```

Functions:

```ts
export async function createDailySale(input: { salesDate: string; file: File }): Promise<DailySale>
export async function listDailySales(): Promise<PaginatedDailySales>
export async function getDailySale(id: number): Promise<DailySale>
export async function confirmDailySale(id: number): Promise<DailySale>
export async function cancelDailySale(id: number, reason: string): Promise<DailySale>
```

- [ ] **Step 4: Create preview table component**

Columns:

```text
Dòng
Mã SKU
SKU gốc
Số lượng bán gốc
Tồn lúc xem trước
Số bán
Tồn dự kiến
Lỗi
```

States:

```text
If lines empty: "Chưa có dữ liệu phiếu bán."
Rows with error_message show message.
Rows without error show "Hợp lệ".
```

- [ ] **Step 5: Create `/daily-sales/new` page**

Required labels:

```text
Phiếu bán hằng ngày
Ngày bán
Chọn file CSV
Xem trước
Xác nhận trừ tồn
File phải có cột sku_code và quantity_sold.
Chưa có file bán hàng.
Không tải được preview phiếu bán.
Phiếu còn lỗi, vui lòng sửa CSV rồi tải lại.
Đã xác nhận và trừ tồn.
```

Behavior:

```text
Initial state shows sales_date input default yesterday in local browser time.
Selecting file stores File in state.
Click Xem trước calls createDailySale({ salesDate, file }).
Show loading while uploading.
Show preview summary: file name, sales_date, status, valid line count, error line count.
Disable confirm if no preview, preview.has_errors, preview.status !== 'DRAFT', or loading.
Click Xác nhận trừ tồn calls confirmDailySale(preview.id).
After confirm, replace preview with confirmed response.
Show API error message from response.message when available.
```

- [ ] **Step 6: Create `/daily-sales` list page**

Required labels:

```text
Phiếu bán
Tạo phiếu bán
Ngày bán
Trạng thái
Số dòng
Lỗi
Đã xác nhận
Đã hủy
Nháp
Hủy phiếu
Lý do hủy
```

Behavior:

```text
Load listDailySales on mount.
Show rows newest first.
Each row links to the same page detail area or expands inline with line preview.
Cancel button only for CONFIRMED rows.
Cancel requires reason, then calls cancelDailySale.
After cancel, update row with response.
```

- [ ] **Step 7: Add routes and navigation**

In `App.tsx`:

```tsx
<Route path="/daily-sales" element={<DailySalesPage />} />
<Route path="/daily-sales/new" element={<NewDailySalePage />} />
```

Do not wrap in `RequireManager`.

Add dashboard/nav link:

```text
Phiếu bán
/daily-sales
```

- [ ] **Step 8: Run frontend tests and build**

Run:

```powershell
npm --prefix frontend test
npm --prefix frontend run build
```

If local npm shim is broken, use bundled Node for `.mjs` tests and local `frontend\node_modules\.bin\tsc.cmd -b`, `frontend\node_modules\.bin\vite.cmd build`.

Expected: PASS.

- [ ] **Step 9: Commit**

```powershell
git add frontend/src/App.tsx frontend/src/App.css frontend/src/features frontend/src/dailySalesExperience.test.mjs frontend/package.json
git commit -m "feat(frontend): add daily sales pages"
```

---

## Task 6: Docs And Final Verification

**Files:**

- Modify: `docs/api.md`
- Modify: `docs/progress_report_2.8.26.md`
- Modify: `README.md`
- Modify: `docs/TaskImplementDetailPlan/DailySalesUC-13-15plan.md`

**Interfaces:**

- Docs state UC-13/14/15 Daily Sales are implemented.
- API docs move Daily Sales endpoints from Future to Implemented.
- Future endpoints remain clearly labeled: Borrow, Stock Count.

- [ ] **Step 1: Update docs/api.md**

Move Daily Sales endpoints to Implemented:

```text
GET    /api/inventory/v1/daily-sales?date_from=&date_to=&status=
POST   /api/inventory/v1/daily-sales
GET    /api/inventory/v1/daily-sales/{id}
POST   /api/inventory/v1/daily-sales/{id}/confirm
POST   /api/inventory/v1/daily-sales/{id}/cancel
```

Add behavior:

```text
Daily Sales UC-13/14/15:
- `POST /daily-sales` upload CSV field `file` plus `sales_date`, tạo DRAFT preview và trả `201`.
- CSV MVP chỉ hỗ trợ header `sku_code,quantity_sold`.
- Dòng cùng SKU được gom tổng số bán.
- SKU không tồn tại, inactive/soft-deleted, quantity <= 0 hoặc không phải số nguyên được báo lỗi theo dòng.
- DRAFT không ảnh hưởng tồn kho.
- `POST /daily-sales/{id}/confirm` yêu cầu CSRF, trừ tồn bằng `InventoryBalanceService::decrease()` và ghi transaction `SALE`.
- Confirm rollback toàn bộ nếu bất kỳ SKU không đủ tồn.
- Chỉ một phiếu được CONFIRMED cho cùng `sales_date`; DB unique `confirmed_sales_date` chặn race.
- `POST /daily-sales/{id}/cancel` yêu cầu CSRF, cộng trả tồn bằng `InventoryBalanceService::increase()` và ghi transaction `SALE_REVERSAL`.
```

- [ ] **Step 2: Update progress docs and README**

In `docs/progress_report_2.8.26.md`:

```text
UC-13: DONE.
UC-14: DONE.
UC-15: DONE.
Tiến trình: 14 / 20 DONE + 1 PARTIAL.
```

In `README.md`:

```text
Current state includes Daily Sales CSV draft/confirm/cancel.
Next: Borrow/Return.
```

- [ ] **Step 3: Run backend verification**

Run:

```powershell
docker compose exec -T inventory-service php artisan test
docker compose exec -T inventory-service php artisan migrate:fresh --env=testing --database=sqlite --force
docker compose exec -T inventory-service php artisan test
docker compose exec -T inventory-service php artisan route:list --path=api/v1
```

Expected:

```text
Inventory tests pass.
Testing sqlite migrate fresh succeeds without touching inventory-db PostgreSQL volume.
Route list includes daily-sales create/list/show/confirm/cancel.
```

- [ ] **Step 4: Run frontend verification**

Run:

```powershell
npm --prefix frontend test
npm --prefix frontend run build
```

Expected: PASS or equivalent bundled Node/local bin checks pass if npm shim is broken.

- [ ] **Step 5: Gateway smoke**

Run:

```powershell
docker compose up -d --build
Invoke-RestMethod http://localhost:8080/api/inventory/ready
```

Expected:

```json
{
  "status": "ready",
  "service": "inventory-service",
  "database": "connected"
}
```

- [ ] **Step 6: Commit**

```powershell
git add docs/api.md docs/progress_report_2.8.26.md README.md docs/TaskImplementDetailPlan/DailySalesUC-13-15plan.md
git commit -m "docs: document daily sales workflow"
```

---

## Final Verification

Run from repo root:

```powershell
docker compose exec -T people-service php artisan test
docker compose exec -T inventory-service php artisan test
docker compose exec -T inventory-service php artisan migrate:fresh --env=testing --database=sqlite --force
docker compose exec -T inventory-service php artisan test
docker compose config
npm --prefix frontend test
npm --prefix frontend run build
```

Expected:

- People Service tests pass.
- Inventory Service tests pass.
- Inventory testing sqlite fresh migration succeeds.
- Compose config is valid.
- Frontend route tests pass.
- Frontend build succeeds.
- `/daily-sales` and `/daily-sales/new` are accessible to `STORE_MANAGER` and `STAFF`.

Manual demo CSV:

```csv
sku_code,quantity_sold
AO-THUN-M,2
AO-THUN-L,1
AO-THUN-M,3
```

Manual error CSV:

```csv
sku_code,quantity_sold
AO-THUN-M,0
SKU-KHONG-CO,5
AO-THUN-L,abc
```

Expected manual result:

- Valid CSV previews grouped SKU totals and confirms.
- Confirm writes `SALE` transaction history visible on `/inventory`.
- Cancel writes `SALE_REVERSAL` transaction history visible on `/inventory`.
- Error CSV shows row-level errors and confirm button disabled.

---

## Acceptance Checklist

- [ ] `daily_sales` table exists with `confirmed_sales_date` unique nullable.
- [ ] `daily_sales.file_hash` is calculated with sha256 and stored for audit only.
- [ ] `daily_sale_lines` stores row-level preview.
- [ ] `daily_sale_lines.sku_id` uses restrict on delete.
- [ ] CSV parser accepts exact `sku_code,quantity_sold` header.
- [ ] CSV parser accepts UTF-8 BOM.
- [ ] CSV parser ignores completely blank rows.
- [ ] CSV parser rejects empty/header-only files.
- [ ] CSV parser rejects files with more than 5000 data rows.
- [ ] Wrong header returns Vietnamese validation error.
- [ ] SKU code is normalized by trim + uppercase.
- [ ] Duplicate SKU in one file is grouped.
- [ ] Missing SKU creates line error.
- [ ] Inactive or soft-deleted SKU creates `SKU đã ngừng hoạt động.` line error.
- [ ] Quantity <= 0 creates line error.
- [ ] Non-integer quantity creates line error.
- [ ] DRAFT creation does not mutate inventory balances.
- [ ] DRAFT creation stores preview_quantity_before and preview_quantity_after.
- [ ] DRAFT creation rejects future sales_date.
- [ ] DRAFT creation blocks when another sale is already CONFIRMED for same sales_date.
- [ ] List route returns paginated summaries without full lines.
- [ ] List supports date_from, date_to and status filters.
- [ ] List rejects date_to before date_from.
- [ ] List and show routes work without CSRF.
- [ ] Create requires CSRF.
- [ ] Confirm requires CSRF.
- [ ] Cancel requires CSRF.
- [ ] STAFF can create, confirm and cancel.
- [ ] STORE_MANAGER can create, confirm and cancel.
- [ ] Confirm blocks sales with line errors.
- [ ] Confirm cannot run twice.
- [ ] Confirm blocks another CONFIRMED sale for same sales_date.
- [ ] Confirm revalidates SKU and balance at execution time.
- [ ] Confirm race is protected by unique `confirmed_sales_date`.
- [ ] Confirm rolls back all balance changes when any SKU lacks stock.
- [ ] Confirm updates balances through `InventoryBalanceService::decrease()`.
- [ ] Confirm creates `SALE` ledger rows.
- [ ] Cancel only works for CONFIRMED sales.
- [ ] Cancel cannot run twice.
- [ ] Cancel revalidates SKU and balance at execution time.
- [ ] Cancel updates balances through `InventoryBalanceService::increase()`.
- [ ] Cancel creates `SALE_REVERSAL` ledger rows.
- [ ] Cancel clears `confirmed_sales_date`.
- [ ] React `/daily-sales/new` uploads CSV and displays preview.
- [ ] React confirm button disables on errors and after confirmed.
- [ ] React `/daily-sales` lists sales and supports cancel reason.
- [ ] Docs mark Daily Sales implemented and future endpoints clearly separated.

---

## Self-Review

- Spec coverage: UC-13 create/import draft, UC-14 confirm and stock decrement, UC-15 cancel and stock reversal are covered.
- Placeholder scan: no TBD/TODO markers; out-of-scope items are explicit.
- Type consistency: `DailySaleStatus`, `DailySale`, `DailySaleLine`, `CreateDailySaleData`, `CsvDailySaleParser`, use case names and route names are defined before use.
- Scope control: no paste text, XLSX, POS, revenue, Borrow or Stock Count.
- Architecture alignment: controller thin, FormRequest validation, use cases own workflow, parser in Infrastructure, balance mutation through `InventoryBalanceService`.

---

## Execution Choice

Plan complete and saved to `docs/TaskImplementDetailPlan/DailySalesUC-13-15plan.md`.

Recommended execution:

1. Use `superpowers:subagent-driven-development` if multi-agent tools are enabled.
2. Otherwise use `superpowers:executing-plans` inline.

This plan intentionally stops before Borrow/Return. Next large plan after UC-13/14/15 should be UC-16/17 Borrow/Return.
