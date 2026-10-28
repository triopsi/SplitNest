<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Add optional household or external payers without creating duplicate household income. */
return new class extends Migration
{
    /** Store a receipt's source and whether its payment belongs in the monthly plan. */
    public function up(): void
    {
        Schema::table('incomes', function (Blueprint $table): void {
            $table->foreignId('sender_id')->nullable()->constrained('participants')->restrictOnDelete();
            $table->string('sender_name')->nullable();
            $table->boolean('include_in_payment_plan')->default(false);
        });
    }

    /** Remove the source reference and payment-plan preference. */
    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sender_id');
            $table->dropColumn(['sender_name', 'include_in_payment_plan']);
        });
    }
};
