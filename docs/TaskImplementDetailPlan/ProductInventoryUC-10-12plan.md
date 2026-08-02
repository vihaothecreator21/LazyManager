# Product Inventory UC-10/12 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Xây Inventory Service và React UI tối thiểu cho CRUD sản phẩm/SKU, xem tồn kho và xem lịch sử giao dịch tồn kho.

**Architecture:** Inventory Service sở hữu toàn bộ dữ liệu product, SKU, inventory balance và inventory transaction trong `inventory_db`. Controller chỉ nhận request, validate, gọi Use Case và trả Resource; mọi thay đổi tồn kho phải đi qua `InventoryBalanceService` để cập nhật balance và ghi ledger trong cùng DB transaction.

**Tech Stack:** PHP 8.3, Laravel 13, PostgreSQL, PHPUnit feature tests, Docker Compose.

## Global Constraints

- Tất cả text tiếng Việt trong code response, docs, backend messages và frontend text phải có dấu đầy đủ.
- Không thêm dependency mới.
- Có frontend tối thiểu cho `/products` và `/inventory` vì `StoreOps_MVP_1_Thang_v2.0.md` yêu cầu ngày 10 có React products + inventory list.
- Mọi task frontend/UI/UX phải dùng taste/design skill trước khi sửa UI code.
- UI LazyManager phải giữ vibe "Cozy" và "Lazy": thân thiện, bình tĩnh, dễ thao tác, ít ma sát, khoảng cách đọc thoải mái, trạng thái rõ, phản hồi mềm.
- Không dùng UI dashboard hào nhoáng, không dùng hiệu ứng nặng, không dùng palette beige-heavy một màu; ưu tiên giao diện làm việc ấm, gọn, dễ demo.
- Chuẩn hóa `product_code` và `sku_code` bằng `trim` + `uppercase` (ví dụ `strtoupper(trim($code))`).
- Không cho phép cập nhật trực tiếp trường `active` qua request PUT; chỉ deactivate sản phẩm/SKU thông qua endpoint DELETE.
- Không dùng `$balance?->quantity ?? 0` để che đậy lỗi thiếu record `inventory_balances` (balance record luôn được tạo tự động 0 khi tạo SKU).
- Tìm kiếm theo chuỗi trong PostgreSQL phải sử dụng `ILIKE` để không phân biệt chữ hoa chữ thường.
- Không thêm category tree, price, promotion, image, barcode, supplier, store, purchase order hoặc service thứ ba.
- Không gọi thẳng database của service khác.
- Không cập nhật `inventory_balances` từ Controller.
- Mọi thay đổi balance phải tạo `inventory_transactions`.
- Mọi thay đổi balance phải nằm trong DB transaction.
- Quantity dùng integer, không dùng float.
- `inventory_balances.quantity` không được âm (bảo đảm bằng DB CHECK constraint `quantity >= 0`).
- API ghi dữ liệu phải dùng cookie auth và CSRF hiện có. GET không yêu cầu CSRF, nhưng mutation (POST, PUT, DELETE) bắt buộc CSRF token.
- Cả `STORE_MANAGER` và `STAFF` được thao tác module inventory trong MVP.

---

## Current Project State

Inventory Service hiện có:

- Auth middleware: `auth.jwt`.
- CSRF middleware: `csrf.double_submit`.
- Test auth: `tests/Feature/InventoryAuthTest.php`.
- Route auth probe trong `routes/api.php`.
- Chưa có bảng inventory ngoài Laravel default `users`, `cache`, `jobs`.
- Chưa có Product/SKU model, migration, controller, request, resource, use case hoặc test nghiệp vụ.

Frontend hiện có:

- Router trong `frontend/src/App.tsx`.
- API client cookie auth + CSRF trong `frontend/src/lib/apiClient.ts`.
- API feature pattern thủ công trong `frontend/src/features/health/api/employeesApi.ts` và `frontend/src/features/schedule/api/scheduleApi.ts`.
- Chưa có TanStack Query trong dependency hiện tại; plan này không thêm dependency mới, dùng pattern `useEffect`/local state hiện có cho UI tối thiểu.

Docs đã đọc trước khi chốt plan:

- `docs/scope.md`: UC-11 là CRUD sản phẩm/SKU, UC-13 là xem tồn và lịch sử giao dịch theo đánh số trong file hiện tại.
- `docs/rules.md`: không cập nhật current balance từ controller, mọi thay đổi tồn phải có `inventory_transaction`.
- `docs/architecture.md`: Inventory Service sở hữu product, SKU, balance, transaction; public route qua `/api/inventory/v1/*`.
- `docs/decisions.md`: ADR-014 quy định `IMPORT_SYNC` là đồng bộ tuyệt đối; ADR-015 để dành stock import preview cho task sau.
- `docs/api.md`: API công khai Inventory Service đã liệt kê Product/SKU/Inventory endpoints.
- `docs/setup-plan.md`: product/inventory dùng skill `09-product-inventory`.
- `docs/StoreOps_MVP_1_Thang_v2.0.md`: ngày 9 backend Product/SKU CRUD, ngày 10 React products + inventory list.
- `docs/TaskImplementDetailPlan/ScheduleManagementUC-07-08-09plan.md`: format task TDD chi tiết, có file map, interfaces, steps, verification, self-review.

Task này bắt đầu từ backend rỗng, rồi đi đến UI tối thiểu: schema -> model -> API product/SKU -> inventory query -> ledger service test -> React products -> React inventory list.

Use case naming note:

- Tài liệu nguồn cũ `StoreOps_MVP_1_Thang_v2.0.md` gọi CRUD sản phẩm/SKU là `UC-10` và xem tồn/lịch sử là `UC-12`.
- `docs/scope.md` hiện tại có thêm use case refresh/logout nên đánh số CRUD sản phẩm/SKU là use case 11 và xem tồn/lịch sử là use case 13.
- File này giữ tên `UC-10/12` để nối tiếp skill `09-product-inventory`, nhưng phạm vi thực tế là CRUD sản phẩm/SKU + xem tồn/lịch sử, không gồm stock import.

---

## API Target

Gateway public routes:

```text
GET    /api/inventory/v1/products?search=ao
POST   /api/inventory/v1/products
GET    /api/inventory/v1/products/{id}
PUT    /api/inventory/v1/products/{id}
DELETE /api/inventory/v1/products/{id}
POST   /api/inventory/v1/products/{id}/skus
PUT    /api/inventory/v1/skus/{id}
DELETE /api/inventory/v1/skus/{id}
GET    /api/inventory/v1/inventory?search=ao
GET    /api/inventory/v1/inventory/{skuId}/transactions
```

Service internal routes:

```text
GET    /api/v1/products?search=ao
POST   /api/v1/products
GET    /api/v1/products/{id}
PUT    /api/v1/products/{id}
DELETE /api/v1/products/{id}
POST   /api/v1/products/{id}/skus
PUT    /api/v1/skus/{id}
DELETE /api/v1/skus/{id}
GET    /api/v1/inventory?search=ao
GET    /api/v1/inventory/{skuId}/transactions
```

### Product Response

```json
{
  "product": {
    "id": 1,
    "product_code": "AO-THUN",
    "name": "Áo thun",
    "active": true,
    "skus": [
      {
        "id": 1,
        "sku_code": "AO-THUN-M",
        "size": "M",
        "active": true,
        "quantity": 0
      }
    ],
    "created_at": "2026-08-01T10:00:00.000000Z",
    "updated_at": "2026-08-01T10:00:00.000000Z"
  }
}
```

