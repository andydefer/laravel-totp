<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('totp_secrets', function (Blueprint $table): void {
            $table->id();
            $table->string('authenticatable_type');
            $table->unsignedBigInteger('authenticatable_id');
            $table->string('secret')->unique();
            $table->boolean('is_enabled')->default(false);
            $table->json('recovery_codes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['authenticatable_type', 'authenticatable_id']);
            $table->index(['secret']);
            $table->index(['is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('totp_secrets');
    }
};