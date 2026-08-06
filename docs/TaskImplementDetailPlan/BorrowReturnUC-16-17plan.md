# Borrow/Return UC-16/17 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Xây luồng mượn hàng và trả hàng: ghi nhận sản phẩm cho mượn, trừ tồn ngay bằng ledger `BORROW_OUT`, trả lại đúng một lần và cộng tồn bằng ledger `BORROW_RETURN`.

**Architecture:** Inventory Service sở hữu toàn bộ dữ liệu borrow trong `inventory_db`. Controller chỉ validate request, lấy `verified_token`, gọi Use Case và trả Resource. Mọi thay đổi tồn kho bắt buộc đi qua `InventoryBalanceService` trong DB transaction; frontend dùng route `/borrowed` theo pattern Inventory/Daily Sales hiện có.

**Tech Stack:** PHP 8.3/8.4 runtime hiện có, Laravel 13, PostgreSQL, PHPUnit feature tests, React + TypeScript + Vite, Docker Compose.

## Global Constraints

- Tất cả text tiếng Việt trong code response, docs, backend messages và frontend text phải có dấu đầy đủ.
- Không thêm dependency mới.
- Cả `STORE_MANAGER` và `STAFF` được thao tác Borrow/Return trong MVP.
- API ghi dữ liệu dùng cookie auth và CSRF hiện có; `GET` không yêu cầu CSRF, mutation `POST` bắt buộc CSRF token.
- Không gọi thẳng database service khác.
- Không cập nhật `inventory_balances` từ Controller.
- Mọi thay đổi balance phải đi qua `InventoryBalanceService`.
- Mọi thay đổi balance phải tạo `inventory_transactions`.
- Mọi thay đổi balance phải nằm trong DB transaction.
- Quantity dùng integer, không dùng float.
- Borrow chỉ dùng active, non-soft-deleted SKU.
- Mượn sản phẩm làm giảm tồn kho ngay.
- Trả sản phẩm đã mượn làm tăng tồn kho.
- Một borrow record không được return hai lần.
- Frontend/UI task phải dùng taste/design skill trước khi sửa UI code.
- UI LazyManager giữ vibe "Cozy" và "Lazy": thân thiện, bình tĩnh, dễ thao tác, ít ma sát, trạng thái rõ.

---

## Current Project State

Đã có nền Inventory:

- Models: `Product`, `ProductSku`, `InventoryBalance`, `InventoryTransaction`, `StockImport`, `DailySale`.
- Enum: `InventoryTransactionType` đã có `BorrowOut = 'BORROW_OUT'` và `BorrowReturn = 'BORROW_RETURN'`.
- Service: `InventoryBalanceService::decrease()` và `increase()` dùng DB transaction, `lockForUpdate()` và ghi ledger.
- Exception: `InventoryBusinessException` hỗ trợ status tùy chỉnh; `InsufficientStockException` dùng cho tồn không đủ.
- Auth: `AuthenticateJwt` đặt `VerifiedToken` vào request attribute `verified_token`.
- Frontend đã có inventory routes: `/products`, `/inventory`, `/inventory/import`, `/daily-sales`, `/daily-sales/new`.
- `docs/api.md` đang để `borrow-records` trong Future.
- `docs/progress_report_2.8.26.md` đang ghi UC-16/17 chưa làm.

Nguồn sự thật:

- `docs/rules.md`: mượn làm giảm tồn ngay, trả làm tăng tồn, không return hai lần.
- `docs/StoreOps_MVP_1_Thang_v2.0.md`: hàng mượn cần ghi nhận mượn ra, trả lại, ghi chú nơi mượn; bỏ approve/reject nhiều bước.
- `docs/api.md`: public gateway route dưới `/api/inventory/v1/*`.

---

## Scope

In scope:

- Tạo borrow record thủ công từ UI/API.
- Chọn SKU bằng `sku_id`.
- Nhập `quantity`, `borrower_name`, `borrow_location`, `note`.
- Khi tạo borrow record, trừ tồn ngay bằng `InventoryBalanceService::decrease(... BorrowOut ...)`.
- Nếu SKU không tồn tại, inactive hoặc soft-deleted, trả `409`.
- Nếu tồn không đủ, rollback toàn bộ và trả `422`.
- Danh sách borrow records newest first.
- Lọc danh sách theo `status` và `search`.
- `search` tìm theo `sku_code`, product name, borrower name, borrow location.
- Return borrow record đúng một lần.
- Khi return, cộng tồn bằng `InventoryBalanceService::increase(... BorrowReturn ...)`.
- Return lưu `returned_at`, `returned_by`, `return_note`.
- Frontend `/borrowed`: tạo phiếu mượn, xem danh sách, trả hàng.

Out of scope:

- Approval/reject workflow.
- Partial return.
- Nhiều SKU trong một borrow record.
- Due date, overdue notification.
- Người mượn liên kết People Service bằng FK.
- Upload/import CSV borrow.
- In phiếu, chữ ký, ảnh, barcode scan.
- Multi-store.

---

## API Target

Gateway public routes:

```text
GET  /api/inventory/v1/borrow-records?status=&search=
POST /api/inventory/v1/borrow-records
GET  /api/inventory/v1/borrow-records/{id}
POST /api/inventory/v1/borrow-records/{id}/return
```

Service internal routes:

```text
GET  /api/v1/borrow-records?status=&search=
POST /api/v1/borrow-records
GET  /api/v1/borrow-records/{borrowRecord}
POST /api/v1/borrow-records/{borrowRecord}/return
```

Create request:

```json
{
  "sku_id": 1,
  "quantity": 2,
  "borrower_name": "Lan cửa hàng A",
  "borrow_location": "Quầy pop-up cuối tuần",
  "note": "Mang đi chụp mẫu"
}
```

Return request:

```json
{
  "return_note": "Đã nhận đủ, hàng còn nguyên."
}
```

List response:

```json
{
  "borrow_records": [
    {
      "id": 1,
      "status": "BORROWED",
      "sku_id": 1,
      "sku_code": "AO-THUN-M",
      "product_id": 1,
      "product_code": "AO-THUN",
      "product_name": "Áo thun",
      "quantity": 2,
      "borrower_name": "Lan cửa hàng A",
      "borrow_location": "Quầy pop-up cuối tuần",
      "note": "Mang đi chụp mẫu",
      "return_note": null,
      "created_by": 10,
      "returned_by": null,
      "borrowed_at": "2026-08-06T10:00:00.000000Z",
      "returned_at": null,
      "created_at": "2026-08-06T10:00:00.000000Z",
      "updated_at": "2026-08-06T10:00:00.000000Z"
    }
  ]
}
```

Create success response:

```json
{
  "borrow_record": {
    "id": 1,
    "status": "BORROWED",
    "sku_id": 1,
    "sku_code": "AO-THUN-M",
    "quantity": 2,
    "borrower_name": "Lan cửa hàng A",
    "borrow_location": "Quầy pop-up cuối tuần"
  }
}
```

Business error responses:

```json
{ "message": "SKU đã ngừng hoạt động và không thể cho mượn." }
```

```json
{ "message": "Không đủ tồn kho để cho mượn sản phẩm." }
```

```json
{ "message": "Phiếu mượn này đã được trả." }
```

---

## Database Design

### `borrow_records`

```text
id bigint primary key
sku_id bigint not null foreign key product_skus.id restrict on delete
quantity integer not null
status varchar(32) not null default BORROWED
borrower_name varchar(255) not null
borrow_location varchar(255) not null
note varchar(255) nullable
return_note varchar(255) nullable
created_by bigint nullable
returned_by bigint nullable
borrowed_at timestamp not null
returned_at timestamp nullable
created_at timestamp nullable
updated_at timestamp nullable
check(quantity > 0)
index(sku_id)
index(status)
index(borrowed_at)
index(returned_at)
```

