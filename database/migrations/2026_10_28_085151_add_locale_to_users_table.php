<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Remember each account's chosen interface language across sessions. */
return new class extends Migration
{
    /**
     * Add an optional locale so existing accounts retain their current fallback behavior.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 16)->nullable();
        });
    }

    /**
     * Remove the saved language preference.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
