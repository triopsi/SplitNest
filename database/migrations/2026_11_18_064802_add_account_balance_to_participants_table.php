<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Store an optional observed month-end balance as the starting point for cash forecasts. */
return new class extends Migration
{
    /** Add signed account balances and their month-end reference. */
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->char('account_balance_month', 7)->nullable();
            $table->bigInteger('account_balance_cents')->nullable();
        });
    }

    /** Remove only the optional cash forecast starting points. */
    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->dropColumn(['account_balance_month', 'account_balance_cents']);
        });
    }
};