Deliberate simplifications:

- `borrower_name` là text, không FK sang People Service.
- `borrow_location` là text để đáp ứng ghi chú nơi mượn trong MVP.
- Một borrow record chỉ chứa một SKU.
- Không có soft delete cho borrow record; audit giữ bằng ledger và status.
- Không dùng enum PostgreSQL native; dùng string + PHP enum để migration đơn giản và test SQLite dễ chạy.

---

## File Map

Create:

```text
services/inventory-service/database/migrations/2026_08_06_000400_create_borrow_records_table.php
services/inventory-service/app/Domain/Enums/BorrowRecordStatus.php
services/inventory-service/app/Models/BorrowRecord.php
services/inventory-service/app/Application/DTOs/CreateBorrowRecordData.php
services/inventory-service/app/Application/DTOs/ReturnBorrowRecordData.php
services/inventory-service/app/Application/UseCases/ListBorrowRecordsUseCase.php
services/inventory-service/app/Application/UseCases/GetBorrowRecordUseCase.php
services/inventory-service/app/Application/UseCases/CreateBorrowRecordUseCase.php
services/inventory-service/app/Application/UseCases/ReturnBorrowRecordUseCase.php
services/inventory-service/app/Http/Controllers/BorrowRecordController.php
services/inventory-service/app/Http/Requests/StoreBorrowRecordRequest.php
services/inventory-service/app/Http/Requests/ReturnBorrowRecordRequest.php
services/inventory-service/app/Http/Resources/BorrowRecordResource.php
services/inventory-service/tests/Feature/BorrowRecordApiTest.php
frontend/src/features/inventory/api/borrowRecordsApi.ts
frontend/src/features/inventory/pages/BorrowedPage.tsx
frontend/src/borrowedExperience.test.mjs
```

Modify:

```text
services/inventory-service/routes/api.php
frontend/src/App.tsx
frontend/src/features/health/pages/DashboardPage.tsx
frontend/package.json
docs/api.md
docs/progress_report_2.8.26.md
README.md
```

No repository interfaces:

- Existing Inventory Service uses Eloquent directly in use cases.
- Borrow/Return is single-service persistence.
- Add repository later only if Stock Count/reporting needs shared query objects.

---

## Domain Flow

### Borrow Out

```text
Client POST /borrow-records
  -> auth.jwt + csrf.double_submit
  -> StoreBorrowRecordRequest validates sku_id, quantity, borrower_name, borrow_location, note
  -> BorrowRecordController reads verified_token.userId
  -> CreateBorrowRecordUseCase
  -> DB transaction locks SKU row and validates active/non-soft-deleted
  -> InventoryBalanceService::decrease(... BORROW_OUT ...)
  -> create borrow_records row with BORROWED
  -> BorrowRecordResource
  -> JSON
```

### Borrow Return

```text
Client POST /borrow-records/{id}/return
  -> auth.jwt + csrf.double_submit
  -> ReturnBorrowRecordRequest validates return_note
  -> BorrowRecordController reads verified_token.userId
  -> ReturnBorrowRecordUseCase
  -> DB transaction locks borrow_records row
  -> block if status RETURNED
  -> re-fetch SKU with withTrashed() (cho phép trả SKU đã inactive/soft-deleted)
  -> InventoryBalanceService::increase(... BORROW_RETURN ...)
  -> set status RETURNED, returned_at, returned_by, return_note
  -> BorrowRecordResource
  -> JSON
```

---

## Task 0: Verify Existing Inventory Infrastructure

**Files:**

- Inspect: `services/inventory-service/app/Domain/Enums/InventoryTransactionType.php`
- Inspect: `services/inventory-service/app/Domain/Services/InventoryBalanceService.php`
- Inspect: `services/inventory-service/app/Models/ProductSku.php`
- Inspect: `services/inventory-service/app/Application/DTOs/VerifiedToken.php`
- Modify: `docs/TaskImplementDetailPlan/BorrowReturnUC-16-17plan.md`

**Interfaces:**

- Confirms `InventoryTransactionType::BorrowOut` and `BorrowReturn` exist.
- Confirms borrow can use `InventoryBalanceService::decrease()` and return can use `increase()`.
- Confirms `VerifiedToken->userId` is available for `created_by` and `returned_by`.

- [x] **Step 1: Verify transaction enum**

Run:

```powershell
Get-Content services\inventory-service\app\Domain\Enums\InventoryTransactionType.php
```

Expected:

```text
BorrowOut = 'BORROW_OUT'
BorrowReturn = 'BORROW_RETURN'
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

- [x] **Step 3: Verify SKU policy**

Run:

```powershell
Get-Content services\inventory-service\app\Models\ProductSku.php
```

Expected:

```text
ProductSku uses SoftDeletes.
ProductSku has active boolean.
Borrow mới chỉ accepts active, non-soft-deleted SKU.
Return re-fetches SKU with `withTrashed()` so returned stock can be accepted even after SKU is inactive or soft-deleted.
```

- [x] **Step 4: Document result in this plan**

Append:

```text
Task 0 result, verified on YYYY-MM-DD:
- InventoryTransactionType has BorrowOut and BorrowReturn.
- InventoryBalanceService decrease/increase use DB transaction and lockForUpdate.
- ProductSku uses SoftDeletes and active boolean.
- VerifiedToken exposes userId and role.
```

Task 0 result, verified on 2026-08-06:

```text
- InventoryTransactionType has BorrowOut = 'BORROW_OUT' and BorrowReturn = 'BORROW_RETURN'.
- InventoryBalanceService::decrease() and increase() reject quantity <= 0, delegate to applyDelta(), use DB::transaction(), lock inventory_balances with lockForUpdate(), and write inventory_transactions.
- InventoryBalanceService::decrease() throws InsufficientStockException and rolls back when quantityAfter < 0.
- ProductSku uses SoftDeletes and casts active to boolean.
- Borrow out must query active, non-soft-deleted SKU.
- Borrow return can re-fetch ProductSku withTrashed() per final Domain Flow, because returning stock must still be accepted after SKU is inactive or soft-deleted.
- VerifiedToken exposes userId and role.
```

- [x] **Step 5: Commit preflight note if docs changed**

```powershell
git add docs/TaskImplementDetailPlan/BorrowReturnUC-16-17plan.md
git commit -m "docs: verify borrow return preflight"
```

---

## Task 1: Borrow Schema And Model

**Files:**

- Create: `services/inventory-service/database/migrations/2026_08_06_000400_create_borrow_records_table.php`
- Create: `services/inventory-service/app/Domain/Enums/BorrowRecordStatus.php`
- Create: `services/inventory-service/app/Models/BorrowRecord.php`
- Test: `services/inventory-service/tests/Feature/BorrowRecordApiTest.php`

**Interfaces:**

- Produces enum `BorrowRecordStatus` with `Borrowed = 'BORROWED'`, `Returned = 'RETURNED'`.
- Produces `BorrowRecord::sku(): BelongsTo`.
- Later Resource relies on `BorrowRecord` loading `sku.product`.

- [ ] **Step 1: Add failing schema tests**

Create `BorrowRecordApiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Domain\Enums\BorrowRecordStatus;
use App\Models\BorrowRecord;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Support\Facades\Schema;

