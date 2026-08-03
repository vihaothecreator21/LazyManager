# Stock Import UC-11 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Xây luồng UC-11 Import tồn kho: upload CSV, lưu preview, báo lỗi theo dòng, chặn file trùng, xác nhận đồng bộ tồn kho và ghi ledger `IMPORT_SYNC`.

**Architecture:** Inventory Service sở hữu toàn bộ dữ liệu import trong `inventory_db`. Controller chỉ validate request, gọi use case và trả resource; preview được lưu vào `stock_imports` và `stock_import_lines`; confirm bắt buộc đi qua `InventoryBalanceService::synchronize()` để cập nhật `inventory_balances` và ghi `inventory_transactions` trong DB transaction.

**Tech Stack:** PHP 8.3, Laravel 13, PostgreSQL, PHPUnit feature tests, React + TypeScript + Vite, Docker Compose.

## Global Constraints

- Tất cả text tiếng Việt trong code response, docs, backend messages và frontend text phải có dấu đầy đủ.
- Không thêm dependency mới.
- UC-11 chỉ làm CSV trong MVP; XLSX trong tài liệu nguồn được ghi là future vì hiện không có Excel parser và không thêm dependency.
- Template CSV cố định gồm đúng header `sku_code,quantity`.
- Chuẩn hóa `sku_code` bằng `trim` + `uppercase`.
- Quantity dùng integer, không dùng float.
- Quantity import phải là số nguyên `>= 0`.
- Duplicate SKU trong cùng file bị báo lỗi theo dòng, không tự gom.
- File hash trùng bị chặn bằng `409`, không tạo preview mới.
- Raw CSV input phải được lưu lại trên từng dòng preview để debug được file gốc.
- Preview snapshot phải bất biến sau khi tạo; confirm không được ghi đè `quantity_before` hoặc `quantity_after` đã preview.
- Duplicate file hash phải dựa vào unique DB constraint và catch race condition thành response `409`.
- Không gọi thẳng database service khác.
- Không cập nhật `inventory_balances` từ Controller.
- Mọi thay đổi balance phải đi qua `InventoryBalanceService`.
- Mọi thay đổi balance phải tạo `inventory_transactions`.
- Mọi thay đổi balance phải nằm trong DB transaction.
- API ghi dữ liệu phải dùng cookie auth và CSRF hiện có. GET không yêu cầu CSRF, mutation `POST`, `PUT`, `DELETE` bắt buộc CSRF token.
- Cả `STORE_MANAGER` và `STAFF` được thao tác module inventory trong MVP.
- Frontend/UI task phải dùng taste/design skill trước khi sửa UI code.
- UI LazyManager giữ vibe "Cozy" và "Lazy": thân thiện, bình tĩnh, dễ thao tác, ít ma sát, trạng thái rõ.

---

## Current Project State

Đã có nền UC-10/12:

- Model: `Product`, `ProductSku`, `InventoryBalance`, `InventoryTransaction`.
- Enum: `InventoryTransactionType` có `ImportSync = 'IMPORT_SYNC'`.
- Service: `InventoryBalanceService::synchronize(ProductSku $sku, int $targetQuantity, InventoryTransactionType $type, ?string $referenceType, ?string $referenceId, ?string $reason, ?int $createdBy): InventoryTransaction`.
- API hiện có: Product/SKU CRUD, inventory list, transaction history.
- Routes hiện có trong `services/inventory-service/routes/api.php` nằm dưới `Route::prefix('v1')->middleware(['auth.jwt', 'csrf.double_submit'])`.
- Frontend hiện có `/products` và `/inventory`, API client cookie auth + CSRF.
- `docs/api.md` đã liệt kê stock import endpoints như future endpoints, nhưng backend chưa implement.

Nguồn sự thật:

- `docs/StoreOps_MVP_1_Thang_v2.0.md`: UC-11 Import tồn kho.
- `docs/TaskImplementDetailPlan/ProductInventoryUC-10-12plan.md`: style plan, ràng buộc inventory.
- `docs/api.md`: route public gateway `/api/inventory/v1/*`.
- `docs/progress_report_2.8.26.md`: tiến trình hiện tại trước UC-11.

---

## Scope

In scope:

- Upload CSV bằng `multipart/form-data` field `file`.
- Tính `file_hash = hash_file('sha256', uploaded file path)`.
- Chặn hash trùng bằng unique DB constraint và response `409`.
- Parse preview, lưu từng dòng kèm `raw_sku_code`, `raw_quantity`, `quantity_before`, `quantity_after`, `error_message`.
- Báo lỗi theo dòng khi SKU không tồn tại, quantity âm, quantity không phải số nguyên, SKU bị lặp trong file.
- SKU inactive hoặc soft-deleted không được import; báo lỗi dòng `SKU đã ngừng hoạt động.`.
- Confirm chỉ chạy khi import ở trạng thái `PREVIEWED` và không có dòng lỗi.
- Confirm đồng bộ `inventory_balances.quantity` bằng quantity trong file, không cộng dồn.
- Confirm tạo transaction `IMPORT_SYNC` cho từng dòng hợp lệ, kể cả khi quantity không đổi.
- Confirm chỉ chạy một lần.
- Confirm không sửa preview snapshot; transaction ledger là nguồn sự thật cho trạng thái thực tế lúc confirm.
- React page `/inventory/import`: chọn file CSV, upload preview, xem lỗi, xác nhận đồng bộ.
- Docs cập nhật API implemented vs future.

Out of scope:

- XLSX parser.
- AI column mapping.
- Background queue.
- Daily sales UC-13/14/15.
- Borrow UC-16/17.
- Stock count UC-18/19/20.
- Supplier, store, purchase order, barcode, category, image.

---

## API Target

Gateway public routes:

```text
POST /api/inventory/v1/stock-imports
GET  /api/inventory/v1/stock-imports/{id}/preview
POST /api/inventory/v1/stock-imports/{id}/confirm
```

Service internal routes:

```text
POST /api/v1/stock-imports
GET  /api/v1/stock-imports/{stockImport}/preview
POST /api/v1/stock-imports/{stockImport}/confirm
```

Upload request:

```text
Content-Type: multipart/form-data
file: inventory.csv
```

CSV template:

```csv
sku_code,quantity
AO-THUN-M,12
AO-THUN-L,8
```

Preview response:

```json
{
  "stock_import": {
    "id": 1,
    "file_name": "inventory.csv",
    "file_hash": "sha256",
    "status": "PREVIEWED",
    "has_errors": false,
    "confirmed_at": null,
    "created_by": 10,
    "created_at": "2026-08-03T10:00:00.000000Z",
    "lines": [
      {
        "id": 1,
        "row_number": 2,
        "raw_sku_code": "AO-THUN-M",
        "raw_quantity": "12",
        "sku_code": "AO-THUN-M",
        "sku_id": 1,
        "quantity": 12,
        "quantity_before": 5,
        "quantity_after": 12,
        "error_message": null
      }
    ]
  }
}
```

Duplicate file response:

```json
{
  "message": "File này đã được import trước đó."
}
```

Bad header response:

```json
{
  "message": "File CSV phải có đúng hai cột sku_code và quantity."
}
```

Confirm error response:

```json
{
  "message": "Không thể xác nhận file còn dòng lỗi."
}
```

---

## Database Design

### `stock_imports`

```text
id bigint primary key
file_name varchar(255) not null
file_hash varchar(64) not null unique
status varchar(32) not null default PREVIEWED
confirmed_at timestamp nullable
created_by bigint nullable
created_at timestamp nullable
updated_at timestamp nullable
index(status)
index(created_at)
```

### `stock_import_lines`

```text
id bigint primary key
stock_import_id bigint not null foreign key stock_imports.id cascade delete
row_number integer not null
sku_code varchar(64) not null
sku_id bigint nullable foreign key product_skus.id null on delete
raw_sku_code varchar(255) nullable
raw_quantity varchar(255) nullable
quantity integer nullable
quantity_before integer nullable
quantity_after integer nullable
error_message varchar(255) nullable
created_at timestamp nullable
updated_at timestamp nullable
unique(stock_import_id, row_number)
index(stock_import_id)
index(sku_id)
```

Deliberate simplification:

- Không lưu file gốc. Preview lines là audit đủ cho MVP.
- Vẫn lưu raw value theo dòng (`raw_sku_code`, `raw_quantity`) để không mất input lỗi như `quantity = abc`.
- `quantity_before` và `quantity_after` là snapshot tại thời điểm preview, không cập nhật lại sau confirm.
- Không tạo enum PostgreSQL native; dùng string + PHP enum để migration đơn giản.
- `created_by` không FK sang People Service vì không cross-database.

---

## File Map

Create:

```text
services/inventory-service/database/migrations/2026_08_03_000200_create_stock_import_tables.php
services/inventory-service/app/Domain/Enums/StockImportStatus.php
services/inventory-service/app/Models/StockImport.php
services/inventory-service/app/Models/StockImportLine.php
services/inventory-service/app/Application/DTOs/CreateStockImportData.php
services/inventory-service/app/Application/UseCases/PreviewStockImportUseCase.php
services/inventory-service/app/Application/UseCases/GetStockImportPreviewUseCase.php
services/inventory-service/app/Application/UseCases/ConfirmStockImportUseCase.php
services/inventory-service/app/Infrastructure/CsvStockImportParser.php
services/inventory-service/app/Http/Controllers/StockImportController.php
services/inventory-service/app/Http/Requests/StoreStockImportRequest.php
services/inventory-service/app/Http/Resources/StockImportResource.php
services/inventory-service/app/Http/Resources/StockImportLineResource.php
services/inventory-service/tests/Feature/StockImportApiTest.php
frontend/src/features/inventory/api/stockImportApi.ts
frontend/src/features/inventory/components/StockImportPreviewTable.tsx
frontend/src/features/inventory/pages/StockImportPage.tsx
frontend/src/stockImportExperience.test.mjs
```

Modify:

```text
services/inventory-service/routes/api.php
frontend/src/App.tsx
frontend/src/features/health/pages/DashboardPage.tsx
docs/api.md
docs/progress_report_2.8.26.md
README.md
```

No repository interfaces:

- Existing Inventory Service uses Eloquent directly in use cases.
- Stock import is single-service persistence.
- Add repository only when sales/import/borrow share complex queries later.

---

## Domain Flow

### Preview

```text
Client POST /stock-imports
  -> auth.jwt + csrf.double_submit
  -> StoreStockImportRequest validates CSV upload
  -> PreviewStockImportUseCase
  -> CsvStockImportParser validates header and rows
  -> stock_imports row with PREVIEWED
  -> stock_import_lines rows with error_message per row
  -> StockImportResource
  -> JSON preview
```

### Confirm

```text
Client POST /stock-imports/{id}/confirm
  -> auth.jwt + csrf.double_submit
  -> ConfirmStockImportUseCase
  -> DB transaction locks stock_import row
  -> block if status != PREVIEWED
  -> block if any line error exists
  -> for each line call InventoryBalanceService::synchronize(... IMPORT_SYNC ...)
  -> status CONFIRMED, confirmed_at now
  -> StockImportResource
  -> JSON confirmed preview
```

---

## Task 0: Verify Existing Inventory Infrastructure

**Files:**

- Inspect: `services/inventory-service/app/Domain/Services/InventoryBalanceService.php`
- Inspect: `services/inventory-service/app/Domain/Exceptions/InventoryBusinessException.php`
- Inspect: `services/inventory-service/bootstrap/app.php`
- Inspect: `services/inventory-service/app/Models/ProductSku.php`
- Inspect: `services/inventory-service/app/Http/Middleware/AuthenticateJwt.php`
- Inspect: `services/inventory-service/app/Application/DTOs/VerifiedToken.php`
- Inspect: `services/inventory-service/phpunit.xml`
- Modify: `docs/TaskImplementDetailPlan/StockImportUC-11plan.md`

**Interfaces:**

- Confirms `InventoryBalanceService::synchronize()` can be used by UC-11 without changing its public signature.
- Confirms `InventoryBusinessException(string $message, int $status = 422)` supports `409` and `422`.
- Confirms `VerifiedToken->userId` is available from `$request->attributes->get('verified_token')`.
- Produces documented import policy: only active, non-soft-deleted SKU can be imported.

- [x] **Step 1: Verify `InventoryBalanceService::synchronize()`**

Inspect:

```powershell
Get-Content services\inventory-service\app\Domain\Services\InventoryBalanceService.php
```

Expected:

```text
synchronize() rejects negative target quantity.
synchronize() accepts only InventoryTransactionType::ImportSync.
synchronize() delegates to applyTarget().
applyTarget() wraps mutation in DB::transaction().
applyTarget() reads inventory_balances through lockedBalance().
lockedBalance() uses lockForUpdate().
applyTarget() always calls createTransaction(), including quantity_change = 0.
```

If `quantity_change = 0` does not create a transaction, do not change existing behavior blindly. Add backward-compatible support:

```php
public function synchronize(
    ProductSku $sku,
    int $targetQuantity,
    InventoryTransactionType $type,
    ?string $referenceType = null,
    ?string $referenceId = null,
    ?string $reason = null,
    ?int $createdBy = null,
    bool $recordWhenUnchanged = true,
): InventoryTransaction
```

Use `recordWhenUnchanged = true` for UC-11. Preserve old callers because the new parameter has a default.

- [x] **Step 2: Verify zero-change transaction test exists**

Inspect:

```powershell
Select-String -Path services\inventory-service\tests\Unit\InventoryBalanceServiceTest.php -Pattern "quantity_change|targetQuantity|ImportSync" -Context 2,2
```

Expected evidence:

```text
A synchronize test asserts quantity_before, quantity_change and quantity_after.
If no zero-change test exists, keep Task 4 test `test_confirm_writes_import_sync_transaction_when_quantity_does_not_change`.
```

- [x] **Step 3: Verify exception HTTP status mapping**

Inspect:

```powershell
Get-Content services\inventory-service\app\Domain\Exceptions\InventoryBusinessException.php
Get-Content services\inventory-service\bootstrap\app.php
```