### Product List Response

```json
{
  "products": [
    {
      "id": 1,
      "product_code": "AO-THUN",
      "name": "Áo thun",
      "active": true,
      "skus": [
        {
          "id": 1,
          "sku_code": "AO-THUN-M",
          "size": "M",
          "active": true,
          "quantity": 0
        }
      ],
      "created_at": "2026-08-01T10:00:00.000000Z",
      "updated_at": "2026-08-01T10:00:00.000000Z"
    }
  ]
}
```

### Inventory List Response

```json
{
  "inventory": [
    {
      "sku_id": 1,
      "sku_code": "AO-THUN-M",
      "size": "M",
      "sku_active": true,
      "product_id": 1,
      "product_code": "AO-THUN",
      "product_name": "Áo thun",
      "product_active": true,
      "quantity": 0,
      "updated_at": "2026-08-01T10:00:00.000000Z"
    }
  ]
}
```

### Transaction History Response

```json
{
  "transactions": [
    {
      "id": 1,
      "sku_id": 1,
      "type": "IMPORT_SYNC",
      "quantity_change": 5,
      "quantity_before": 0,
      "quantity_after": 5,
      "reference_type": "manual_test",
      "reference_id": "seed-1",
      "reason": "Tạo dữ liệu kiểm thử",
      "created_by": 10,
      "created_at": "2026-08-01T10:00:00.000000Z"
    }
  ]
}
```

---

## Database Design

### `products`

```text
id bigint primary key
product_code varchar(64) not null unique
name varchar(255) not null
active boolean not null default true
created_at timestamp nullable
updated_at timestamp nullable
deleted_at timestamp nullable
index(name)
```

### `product_skus`

```text
id bigint primary key
product_id bigint not null foreign key products.id
sku_code varchar(64) not null unique
size varchar(64) not null
active boolean not null default true
created_at timestamp nullable
updated_at timestamp nullable
deleted_at timestamp nullable
index(product_id)
```

### `inventory_balances`

```text
id bigint primary key
sku_id bigint not null unique foreign key product_skus.id
quantity integer not null default 0
created_at timestamp nullable
updated_at timestamp nullable
check(quantity >= 0)
```

### `inventory_transactions`

```text
id bigint primary key
sku_id bigint not null foreign key product_skus.id
type varchar(32) not null
quantity_change integer not null
quantity_before integer not null
quantity_after integer not null
reference_type varchar(64) nullable
reference_id varchar(64) nullable
reason varchar(255) nullable
created_by bigint nullable
created_at timestamp not null
updated_at timestamp nullable
check(quantity_before >= 0)
check(quantity_after >= 0)
index(sku_id)
index(created_at)
index(type)
```

Deliberate simplification:

- `created_by` không có foreign key sang People Service vì cấm cross-database.
- `reference_id` là string để tái dùng cho import/sales/borrow sau này.
- `DELETE product` là deactivate product, deactivate + soft delete toàn bộ SKU thuộc product trong cùng DB transaction. Balance và transaction giữ nguyên.
- `DELETE SKU` là deactivate + soft delete SKU. Balance và transaction giữ nguyên.
- Inventory list chỉ hiển thị Product/SKU chưa soft-delete.
- Transaction history vẫn xem được cho SKU đã soft-delete bằng route `withTrashed()`.

---

## Domain Flow

### Product CRUD

```text
Client
  -> auth.jwt + csrf.double_submit
  -> FormRequest
  -> ProductController
  -> UseCase
  -> Eloquent model
  -> ProductResource
  -> JSON
```

### Create SKU

```text
Client POST /products/{id}/skus
  -> validate sku_code + size
  -> find active, non-deleted product
  -> create product_skus row
  -> create inventory_balances row with quantity = 0
  -> return SKU in product context
```

### Deactivate SKU

```text
Client DELETE /skus/{id}
  -> find SKU including balance and transactions
  -> set active = false
  -> soft delete SKU
  -> keep inventory_balances
  -> keep inventory_transactions
  -> return 200 with SKU status
```

### Inventory List

```text
Client GET /inventory
  -> query product_skus with product + balance
  -> optional search product_code, sku_code, product name
  -> newest product_skus first by id desc
  -> return flattened rows for table UI
```

### Transaction History

```text
Client GET /inventory/{skuId}/transactions
  -> route model binding uses withTrashed()
  -> verify SKU exists, including soft-deleted SKU
  -> query inventory_transactions by sku_id
  -> order by created_at desc, id desc
  -> return ledger rows
```

---

## Core Algorithms

### `InventoryBalanceService::increase`

```text
Input: sku, quantity, type, reference_type, reference_id, reason, created_by
1. Validate quantity > 0.
2. Delegate to private helper applyDelta(sku, +quantity, type, reference_type, reference_id, reason, created_by).
```

### `InventoryBalanceService::decrease`

```text
Input: sku, quantity, type, reference_type, reference_id, reason, created_by
1. Validate quantity > 0.
2. Delegate to private helper applyDelta(sku, -quantity, type, reference_type, reference_id, reason, created_by).
```

### `InventoryBalanceService::synchronize`

```text
Input: sku, target_quantity, type, reference_type, reference_id, reason, created_by
1. Validate target_quantity >= 0.
2. Validate type == InventoryTransactionType::ImportSync. If not, throw InventoryBusinessException('Phương thức đồng bộ tồn kho chỉ chấp nhận loại giao dịch IMPORT_SYNC.').
3. Delegate to private helper applyTarget(sku, target_quantity, type, reference_type, reference_id, reason, created_by).
```

### Private Helpers

`applyDelta(sku, delta, type, ...)`:

```text
1. Start DB transaction.
2. Lock existing `inventory_balances` row by sku_id with `lockForUpdate`.
3. If missing, throw `InventoryBusinessException('Không tìm thấy dữ liệu tồn kho của SKU.')`; do not auto-create balance.
4. quantity_before = balance.quantity.
5. quantity_after = quantity_before + delta.
6. If quantity_after < 0, throw `InsufficientStockException`; rollback.
7. quantity_change = delta.
8. Update balance.quantity = quantity_after.
9. Insert `inventory_transactions` with before/change/after.
10. Commit transaction and return transaction model.
```

`applyTarget(sku, targetQuantity, type, ...)`:

```text
1. Start DB transaction.
2. Lock existing `inventory_balances` row by sku_id with `lockForUpdate`.
3. If missing, throw `InventoryBusinessException('Không tìm thấy dữ liệu tồn kho của SKU.')`; do not auto-create balance.
4. quantity_before = balance.quantity.
5. quantity_after = targetQuantity.
6. quantity_change = quantity_after - quantity_before.
7. Update balance.quantity = quantity_after.
8. Insert `inventory_transactions` even when quantity_change = 0, because import sync must be explainable.
9. Commit transaction and return transaction model.
```

---

## File Map

Create:

```text
services/inventory-service/database/migrations/2026_08_01_000100_create_inventory_catalog_tables.php
services/inventory-service/app/Domain/Enums/InventoryTransactionType.php
services/inventory-service/app/Domain/Exceptions/InventoryBusinessException.php
services/inventory-service/app/Domain/Exceptions/InsufficientStockException.php
services/inventory-service/app/Domain/Services/InventoryBalanceService.php
services/inventory-service/app/Models/Product.php
services/inventory-service/app/Models/ProductSku.php
services/inventory-service/app/Models/InventoryBalance.php
services/inventory-service/app/Models/InventoryTransaction.php
services/inventory-service/app/Application/DTOs/CreateProductData.php
services/inventory-service/app/Application/DTOs/UpdateProductData.php
services/inventory-service/app/Application/DTOs/CreateSkuData.php
services/inventory-service/app/Application/DTOs/UpdateSkuData.php
services/inventory-service/app/Application/UseCases/ListProductsUseCase.php
services/inventory-service/app/Application/UseCases/CreateProductUseCase.php
services/inventory-service/app/Application/UseCases/GetProductUseCase.php
services/inventory-service/app/Application/UseCases/UpdateProductUseCase.php
services/inventory-service/app/Application/UseCases/DeactivateProductUseCase.php
services/inventory-service/app/Application/UseCases/CreateSkuUseCase.php
services/inventory-service/app/Application/UseCases/UpdateSkuUseCase.php
services/inventory-service/app/Application/UseCases/DeactivateSkuUseCase.php
services/inventory-service/app/Application/UseCases/ListInventoryBalancesUseCase.php
services/inventory-service/app/Application/UseCases/ListInventoryTransactionsUseCase.php
services/inventory-service/app/Http/Controllers/ProductController.php
services/inventory-service/app/Http/Controllers/SkuController.php
services/inventory-service/app/Http/Controllers/InventoryController.php
services/inventory-service/app/Http/Requests/StoreProductRequest.php
services/inventory-service/app/Http/Requests/UpdateProductRequest.php
services/inventory-service/app/Http/Requests/StoreSkuRequest.php
services/inventory-service/app/Http/Requests/UpdateSkuRequest.php
services/inventory-service/app/Http/Resources/ProductResource.php
services/inventory-service/app/Http/Resources/ProductSkuResource.php
services/inventory-service/app/Http/Resources/InventoryBalanceResource.php
services/inventory-service/app/Http/Resources/InventoryTransactionResource.php
services/inventory-service/tests/Feature/ProductInventoryTest.php
services/inventory-service/tests/Unit/InventoryBalanceServiceTest.php
frontend/src/features/inventory/api/inventoryApi.ts
frontend/src/features/inventory/pages/ProductsPage.tsx
frontend/src/features/inventory/pages/InventoryPage.tsx
```

Modify:

```text
services/inventory-service/routes/api.php
services/inventory-service/bootstrap/app.php
frontend/src/App.tsx
frontend/src/features/health/pages/DashboardPage.tsx
frontend/src/dashboardExperience.test.mjs
docs/api.md
```

No repository interfaces in this plan:

- Current Inventory Service has no repository pattern yet.
- Product/SKU queries are single-service Eloquent reads/writes.
- The rule-heavy part is balance mutation; it lives in `InventoryBalanceService`.
- Có thể bổ sung repository interfaces khi các tính năng import/sales/borrow tái sử dụng query phức tạp hoặc test cần fake persistence.

---

## Task 1: Inventory Schema

**Files:**

- Create: `services/inventory-service/database/migrations/2026_08_01_000100_create_inventory_catalog_tables.php`
- Test: `services/inventory-service/tests/Feature/ProductInventoryTest.php`

**Interfaces:**

- Produces tables: `products`, `product_skus`, `inventory_balances`, `inventory_transactions`.
- Later tasks rely on FK names and columns exactly as listed in Database Design.

- [ ] **Step 1: Write failing schema test**

Add this first test class:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ProductInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_catalog_tables_exist(): void
    {
        self::assertTrue(Schema::hasTable('products'));
        self::assertTrue(Schema::hasTable('product_skus'));
        self::assertTrue(Schema::hasTable('inventory_balances'));
        self::assertTrue(Schema::hasTable('inventory_transactions'));

        self::assertTrue(Schema::hasColumns('inventory_balances', [
            'sku_id',
            'quantity',
        ]));
        self::assertTrue(Schema::hasColumns('inventory_transactions', [
            'quantity_change',
            'quantity_before',
            'quantity_after',
        ]));
    }

    public function test_inventory_balance_cannot_be_negative(): void
    {
        $productId = \Illuminate\Support\Facades\DB::table('products')->insertGetId([
            'product_code' => 'AO-THUN',
            'name' => 'Áo thun',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $skuId = \Illuminate\Support\Facades\DB::table('product_skus')->insertGetId([
            'product_id' => $productId,
            'sku_code' => 'AO-THUN-M',
            'size' => 'M',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \Illuminate\Support\Facades\DB::table('inventory_balances')->insert([
            'sku_id' => $skuId,
            'quantity' => -1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
```

- [ ] **Step 2: Run failing test**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=ProductInventoryTest
```

Expected: FAIL because tables do not exist.

- [ ] **Step 3: Create migration**

Create migration with:

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
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('product_code', 64)->unique();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('name');
        });

        Schema::create('product_skus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products');
            $table->string('sku_code', 64)->unique();
            $table->string('size', 64);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('product_id');
        });

        Schema::create('inventory_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sku_id')->unique()->constrained('product_skus');
            $table->integer('quantity')->default(0);
            $table->timestamps();
        });

        Schema::create('inventory_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sku_id')->constrained('product_skus');
            $table->string('type', 32);
            $table->integer('quantity_change');
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->string('reference_type', 64)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
            $table->index('sku_id');
            $table->index('created_at');
            $table->index('type');
        });

        DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT inventory_balances_quantity_non_negative CHECK (quantity >= 0)');
        DB::statement('ALTER TABLE inventory_transactions ADD CONSTRAINT inventory_transactions_quantity_before_non_negative CHECK (quantity_before >= 0)');
        DB::statement('ALTER TABLE inventory_transactions ADD CONSTRAINT inventory_transactions_quantity_after_non_negative CHECK (quantity_after >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('product_skus');
        Schema::dropIfExists('products');
    }
};
```

- [ ] **Step 4: Run migration test**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=ProductInventoryTest
```

Expected: PASS.

- [ ] **Step 5: Verify rollback**

Run:

```powershell
docker compose exec -T inventory-service php artisan migrate:fresh --force
docker compose exec -T inventory-service php artisan migrate:rollback --step=1 --force
docker compose exec -T inventory-service php artisan migrate --force
```

Expected: all commands exit `0`.

- [ ] **Step 6: Commit**

```powershell
git add services/inventory-service/database/migrations/2026_08_01_000100_create_inventory_catalog_tables.php services/inventory-service/tests/Feature/ProductInventoryTest.php
git commit -m "feat(inventory): add product inventory schema"
```

---

## Task 2: Models And Enum

**Files:**

- Create: `services/inventory-service/app/Domain/Enums/InventoryTransactionType.php`
- Create: `services/inventory-service/app/Models/Product.php`
- Create: `services/inventory-service/app/Models/ProductSku.php`
- Create: `services/inventory-service/app/Models/InventoryBalance.php`
- Create: `services/inventory-service/app/Models/InventoryTransaction.php`
- Modify: `services/inventory-service/tests/Feature/ProductInventoryTest.php`

**Interfaces:**

- Produces enum cases:
  - `InventoryTransactionType::ImportSync`
  - `InventoryTransactionType::Sale`
  - `InventoryTransactionType::SaleReversal`
  - `InventoryTransactionType::BorrowOut`
  - `InventoryTransactionType::BorrowReturn`
- Produces relationships:
  - `Product::skus()`
  - `ProductSku::product()`
  - `ProductSku::balance()`
  - `ProductSku::transactions()`
  - `InventoryBalance::sku()`
  - `InventoryTransaction::sku()`

- [ ] **Step 1: Write failing relationship test**

Append:

```php
public function test_product_sku_balance_and_transaction_relationships_work(): void
{
    $product = \App\Models\Product::query()->create([
        'product_code' => 'AO-THUN',
        'name' => 'Áo thun',
        'active' => true,
    ]);

    $sku = $product->skus()->create([
        'sku_code' => 'AO-THUN-M',
        'size' => 'M',
        'active' => true,
    ]);

    $sku->balance()->create(['quantity' => 0]);
    $sku->transactions()->create([
        'type' => \App\Domain\Enums\InventoryTransactionType::ImportSync->value,
        'quantity_change' => 0,
        'quantity_before' => 0,
        'quantity_after' => 0,
        'reason' => 'Tạo dữ liệu kiểm thử',
        'created_by' => 10,
    ]);

    $sku->refresh();

    self::assertSame('AO-THUN', $sku->product->product_code);
    self::assertSame(0, $sku->balance->quantity);
    self::assertSame('IMPORT_SYNC', $sku->transactions()->first()->type->value);
}
```

- [ ] **Step 2: Run failing test**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=product_sku_balance
```

Expected: FAIL because models and enum do not exist.

- [ ] **Step 3: Add enum**

```php
<?php

namespace App\Domain\Enums;

enum InventoryTransactionType: string
{
    case ImportSync = 'IMPORT_SYNC';
    case Sale = 'SALE';
    case SaleReversal = 'SALE_REVERSAL';
    case BorrowOut = 'BORROW_OUT';
    case BorrowReturn = 'BORROW_RETURN';
}
```

- [ ] **Step 4: Add models**

Use `SoftDeletes` on `Product` and `ProductSku`. Use `$guarded = []`. Cast `active` to boolean, `quantity` to integer, `type` to `InventoryTransactionType::class`.

Core relationship signatures:

```php
public function skus(): HasMany
public function product(): BelongsTo
public function balance(): HasOne
public function transactions(): HasMany
public function sku(): BelongsTo
```

- [ ] **Step 5: Run relationship test**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=product_sku_balance
```

Expected: PASS.

- [ ] **Step 6: Commit**

```powershell
git add services/inventory-service/app/Domain/Enums/InventoryTransactionType.php services/inventory-service/app/Models services/inventory-service/tests/Feature/ProductInventoryTest.php
git commit -m "feat(inventory): add inventory catalog models"
```

---

## Task 3: Product CRUD API

**Files:**

- Create: `services/inventory-service/app/Application/DTOs/CreateProductData.php`
- Create: `services/inventory-service/app/Application/DTOs/UpdateProductData.php`
- Create: `services/inventory-service/app/Application/UseCases/ListProductsUseCase.php`
- Create: `services/inventory-service/app/Application/UseCases/CreateProductUseCase.php`
- Create: `services/inventory-service/app/Application/UseCases/GetProductUseCase.php`
- Create: `services/inventory-service/app/Application/UseCases/UpdateProductUseCase.php`
- Create: `services/inventory-service/app/Application/UseCases/DeactivateProductUseCase.php`
- Create: `services/inventory-service/app/Domain/Exceptions/InventoryBusinessException.php`
- Create: `services/inventory-service/app/Http/Requests/StoreProductRequest.php`
- Create: `services/inventory-service/app/Http/Requests/UpdateProductRequest.php`
- Create: `services/inventory-service/app/Http/Resources/ProductResource.php`
- Create: `services/inventory-service/app/Http/Resources/ProductSkuResource.php`
- Create: `services/inventory-service/app/Http/Controllers/ProductController.php`
- Modify: `services/inventory-service/bootstrap/app.php`
- Modify: `services/inventory-service/routes/api.php`
- Modify: `services/inventory-service/tests/Feature/ProductInventoryTest.php`

**Interfaces:**

- `new CreateProductData(string $productCode, string $name)` (tự động `strtoupper(trim($productCode))`)
- `new UpdateProductData(?string $productCode, ?string $name)` (tự động `strtoupper(trim($productCode))`, không cho sửa `active` qua PUT)
- `CreateProductUseCase::execute(CreateProductData $data): Product`
- `UpdateProductUseCase::execute(Product $product, UpdateProductData $data): Product`
- `DeactivateProductUseCase::execute(Product $product): Product`
- Routes use implicit binding `{product}`.
- Duplicate `product_code` throws `InventoryBusinessException('Mã sản phẩm đã tồn tại.', 409)`.

- [ ] **Step 1: Add product API tests**

Add tests for:

```php
public function test_authenticated_user_can_create_product(): void
public function test_staff_role_can_create_and_manage_products(): void
public function test_product_code_must_be_unique(): void
public function test_cannot_reuse_soft_deleted_product_code(): void
public function test_product_code_is_normalized_to_uppercase_and_trimmed(): void
public function test_authenticated_user_can_list_products_with_skus(): void
public function test_authenticated_user_can_update_product(): void
public function test_authenticated_user_can_deactivate_product(): void
public function test_deactivating_product_deactivates_and_soft_deletes_all_associated_skus(): void
public function test_get_product_requests_do_not_require_csrf(): void
public function test_product_mutation_without_csrf_returns_419(): void
```

Use helpers:

```php
private function authHeaders(): array
{
    return ['X-CSRF-TOKEN' => 'inventory-csrf'];
}

private function actingWithInventoryCookie(string $role = 'STORE_MANAGER'): self
{
    return $this
        ->withCredentials()
        ->withUnencryptedCookie('lm_access_token', $this->issueToken(role: $role))
        ->withUnencryptedCookie('lm_csrf_token', 'inventory-csrf');
}
```

Expected create assertion:

```php
$this->actingWithInventoryCookie()
    ->withHeaders($this->authHeaders())
    ->postJson('/api/v1/products', [
        'product_code' => '  ao-thun  ',
        'name' => 'Áo thun',
    ])
    ->assertCreated()
    ->assertJsonPath('product.product_code', 'AO-THUN')
    ->assertJsonPath('product.name', 'Áo thun')
    ->assertJsonPath('product.active', true);
```

Expected duplicate assertion:

```php
$this->actingWithInventoryCookie()
    ->withHeaders($this->authHeaders())
    ->postJson('/api/v1/products', [
        'product_code' => 'AO-THUN',
        'name' => 'Áo thun trùng',
    ])
    ->assertStatus(409)
    ->assertJsonPath('message', 'Mã sản phẩm đã tồn tại.');
```

- [ ] **Step 2: Run failing tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=ProductInventoryTest
```

Expected: product API tests fail with `404` or missing classes.

- [ ] **Step 3: Create DTOs**

Use readonly DTOs với constructor normalization:

```php
final readonly class CreateProductData
{
    public string $productCode;

    public function __construct(
        string $productCode,
        public string $name,
    ) {
        $this->productCode = strtoupper(trim($productCode));
    }
}
```

```php
final readonly class UpdateProductData
{
    public ?string $productCode;

    public function __construct(
        ?string $productCode,
        public ?string $name,
    ) {
        $this->productCode = $productCode !== null ? strtoupper(trim($productCode)) : null;
    }
}
```

- [ ] **Step 4: Create FormRequests**

`StoreProductRequest` rules (không có trường `active`):

```php
[
    'product_code' => ['required', 'string', 'max:64'],
    'name' => ['required', 'string', 'max:255'],
]
```

`UpdateProductRequest` rules (không cho cập nhật trực tiếp `active`):

```php
[
    'product_code' => ['sometimes', 'required', 'string', 'max:64'],
    'name' => ['sometimes', 'required', 'string', 'max:255'],
]
```

- [ ] **Step 5: Create business exception**

```php
<?php

