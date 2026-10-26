<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 4: migrate the existing four transaction types into the new
 * configuration structure as protected system types (never deletable,
 * deactivation only hides them from NEW submissions — existing
 * transactions keep resolving their labels from these rows).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('trx_types')->insert([
            ['slug' => 'conversion', 'name_ar' => 'تحويل مبلغ', 'name_en' => 'Money transfer', 'is_active' => 1, 'is_system' => 1, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'purchase', 'name_ar' => 'شراء أجهزة', 'name_en' => 'Equipment purchase', 'is_active' => 1, 'is_system' => 1, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'bills', 'name_ar' => 'دفع فواتير', 'name_en' => 'Bill payment', 'is_active' => 1, 'is_system' => 1, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'contract', 'name_ar' => 'تعاقد', 'name_en' => 'Contract', 'is_active' => 1, 'is_system' => 1, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        // Configuration seed — removing it on rollback would hide types the
        // admin may have edited; keep forward-only.
    }
};