Expected:

```text
InventoryBusinessException has public readonly int $status default 422.
bootstrap/app.php renders JSON response with $e->status for api/* requests.
UC-11 can throw InventoryBusinessException('File này đã được import trước đó.', 409).
UC-11 can throw InventoryBusinessException('Không thể xác nhận file còn dòng lỗi.', 422).
```

- [x] **Step 4: Verify `ProductSku` deletion behavior and document inactive policy**

Inspect:

```powershell
Get-Content services\inventory-service\app\Models\ProductSku.php
Get-Content services\inventory-service\database\migrations\2026_08_01_000100_create_inventory_catalog_tables.php
```

Expected:

```text
ProductSku uses SoftDeletes.
product_skus has active boolean and deleted_at.
UC-11 preview must query ProductSku without withTrashed().
UC-11 preview must reject active = false with line error "SKU đã ngừng hoạt động.".
UC-11 preview must treat soft-deleted SKU as missing or inactive; use the same message "SKU đã ngừng hoạt động." only if found withTrashed(), otherwise "Không tìm thấy SKU.".
```

- [x] **Step 5: Verify authentication context**

Inspect:

```powershell
Get-Content services\inventory-service\app\Http\Middleware\AuthenticateJwt.php
Get-Content services\inventory-service\app\Application\DTOs\VerifiedToken.php
Get-Content services\inventory-service\app\Domain\Enums\UserRole.php
Get-Content services\inventory-service\routes\api.php
```

Expected:

```text
AuthenticateJwt stores VerifiedToken in request attribute "verified_token".
VerifiedToken exposes int $userId and UserRole $role.
UserRole supports STORE_MANAGER and STAFF.
Inventory routes are protected by auth.jwt and csrf.double_submit, but not manager-only.
UC-11 controller can read created_by from verified_token.userId.
UC-11 preview and confirm must allow both STORE_MANAGER and STAFF.
```

- [x] **Step 6: Verify migrate safety**

Inspect:

```powershell
Get-Content services\inventory-service\phpunit.xml
```

Expected:

```text
phpunit.xml sets APP_ENV=testing.
phpunit.xml sets DB_CONNECTION=sqlite.
phpunit.xml sets DB_DATABASE=:memory:.
```

Rule for this plan:

```text
Do not run docker compose exec -T inventory-service php artisan migrate:fresh --force as routine verification.
Only run migrate:fresh against explicit testing sqlite env, or use php artisan test which runs RefreshDatabase.
```

Safe migration smoke command:

```powershell
docker compose exec -T inventory-service php artisan migrate:fresh --env=testing --database=sqlite --force
```

Expected:

```text
Command uses testing env, sqlite connection and does not touch inventory-db PostgreSQL volume.
```

- [x] **Step 7: Document Task 0 result before Task 1**

Add a short note in the implementation log or commit body:

```text
Verified inventory infrastructure before UC-11:
- synchronize() uses DB transaction + lockForUpdate and records zero-change IMPORT_SYNC.
- InventoryBusinessException supports 409/422 JSON mapping.
- ProductSku uses SoftDeletes; UC-11 imports only active, non-soft-deleted SKU.
- verified_token.userId is available for created_by; STAFF and STORE_MANAGER both allowed.
- migrate:fresh routine verification must use testing sqlite only.
```

- [x] **Step 8: Commit plan verification note if docs changed**

```powershell
git add docs/TaskImplementDetailPlan/StockImportUC-11plan.md
git commit -m "docs: add stock import preflight checks"
```

Task 0 result, verified on 2026-08-03:

```text
InventoryBalanceService::synchronize() uses DB::transaction(), locks inventory_balances with lockForUpdate(), and creates an IMPORT_SYNC transaction even when quantity_change = 0.
InventoryBalanceServiceTest passed: 6 tests, 29 assertions.
InventoryBusinessException supports custom HTTP status; bootstrap/app.php maps it to JSON for api/* requests.
ProductSku uses SoftDeletes and active boolean. UC-11 policy: import only active, non-soft-deleted SKU; inactive or soft-deleted SKU returns line error "SKU đã ngừng hoạt động.".
AuthenticateJwt stores VerifiedToken in request attribute "verified_token"; VerifiedToken exposes userId and role. Inventory routes are auth + CSRF protected, not manager-only.
InventoryAuthTest passed: 7 tests, 13 assertions.
phpunit.xml uses APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:.
Safe migrate check passed with: docker compose exec -T inventory-service php artisan migrate:fresh --env=testing --database=sqlite --force.
```

---

## Task 1: Stock Import Schema And Models

**Files:**

- Create: `services/inventory-service/database/migrations/2026_08_03_000200_create_stock_import_tables.php`
- Create: `services/inventory-service/app/Domain/Enums/StockImportStatus.php`
- Create: `services/inventory-service/app/Models/StockImport.php`
- Create: `services/inventory-service/app/Models/StockImportLine.php`
- Test: `services/inventory-service/tests/Feature/StockImportApiTest.php`

**Interfaces:**

- Produces enum `StockImportStatus` with `Previewed = 'PREVIEWED'`, `Confirmed = 'CONFIRMED'`.
- Produces `StockImport::lines(): HasMany`.
- Produces `StockImportLine::stockImport(): BelongsTo`.
- Produces `StockImportLine::sku(): BelongsTo`.

- [x] **Step 1: Write failing schema/model test**

Create `StockImportApiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Domain\Enums\StockImportStatus;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\StockImport;
use App\Models\StockImportLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class StockImportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_import_tables_exist(): void
    {
        self::assertTrue(Schema::hasTable('stock_imports'));
        self::assertTrue(Schema::hasTable('stock_import_lines'));
        self::assertTrue(Schema::hasColumns('stock_imports', [
            'file_name',
            'file_hash',
            'status',
            'confirmed_at',
            'created_by',
        ]));
        self::assertTrue(Schema::hasColumns('stock_import_lines', [
            'stock_import_id',
            'row_number',
            'sku_code',
            'sku_id',
            'raw_sku_code',
            'raw_quantity',
            'quantity',
            'quantity_before',
            'quantity_after',
            'error_message',
        ]));
    }

    public function test_stock_import_model_relationships_work(): void
    {
        $product = Product::query()->create([
            'product_code' => 'AO-THUN',
            'name' => 'Áo thun',
            'active' => true,
        ]);
        $sku = ProductSku::query()->create([
            'product_id' => $product->id,
            'sku_code' => 'AO-THUN-M',
            'size' => 'M',
            'active' => true,
        ]);
        $stockImport = StockImport::query()->create([
            'file_name' => 'inventory.csv',
            'file_hash' => str_repeat('a', 64),
            'status' => StockImportStatus::Previewed->value,
            'created_by' => 10,
        ]);
        StockImportLine::query()->create([
            'stock_import_id' => $stockImport->id,
            'row_number' => 2,
            'sku_code' => 'AO-THUN-M',
            'raw_sku_code' => ' ao-thun-m ',
            'raw_quantity' => '12',
            'sku_id' => $sku->id,
            'quantity' => 12,
            'quantity_before' => 5,
            'quantity_after' => 12,
        ]);

        self::assertSame('AO-THUN-M', $stockImport->lines()->first()->sku->sku_code);
        self::assertSame(StockImportStatus::Previewed, $stockImport->status);
    }
}
```