final class BorrowRecordApiTest extends InventoryFeatureTestCase
{
    public function test_borrow_records_table_exists(): void
    {
        self::assertTrue(Schema::hasTable('borrow_records'));
        self::assertTrue(Schema::hasColumns('borrow_records', [
            'sku_id',
            'quantity',
            'status',
            'borrower_name',
            'borrow_location',
            'note',
            'return_note',
            'created_by',
            'returned_by',
            'borrowed_at',
            'returned_at',
        ]));
    }

    public function test_borrow_record_model_relationships_work(): void
    {
        $sku = $this->createSkuWithBalance(quantity: 10);

        $borrowRecord = BorrowRecord::query()->create([
            'sku_id' => $sku->id,
            'quantity' => 2,
            'status' => BorrowRecordStatus::Borrowed->value,
            'borrower_name' => 'Lan cửa hàng A',
            'borrow_location' => 'Quầy pop-up cuối tuần',
            'borrowed_at' => now(),
            'created_by' => 10,
        ]);

        self::assertSame('AO-THUN-M', $borrowRecord->sku->sku_code);
        self::assertSame(BorrowRecordStatus::Borrowed, $borrowRecord->status);
    }

    private function createSkuWithBalance(int $quantity, bool $active = true, string $skuCode = 'AO-THUN-M'): ProductSku
    {
        $product = Product::query()->firstOrCreate(
            ['product_code' => 'AO-THUN'],
            ['name' => 'Áo thun', 'active' => true],
        );
        $sku = ProductSku::query()->firstOrCreate(
            ['sku_code' => $skuCode],
            ['product_id' => $product->id, 'size' => 'M', 'active' => $active],
        );
        InventoryBalance::query()->updateOrCreate(
            ['sku_id' => $sku->id],
            ['quantity' => $quantity],
        );

        return $sku;
    }

    private function createBorrowRecord(
        ?ProductSku $sku = null,
        int $quantity = 2,
        string $borrowerName = 'Lan cửa hàng A',
        string $borrowLocation = 'Quầy pop-up cuối tuần',
        ?\DateTimeInterface $borrowedAt = null,
    ): BorrowRecord {
        $sku ??= $this->createSkuWithBalance(quantity: 10);

        return BorrowRecord::query()->create([
            'sku_id' => $sku->id,
            'quantity' => $quantity,
            'status' => BorrowRecordStatus::Borrowed->value,
            'borrower_name' => $borrowerName,
            'borrow_location' => $borrowLocation,
            'borrowed_at' => $borrowedAt ?? now(),
            'created_by' => 10,
        ]);
    }
}
```

- [ ] **Step 2: Run failing schema tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=BorrowRecordApiTest
```

Expected: FAIL because table/model/enum do not exist.

- [ ] **Step 3: Create migration**

Create `2026_08_06_000400_create_borrow_records_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->createSqliteBorrowRecords();

            return;
        }

        Schema::create('borrow_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sku_id')->constrained('product_skus')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 32)->default('BORROWED');
            $table->string('borrower_name');
            $table->string('borrow_location');
            $table->string('note')->nullable();
            $table->string('return_note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('returned_by')->nullable();
            $table->timestamp('borrowed_at');
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->index('sku_id');
            $table->index('status');
            $table->index('borrowed_at');
            $table->index('returned_at');
        });

        DB::statement('ALTER TABLE borrow_records ADD CONSTRAINT borrow_records_quantity_positive CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('borrow_records');
    }

    private function createSqliteBorrowRecords(): void
    {
        DB::statement(
            'CREATE TABLE borrow_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                sku_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT \'BORROWED\',
                borrower_name VARCHAR(255) NOT NULL,
                borrow_location VARCHAR(255) NOT NULL,
                note VARCHAR(255),
                return_note VARCHAR(255),
                created_by INTEGER,
                returned_by INTEGER,
                borrowed_at DATETIME NOT NULL,
                returned_at DATETIME,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY (sku_id) REFERENCES product_skus(id),
                CHECK (quantity > 0)
            )',
        );
        DB::statement('CREATE INDEX borrow_records_sku_id_index ON borrow_records(sku_id)');
        DB::statement('CREATE INDEX borrow_records_status_index ON borrow_records(status)');
        DB::statement('CREATE INDEX borrow_records_borrowed_at_index ON borrow_records(borrowed_at)');
        DB::statement('CREATE INDEX borrow_records_returned_at_index ON borrow_records(returned_at)');
    }
};
```

- [ ] **Step 4: Create enum**

Create `BorrowRecordStatus.php`:

```php
<?php

namespace App\Domain\Enums;

enum BorrowRecordStatus: string
{
    case Borrowed = 'BORROWED';
    case Returned = 'RETURNED';
}
```

- [ ] **Step 5: Create model**

Create `BorrowRecord.php`:

```php
<?php

namespace App\Models;

use App\Domain\Enums\BorrowRecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BorrowRecord extends Model
{
    protected $fillable = [
        'sku_id',
        'quantity',
        'status',
        'borrower_name',
        'borrow_location',
        'note',
        'return_note',
        'created_by',
        'returned_by',
        'borrowed_at',
        'returned_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'status' => BorrowRecordStatus::class,
        'borrowed_at' => 'datetime',
        'returned_at' => 'datetime',
        'created_by' => 'integer',
        'returned_by' => 'integer',
    ];

    /** withTrashed: borrow record phải hiển thị SKU info kể cả khi SKU đã bị soft-delete sau lúc cho mượn. */
    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class, 'sku_id')
            ->withTrashed();
    }
}
```

- [ ] **Step 6: Run schema tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=BorrowRecordApiTest
```

Expected: PASS.

- [ ] **Step 7: Commit**

```powershell
git add services/inventory-service/database/migrations services/inventory-service/app/Domain/Enums/BorrowRecordStatus.php services/inventory-service/app/Models/BorrowRecord.php services/inventory-service/tests/Feature/BorrowRecordApiTest.php
git commit -m "feat(inventory): add borrow record schema"
```

---

## Task 2: Borrow Out API

**Files:**

- Create: `services/inventory-service/app/Application/DTOs/CreateBorrowRecordData.php`
- Create: `services/inventory-service/app/Application/UseCases/CreateBorrowRecordUseCase.php`
- Create: `services/inventory-service/app/Http/Requests/StoreBorrowRecordRequest.php`
- Create: `services/inventory-service/app/Http/Resources/BorrowRecordResource.php`
- Create: `services/inventory-service/app/Http/Controllers/BorrowRecordController.php`
- Modify: `services/inventory-service/routes/api.php`
- Test: `services/inventory-service/tests/Feature/BorrowRecordApiTest.php`

**Interfaces:**

- `CreateBorrowRecordData::__construct(int $skuId, int $quantity, string $borrowerName, string $borrowLocation, ?string $note)`
- `CreateBorrowRecordUseCase::execute(CreateBorrowRecordData $data, ?int $createdBy): BorrowRecord`
- `BorrowRecordController::store(StoreBorrowRecordRequest $request, CreateBorrowRecordUseCase $useCase): JsonResponse`

- [ ] **Step 1: Add failing borrow API tests**

Add tests:

```php
public function test_authenticated_user_can_create_borrow_record_and_decrement_stock(): void
public function test_borrow_record_creation_rolls_back_when_stock_is_insufficient(): void
public function test_borrow_record_creation_blocks_inactive_sku(): void
public function test_borrow_record_creation_requires_positive_quantity(): void
public function test_borrow_record_creation_requires_csrf(): void
public function test_staff_can_create_borrow_record(): void
```

Key success assertion:

```php
$sku = $this->createSkuWithBalance(quantity: 10);