namespace App\Domain\Exceptions;

use RuntimeException;

class InventoryBusinessException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
```

- [ ] **Step 6: Create use cases**

`ListProductsUseCase` (dùng `ILIKE` cho PostgreSQL):

```text
Query Product with ['skus.balance'].
If search present, filter using ILIKE: product_code ILIKE %, name ILIKE %, or skus.sku_code ILIKE %.
Order by id desc.
Return Collection.
```

`DeactivateProductUseCase`:

```text
Run in DB transaction.
Set product.active = false.
Set all product SKUs active = false.
Soft delete all product SKUs.
Soft delete product.
Do not delete balance or transaction rows.
Return product withTrashed fresh with ['skus' => withTrashed + balance].
```

`CreateProductUseCase` conflict check:

```php
if (Product::withTrashed()->where('product_code', $data->productCode)->exists()) {
    throw new InventoryBusinessException('Mã sản phẩm đã tồn tại.', 409);
}
```

`UpdateProductUseCase` conflict check:

```php
if (
    $data->productCode !== null
    && Product::withTrashed()
        ->where('product_code', $data->productCode)
        ->whereKeyNot($product->id)
        ->exists()
) {
    throw new InventoryBusinessException('Mã sản phẩm đã tồn tại.', 409);
}
```

Wrap create/update save in `try/catch` for `Illuminate\Database\UniqueConstraintViolationException` and return the same `InventoryBusinessException('Mã sản phẩm đã tồn tại.', 409)` for concurrent duplicate requests.

- [ ] **Step 7: Map business exceptions**

In `bootstrap/app.php`, add exception rendering using Laravel 13 bootstrap exceptions API.

Behavior:

```text
If request expects JSON and exception is InventoryBusinessException:
Return response()->json(['message' => $e->getMessage()], $e->status)
```

- [ ] **Step 8: Create resources**

`ProductResource` returns exact Product Response shape. `ProductSkuResource` maps `'quantity' => $this->balance->quantity` directly from loaded `balance` (as a balance record is always created alongside every SKU).

- [ ] **Step 9: Create controller and routes**

Routes inside existing `Route::prefix('v1')->middleware(['auth.jwt', 'csrf.double_submit'])->group(...)`:

```php
Route::apiResource('products', ProductController::class);
```

Controller methods:

```php
public function index(Request $request, ListProductsUseCase $useCase): JsonResponse
public function store(StoreProductRequest $request, CreateProductUseCase $useCase): JsonResponse
public function show(Product $product, GetProductUseCase $useCase): JsonResponse
public function update(UpdateProductRequest $request, Product $product, UpdateProductUseCase $useCase): JsonResponse
public function destroy(Product $product, DeactivateProductUseCase $useCase): JsonResponse
```

- [ ] **Step 10: Run product API tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=ProductInventoryTest
```

Expected: product CRUD tests PASS.

- [ ] **Step 11: Commit**

```powershell
git add services/inventory-service/app services/inventory-service/routes/api.php services/inventory-service/tests/Feature/ProductInventoryTest.php
git commit -m "feat(inventory): add product CRUD API"
```

---

## Task 4: SKU CRUD API

**Files:**

- Create: `services/inventory-service/app/Application/DTOs/CreateSkuData.php`
- Create: `services/inventory-service/app/Application/DTOs/UpdateSkuData.php`
- Create: `services/inventory-service/app/Application/UseCases/CreateSkuUseCase.php`
- Create: `services/inventory-service/app/Application/UseCases/UpdateSkuUseCase.php`
- Create: `services/inventory-service/app/Application/UseCases/DeactivateSkuUseCase.php`
- Create: `services/inventory-service/app/Http/Requests/StoreSkuRequest.php`
- Create: `services/inventory-service/app/Http/Requests/UpdateSkuRequest.php`
- Create: `services/inventory-service/app/Http/Controllers/SkuController.php`
- Modify: `services/inventory-service/routes/api.php`
- Modify: `services/inventory-service/tests/Feature/ProductInventoryTest.php`

**Interfaces:**

- `new CreateSkuData(string $skuCode, string $size)` (tự động `strtoupper(trim($skuCode))`)
- `new UpdateSkuData(?string $skuCode, ?string $size)` (tự động `strtoupper(trim($skuCode))`, không cho sửa `active` qua PUT)
- `CreateSkuUseCase::execute(Product $product, CreateSkuData $data): ProductSku`
- `UpdateSkuUseCase::execute(ProductSku $sku, UpdateSkuData $data): ProductSku`
- `DeactivateSkuUseCase::execute(ProductSku $sku): ProductSku`
- Duplicate `sku_code` throws `InventoryBusinessException('Mã SKU đã tồn tại.', 409)`.

- [ ] **Step 1: Add SKU API tests**

Add tests:

```php
public function test_authenticated_user_can_create_sku_and_zero_balance_is_created(): void
public function test_sku_code_is_normalized_to_uppercase_and_trimmed(): void
public function test_sku_code_must_be_unique(): void
public function test_cannot_reuse_soft_deleted_sku_code(): void
public function test_cannot_create_sku_for_inactive_product(): void
public function test_authenticated_user_can_update_sku(): void
public function test_authenticated_user_can_deactivate_sku_without_deleting_balance(): void
```

Create SKU assertion:

```php
$response = $this->actingWithInventoryCookie()
    ->withHeaders($this->authHeaders())
    ->postJson("/api/v1/products/{$product->id}/skus", [
        'sku_code' => '  ao-thun-m  ',
        'size' => 'M',
    ])
    ->assertCreated()
    ->assertJsonPath('sku.sku_code', 'AO-THUN-M')
    ->assertJsonPath('sku.quantity', 0);

$skuId = $response->json('sku.id');
$this->assertDatabaseHas('inventory_balances', [
    'sku_id' => $skuId,
    'quantity' => 0,
]);
```

Expected duplicate assertion:

```php
$this->actingWithInventoryCookie()
    ->withHeaders($this->authHeaders())
    ->postJson("/api/v1/products/{$product->id}/skus", [
        'sku_code' => 'AO-THUN-M',
        'size' => 'L',
    ])
    ->assertStatus(409)
    ->assertJsonPath('message', 'Mã SKU đã tồn tại.');
```

