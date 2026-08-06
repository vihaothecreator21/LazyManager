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
