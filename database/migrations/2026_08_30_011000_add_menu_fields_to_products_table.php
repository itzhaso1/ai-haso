<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('show_in_menu')->default(true)->after('inventory_tracking');
            $table->boolean('allow_online_ordering')->default(true)->after('show_in_menu');
            $table->unsignedInteger('menu_sort_order')->default(0)->after('allow_online_ordering');

            $table->index(['workspace_id', 'show_in_menu', 'allow_online_ordering'], 'products_menu_visibility_idx');
            $table->index(['workspace_id', 'menu_sort_order'], 'products_menu_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_menu_visibility_idx');
            $table->dropIndex('products_menu_sort_idx');
            $table->dropColumn(['show_in_menu', 'allow_online_ordering', 'menu_sort_order']);
        });
    }
};
