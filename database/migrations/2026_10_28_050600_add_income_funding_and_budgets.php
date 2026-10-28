<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Add earmarked budgets and separate income weighting from cash receipts. */
return new class extends Migration
{
    /** Create budgets and attach optional funding destinations to entries. */
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreignId('budget_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('incomes', function (Blueprint $table): void {
            $table->boolean('affects_allocation')->default(true);
            $table->foreignId('budget_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    /** Remove funding links before removing the budget table. */
    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('expense_id');
            $table->dropConstrainedForeignId('budget_id');
            $table->dropColumn('affects_allocation');
        });
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('budget_id');
        });
        Schema::dropIfExists('budgets');
    }
};