- [x] **Step 2: Run failing test**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=StockImportApiTest
```

Expected: FAIL because stock import tables/models do not exist.

- [x] **Step 3: Create migration**

Create migration with:

```php
Schema::create('stock_imports', function (Blueprint $table): void {
    $table->id();
    $table->string('file_name');
    $table->string('file_hash', 64)->unique();
    $table->string('status', 32)->default('PREVIEWED')->index();
    $table->timestamp('confirmed_at')->nullable();
    $table->unsignedBigInteger('created_by')->nullable();
    $table->timestamps();
    $table->index('created_at');
});

Schema::create('stock_import_lines', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('stock_import_id')->constrained('stock_imports')->cascadeOnDelete();
    $table->unsignedInteger('row_number');
    $table->string('sku_code', 64);
    $table->foreignId('sku_id')->nullable()->constrained('product_skus')->nullOnDelete();
    $table->string('raw_sku_code')->nullable();
    $table->string('raw_quantity')->nullable();
    $table->integer('quantity')->nullable();
    $table->integer('quantity_before')->nullable();
    $table->integer('quantity_after')->nullable();
    $table->string('error_message')->nullable();
    $table->timestamps();
    $table->unique(['stock_import_id', 'row_number']);
    $table->index('sku_id');
});
```

Down order:

```php
Schema::dropIfExists('stock_import_lines');
Schema::dropIfExists('stock_imports');
```

- [x] **Step 4: Create enum and models**

`StockImportStatus.php`:

```php
<?php

namespace App\Domain\Enums;

enum StockImportStatus: string
{
    case Previewed = 'PREVIEWED';
    case Confirmed = 'CONFIRMED';
}
```

`StockImport.php`:

```php
protected $fillable = ['file_name', 'file_hash', 'status', 'confirmed_at', 'created_by'];

protected function casts(): array
{
    return [
        'status' => StockImportStatus::class,
        'confirmed_at' => 'datetime',
    ];
}

public function lines(): HasMany
{
    return $this->hasMany(StockImportLine::class);
}
```

`StockImportLine.php`:

```php
protected $fillable = [
    'stock_import_id',
    'row_number',
    'sku_code',
    'sku_id',
    'raw_sku_code',
    'raw_quantity',
    'quantity',
    'quantity_before',
    'quantity_after',
    'error_message',
];

public function stockImport(): BelongsTo
{
    return $this->belongsTo(StockImport::class);
}

public function sku(): BelongsTo
{
    return $this->belongsTo(ProductSku::class);
}
```

- [x] **Step 5: Run schema/model test**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=StockImportApiTest
```

Expected: PASS.

- [x] **Step 6: Commit**

```powershell
git add services/inventory-service/database/migrations services/inventory-service/app/Domain/Enums/StockImportStatus.php services/inventory-service/app/Models/StockImport.php services/inventory-service/app/Models/StockImportLine.php services/inventory-service/tests/Feature/StockImportApiTest.php
git commit -m "feat(inventory): add stock import schema"
```

---

## Task 2: CSV Parser

**Files:**

- Create: `services/inventory-service/app/Infrastructure/CsvStockImportParser.php`
- Test: `services/inventory-service/tests/Feature/StockImportApiTest.php`

**Interfaces:**

- `CsvStockImportParser::parse(string $path): array`
- Returns array rows with keys `row_number`, `raw_sku_code`, `raw_quantity`, `sku_code`, `quantity`, `error_message`.
- Throws `InventoryBusinessException('File CSV phải có đúng hai cột sku_code và quantity.')` for wrong header.

- [x] **Step 1: Add failing parser tests**

Add tests:

```php
public function test_csv_parser_accepts_template_and_normalizes_sku_code(): void
{
    $path = tempnam(sys_get_temp_dir(), 'stock-import-');
    file_put_contents($path, "sku_code,quantity\n ao-thun-m ,12\n");

    $rows = app(\App\Infrastructure\CsvStockImportParser::class)->parse($path);

    self::assertSame([
        [
            'row_number' => 2,
            'raw_sku_code' => ' ao-thun-m ',
            'raw_quantity' => '12',
            'sku_code' => 'AO-THUN-M',
            'quantity' => 12,
            'error_message' => null,
        ],
    ], $rows);
}

public function test_csv_parser_rejects_wrong_header(): void
{
    $path = tempnam(sys_get_temp_dir(), 'stock-import-');
    file_put_contents($path, "code,qty\nAO-THUN-M,12\n");

    $this->expectException(\App\Domain\Exceptions\InventoryBusinessException::class);
    $this->expectExceptionMessage('File CSV phải có đúng hai cột sku_code và quantity.');

    app(\App\Infrastructure\CsvStockImportParser::class)->parse($path);
}

public function test_csv_parser_marks_invalid_quantity_rows(): void
{
    $path = tempnam(sys_get_temp_dir(), 'stock-import-');
    file_put_contents($path, "sku_code,quantity\nAO-THUN-M,-1\nAO-THUN-L,abc\n");

    $rows = app(\App\Infrastructure\CsvStockImportParser::class)->parse($path);

    self::assertSame('Số lượng không được âm.', $rows[0]['error_message']);
    self::assertSame('-1', $rows[0]['raw_quantity']);
    self::assertSame('Số lượng phải là số nguyên.', $rows[1]['error_message']);
    self::assertSame('abc', $rows[1]['raw_quantity']);
}
```

- [x] **Step 2: Run failing parser tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=csv_parser
```

Expected: FAIL because parser does not exist.

- [x] **Step 3: Implement parser**

Rules:

```text
Use fopen/fgetcsv from PHP stdlib.
Header must be exactly sku_code,quantity after trim + lowercase.
Skip completely blank rows.
row_number is physical CSV line number starting at 2 for first data row.
sku_code = strtoupper(trim(value)).
raw_sku_code stores the original first-column value before trim/uppercase.
raw_quantity stores the original second-column value before validation.
Empty sku_code gets error "Mã SKU là bắt buộc."
quantity uses regex /^\d+$/ before cast.
Negative numeric strings get error "Số lượng không được âm."
Non-integer values get error "Số lượng phải là số nguyên."
Valid quantity cast to int.
Do not check SKU existence here; use case owns database lookup.
```

Core implementation shape:

```php
public function parse(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new InventoryBusinessException('Không đọc được file CSV.');
    }

    try {
        $header = fgetcsv($handle);
        if ($header === false || array_map(fn ($value) => strtolower(trim((string) $value)), $header) !== ['sku_code', 'quantity']) {
            throw new InventoryBusinessException('File CSV phải có đúng hai cột sku_code và quantity.');
        }

        $rows = [];
        $rowNumber = 1;
        while (($columns = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if ($columns === [null] || trim(implode('', array_map('strval', $columns))) === '') {
                continue;
            }

            $rawSkuCode = (string) ($columns[0] ?? '');
            $rawQuantity = (string) ($columns[1] ?? '');
            $skuCode = strtoupper(trim($rawSkuCode));
            $trimmedQuantity = trim($rawQuantity);
            $error = null;
            $quantity = null;

            if ($skuCode === '') {
                $error = 'Mã SKU là bắt buộc.';
            } elseif (str_starts_with($trimmedQuantity, '-')) {
                $error = 'Số lượng không được âm.';
            } elseif (! preg_match('/^\d+$/', $trimmedQuantity)) {
                $error = 'Số lượng phải là số nguyên.';
            } else {
                $quantity = (int) $trimmedQuantity;
            }

            $rows[] = compact('rowNumber', 'rawSkuCode', 'rawQuantity', 'skuCode', 'quantity', 'error');
        }

        return array_map(fn (array $row): array => [
            'row_number' => $row['rowNumber'],
            'raw_sku_code' => $row['rawSkuCode'],
            'raw_quantity' => $row['rawQuantity'],
            'sku_code' => $row['skuCode'],
            'quantity' => $row['quantity'],
            'error_message' => $row['error'],
        ], $rows);
    } finally {
        fclose($handle);
    }
}
```

- [x] **Step 4: Run parser tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=csv_parser
```

