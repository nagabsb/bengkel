<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vehicle_master_brands')) {
            Schema::create('vehicle_master_brands', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 140)->unique();
                $table->string('vehicle_type', 20)->default('universal');
                $table->string('external_id', 120)->nullable();
                $table->string('source', 120)->default('json-sync');
                $table->boolean('is_active')->default(true);
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->index(['vehicle_type', 'is_active'], 'vehicle_master_brands_type_active_idx');
                $table->index(['is_active', 'name'], 'vehicle_master_brands_active_name_idx');
            });
        }

        if (! Schema::hasTable('vehicle_master_models')) {
            Schema::create('vehicle_master_models', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('vehicle_master_brand_id')
                    ->constrained('vehicle_master_brands')
                    ->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('slug', 140);
                $table->string('vehicle_type', 20)->default('motor');
                $table->string('external_id', 120)->nullable();
                $table->string('source', 120)->default('json-sync');
                $table->boolean('is_active')->default(true);
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['vehicle_master_brand_id', 'slug', 'vehicle_type'],
                    'vehicle_master_models_brand_slug_type_uq',
                );
                $table->index(['vehicle_type', 'is_active'], 'vehicle_master_models_type_active_idx');
                $table->index(
                    ['vehicle_master_brand_id', 'is_active', 'name'],
                    'vehicle_master_models_brand_active_name_idx',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_master_models');
        Schema::dropIfExists('vehicle_master_brands');
    }
};

