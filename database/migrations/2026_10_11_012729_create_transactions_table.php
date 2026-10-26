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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number');
            $table->string('subject');
            $table->enum('type', ['conversion', 'purchase', 'bills', 'contract']);
            $table->date('date');
            $table->enum('status', ['done', 'pending', 'rejected', 'draft']);
            $table->foreignId('dept_id')->nullable()->constrained('departments')->nullOnDelete();//تحافظ على السجل الحالي عند حذف السجل المرتبط به، وتفصل العلاقة بينهما
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