Expected: PASS.

- [x] **Step 5: Commit**

```powershell
git add services/inventory-service/app/Infrastructure/CsvStockImportParser.php services/inventory-service/tests/Feature/StockImportApiTest.php
git commit -m "feat(inventory): add stock import csv parser"
```

---

## Task 3: Preview Stock Import API

**Files:**

- Create: `services/inventory-service/app/Application/DTOs/CreateStockImportData.php`
- Create: `services/inventory-service/app/Application/UseCases/PreviewStockImportUseCase.php`
- Create: `services/inventory-service/app/Application/UseCases/GetStockImportPreviewUseCase.php`
- Create: `services/inventory-service/app/Http/Requests/StoreStockImportRequest.php`
- Create: `services/inventory-service/app/Http/Resources/StockImportResource.php`
- Create: `services/inventory-service/app/Http/Resources/StockImportLineResource.php`
- Create: `services/inventory-service/app/Http/Controllers/StockImportController.php`
- Modify: `services/inventory-service/routes/api.php`
- Test: `services/inventory-service/tests/Feature/StockImportApiTest.php`

**Interfaces:**

- `CreateStockImportData::__construct(UploadedFile $file, ?int $createdBy)`.
- `PreviewStockImportUseCase::execute(CreateStockImportData $data): StockImport`.
- `GetStockImportPreviewUseCase::execute(StockImport $stockImport): StockImport`.
- `StockImportController::store(StoreStockImportRequest $request, PreviewStockImportUseCase $useCase): JsonResponse`.
- `StockImportController::preview(StockImport $stockImport, GetStockImportPreviewUseCase $useCase): JsonResponse`.

- [x] **Step 1: Add failing preview API tests**

Add helper:

```php
private function csvUpload(string $content, string $name = 'inventory.csv'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'stock-import-');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, 'text/csv', null, true);
}
```

Add tests:

```php
public function test_preview_stock_import_creates_import_and_lines(): void
public function test_preview_marks_missing_sku_error_by_row(): void
public function test_preview_marks_duplicate_sku_in_same_file(): void
public function test_preview_rejects_duplicate_file_hash(): void
public function test_preview_preserves_raw_invalid_csv_values(): void
public function test_get_preview_does_not_require_csrf(): void
public function test_staff_can_preview_stock_import(): void
```

Key assertions:

```php
$this->actingWithInventoryCookie(userId: 10, role: 'STORE_MANAGER')
    ->withCsrfCookie()
    ->post('/api/v1/stock-imports', [
        'file' => $this->csvUpload("sku_code,quantity\nAO-THUN-M,12\n"),
    ])
    ->assertCreated()
    ->assertJsonPath('stock_import.status', 'PREVIEWED')
    ->assertJsonPath('stock_import.has_errors', false)
    ->assertJsonPath('stock_import.lines.0.sku_code', 'AO-THUN-M')
    ->assertJsonPath('stock_import.lines.0.quantity_before', 5)
    ->assertJsonPath('stock_import.lines.0.quantity_after', 12);
```

Missing SKU assertion:

```php
->assertJsonPath('stock_import.has_errors', true)
->assertJsonPath('stock_import.lines.0.error_message', 'Không tìm thấy SKU.');
```

Duplicate SKU row assertion:

```php
->assertJsonPath('stock_import.lines.1.error_message', 'SKU bị lặp trong file.');
```

Duplicate hash assertion:

```php
->assertStatus(409)
->assertJsonPath('message', 'File này đã được import trước đó.');
```

Raw value assertion:

```php
->assertJsonPath('stock_import.lines.0.raw_sku_code', ' ao-thun-m ')
->assertJsonPath('stock_import.lines.0.raw_quantity', 'abc')
->assertJsonPath('stock_import.lines.0.quantity', null)
->assertJsonPath('stock_import.lines.0.error_message', 'Số lượng phải là số nguyên.');
```

- [x] **Step 2: Run failing preview tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=preview
```

Expected: FAIL because API does not exist.

- [x] **Step 3: Implement request and DTO**

`StoreStockImportRequest`:

```php
public function rules(): array
{
    return [
        'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
    ];
}

public function messages(): array
{
    return [
        'file.required' => 'Vui lòng chọn file CSV.',
        'file.file' => 'File tải lên không hợp lệ.',
        'file.mimes' => 'File phải là CSV.',
        'file.max' => 'File CSV không được vượt quá 2MB.',
    ];
}
```

`CreateStockImportData`:

```php
public function __construct(
    public readonly UploadedFile $file,
    public readonly ?int $createdBy,
) {
}
```

- [x] **Step 4: Implement preview use cases**

`PreviewStockImportUseCase` behavior:

```text
Compute hash with hash_file('sha256', $data->file->getRealPath()).
Parse CSV.
Create stock_import with file_name, file_hash, PREVIEWED, created_by inside DB transaction.
Catch QueryException for unique violation on file_hash and throw InventoryBusinessException('File này đã được import trước đó.', 409). This handles two workers uploading the same file at the same time.
For each parsed row:
  Find ProductSku by sku_code with withTrashed() to distinguish missing vs inactive/soft-deleted.
  If parser error exists, keep it.
  If SKU code appeared earlier in this file and current row has no parser error, set "SKU bị lặp trong file."
  If SKU missing and current row has no parser error, set "Không tìm thấy SKU."
  If SKU exists but deleted_at is not null or active is false and current row has no parser error, set "SKU đã ngừng hoạt động."
  If SKU exists and row valid, load balance quantity; quantity_before = balance.quantity; quantity_after = parsed quantity.
  Create stock_import_lines with raw_sku_code and raw_quantity from parser.
