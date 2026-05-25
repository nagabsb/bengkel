<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->char('workshop_id', 26)->nullable()->comment('NULL = system menu, not NULL = tenant custom menu');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->enum('menu_type', ['system', 'tenant'])->default('tenant');
            $table->string('label', 100);
            $table->string('route', 200)->nullable();
            $table->string('icon', 100)->nullable()->comment('e.g. heroicon name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('workshop_id', 'menus_workshop_index');
            $table->index('parent_id', 'menus_parent_index');
            $table->index('menu_type', 'menus_type_index');

            $table->foreign('parent_id')
                ->references('id')
                ->on('menus')
                ->nullOnDelete();

            $table->foreign('workshop_id')
                ->references('id')
                ->on('workshops')
                ->cascadeOnDelete();
        });

        Schema::create('plan_menu', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('menu_id');

            $table->primary(['plan_id', 'menu_id'], 'plan_menu_primary');

            $table->foreign('plan_id')
                ->references('id')
                ->on('plans')
                ->cascadeOnDelete();

            $table->foreign('menu_id')
                ->references('id')
                ->on('menus')
                ->cascadeOnDelete();
        });

        Schema::create('workshop_menu_overrides', function (Blueprint $table) {
            $table->id();
            $table->char('workshop_id', 26);
            $table->unsignedBigInteger('menu_id');
            $table->string('custom_label', 100)->nullable();
            $table->string('custom_route', 200)->nullable();
            $table->string('custom_icon', 100)->nullable();
            $table->integer('sort_order')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['workshop_id', 'menu_id'], 'workshop_menu_overrides_unique');
            $table->index('workshop_id', 'workshop_menu_overrides_workshop_index');
            $table->index('menu_id', 'workshop_menu_overrides_menu_index');

            $table->foreign('workshop_id')
                ->references('id')
                ->on('workshops')
                ->cascadeOnDelete();

            $table->foreign('menu_id')
                ->references('id')
                ->on('menus')
                ->cascadeOnDelete();
        });

        Schema::create('menu_role', function (Blueprint $table) {
            $table->unsignedBigInteger('menu_id');
            $table->unsignedBigInteger('role_id');

            $table->primary(['menu_id', 'role_id'], 'menu_role_primary');

            $table->foreign('menu_id')
                ->references('id')
                ->on('menus')
                ->cascadeOnDelete();

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_role');
        Schema::dropIfExists('workshop_menu_overrides');
        Schema::dropIfExists('plan_menu');
        Schema::dropIfExists('menus');
    }
};