$this->actingWithInventoryCookie(userId: 10)
    ->withHeaders($this->authHeaders())
    ->postJson('/api/v1/borrow-records', [
        'sku_id' => $sku->id,
        'quantity' => 2,
        'borrower_name' => 'Lan cửa hàng A',
        'borrow_location' => 'Quầy pop-up cuối tuần',
        'note' => 'Mang đi chụp mẫu',
    ])
    ->assertCreated()
    ->assertJsonPath('borrow_record.status', 'BORROWED')
    ->assertJsonPath('borrow_record.sku_code', 'AO-THUN-M')
    ->assertJsonPath('borrow_record.quantity', 2)
    ->assertJsonPath('borrow_record.created_by', 10);

$this->assertDatabaseHas('inventory_balances', [
    'sku_id' => $sku->id,
    'quantity' => 8,
]);
$this->assertDatabaseHas('inventory_transactions', [
    'sku_id' => $sku->id,
    'type' => 'BORROW_OUT',
    'quantity_before' => 10,
    'quantity_change' => -2,
    'quantity_after' => 8,
    'reference_type' => 'borrow_record',
    'reason' => 'Cho mượn: Quầy pop-up cuối tuần',
    'created_by' => 10,
]);
```

Insufficient stock assertion:

```php
->assertStatus(422)
->assertJsonPath('message', 'Không đủ tồn kho để cho mượn sản phẩm.');
```

Inactive SKU assertion:

```php
->assertStatus(409)
->assertJsonPath('message', 'SKU đã ngừng hoạt động và không thể cho mượn.');
```

- [ ] **Step 2: Run failing borrow API tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=borrow_record_creation
```

Expected: FAIL because endpoint/use case does not exist.

- [ ] **Step 3: Create DTO**

Create `CreateBorrowRecordData.php`:

```php
<?php

namespace App\Application\DTOs;

final readonly class CreateBorrowRecordData
{
    public function __construct(
        public int $skuId,
        public int $quantity,
        public string $borrowerName,
        public string $borrowLocation,
        public ?string $note,
    ) {
    }
}
```

- [ ] **Step 4: Create FormRequest**

Create `StoreBorrowRecordRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Application\DTOs\CreateBorrowRecordData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreBorrowRecordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'sku_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
            'borrower_name' => ['required', 'string', 'max:255'],
            'borrow_location' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku_id.required' => 'Vui lòng chọn SKU.',
            'quantity.required' => 'Vui lòng nhập số lượng mượn.',
            'quantity.integer' => 'Số lượng mượn phải là số nguyên.',
            'quantity.min' => 'Số lượng mượn phải lớn hơn 0.',
            'borrower_name.required' => 'Vui lòng nhập người mượn.',
            'borrow_location.required' => 'Vui lòng nhập nơi mượn.',
        ];
    }

    public function toData(): CreateBorrowRecordData
    {
        return new CreateBorrowRecordData(
            skuId: (int) $this->integer('sku_id'),
            quantity: (int) $this->integer('quantity'),
            borrowerName: trim((string) $this->input('borrower_name')),
            borrowLocation: trim((string) $this->input('borrow_location')),
            note: $this->filled('note') ? trim((string) $this->input('note')) : null,
        );
    }
}
```

- [ ] **Step 5: Create Resource**

Create `BorrowRecordResource.php`:

```php
<?php

namespace App\Http\Resources;

use App\Models\BorrowRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BorrowRecord */
final class BorrowRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'sku_id' => $this->sku_id,
            'sku_code' => $this->sku->sku_code,
            'product_id' => $this->sku->product->id,
            'product_code' => $this->sku->product->product_code,
            'product_name' => $this->sku->product->name,
            'quantity' => $this->quantity,
            'borrower_name' => $this->borrower_name,
            'borrow_location' => $this->borrow_location,
            'note' => $this->note,
            'return_note' => $this->return_note,
            'created_by' => $this->created_by,
            'returned_by' => $this->returned_by,
            'borrowed_at' => $this->borrowed_at?->toJSON(),
            'returned_at' => $this->returned_at?->toJSON(),
            'created_at' => $this->created_at?->toJSON(),
            'updated_at' => $this->updated_at?->toJSON(),
        ];
    }
}
```

- [ ] **Step 6: Create use case**

Create `CreateBorrowRecordUseCase.php`:

```php
<?php

namespace App\Application\UseCases;

use App\Application\DTOs\CreateBorrowRecordData;
use App\Domain\Enums\BorrowRecordStatus;
use App\Domain\Enums\InventoryTransactionType;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Domain\Exceptions\InsufficientStockException;
use App\Domain\Services\InventoryBalanceService;
use App\Models\BorrowRecord;
use App\Models\ProductSku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateBorrowRecordUseCase
{
    public function __construct(private readonly InventoryBalanceService $balanceService)
    {
    }

    public function execute(CreateBorrowRecordData $data, ?int $createdBy): BorrowRecord
    {
        try {
            return DB::transaction(function () use ($data, $createdBy): BorrowRecord {
                $sku = ProductSku::query()
                    ->whereKey($data->skuId)
                    ->lockForUpdate()
                    ->first();

                if (! $sku instanceof ProductSku || ! $sku->active) {
                    throw new InventoryBusinessException('SKU đã ngừng hoạt động và không thể cho mượn.', 409);
                }

                $borrowRecord = BorrowRecord::query()->create([
                    'sku_id' => $sku->id,
                    'quantity' => $data->quantity,
                    'status' => BorrowRecordStatus::Borrowed->value,
                    'borrower_name' => $data->borrowerName,
                    'borrow_location' => $data->borrowLocation,
                    'note' => $data->note,
                    'borrowed_at' => now(),
                    'created_by' => $createdBy,
                ]);

                $this->balanceService->decrease(
                    sku: $sku,
                    quantity: $data->quantity,
                    type: InventoryTransactionType::BorrowOut,
                    referenceType: 'borrow_record',
                    referenceId: (string) $borrowRecord->id,
                    reason: Str::limit('Cho mượn: '.$data->borrowLocation, 255, ''),
                    createdBy: $createdBy,
                );

                return $borrowRecord->load('sku.product');
            });
        } catch (InsufficientStockException) {
            throw new InventoryBusinessException('Không đủ tồn kho để cho mượn sản phẩm.', 422);
        }
    }
}
```

- [ ] **Step 7: Create controller store method**

Create `BorrowRecordController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Application\DTOs\VerifiedToken;
use App\Application\UseCases\CreateBorrowRecordUseCase;
use App\Http\Requests\StoreBorrowRecordRequest;
use App\Http\Resources\BorrowRecordResource;
use Illuminate\Http\JsonResponse;

final class BorrowRecordController extends Controller
{
    public function store(StoreBorrowRecordRequest $request, CreateBorrowRecordUseCase $useCase): JsonResponse
    {
        /** @var VerifiedToken $verified */
        $verified = $request->attributes->get('verified_token');

        return response()->json([
            'borrow_record' => new BorrowRecordResource($useCase->execute($request->toData(), $verified->userId)),
        ], 201);
    }
}
```

- [ ] **Step 8: Register route**

Modify `routes/api.php`:

```php
use App\Http\Controllers\BorrowRecordController;

Route::post('/borrow-records', [BorrowRecordController::class, 'store']);
```