Return stock import loaded with lines ordered by row_number.
```

Important: use `DB::transaction()` around stock import + lines creation.

Duplicate race handling:

`$resolvedRows` is the parsed rows after SKU lookup, duplicate-row detection and balance snapshot calculation.

```php
try {
    return DB::transaction(function () use ($data, $hash, $rows): StockImport {
        $stockImport = StockImport::query()->create([
            'file_name' => $data->file->getClientOriginalName(),
            'file_hash' => $hash,
            'status' => StockImportStatus::Previewed,
            'created_by' => $data->createdBy,
        ]);

        foreach ($resolvedRows as $row) {
            $stockImport->lines()->create([
                'row_number' => $row['row_number'],
                'raw_sku_code' => $row['raw_sku_code'],
                'raw_quantity' => $row['raw_quantity'],
                'sku_code' => $row['sku_code'],
                'sku_id' => $row['sku_id'],
                'quantity' => $row['quantity'],
                'quantity_before' => $row['quantity_before'],
                'quantity_after' => $row['quantity_after'],
                'error_message' => $row['error_message'],
            ]);
        }

        return $stockImport->load(['lines' => fn ($query) => $query->orderBy('row_number')]);
    });
} catch (QueryException $exception) {
    if (($exception->errorInfo[0] ?? null) === '23505') {
        throw new InventoryBusinessException('File này đã được import trước đó.', 409);
    }

    throw $exception;
}
```

`GetStockImportPreviewUseCase`:

```php
public function execute(StockImport $stockImport): StockImport
{
    return $stockImport->load(['lines' => fn ($query) => $query->orderBy('row_number')]);
}
```

- [x] **Step 5: Implement resources**

`StockImportLineResource` maps:

```php
[
    'id' => $this->id,
    'row_number' => $this->row_number,
    'raw_sku_code' => $this->raw_sku_code,
    'raw_quantity' => $this->raw_quantity,
    'sku_code' => $this->sku_code,
    'sku_id' => $this->sku_id,
    'quantity' => $this->quantity,
    'quantity_before' => $this->quantity_before,
    'quantity_after' => $this->quantity_after,
    'error_message' => $this->error_message,
]
```

`StockImportResource` maps:

```php
[
    'id' => $this->id,
    'file_name' => $this->file_name,
    'file_hash' => $this->file_hash,
    'status' => $this->status->value,
    'has_errors' => $this->lines->contains(fn ($line) => $line->error_message !== null),
    'confirmed_at' => $this->confirmed_at?->toJSON(),
    'created_by' => $this->created_by,
    'created_at' => $this->created_at?->toJSON(),
    'lines' => StockImportLineResource::collection($this->whenLoaded('lines')),
]
```

- [x] **Step 6: Implement controller and routes**

Controller:

```php
public function store(StoreStockImportRequest $request, PreviewStockImportUseCase $useCase): JsonResponse
{
    /** @var VerifiedToken $verified */
    $verified = $request->attributes->get('verified_token');

    $stockImport = $useCase->execute(new CreateStockImportData(
        file: $request->file('file'),
        createdBy: $verified->userId,
    ));

    return response()->json(['stock_import' => new StockImportResource($stockImport)], 201);
}

public function preview(StockImport $stockImport, GetStockImportPreviewUseCase $useCase): JsonResponse
{
    return response()->json(['stock_import' => new StockImportResource($useCase->execute($stockImport))]);
}
```

Routes:

```php
Route::post('/stock-imports', [StockImportController::class, 'store']);
Route::get('/stock-imports/{stockImport}/preview', [StockImportController::class, 'preview']);
```

- [x] **Step 7: Run preview API tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=preview
```

Expected: PASS.

- [x] **Step 8: Commit**

```powershell
git add services/inventory-service/app services/inventory-service/routes/api.php services/inventory-service/tests/Feature/StockImportApiTest.php
git commit -m "feat(inventory): add stock import preview API"
```

---

## Task 4: Confirm Stock Import API

**Files:**

- Create: `services/inventory-service/app/Application/UseCases/ConfirmStockImportUseCase.php`
- Modify: `services/inventory-service/app/Http/Controllers/StockImportController.php`
- Modify: `services/inventory-service/routes/api.php`
- Test: `services/inventory-service/tests/Feature/StockImportApiTest.php`

**Interfaces:**

- `ConfirmStockImportUseCase::execute(StockImport $stockImport, ?int $createdBy): StockImport`.
- `StockImportController::confirm(StockImport $stockImport, ConfirmStockImportUseCase $useCase): JsonResponse`.

- [x] **Step 1: Add failing confirm API tests**

Add tests:

```php
public function test_confirm_stock_import_synchronizes_balances_and_writes_import_sync_transactions(): void
public function test_confirm_writes_import_sync_transaction_when_quantity_does_not_change(): void
public function test_confirm_does_not_mutate_preview_snapshot(): void
public function test_confirm_blocks_import_with_line_errors(): void
public function test_confirm_cannot_run_twice(): void
public function test_confirm_requires_csrf(): void
public function test_staff_can_confirm_stock_import(): void
```

Key success assertions:

```php
$this->actingWithInventoryCookie(userId: 10, role: 'STORE_MANAGER')
    ->withCsrfCookie()
    ->postJson("/api/v1/stock-imports/{$stockImport->id}/confirm")
    ->assertOk()
    ->assertJsonPath('stock_import.status', 'CONFIRMED')
    ->assertJsonPath('stock_import.confirmed_at', fn ($value) => is_string($value));

$this->assertDatabaseHas('inventory_balances', [
    'sku_id' => $sku->id,
    'quantity' => 12,
]);

$this->assertDatabaseHas('inventory_transactions', [
    'sku_id' => $sku->id,
    'type' => 'IMPORT_SYNC',
    'quantity_before' => 5,
    'quantity_change' => 7,
    'quantity_after' => 12,
    'reference_type' => 'stock_import',
    'reference_id' => (string) $stockImport->id,
    'reason' => 'Đồng bộ tồn kho từ file CSV.',
    'created_by' => 10,
]);
```

Zero-change `IMPORT_SYNC` assertion:

```php
$this->assertDatabaseHas('inventory_transactions', [
    'sku_id' => $sku->id,
    'type' => 'IMPORT_SYNC',
    'quantity_before' => 5,
    'quantity_change' => 0,
    'quantity_after' => 5,
    'reference_type' => 'stock_import',
    'reference_id' => (string) $stockImport->id,
]);
```

Immutable preview assertion:

```php
$line->refresh();
self::assertSame(3, $line->quantity_before);
self::assertSame(12, $line->quantity_after);
```

Line-error block assertion:

```php
->assertStatus(422)
->assertJsonPath('message', 'Không thể xác nhận file còn dòng lỗi.');
```

Second confirm assertion:

```php
->assertStatus(409)
->assertJsonPath('message', 'File import này đã được xác nhận.');
```

CSRF assertion:

```php
->assertStatus(419);
```

