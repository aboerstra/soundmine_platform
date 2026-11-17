<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('display_name');
            $table->timestampsTz();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestampsTz();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['draft', 'pending', 'completed', 'cancelled', 'refunded'])->default('draft');
            $table->char('currency', 3);
            $table->bigInteger('total_minor')->default(0);
            $table->timestampsTz();
            $table->index(['customer_id', 'status']);
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->bigInteger('unit_price_minor');
            $table->timestampsTz();
        });
        Schema::create('licenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('status', ['pending', 'active', 'suspended', 'revoked'])->default('pending');
            $table->integer('max_activations')->default(2);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
            $table->index(['customer_id', 'status']);
        });
        Schema::create('entitlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('license_id')->constrained()->restrictOnDelete();
            $table->string('key');
            $table->jsonb('value');
            $table->timestampsTz();
            $table->unique(['license_id', 'key']);
        });
        Schema::create('activations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('license_id')->constrained()->restrictOnDelete();
            $table->char('machine_hash', 64);
            $table->enum('platform', ['macos', 'windows']);
            $table->string('plugin_version')->nullable();
            $table->enum('status', ['active', 'deactivated', 'revoked'])->default('active');
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('deactivated_at')->nullable();
            $table->timestampsTz();
            $table->unique(['license_id', 'machine_hash']);
            $table->index(['license_id', 'status']);
        });
        Schema::create('offline_authorization_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('license_id')->constrained()->restrictOnDelete();
            $table->char('machine_hash', 64);
            $table->char('request_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampsTz();
        });
        Schema::create('license_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activation_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('offline_authorization_request_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('key_id');
            $table->integer('schema_version')->default(1);
            $table->jsonb('payload');
            $table->text('signature');
            $table->timestampTz('issued_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
        });
        // PostgreSQL is the supported database; these constraints protect every write path.
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_nonnegative CHECK (total_minor >= 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_amounts_valid CHECK (quantity > 0 AND unit_price_minor >= 0)');
        DB::statement('ALTER TABLE licenses ADD CONSTRAINT licenses_limits_valid CHECK (max_activations > 0 AND (expires_at IS NULL OR starts_at IS NULL OR expires_at > starts_at))');
        DB::statement('ALTER TABLE license_certificates ADD CONSTRAINT certificates_dates_valid CHECK (expires_at > issued_at AND schema_version > 0)');
    }

    public function down(): void
    {
        foreach (['license_certificates', 'offline_authorization_requests', 'activations', 'entitlements', 'licenses', 'order_items', 'orders', 'products', 'customers'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