- [ ] **Step 2: Run failing SKU tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=ProductInventoryTest
```

Expected: SKU tests fail because endpoint/use cases do not exist.

- [ ] **Step 3: Create DTOs and FormRequests**

`CreateSkuData` & `UpdateSkuData` normalization:

```php
final readonly class CreateSkuData
{
    public string $skuCode;

    public function __construct(
        string $skuCode,
        public string $size,
    ) {
        $this->skuCode = strtoupper(trim($skuCode));
    }
}
```

```php
final readonly class UpdateSkuData
{
    public ?string $skuCode;

    public function __construct(
        ?string $skuCode,
        public ?string $size,
    ) {
        $this->skuCode = $skuCode !== null ? strtoupper(trim($skuCode)) : null;
    }
}
```

`StoreSkuRequest` rules (không có `active`):

```php
[
    'sku_code' => ['required', 'string', 'max:64'],
    'size' => ['required', 'string', 'max:64'],
]
```

`UpdateSkuRequest` rules (không cho cập nhật trực tiếp `active`):

```php
[
    'sku_code' => ['sometimes', 'required', 'string', 'max:64'],
    'size' => ['sometimes', 'required', 'string', 'max:64'],
]
```

- [ ] **Step 4: Create SKU use cases**

`CreateSkuUseCase`:

```text
If product.active is false or product trashed, throw 422 with message "Không thể tạo SKU cho sản phẩm không còn hoạt động."
Run in DB transaction.
Create product_skus.
Create inventory_balances quantity = 0 in the same transaction.
Return SKU with ['product', 'balance'].
```

`CreateSkuUseCase` conflict check:

```php
if (ProductSku::withTrashed()->where('sku_code', $data->skuCode)->exists()) {
    throw new InventoryBusinessException('Mã SKU đã tồn tại.', 409);
}
```

`UpdateSkuUseCase` conflict check:

```php
if (
    $data->skuCode !== null
    && ProductSku::withTrashed()
        ->where('sku_code', $data->skuCode)
        ->whereKeyNot($sku->id)
        ->exists()
) {
    throw new InventoryBusinessException('Mã SKU đã tồn tại.', 409);
}
```

Wrap create/update save in `try/catch` for `Illuminate\Database\UniqueConstraintViolationException` and return the same `InventoryBusinessException('Mã SKU đã tồn tại.', 409)` for concurrent duplicate requests.

`DeactivateSkuUseCase`:

```text
Set sku.active = false.
Soft delete SKU.
Keep balance row.
Keep transactions.
Return SKU withTrashed with ['product', 'balance'].
```

- [ ] **Step 5: Create controller and routes**

Routes:

```php
Route::post('/products/{product}/skus', [SkuController::class, 'store']);
Route::put('/skus/{sku}', [SkuController::class, 'update']);
Route::delete('/skus/{sku}', [SkuController::class, 'destroy']);
```

Controller methods:

```php
public function store(StoreSkuRequest $request, Product $product, CreateSkuUseCase $useCase): JsonResponse
public function update(UpdateSkuRequest $request, ProductSku $sku, UpdateSkuUseCase $useCase): JsonResponse
public function destroy(ProductSku $sku, DeactivateSkuUseCase $useCase): JsonResponse
```

- [ ] **Step 6: Run SKU tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=ProductInventoryTest
```

Expected: SKU tests PASS.

- [ ] **Step 7: Commit**

```powershell
git add services/inventory-service/app services/inventory-service/routes/api.php services/inventory-service/tests/Feature/ProductInventoryTest.php
git commit -m "feat(inventory): add SKU CRUD API"
```

---

## Task 5: Inventory Balance Service

**Files:**

- Modify: `services/inventory-service/app/Domain/Exceptions/InventoryBusinessException.php`
- Create: `services/inventory-service/app/Domain/Exceptions/InsufficientStockException.php`
- Create: `services/inventory-service/app/Domain/Services/InventoryBalanceService.php`
- Create: `services/inventory-service/tests/Unit/InventoryBalanceServiceTest.php`

**Interfaces:**

```php
public function increase(
    ProductSku $sku,
    int $quantity,
    InventoryTransactionType $type,
    ?string $referenceType = null,
    ?string $referenceId = null,
    ?string $reason = null,
    ?int $createdBy = null,
): InventoryTransaction
```

```php
public function decrease(
    ProductSku $sku,
    int $quantity,
    InventoryTransactionType $type,
    ?string $referenceType = null,
    ?string $referenceId = null,
    ?string $reason = null,
    ?int $createdBy = null,
): InventoryTransaction
```

```php
public function synchronize(
    ProductSku $sku,
    int $targetQuantity,
    InventoryTransactionType $type,
    ?string $referenceType = null,
    ?string $referenceId = null,
    ?string $reason = null,
    ?int $createdBy = null,
): InventoryTransaction
```

- [ ] **Step 1: Write failing service tests**

Test cases:

```php
public function test_increase_updates_balance_and_creates_transaction(): void
public function test_decrease_updates_balance_and_creates_transaction(): void
public function test_decrease_more_than_balance_throws_and_rolls_back(): void
public function test_synchronize_sets_absolute_quantity_and_creates_transaction(): void
public function test_quantity_must_be_positive_for_increase_and_decrease(): void
public function test_missing_balance_throws_business_error_without_creating_balance(): void
```

Key assertion:

```php
$transaction = $service->increase(
    sku: $sku,
    quantity: 5,
    type: InventoryTransactionType::ImportSync,
    referenceType: 'manual_test',
    referenceId: 'seed-1',
    reason: 'Tạo dữ liệu kiểm thử',
    createdBy: 10,
);

self::assertSame(0, $transaction->quantity_before);
self::assertSame(5, $transaction->quantity_change);
self::assertSame(5, $transaction->quantity_after);
$this->assertDatabaseHas('inventory_balances', [
    'sku_id' => $sku->id,
    'quantity' => 5,
]);
```

- [ ] **Step 2: Run failing service tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=InventoryBalanceServiceTest
```

Expected: FAIL because service/exceptions do not exist.

- [ ] **Step 3: Create exceptions**

```php
<?php

namespace App\Domain\Exceptions;

use RuntimeException;

class InventoryBusinessException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
```

```php
<?php

namespace App\Domain\Exceptions;

final class InsufficientStockException extends InventoryBusinessException
{
    public function __construct()
    {
        parent::__construct('Tồn kho không đủ để thực hiện thao tác này.');
    }
}
```

- [ ] **Step 4: Implement `InventoryBalanceService`**

Implement two private helper methods:

```php
private function applyDelta(
    ProductSku $sku,
    int $delta,
    InventoryTransactionType $type,
    ?string $referenceType,
    ?string $referenceId,
    ?string $reason,
    ?int $createdBy,
): InventoryTransaction
```

```php
private function applyTarget(
    ProductSku $sku,
    int $targetQuantity,
    InventoryTransactionType $type,
    ?string $referenceType,
    ?string $referenceId,
    ?string $reason,
    ?int $createdBy,
): InventoryTransaction
```

Behavior:

```text
increase(): Call applyDelta(sku, +quantity, type, ...)
decrease(): Call applyDelta(sku, -quantity, type, ...)
synchronize(): Check type == InventoryTransactionType::ImportSync (else throw InventoryBusinessException('Phương thức đồng bộ tồn kho chỉ chấp nhận loại giao dịch IMPORT_SYNC.')). Then call applyTarget(sku, targetQuantity, type, ...)

Inside applyDelta:
  DB::transaction.
  Query inventory_balances by sku_id with lockForUpdate() BEFORE reading balance.quantity.
  If missing, throw InventoryBusinessException('Không tìm thấy dữ liệu tồn kho của SKU.').
  quantity_before = balance.quantity; quantity_after = quantity_before + delta.
  If quantity_after < 0, throw InsufficientStockException.
  balance.quantity = quantity_after; balance.save().
  Create inventory_transactions with before/change/after and return transaction.

Inside applyTarget:
  DB::transaction.
  Query inventory_balances by sku_id with lockForUpdate() BEFORE reading balance.quantity.
  If missing, throw InventoryBusinessException('Không tìm thấy dữ liệu tồn kho của SKU.').
  quantity_before = balance.quantity; quantity_after = targetQuantity; quantity_change = quantity_after - quantity_before.
  balance.quantity = quantity_after; balance.save().
  Create inventory_transactions with before/change/after and return transaction.
```

- [ ] **Step 5: Run service tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=InventoryBalanceServiceTest
```

Expected: PASS.

- [ ] **Step 6: Commit**

```powershell
git add services/inventory-service/app/Domain services/inventory-service/tests/Unit/InventoryBalanceServiceTest.php
git commit -m "feat(inventory): add balance ledger service"
```

---

## Task 6: Inventory Query API