- [x] **Step 2: Run failing confirm tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=confirm
```

Expected: FAIL because confirm endpoint does not exist.

- [x] **Step 3: Implement confirm use case**

Behavior:

```text
Use DB::transaction().
Lock stock_imports row with lockForUpdate().
Reload lines ordered by row_number.
If status is CONFIRMED, throw InventoryBusinessException('File import này đã được xác nhận.', 409).
If any line has error_message, throw InventoryBusinessException('Không thể xác nhận file còn dòng lỗi.').
For each line:
  Re-fetch ProductSku by sku_id.
  Call InventoryBalanceService::synchronize(
    sku: $sku,
    targetQuantity: $line->quantity,
    type: InventoryTransactionType::ImportSync,
    referenceType: 'stock_import',
    referenceId: (string) $stockImport->id,
    reason: 'Đồng bộ tồn kho từ file CSV.',
    createdBy: $createdBy,
  ).
  Do not update stock_import_lines.quantity_before or stock_import_lines.quantity_after. Preview snapshot is immutable.
Set status CONFIRMED, confirmed_at now, save.
Return loaded stock import with lines.
```

Use case constructor:

```php
public function __construct(private readonly InventoryBalanceService $balanceService)
{
}
```

- [x] **Step 4: Implement controller method and route**

Controller:

```php
public function confirm(StockImport $stockImport, ConfirmStockImportUseCase $useCase): JsonResponse
{
    /** @var VerifiedToken $verified */
    $verified = request()->attributes->get('verified_token');

    return response()->json([
        'stock_import' => new StockImportResource($useCase->execute($stockImport, $verified->userId)),
    ]);
}
```

Route:

```php
Route::post('/stock-imports/{stockImport}/confirm', [StockImportController::class, 'confirm']);
```

- [x] **Step 5: Run confirm tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=confirm
```

Expected: PASS.

- [x] **Step 6: Run full stock import tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=StockImportApiTest
```

Expected: PASS.

- [x] **Step 7: Commit**

```powershell
git add services/inventory-service/app services/inventory-service/routes/api.php services/inventory-service/tests/Feature/StockImportApiTest.php
git commit -m "feat(inventory): confirm stock imports"
```

---

## Task 5: Stock Import React API And Route

**Files:**

- Create: `frontend/src/features/inventory/api/stockImportApi.ts`
- Create: `frontend/src/features/inventory/pages/StockImportPage.tsx`
- Create: `frontend/src/features/inventory/components/StockImportPreviewTable.tsx`
- Modify: `frontend/src/App.tsx`
- Modify: `frontend/src/features/health/pages/DashboardPage.tsx`
- Test: `frontend/src/stockImportExperience.test.mjs`

**Interfaces:**

- `uploadStockImport(file: File): Promise<StockImport>`.
- `getStockImportPreview(id: number): Promise<StockImport>`.
- `confirmStockImport(id: number): Promise<StockImport>`.
- Route: `/inventory/import`.

- [x] **Step 1: Use frontend design skill**

Run relevant taste/design skill before UI edits:

```text
Use design-taste-frontend or redesign-existing-projects for LazyManager Cozy/Lazy operator UI.
```

Expected design direction:

```text
One focused work page.
No marketing hero.
CSV upload control first.
Preview table below.
Error rows visually clear but calm.
Confirm button disabled when preview has errors or already confirmed.
```

- [x] **Step 2: Add frontend route policy test**

Create `stockImportExperience.test.mjs`:

```js
import fs from 'node:fs'
import assert from 'node:assert/strict'

const app = fs.readFileSync(new URL('./App.tsx', import.meta.url), 'utf8')

assert.match(app, /path="\/inventory\/import"/)
assert.doesNotMatch(app, /<RequireManager>\s*<StockImportPage/)

console.log('stock import route policy ok')
```

- [x] **Step 3: Create API client**

Types:

```ts
export type StockImportLine = {
  id: number
  row_number: number
  raw_sku_code: string | null
  raw_quantity: string | null
  sku_code: string
  sku_id: number | null
  quantity: number | null
  quantity_before: number | null
  quantity_after: number | null
  error_message: string | null
}

export type StockImport = {
  id: number
  file_name: string
  file_hash: string
  status: 'PREVIEWED' | 'CONFIRMED'
  has_errors: boolean
  confirmed_at: string | null
  created_by: number | null
  created_at: string | null
  lines: StockImportLine[]
}
```

Functions:

```ts
export async function uploadStockImport(file: File): Promise<StockImport> {
  const formData = new FormData()
  formData.append('file', file)
  const response = await apiClient.post<{ stock_import: StockImport }>('/api/inventory/v1/stock-imports', formData)
  return response.stock_import
}

export async function getStockImportPreview(id: number): Promise<StockImport> {
  const response = await apiClient.get<{ stock_import: StockImport }>(`/api/inventory/v1/stock-imports/${id}/preview`)
  return response.stock_import
}

export async function confirmStockImport(id: number): Promise<StockImport> {
  const response = await apiClient.post<{ stock_import: StockImport }>(`/api/inventory/v1/stock-imports/${id}/confirm`)
  return response.stock_import
}
```

- [x] **Step 4: Create preview table component**

Props:

```ts
type StockImportPreviewTableProps = {
  lines: StockImportLine[]
}
```

Columns:

```text
Dòng
Mã SKU
SKU gốc
Số lượng gốc
Tồn trước
Số lượng mới
Tồn sau
Lỗi
```

States:

```text
If lines empty: "Chưa có dữ liệu preview."
Rows with error_message show the message.
Rows without error show "Hợp lệ".
```

- [x] **Step 5: Create page**

Required labels:

```text
Nhập tồn kho
Chọn file CSV
Xem trước
Xác nhận đồng bộ
File phải có cột sku_code và quantity.
Chưa có file nhập tồn.
Không tải được preview nhập tồn.
File còn lỗi, vui lòng sửa CSV rồi tải lại.
Đã đồng bộ tồn kho.
```

Behavior:

```text
Initial state shows upload form and empty message.
Selecting file stores File in state.
Click Xem trước calls uploadStockImport(file).
Show loading while uploading.
Show preview summary: file name, status, valid line count, error line count.
Disable confirm if no preview, preview.has_errors, preview.status === 'CONFIRMED', or request loading.
Click Xác nhận đồng bộ calls confirmStockImport(preview.id).
After confirm, replace preview with confirmed response.
Show API error message from response.message when available.
```

- [x] **Step 6: Add route and navigation**

In `App.tsx`:

```tsx
<Route path="/inventory/import" element={<StockImportPage />} />
```

Do not wrap in `RequireManager`.

In dashboard/navigation:

```text
Nhập tồn kho
```

Link URL:

```text
/inventory/import
```

- [x] **Step 7: Run frontend tests and build**

Run:

```powershell
npm --prefix frontend test
npm --prefix frontend run build
```

Expected: PASS.

- [x] **Step 8: Commit**

```powershell
git add frontend/src/App.tsx frontend/src/features/inventory frontend/src/features/health/pages/DashboardPage.tsx frontend/src/stockImportExperience.test.mjs
git commit -m "feat(frontend): add stock import page"
```

---

## Task 6: Docs And Final Verification

**Files:**

- Modify: `docs/api.md`
- Modify: `docs/progress_report_2.8.26.md`
- Modify: `README.md`

**Interfaces:**

- Docs state UC-11 Stock Import is implemented after this task.
- API docs mark stock import endpoints implemented.
- Future endpoints remain clearly labeled as future: Daily Sales, Borrow, Stock Count.

- [ ] **Step 1: Update docs/api.md**

Under Inventory Service:

```text
Implemented:
GET    /api/inventory/v1/products?search=
POST   /api/inventory/v1/products
GET    /api/inventory/v1/products/{id}
PUT    /api/inventory/v1/products/{id}
DELETE /api/inventory/v1/products/{id}
POST   /api/inventory/v1/products/{id}/skus
PUT    /api/inventory/v1/skus/{id}
DELETE /api/inventory/v1/skus/{id}
GET    /api/inventory/v1/inventory?search=
GET    /api/inventory/v1/inventory/{skuId}/transactions
POST   /api/inventory/v1/stock-imports
GET    /api/inventory/v1/stock-imports/{id}/preview
POST   /api/inventory/v1/stock-imports/{id}/confirm

