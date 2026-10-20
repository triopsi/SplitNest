<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelPasskeys\Support\Config;

return new class extends Migration
{
    /**
     * Create passkey credentials linked to the configured Eloquent authentication model.
     */
    public function up(): void
    {
        $authenticatableClass = Config::getAuthenticatableModel();
        $authenticatableModel = new $authenticatableClass;
        if (! $authenticatableModel instanceof Model) {
            throw new LogicException('The configured passkey authenticatable must be an Eloquent model.');
        }

        $authenticatableTableName = $authenticatableModel->getTable();

        Schema::create('passkeys', function (Blueprint $table) use ($authenticatableTableName, $authenticatableModel) {
            $table->id();

            $table
                ->foreignIdFor($authenticatableModel, 'authenticatable_id')
                ->constrained(table: $authenticatableTableName, indexName: 'passkeys_authenticatable_fk')
                ->cascadeOnDelete();

            $table->text('name');
            $table->text('credential_id');
            $table->json('data');

            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }
};