**Files:**

- Create: `services/inventory-service/app/Application/UseCases/ListInventoryBalancesUseCase.php`
- Create: `services/inventory-service/app/Application/UseCases/ListInventoryTransactionsUseCase.php`
- Create: `services/inventory-service/app/Http/Resources/InventoryBalanceResource.php`
- Create: `services/inventory-service/app/Http/Resources/InventoryTransactionResource.php`
- Create: `services/inventory-service/app/Http/Controllers/InventoryController.php`
- Modify: `services/inventory-service/routes/api.php`
- Modify: `services/inventory-service/tests/Feature/ProductInventoryTest.php`

**Interfaces:**

- `ListInventoryBalancesUseCase::execute(?string $search): Collection`
- `ListInventoryTransactionsUseCase::execute(ProductSku $sku): Collection`

- [ ] **Step 1: Add inventory query tests**

Tests:

```php
public function test_inventory_list_returns_flat_balance_rows(): void
public function test_inventory_list_supports_search_by_product_code_name_or_sku_code(): void
public function test_transaction_history_is_newest_first(): void
public function test_transaction_history_for_missing_sku_returns_404(): void
```

Newest first assertion:

```php
$this->actingWithInventoryCookie()
    ->getJson("/api/v1/inventory/{$sku->id}/transactions")
    ->assertOk()
    ->assertJsonPath('transactions.0.quantity_after', 7)
    ->assertJsonPath('transactions.1.quantity_after', 5);
```

- [ ] **Step 2: Run failing query tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=ProductInventoryTest
```

Expected: inventory query tests fail with `404`.

- [ ] **Step 3: Implement use cases**

`ListInventoryBalancesUseCase`:

```text
Query ProductSku with ['product', 'balance'].
Inventory list shows only Product/SKU rows that are not soft-deleted.
Filter search using ILIKE: sku_code ILIKE %, product.product_code ILIKE %, product.name ILIKE %.
Order by product_skus.id desc.
Return collection.
```

`ListInventoryTransactionsUseCase`:

```text
Accept ProductSku route model.
Query sku.transactions().
Order by created_at desc.
Order by id desc.
Return collection.
```

- [ ] **Step 4: Implement resources**

`InventoryBalanceResource` maps:

```php
[
    'sku_id' => $this->id,
    'sku_code' => $this->sku_code,
    'size' => $this->size,
    'sku_active' => $this->active,
    'product_id' => $this->product->id,
    'product_code' => $this->product->product_code,
    'product_name' => $this->product->name,
    'product_active' => $this->product->active,
    'quantity' => $this->balance->quantity,
    'updated_at' => $this->balance->updated_at?->toJSON(),
]
```

`InventoryTransactionResource` maps transaction fields exactly from Transaction History Response.

- [ ] **Step 5: Implement controller and routes**

Routes:

```php
Route::get('/inventory', [InventoryController::class, 'index']);
Route::get('/inventory/{sku}/transactions', [InventoryController::class, 'transactions'])->withTrashed();
```

Controller methods:

```php
public function index(Request $request, ListInventoryBalancesUseCase $useCase): JsonResponse
public function transactions(ProductSku $sku, ListInventoryTransactionsUseCase $useCase): JsonResponse
```

- [ ] **Step 6: Run query tests**

Run:

```powershell
docker compose exec -T inventory-service php artisan test --filter=ProductInventoryTest
```

Expected: PASS.

- [ ] **Step 7: Commit**

```powershell
git add services/inventory-service/app services/inventory-service/routes/api.php services/inventory-service/tests/Feature/ProductInventoryTest.php
git commit -m "feat(inventory): add inventory query API"
```

---

## Task 7: Docs And Backend Verification

**Files:**

- Modify: `docs/api.md`
- Test: full inventory-service backend checks.

**Interfaces:**

- Docs list Product/SKU/Inventory endpoints, duplicate-code policy, soft-delete policy and history policy.
- Response shape:

```json
{
  "message": "Tồn kho không đủ để thực hiện thao tác này."
}
```

- [ ] **Step 1: Update docs/api.md**

Add Product/SKU/Inventory section with:

```text
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
```

Document:

- Product delete = deactivate product, deactivate + soft-delete all SKUs, keep balances and transactions.
- SKU delete = deactivate + soft delete.
- SKU create creates zero balance.
- Duplicate `product_code` or `sku_code` returns `409`.
- Inventory list only shows Product/SKU rows that are not soft-deleted.
- Transaction history route uses `withTrashed()` so ledger for soft-deleted SKU remains readable.
- Transaction history newest first.
- Protected mutations require `X-CSRF-TOKEN`.

- [ ] **Step 2: Run tests and docs scan**

Run:

```powershell
docker compose exec -T inventory-service php artisan test
docker compose exec -T inventory-service php artisan route:list --path=api/v1
```

Expected: tests PASS; route list shows Product/SKU/Inventory endpoints.

- [ ] **Step 3: Commit**

```powershell
git add docs/api.md
git commit -m "docs: document product inventory API"
```

---

## Task 8: Product React Page

**Files:**

- Create: `frontend/src/features/inventory/api/inventoryApi.ts`
- Create: `frontend/src/features/inventory/pages/ProductsPage.tsx`
- Modify: `frontend/src/App.tsx`
- Modify: `frontend/src/dashboardExperience.test.mjs`

**Interfaces:**

- `listProducts(search?: string): Promise<Product[]>`.
- `createProduct(payload: ProductPayload): Promise<Product>`.
- `updateProduct(id: number, payload: Partial<ProductPayload>): Promise<Product>`.
- `deactivateProduct(id: number): Promise<Product>`.
- `createSku(productId: number, payload: SkuPayload): Promise<ProductSku>`.
- `updateSku(id: number, payload: Partial<SkuPayload>): Promise<ProductSku>`.
- `deactivateSku(id: number): Promise<ProductSku>`.
- Route: `/products`.

- [ ] **Step 1: Create API types and functions**

Create `inventoryApi.ts` using existing `apiClient`. Use only gateway URLs:

```text
/api/inventory/v1/products
/api/inventory/v1/products/{id}
/api/inventory/v1/products/{id}/skus
```

Core types:

```ts
export type ProductSku = {
  id: number
  sku_code: string
  size: string
  active: boolean
  quantity: number
}