- [ ] **Step 9: Run borrow API tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=borrow_record_creation
```

Expected: PASS.

- [ ] **Step 10: Commit**

```powershell
git add services/inventory-service/app services/inventory-service/routes/api.php services/inventory-service/tests/Feature/BorrowRecordApiTest.php
git commit -m "feat(inventory): create borrow records"
```

---

## Task 3: Borrow List And Detail API

**Files:**

- Create: `services/inventory-service/app/Application/UseCases/ListBorrowRecordsUseCase.php`
- Create: `services/inventory-service/app/Application/UseCases/GetBorrowRecordUseCase.php`
- Modify: `services/inventory-service/app/Http/Controllers/BorrowRecordController.php`
- Modify: `services/inventory-service/routes/api.php`
- Test: `services/inventory-service/tests/Feature/BorrowRecordApiTest.php`

**Interfaces:**

- `ListBorrowRecordsUseCase::execute(?string $status, ?string $search): Collection`
- `GetBorrowRecordUseCase::execute(BorrowRecord $borrowRecord): BorrowRecord`

- [ ] **Step 1: Add failing list/detail tests**

Add tests:

```php
public function test_list_borrow_records_returns_newest_first(): void
public function test_list_borrow_records_filters_by_status(): void
public function test_list_borrow_records_searches_sku_product_borrower_and_location(): void
public function test_show_borrow_record_returns_detail(): void
public function test_list_and_show_do_not_require_csrf(): void
```

Newest first assertion:

```php
$old = $this->createBorrowRecord(borrowerName: 'Cũ', borrowedAt: now()->subDay());
$new = $this->createBorrowRecord(borrowerName: 'Mới', borrowedAt: now());

$this->actingWithInventoryCookie()
    ->getJson('/api/v1/borrow-records')
    ->assertOk()
    ->assertJsonPath('borrow_records.0.id', $new->id)
    ->assertJsonPath('borrow_records.1.id', $old->id);
```

Search assertion:

```php
$this->actingWithInventoryCookie()
    ->getJson('/api/v1/borrow-records?search=pop-up')
    ->assertOk()
    ->assertJsonCount(1, 'borrow_records')
    ->assertJsonPath('borrow_records.0.borrow_location', 'Quầy pop-up cuối tuần');
```

- [ ] **Step 2: Run failing list/detail tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=borrow_records
```

Expected: list/detail tests fail with 404 or missing methods.

- [ ] **Step 3: Create list use case**

Create `ListBorrowRecordsUseCase.php`:

```php
<?php

namespace App\Application\UseCases;

use App\Domain\Enums\BorrowRecordStatus;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Models\BorrowRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ListBorrowRecordsUseCase
{
    public function execute(?string $status, ?string $search): Collection
    {
        $query = BorrowRecord::query()->with('sku.product');

        if ($status !== null && $status !== '') {
            $normalizedStatus = strtoupper(trim($status));
            if (! in_array($normalizedStatus, array_column(BorrowRecordStatus::cases(), 'value'), true)) {
                throw new InventoryBusinessException('Trạng thái phiếu mượn không hợp lệ.', 422);
            }
            $query->where('status', $normalizedStatus);
        }

        if ($search !== null && trim($search) !== '') {
            $operator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            $term = '%'.trim($search).'%';
            $query->where(function (Builder $inner) use ($operator, $term): void {
                $inner->where('borrower_name', $operator, $term)
                    ->orWhere('borrow_location', $operator, $term)
                    ->orWhereHas('sku', fn (Builder $skuQuery) => $skuQuery->where('sku_code', $operator, $term))
                    ->orWhereHas('sku.product', fn (Builder $productQuery) => $productQuery
                        ->where('product_code', $operator, $term)
                        ->orWhere('name', $operator, $term));
            });
        }

        return $query
            ->orderByDesc('borrowed_at')
            ->orderByDesc('id')
            ->get();
    }
}
```

- [ ] **Step 4: Create get use case**

Create `GetBorrowRecordUseCase.php`:

```php
<?php

namespace App\Application\UseCases;

use App\Models\BorrowRecord;

final class GetBorrowRecordUseCase
{
    public function execute(BorrowRecord $borrowRecord): BorrowRecord
    {
        return $borrowRecord->load('sku.product');
    }
}
```

- [ ] **Step 5: Add controller methods and routes**

Controller:

```php
public function index(Request $request, ListBorrowRecordsUseCase $useCase): JsonResponse
{
    return response()->json([
        'borrow_records' => BorrowRecordResource::collection($useCase->execute(
            status: $request->query('status'),
            search: $request->query('search'),
        )),
    ]);
}

public function show(BorrowRecord $borrowRecord, GetBorrowRecordUseCase $useCase): JsonResponse
{
    return response()->json([
        'borrow_record' => new BorrowRecordResource($useCase->execute($borrowRecord)),
    ]);
}
```

Routes:

```php
Route::get('/borrow-records', [BorrowRecordController::class, 'index']);
Route::get('/borrow-records/{borrowRecord}', [BorrowRecordController::class, 'show']);
```

- [ ] **Step 6: Run list/detail tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=borrow_records
```

Expected: PASS.

- [ ] **Step 7: Commit**

```powershell
git add services/inventory-service/app services/inventory-service/routes/api.php services/inventory-service/tests/Feature/BorrowRecordApiTest.php
git commit -m "feat(inventory): list borrow records"
```

---

## Task 4: Return Borrow Record API

**Files:**

- Create: `services/inventory-service/app/Application/DTOs/ReturnBorrowRecordData.php`
- Create: `services/inventory-service/app/Application/UseCases/ReturnBorrowRecordUseCase.php`
- Create: `services/inventory-service/app/Http/Requests/ReturnBorrowRecordRequest.php`
- Modify: `services/inventory-service/app/Http/Controllers/BorrowRecordController.php`
- Modify: `services/inventory-service/routes/api.php`
- Test: `services/inventory-service/tests/Feature/BorrowRecordApiTest.php`

**Interfaces:**

- `ReturnBorrowRecordData::__construct(?string $returnNote)`
- `ReturnBorrowRecordUseCase::execute(BorrowRecord $borrowRecord, ReturnBorrowRecordData $data, ?int $returnedBy): BorrowRecord`
- `BorrowRecordController::returnRecord(ReturnBorrowRecordRequest $request, BorrowRecord $borrowRecord, ReturnBorrowRecordUseCase $useCase): JsonResponse`

- [ ] **Step 1: Add failing return tests**

Add tests:

```php
public function test_return_borrow_record_increases_stock_and_writes_borrow_return_transaction(): void
public function test_return_borrow_record_cannot_run_twice(): void
public function test_return_succeeds_after_sku_is_deactivated(): void
public function test_return_succeeds_after_sku_is_soft_deleted(): void
public function test_return_borrow_record_requires_csrf(): void
public function test_staff_can_return_borrow_record(): void
public function test_list_borrow_records_still_works_after_sku_is_soft_deleted(): void
public function test_show_borrow_record_still_works_after_sku_is_soft_deleted(): void
```

Success assertion:

```php
$sku = $this->createSkuWithBalance(quantity: 8);
$borrowRecord = $this->createBorrowRecord(sku: $sku, quantity: 2);

$this->actingWithInventoryCookie(userId: 10)
    ->withHeaders($this->authHeaders())
    ->postJson("/api/v1/borrow-records/{$borrowRecord->id}/return", [
        'return_note' => 'Đã nhận đủ, hàng còn nguyên.',
    ])
    ->assertOk()
    ->assertJsonPath('borrow_record.status', 'RETURNED')
    ->assertJsonPath('borrow_record.returned_by', 10)
    ->assertJsonPath('borrow_record.return_note', 'Đã nhận đủ, hàng còn nguyên.');

