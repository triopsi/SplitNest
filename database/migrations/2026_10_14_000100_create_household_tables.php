<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_admin')->default(false));
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('amount_cents');
            $table->char('start_month', 7)->index();
            $table->char('end_month', 7)->nullable();
            $table->unsignedTinyInteger('interval_months')->default(1);
            $table->timestamps();
        });
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payer_id')->constrained('participants')->restrictOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('amount_cents');
            $table->string('split_mode')->default('percentage');
            $table->json('shares');
            $table->char('start_month', 7)->index();
            $table->char('end_month', 7)->nullable();
            $table->unsignedTinyInteger('interval_months')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('monthly_closings', function (Blueprint $table) {
            $table->id();
            $table->char('month', 7)->unique();
            $table->json('snapshot')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_closings');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('incomes');
        Schema::dropIfExists('participants');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
