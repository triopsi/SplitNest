<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Preserve participant history and scheduled expense amounts without duplicating entries. */
return new class extends Migration
{
    /** Add optional archive boundaries and an expense price timeline. */
    public function up(): void
    {
        Schema::table('participants', function (Blueprint $table): void {
            $table->char('archived_from_month', 7)->nullable()->index();
        });
        Schema::table('expenses', function (Blueprint $table): void {
            $table->json('price_changes')->nullable();
        });
    }

    /** Remove only the newly introduced timeline fields. */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropColumn('price_changes');
        });
        Schema::table('participants', function (Blueprint $table): void {
            $table->dropIndex(['archived_from_month']);
            $table->dropColumn('archived_from_month');
        });
    }
};
