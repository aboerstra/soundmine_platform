<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activation_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable();
            $table->timestampsTz();
            $table->index(['activation_id', 'revoked_at']);
        });
        Schema::create('activation_authorizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('license_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->char('machine_hash', 64);
            $table->enum('platform', ['macos', 'windows']);
            $table->char('token_hash', 64)->unique();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('consumed_at')->nullable();
            $table->uuid('request_id')->nullable();
            $table->char('request_fingerprint', 64)->nullable();
            $table->foreignUuid('issued_token_id')->nullable()->constrained('installation_tokens')->restrictOnDelete();
            $table->text('issued_token')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activation_authorizations');
        Schema::dropIfExists('installation_tokens');
    }
};