Future:
POST   /api/inventory/v1/daily-sales
POST   /api/inventory/v1/daily-sales/{id}/confirm
POST   /api/inventory/v1/daily-sales/{id}/cancel
POST   /api/inventory/v1/borrow-records
POST   /api/inventory/v1/borrow-records/{id}/return
POST   /api/inventory/v1/stock-counts
GET    /api/inventory/v1/stock-counts/{id}/export-csv
PUT    /api/inventory/v1/stock-counts/{id}/lines
```

Add stock import behavior:

```text
Stock Import UC-11:
- `POST /stock-imports` upload CSV field `file`, tạo preview và trả `201`.
- CSV MVP chỉ hỗ trợ header `sku_code,quantity`.
- SKU không tồn tại, quantity âm/không phải số nguyên, SKU lặp trong file được báo lỗi theo dòng.
- Preview lưu raw input theo dòng bằng `raw_sku_code` và `raw_quantity`.
- Preview snapshot không thay đổi sau confirm.
- File hash trùng trả `409`.
- `GET /stock-imports/{id}/preview` trả preview, không yêu cầu CSRF.
- `POST /stock-imports/{id}/confirm` yêu cầu CSRF, chặn nếu còn dòng lỗi hoặc đã confirmed.
- Confirm đồng bộ tồn tuyệt đối bằng `InventoryBalanceService::synchronize()` và ghi transaction `IMPORT_SYNC`.
```

- [ ] **Step 2: Update progress docs**

In `docs/progress_report_2.8.26.md` after implementation:

```text
UC-11 Import tồn kho: DONE.
Tiến trình: 11 / 20 DONE + 1 PARTIAL.
UC-03 vẫn PARTIAL theo quyết định docs-only: STAFF chưa xem được employees trong code hiện tại.
```

In `README.md`:

```text
Current state includes Stock Import CSV preview/confirm.
Next: Daily Sales draft/preview.
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
Route list includes:
POST api/v1/stock-imports
GET api/v1/stock-imports/{stockImport}/preview
POST api/v1/stock-imports/{stockImport}/confirm
```

- [ ] **Step 4: Run frontend verification**

Run:

```powershell
npm --prefix frontend test
npm --prefix frontend run build
```

Expected: PASS.

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
git add docs/api.md docs/progress_report_2.8.26.md README.md
git commit -m "docs: document stock import workflow"
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
- `/inventory/import` is accessible to `STORE_MANAGER` and `STAFF`.

Manual demo CSV:

```csv
sku_code,quantity
AO-THUN-M,12
AO-THUN-L,8
```

Manual error CSV:

```csv
sku_code,quantity
AO-THUN-M,-1
SKU-KHONG-CO,5
AO-THUN-M,7
```

Expected manual result:

- Valid CSV previews and confirms.
- Error CSV shows row-level errors and confirm button disabled.
- Confirm writes `IMPORT_SYNC` transaction history visible on `/inventory`.

---

## Acceptance Checklist

- [ ] `stock_imports` table exists with unique `file_hash`.
- [ ] Task 0 verifies inventory service transaction, exception, auth, SKU and migration safety before UC-11 files.
- [ ] `stock_import_lines` stores row-level preview.
- [ ] `stock_import_lines` preserves raw CSV values in `raw_sku_code` and `raw_quantity`.
- [ ] Preview snapshot `quantity_before` and `quantity_after` is not mutated during confirm.
- [ ] CSV parser accepts exact `sku_code,quantity` header.
- [ ] Wrong header returns Vietnamese validation error.
- [ ] SKU code is normalized by trim + uppercase.
- [ ] Missing SKU creates line error.
- [ ] Inactive or soft-deleted SKU creates `SKU đã ngừng hoạt động.` line error.
- [ ] Negative quantity creates line error.
- [ ] Non-integer quantity creates line error.
- [ ] Duplicate SKU in one file creates line error.
- [ ] Duplicate file hash returns `409`.
- [ ] Duplicate file hash race relies on DB unique constraint and maps unique violation to `409`.
- [ ] Preview can be fetched by GET without CSRF.
- [ ] Preview upload requires CSRF.
- [ ] Confirm requires CSRF.
- [ ] STAFF can preview and confirm.
- [ ] STORE_MANAGER can preview and confirm.
- [ ] Confirm blocks imports with line errors.
- [ ] Confirm cannot run twice.
- [ ] Confirm updates balances through `InventoryBalanceService`.
- [ ] Confirm creates `IMPORT_SYNC` ledger rows.
- [ ] Confirm creates `IMPORT_SYNC` ledger row even when `quantity_change = 0`.
- [ ] Confirm stores `reference_type = stock_import` and `reference_id = stock_import id`.
- [ ] React `/inventory/import` uploads CSV and displays preview.
- [ ] React confirm button disables on errors and after confirmed.
- [ ] Docs mark stock import implemented and future endpoints clearly separated.

---

## Self-Review

- Spec coverage: UC-11 upload, preview, confirm, missing SKU errors, invalid quantity errors, raw CSV preservation, immutable preview snapshot, duplicate file hash race handling, zero-change `IMPORT_SYNC` transaction and React page all have tasks.
- Placeholder scan: no empty markers, no deferred implementation details, no undefined future hooks.
- Type consistency: `StockImportStatus`, `StockImport`, `StockImportLine`, `raw_sku_code`, `raw_quantity`, `CreateStockImportData`, `CsvStockImportParser`, preview/confirm use cases and route names are defined before use.
- Scope control: no XLSX, Daily Sales, Borrow, Stock Count, queue, AI mapping, supplier, store or purchase order.
- Architecture alignment: controller thin, FormRequest validation, use cases own workflow, parser in Infrastructure, balance mutation through `InventoryBalanceService`.

---

## Execution Choice

Plan complete and saved to `docs/TaskImplementDetailPlan/StockImportUC-11plan.md`.

Recommended execution:

1. Use `superpowers:subagent-driven-development` if multi-agent tools are enabled.
2. Otherwise use `superpowers:executing-plans` inline.

This plan intentionally stops before Daily Sales. Next large plan after UC-11 should be UC-13/14/15 Daily Sales draft, confirm and cancel.