$this->assertDatabaseHas('inventory_balances', [
    'sku_id' => $sku->id,
    'quantity' => 10,
]);
$this->assertDatabaseHas('inventory_transactions', [
    'sku_id' => $sku->id,
    'type' => 'BORROW_RETURN',
    'quantity_before' => 8,
    'quantity_change' => 2,
    'quantity_after' => 10,
    'reference_type' => 'borrow_record',
    'reference_id' => (string) $borrowRecord->id,
    'reason' => 'Trả hàng mượn: Đã nhận đủ, hàng còn nguyên.',
    'created_by' => 10,
]);
```

Double return assertion:

```php
->assertStatus(409)
->assertJsonPath('message', 'Phiếu mượn này đã được trả.');
```

Return after SKU deactivated assertion (phải cho trả — chỉ borrow mới chặn inactive):

```php
$sku = $this->createSkuWithBalance(quantity: 8);
$borrowRecord = $this->createBorrowRecord(sku: $sku, quantity: 2);
$sku->update(['active' => false]);

$this->actingWithInventoryCookie(userId: 10)
    ->withHeaders($this->authHeaders())
    ->postJson("/api/v1/borrow-records/{$borrowRecord->id}/return", [
        'return_note' => 'SKU đã ngừng nhưng vẫn trả được.',
    ])
    ->assertOk()
    ->assertJsonPath('borrow_record.status', 'RETURNED');

$this->assertDatabaseHas('inventory_balances', [
    'sku_id' => $sku->id,
    'quantity' => 10,
]);
```

Return after SKU soft-deleted assertion:

```php
$sku = $this->createSkuWithBalance(quantity: 8);
$borrowRecord = $this->createBorrowRecord(sku: $sku, quantity: 2);
$sku->update(['active' => false]);
$sku->delete(); // soft delete

$this->actingWithInventoryCookie(userId: 10)
    ->withHeaders($this->authHeaders())
    ->postJson("/api/v1/borrow-records/{$borrowRecord->id}/return")
    ->assertOk()
    ->assertJsonPath('borrow_record.status', 'RETURNED');
```

List/show after soft-delete assertion:

```php
$sku = $this->createSkuWithBalance(quantity: 10);
$borrowRecord = $this->createBorrowRecord(sku: $sku, quantity: 2);
$sku->update(['active' => false]);
$sku->delete();

$this->actingWithInventoryCookie()
    ->getJson('/api/v1/borrow-records')
    ->assertOk()
    ->assertJsonPath('borrow_records.0.sku_code', 'AO-THUN-M');

$this->actingWithInventoryCookie()
    ->getJson("/api/v1/borrow-records/{$borrowRecord->id}")
    ->assertOk()
    ->assertJsonPath('borrow_record.sku_code', 'AO-THUN-M');
```

- [ ] **Step 2: Run failing return tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=return_borrow_record
```

Expected: FAIL because return endpoint/use case does not exist.

- [ ] **Step 3: Create DTO**

Create `ReturnBorrowRecordData.php`:

```php
<?php

namespace App\Application\DTOs;

final readonly class ReturnBorrowRecordData
{
    public function __construct(public ?string $returnNote)
    {
    }
}
```

- [ ] **Step 4: Create FormRequest**

Create `ReturnBorrowRecordRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Application\DTOs\ReturnBorrowRecordData;
use Illuminate\Foundation\Http\FormRequest;

final class ReturnBorrowRecordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'return_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toData(): ReturnBorrowRecordData
    {
        return new ReturnBorrowRecordData(
            returnNote: $this->filled('return_note') ? trim((string) $this->input('return_note')) : null,
        );
    }
}
```

- [ ] **Step 5: Create return use case**

Create `ReturnBorrowRecordUseCase.php`:

```php
<?php

namespace App\Application\UseCases;

use App\Application\DTOs\ReturnBorrowRecordData;
use App\Domain\Enums\BorrowRecordStatus;
use App\Domain\Enums\InventoryTransactionType;
use App\Domain\Exceptions\InventoryBusinessException;
use App\Domain\Services\InventoryBalanceService;
use App\Models\BorrowRecord;
use App\Models\ProductSku;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ReturnBorrowRecordUseCase
{
    public function __construct(private readonly InventoryBalanceService $balanceService)
    {
    }

    public function execute(BorrowRecord $borrowRecord, ReturnBorrowRecordData $data, ?int $returnedBy): BorrowRecord
    {
        return DB::transaction(function () use ($borrowRecord, $data, $returnedBy): BorrowRecord {
            $locked = BorrowRecord::query()
                ->whereKey($borrowRecord->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === BorrowRecordStatus::Returned) {
                throw new InventoryBusinessException('Phiếu mượn này đã được trả.', 409);
            }

            // withTrashed: cho phép trả hàng kể cả khi SKU đã bị inactive/soft-deleted.
            // Chỉ thao tác mượn mới chặn inactive SKU; trả hàng phải luôn nhận được.
            $sku = ProductSku::withTrashed()
                ->whereKey($locked->sku_id)
                ->lockForUpdate()
                ->firstOrFail();

            $reason = $data->returnNote !== null && $data->returnNote !== ''
                ? Str::limit('Trả hàng mượn: '.$data->returnNote, 255, '')
                : 'Trả hàng mượn.';

            $this->balanceService->increase(
                sku: $sku,
                quantity: $locked->quantity,
                type: InventoryTransactionType::BorrowReturn,
                referenceType: 'borrow_record',
                referenceId: (string) $locked->id,
                reason: $reason,
                createdBy: $returnedBy,
            );

            $locked->status = BorrowRecordStatus::Returned;
            $locked->returned_at = now();
            $locked->returned_by = $returnedBy;
            $locked->return_note = $data->returnNote;
            $locked->save();

            return $locked->load('sku.product');
        });
    }
}
```

- [ ] **Step 6: Add controller method and route**

Controller:

```php
public function returnRecord(ReturnBorrowRecordRequest $request, BorrowRecord $borrowRecord, ReturnBorrowRecordUseCase $useCase): JsonResponse
{
    /** @var VerifiedToken $verified */
    $verified = $request->attributes->get('verified_token');

    return response()->json([
        'borrow_record' => new BorrowRecordResource($useCase->execute($borrowRecord, $request->toData(), $verified->userId)),
    ]);
}
```

Route:

```php
Route::post('/borrow-records/{borrowRecord}/return', [BorrowRecordController::class, 'returnRecord']);
```

- [ ] **Step 7: Run return tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=return_borrow_record
```

Expected: PASS.

- [ ] **Step 8: Run full borrow tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=BorrowRecordApiTest
```

Expected: PASS.

- [ ] **Step 9: Commit**

```powershell
git add services/inventory-service/app services/inventory-service/routes/api.php services/inventory-service/tests/Feature/BorrowRecordApiTest.php
git commit -m "feat(inventory): return borrowed records"
```

---

## Task 5: Borrowed React API And Page

**Files:**

- Create: `frontend/src/features/inventory/api/borrowRecordsApi.ts`
- Create: `frontend/src/features/inventory/pages/BorrowedPage.tsx`
- Create: `frontend/src/borrowedExperience.test.mjs`
- Modify: `frontend/src/App.tsx`
- Modify: `frontend/src/features/health/pages/DashboardPage.tsx`
- Modify: `frontend/package.json`

**Interfaces:**

- `listBorrowRecords(params?: { status?: string; search?: string }): Promise<BorrowRecord[]>`
- `getBorrowRecord(id: number): Promise<BorrowRecord>`
- `createBorrowRecord(payload: CreateBorrowRecordPayload): Promise<BorrowRecord>`
- `returnBorrowRecord(id: number, payload: ReturnBorrowRecordPayload): Promise<BorrowRecord>`
- Route: `/borrowed`.

- [ ] **Step 1: Use frontend design skill**

Run relevant taste/design skill before UI edits:

```text
Use design-taste-frontend or redesign-existing-projects for LazyManager Cozy/Lazy operator UI.
```

