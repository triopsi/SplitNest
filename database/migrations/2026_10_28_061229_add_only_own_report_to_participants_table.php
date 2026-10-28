<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Allow participants to receive a statement limited to their own obligations. */
return new class extends Migration
{
    /** Preserve full statements for existing participants unless explicitly restricted. */
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->boolean('only_own_report')->default(false);
        });
    }

    /** Remove the per-participant statement preference. */
    public function down(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->dropColumn('only_own_report');
        });
    }
};
