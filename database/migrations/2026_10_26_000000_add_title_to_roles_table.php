<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إضافة عمود title (تسمية الدور العربية للعرض) إلى جدول roles.
 *
 * النظام يخزّن slug إنجليزي في roles.name (يُفحص حرفيًا في hasRole)؛
 * والتسمية العربية للأساسية تُشتق عبر Roles::label. الأدوار المخصّصة
 * الجديدة تحتاج تسمية يُدخلها مدير النظام من /roles/form — وهذا العمود
 * هو موضعها. إضافة آمنة (nullable) لا تمسّ أي صف موجود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('title', 100)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