Expected design direction:

```text
Operational list + compact create panel.
No landing hero.
Status tabs or select: Tất cả, Đang mượn, Đã trả.
Rows show product/SKU, quantity, borrower, location, borrowed date, status.
Return action only for BORROWED rows.
Calm warning state before return.
```

- [ ] **Step 2: Add frontend route policy test**

Create `borrowedExperience.test.mjs`:

```js
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const app = readFileSync(new URL('./App.tsx', import.meta.url), 'utf8')
const dashboard = readFileSync(
  new URL('./features/health/pages/DashboardPage.tsx', import.meta.url),
  'utf8',
)
const api = readFileSync(
  new URL('./features/inventory/api/borrowRecordsApi.ts', import.meta.url),
  'utf8',
)

assert.match(app, /path="\/borrowed"/)
assert.doesNotMatch(app, /<RequireManager>\s*<BorrowedPage/)
assert.match(dashboard, /to="\/borrowed"/)
assert.match(api, /listBorrowRecords/)
assert.match(api, /createBorrowRecord/)
assert.match(api, /returnBorrowRecord/)

console.log('borrowed route policy ok')
```

- [ ] **Step 3: Create API client**

Create `borrowRecordsApi.ts`:

```ts
import { apiClient } from '../../../lib/apiClient'

export type BorrowRecordStatus = 'BORROWED' | 'RETURNED'

export type BorrowRecord = {
  id: number
  status: BorrowRecordStatus
  sku_id: number
  sku_code: string
  product_id: number
  product_code: string
  product_name: string
  quantity: number
  borrower_name: string
  borrow_location: string
  note: string | null
  return_note: string | null
  created_by: number | null
  returned_by: number | null
  borrowed_at: string | null
  returned_at: string | null
  created_at: string | null
  updated_at: string | null
}

export type CreateBorrowRecordPayload = {
  sku_id: number
  quantity: number
  borrower_name: string
  borrow_location: string
  note?: string
}

export type ReturnBorrowRecordPayload = {
  return_note?: string
}

export async function listBorrowRecords(params?: {
  status?: string
  search?: string
}): Promise<BorrowRecord[]> {
  const queryParts: string[] = []
  if (params?.status) queryParts.push(`status=${encodeURIComponent(params.status)}`)
  if (params?.search) queryParts.push(`search=${encodeURIComponent(params.search)}`)
  const queryString = queryParts.length > 0 ? `?${queryParts.join('&')}` : ''
  const response = await apiClient<{ borrow_records: BorrowRecord[] }>(
    `/api/inventory/v1/borrow-records${queryString}`,
  )

  return response.borrow_records
}

export async function getBorrowRecord(id: number): Promise<BorrowRecord> {
  const response = await apiClient<{ borrow_record: BorrowRecord }>(
    `/api/inventory/v1/borrow-records/${id.toString()}`,
  )

  return response.borrow_record
}

export async function createBorrowRecord(payload: CreateBorrowRecordPayload): Promise<BorrowRecord> {
  const response = await apiClient<{ borrow_record: BorrowRecord }>(
    '/api/inventory/v1/borrow-records',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
  )

  return response.borrow_record
}

export async function returnBorrowRecord(
  id: number,
  payload: ReturnBorrowRecordPayload,
): Promise<BorrowRecord> {
  const response = await apiClient<{ borrow_record: BorrowRecord }>(
    `/api/inventory/v1/borrow-records/${id.toString()}/return`,
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
  )

  return response.borrow_record
}
```

- [ ] **Step 4: Create `/borrowed` page**

Required labels:

```text
Hàng mượn
Tìm theo SKU, sản phẩm, người mượn hoặc nơi mượn
Tất cả
Đang mượn
Đã trả
Ghi nhận mượn hàng
Tìm sản phẩm / SKU
Số lượng
Người mượn
Nơi mượn
Ghi chú
Tạo phiếu mượn
Trả hàng
Ghi chú trả hàng
Xác nhận trả hàng
Chưa có phiếu mượn.
Không tải được danh sách hàng mượn.
Không tạo được phiếu mượn.
Không trả được phiếu mượn.
```

Behavior:

```text
Load listBorrowRecords on mount.
Filter by status via select/segmented control.
Search input reloads list.
Create form: search SKU bằng text input (gọi GET /products?search= hoặc GET /inventory?search= hiện có).
  Hiển thị dropdown kết quả: mã SKU, tên sản phẩm, size, tồn khả dụng.
  Khi chọn, gán sku_id ngầm, hiển thị thông tin SKU đã chọn.
  Debounce search 300ms, bỏ qua response cũ khi có request mới.
Create form posts sku_id, quantity, borrower_name, borrow_location, note.
After create, prepend or reload list.
Return button only visible/enabled for BORROWED rows.
Return opens inline panel/modal-like section with return_note.
After return, replace updated row.
Show loading, empty and API error states.
```

Design requirements:

```text
Vibe: Cozy, Lazy.
Density: bảng dễ scan, action rõ, không nhồi quá nhiều text.
Palette: đồng bộ Inventory/Daily Sales, warm-neutral không beige-heavy.
States: loading row skeleton, empty state bình tĩnh, error rõ.
Accessibility: labels thật, focus state rõ, return button có accessible name.
```

- [ ] **Step 5: Add route and navigation**

In `App.tsx`:

```tsx
import { BorrowedPage } from './features/inventory/pages/BorrowedPage'

<Route path="/borrowed" element={<BorrowedPage />} />
```

Do not wrap in `RequireManager`.

In `DashboardPage.tsx` nav:

```tsx
<Link to="/borrowed">Hàng mượn</Link>
```

- [ ] **Step 6: Update package test script**

In `frontend/package.json`:

```json
"test": "node src/routePolicy.test.mjs && node src/dashboardExperience.test.mjs && node src/stockImportExperience.test.mjs && node src/dailySalesExperience.test.mjs && node src/borrowedExperience.test.mjs"
```

- [ ] **Step 7: Run frontend checks**

Run:

```powershell
npm --prefix frontend test
npm --prefix frontend run build
```

If local npm shim is broken, use:

```powershell
& 'C:\Users\vihao\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe' frontend\src\routePolicy.test.mjs
& 'C:\Users\vihao\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe' frontend\src\dashboardExperience.test.mjs
& 'C:\Users\vihao\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe' frontend\src\stockImportExperience.test.mjs
& 'C:\Users\vihao\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe' frontend\src\dailySalesExperience.test.mjs
& 'C:\Users\vihao\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe' frontend\src\borrowedExperience.test.mjs
& 'C:\Users\vihao\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe' frontend\node_modules\typescript\bin\tsc -b
& 'C:\Users\vihao\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe' frontend\node_modules\vite\bin\vite.js build
```

Expected: PASS.

- [ ] **Step 8: Commit**

```powershell
git add frontend/src/App.tsx frontend/src/features frontend/src/borrowedExperience.test.mjs frontend/package.json
git commit -m "feat(frontend): add borrowed items page"
```

---

## Task 6: Docs And Final Verification

**Files:**

- Modify: `docs/api.md`
- Modify: `docs/progress_report_2.8.26.md`
- Modify: `README.md`
- Modify: `docs/TaskImplementDetailPlan/BorrowReturnUC-16-17plan.md`

**Interfaces:**

- Docs mark UC-16/17 Borrow/Return implemented.
- API docs move borrow endpoints from Future to Implemented.
- Future endpoints remain Stock Count only.

- [ ] **Step 1: Update docs/api.md**

Move Borrow endpoints to Implemented:

