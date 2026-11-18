<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Associate scheduled savings contributions with their destination pool. */
return new class extends Migration
{
    /** Add the optional savings destination without changing existing bills. */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreignId('savings_budget_id')->nullable()->constrained('budgets')->restrictOnDelete();
        });
    }

    /** Remove the savings destination while retaining the expense records. */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('savings_budget_id');
        });
    }
};
