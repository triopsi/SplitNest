<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store monthly widget preferences independently for each login account.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('monthly_dashboard_layout')->nullable();
        });
    }

    /**
     * Remove personal widget preferences when rolling back the migration.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('monthly_dashboard_layout');
        });
    }
};