```text
GET    /api/inventory/v1/borrow-records?status=&search=
POST   /api/inventory/v1/borrow-records
GET    /api/inventory/v1/borrow-records/{id}
POST   /api/inventory/v1/borrow-records/{id}/return
```

Add behavior:

```text
Borrow/Return UC-16/17:
- `POST /borrow-records` tạo phiếu mượn cho một SKU, trừ tồn ngay bằng `BORROW_OUT`.
- Quantity phải là số nguyên > 0.
- SKU inactive hoặc soft-deleted bị chặn.
- Nếu không đủ tồn, toàn bộ request rollback.
- `GET /borrow-records` trả danh sách mới nhất trước, hỗ trợ `status` và `search`.
- `POST /borrow-records/{id}/return` cộng trả tồn bằng `BORROW_RETURN`.
- Một phiếu mượn không được trả hai lần.
- STAFF và STORE_MANAGER đều được thao tác Borrow/Return.
```

- [ ] **Step 2: Update progress docs**

In `docs/progress_report_2.8.26.md`:

```text
Use Cases (UC): 16 / 20 DONE.
UC-16: DONE.
UC-17: DONE.
React Pages: 10 / 14.
Future: UC-18/19/20 Stock Count.
```

In `README.md`:

```text
Current state includes Borrow/Return.
Next: Stock Count.
```

- [ ] **Step 3: Run backend verification**

Run:

```powershell
docker compose exec -T inventory-service php artisan test
docker compose exec -T inventory-service php artisan route:list --path=api/v1
```

Expected:

```text
Inventory tests pass.
Route list includes:
GET api/v1/borrow-records
POST api/v1/borrow-records
GET api/v1/borrow-records/{borrowRecord}
POST api/v1/borrow-records/{borrowRecord}/return
```

- [ ] **Step 4: Run frontend verification**

Run:

```powershell
npm --prefix frontend test
npm --prefix frontend run build
```

If npm shim is broken, use bundled Node commands from Task 5.

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
git add docs/api.md docs/progress_report_2.8.26.md README.md docs/TaskImplementDetailPlan/BorrowReturnUC-16-17plan.md
git commit -m "docs: document borrow return workflow"
```

---

## Final Verification

Run from repo root:

```powershell
docker compose exec -T inventory-service php artisan test
docker compose exec -T inventory-service php artisan route:list --path=api/v1
docker compose config
npm --prefix frontend test
npm --prefix frontend run build
```

If `npm` shim is broken, use bundled Node equivalents from Task 5.

Expected:

- Inventory Service tests pass.
- Route list shows Borrow/Return endpoints.
- Compose config is valid.
- Frontend route tests pass.
- Frontend build succeeds.
- `/borrowed` is accessible to `STORE_MANAGER` and `STAFF`.
- Borrow writes `BORROW_OUT` transaction visible on `/inventory`.
- Return writes `BORROW_RETURN` transaction visible on `/inventory`.

Manual demo:

```text
1. Create product/SKU with stock quantity 10 through Stock Import or test seed.
2. Open /borrowed.
3. Create borrow record: sku_id = target SKU, quantity = 2, borrower = Lan cửa hàng A, location = Quầy pop-up cuối tuần.
4. Verify inventory quantity decreases from 10 to 8.
5. Open /inventory transaction history for SKU.
6. Verify transaction type BORROW_OUT.
7. Return the borrow record with note "Đã nhận đủ, hàng còn nguyên."
8. Verify inventory quantity increases from 8 to 10.
9. Verify transaction type BORROW_RETURN.
10. Try return again; expect blocked message "Phiếu mượn này đã được trả."
```

---

## Acceptance Checklist

- [ ] `borrow_records` table exists.
- [ ] `BorrowRecordStatus` has `BORROWED` and `RETURNED`.
- [ ] `BorrowRecord` belongs to `ProductSku`.
- [ ] Create borrow requires auth + CSRF.
- [ ] Create borrow accepts STAFF and STORE_MANAGER.
- [ ] Create borrow requires active, non-soft-deleted SKU.
- [ ] Create borrow requires integer quantity > 0.
- [ ] Create borrow stores borrower name and borrow location.
- [ ] Create borrow stores optional note.
- [ ] Create borrow decreases stock through `InventoryBalanceService::decrease()`.
- [ ] Create borrow writes `BORROW_OUT` transaction.
- [ ] Create borrow rolls back when stock is insufficient.
- [ ] List borrow records works without CSRF.
- [ ] List borrow records returns newest first.
- [ ] List borrow records filters by `status`.
- [ ] List borrow records searches SKU, product, borrower and location.
- [ ] Show borrow record works without CSRF.
- [ ] Return borrow requires auth + CSRF.
- [ ] Return borrow accepts STAFF and STORE_MANAGER.
- [ ] Return borrow only works for `BORROWED`.
- [ ] Return borrow cannot run twice.
- [ ] Return borrow cho phép trả kể cả khi SKU đã inactive hoặc soft-deleted.
- [ ] List/show borrow record vẫn hiển thị đúng SKU info sau khi SKU bị soft-deleted.
- [ ] Return borrow increases stock through `InventoryBalanceService::increase()`.
- [ ] Return borrow writes `BORROW_RETURN` transaction.
- [ ] Return borrow stores `returned_at`, `returned_by`, `return_note`.
- [ ] React `/borrowed` lists borrow records.
- [ ] React `/borrowed` creates borrow records.
- [ ] React `/borrowed` returns borrow records.
- [ ] Borrow route is not manager-only.
- [ ] Docs mark Borrow/Return implemented.

---

## Self-Review

- Spec coverage: UC-16 borrow out and UC-17 return are covered end-to-end.
- Placeholder scan: no TBD/TODO markers; out-of-scope items are explicit.
- Type consistency: `BorrowRecordStatus`, `BorrowRecord`, `CreateBorrowRecordData`, `ReturnBorrowRecordData`, use case names and route names are defined before use.
- Scope control: no approval workflow, partial return, CSV import, due date, People FK, barcode, multi-store or stock count.
- Architecture alignment: controller thin, FormRequest validation, use cases own workflow, balance mutation through `InventoryBalanceService`.
- Migration dùng SQLite dual-path nhất quán với `create_inventory_catalog_tables.php`.
- Search dùng runtime `DB::getDriverName()` fallback nhất quán với `ListProductsUseCase`, `ListInventoryBalancesUseCase`.
- BorrowRecord `sku()` relationship dùng `withTrashed()` để tránh null khi SKU bị soft-deleted.
- Return use case cho phép trả kể cả SKU inactive/soft-deleted — chỉ borrow mới chặn.
- Controller method dùng `returnRecord()` thay vì `return()` (PHP reserved keyword).
- Ledger `reason` truncated bằng `Str::limit(..., 255)` tránh vượt cột `VARCHAR(255)`.
- SKU picker trên UI dùng search endpoint hiện có thay vì nhập database ID thủ công.

### Technical debt ghi nhận (không thuộc scope UC-16/17)

- Pagination cho list API (Products/Inventory cũng chưa có, nhất quán).
- Frontend component testing (toàn project dùng regex route policy tests).
- Gateway integration tests qua public endpoint.
- Refactor toàn bộ ILIKE fallback sang `whereLike()` khi nâng cấp.

---

## Execution Choice

Plan complete and saved to `docs/TaskImplementDetailPlan/BorrowReturnUC-16-17plan.md`.

Recommended execution:

1. Use `superpowers:subagent-driven-development` if multi-agent tools are enabled.
2. Otherwise use `superpowers:executing-plans` inline.

This plan intentionally stops before Stock Count. Next large plan after UC-16/17 should be UC-18/19/20 Stock Count.