export type Product = {
  id: number
  product_code: string
  name: string
  active: boolean
  skus: ProductSku[]
  created_at: string | null
  updated_at: string | null
}
```

- [ ] **Step 2: Create `ProductsPage`**

Behavior:

```text
Load product list on mount.
Search by product_code, sku_code or name.
Create product with product_code and name.
Update product name inline/dialog.
Deactivate product after confirm().
Add SKU inline with sku_code and size.
Update SKU size inline/dialog.
Deactivate SKU after confirm().
Show loading, empty and API error states.
```

Required Vietnamese labels:

```text
Sản phẩm
Tìm theo mã sản phẩm, mã SKU hoặc tên
Tạo sản phẩm
Chỉnh sửa
Sửa sản phẩm
Sửa SKU
Thêm SKU
Ngừng hoạt động
Chưa có sản phẩm.
Không tải được danh sách sản phẩm.
Lưu
Hủy
```

Design requirements:

```text
Vibe: Cozy, Lazy.
Density: vừa đủ để thao tác nhanh, không nhồi bảng.
Palette: nền ấm nhẹ hoặc trung tính mềm, một accent nhất quán, tránh beige-heavy.
States: loading skeleton, empty state có hướng dẫn, error rõ ràng, button có trạng thái active/disabled.
Accessibility: text đủ tương phản, focus state rõ, form label không chỉ dùng placeholder.
```

- [ ] **Step 3: Add route and navigation link**

In `App.tsx`:

```tsx
<Route path="/products" element={<ProductsPage />} />
```

Do not wrap in `RequireManager`; inventory is available to `STORE_MANAGER` and `STAFF`.

In `DashboardPage.tsx` navigation bar, add links to `/products` ("Sản phẩm") and `/inventory` ("Tồn kho").

- [ ] **Step 4: Add route policy check**

Extend frontend route policy test:

```text
/products exists
/products is not inside RequireManager
```

- [ ] **Step 5: Run frontend checks**

Run:

```powershell
npm --prefix frontend test
npm --prefix frontend run build
```

Expected: PASS.

- [ ] **Step 6: Commit**

```powershell
git add frontend/src/App.tsx frontend/src/features/inventory frontend/src/features/health/pages/DashboardPage.tsx frontend/src/dashboardExperience.test.mjs
git commit -m "feat(frontend): add product inventory page"
```

---

## Task 9: Inventory React Page

**Files:**

- Modify: `frontend/src/features/inventory/api/inventoryApi.ts`
- Create: `frontend/src/features/inventory/pages/InventoryPage.tsx`
- Modify: `frontend/src/App.tsx`
- Modify: `frontend/src/dashboardExperience.test.mjs`

**Interfaces:**

- `listInventory(search?: string): Promise<InventoryBalance[]>`.
- `listInventoryTransactions(skuId: number): Promise<InventoryTransaction[]>`.
- Route: `/inventory`.

- [ ] **Step 1: Extend API types**

Add:

```ts
export type InventoryBalance = {
  sku_id: number
  sku_code: string
  size: string
  sku_active: boolean
  product_id: number
  product_code: string
  product_name: string
  product_active: boolean
  quantity: number
  updated_at: string | null
}
```

Add transaction type union:

```ts
export type InventoryTransactionType =
  | 'IMPORT_SYNC'
  | 'SALE'
  | 'SALE_REVERSAL'
  | 'BORROW_OUT'
  | 'BORROW_RETURN'
```

API functions call:

```text
/api/inventory/v1/inventory
/api/inventory/v1/inventory/{skuId}/transactions
```

- [ ] **Step 2: Create `InventoryPage`**

Behavior:

```text
Load inventory list on mount.
Search by product_code, sku_code or name.
Show table columns product_code, product_name, sku_code, size, quantity, status.
History button loads transaction history for selected SKU.
Show newest transactions first.
Show loading, empty and API error states.
```

Required Vietnamese labels:

```text
Tồn kho
Tìm theo mã sản phẩm, mã SKU hoặc tên
Số lượng
Lịch sử
Chưa có tồn kho.
Không tải được tồn kho.
Chưa có giao dịch.
```

Design requirements:

```text
Vibe: Cozy, Lazy.
Density: bảng tồn kho dễ scan, hàng đủ cao để đọc nhưng không lãng phí không gian.
Palette: đồng bộ với `/products`, một accent nhất quán.
States: loading skeleton theo dạng table row, empty state bình tĩnh, error dễ hiểu.
Accessibility: button Lịch sử có accessible name, focus state rõ, số lượng căn phải để dễ so sánh.
```

- [ ] **Step 3: Add route**

In `App.tsx`:

```tsx
<Route path="/inventory" element={<InventoryPage />} />
```

- [ ] **Step 4: Add route policy check**

Extend frontend route policy test:

```text
/inventory exists
/inventory is not inside RequireManager
```

- [ ] **Step 5: Run frontend checks**

Run:

```powershell
npm --prefix frontend test
npm --prefix frontend run build
```

Expected: PASS.

- [ ] **Step 6: Commit**

```powershell
git add frontend/src/App.tsx frontend/src/features/inventory frontend/src/dashboardExperience.test.mjs
git commit -m "feat(frontend): add inventory list page"
```

---

## Final Verification

Run from repo root:

```powershell
docker compose exec -T inventory-service php artisan test
docker compose exec -T inventory-service php artisan migrate:fresh --force
docker compose exec -T inventory-service php artisan test
docker compose config
npm --prefix frontend test
npm --prefix frontend run build
```

Expected:

- Inventory Service tests pass.
- Migration fresh succeeds.
- Compose config is valid.
- Frontend tests pass.
- Frontend builds with `/products` and `/inventory`.

Gateway smoke verification after starting stack:

```powershell
docker compose up -d --build
Invoke-RestMethod http://localhost:8080/api/inventory/ready
Invoke-RestMethod http://localhost:8080/api/inventory/v1/products
```

Expected readiness and gateway routing:

- `/api/inventory/ready` returns `{"status": "ready", "service": "inventory-service", "database": "connected"}`.
- `/api/inventory/v1/products` proxies correctly to `inventory-service` (returns 401 unauthenticated when missing cookies, confirming gateway proxy route is functioning).

---

## Acceptance Checklist

- [ ] Product CRUD runs through authenticated API.
- [ ] Product code unique enforced by validation and database.
- [ ] SKU CRUD runs through authenticated API.
- [ ] SKU code unique enforced by validation and database.
- [ ] Creating SKU creates `inventory_balances.quantity = 0`.
- [ ] Product delete deactivates and soft deletes; it does not delete ledger.
- [ ] SKU delete deactivates and soft deletes; it does not delete ledger.
- [ ] Inventory list returns flattened product/SKU/balance rows.
- [ ] Inventory search covers `product_code`, `sku_code` and product `name`.
- [ ] Transaction history returns newest first.
- [ ] React `/products` page lists, searches, creates products, adds SKUs and deactivates products.
- [ ] React `/inventory` page lists balances and opens SKU transaction history.
- [ ] Inventory routes are not manager-only.
- [ ] `InventoryBalanceService` increases, decreases and synchronizes balance atomically.
- [ ] Decrease below zero throws `InsufficientStockException` and rolls back.
- [ ] Controllers contain no inventory business logic.
- [ ] No frontend JWT storage or `Authorization: Bearer` changes.
- [ ] `docs/api.md` documents new endpoints.

---

## Self-Review

- Spec coverage: Product/SKU CRUD, inventory balance list, transaction history, ledger service, gateway API docs, `/products` UI and `/inventory` UI all have tasks.
- Placeholder scan: không còn marker rỗng, bước hoãn mơ hồ hoặc chỉ dẫn kiểu sao chép tương tự.
- Type consistency: `Product`, `ProductSku`, `InventoryBalance`, `InventoryTransactionType`, `InventoryBalanceService`, use case names and route names are defined before use.
- Scope: no stock import, daily sales, borrow, stock count, category, price, image, barcode, supplier, store, queue, Redis, RabbitMQ or extra service.
- Docs alignment: `scope.md`, `rules.md`, `architecture.md`, `decisions.md`, `api.md`, `setup-plan.md`, source MVP doc and previous task plan were read before finalizing this version.
- UI/UX alignment: frontend tasks require taste/design skill and preserve "Cozy", "Lazy", friendly operator-focused UX.

---

## Execution Choice

Plan complete and saved to `docs/TaskImplementDetailPlan/ProductInventoryUC-10-12plan.md`.

Recommended execution:

1. Use `superpowers:subagent-driven-development` if multi-agent tools are enabled.
2. Otherwise use `superpowers:executing-plans` inline.

This plan intentionally skips stock import, daily sales, borrow and stock count. Add them in their own task plans after this foundation is green.
