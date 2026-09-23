<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropIndex(['domain_expires_at', 'domain_days_until_expiration']);
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn('domain_days_until_expiration');
            $table->index('domain_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropIndex(['domain_expires_at']);
            $table->integer('domain_days_until_expiration')->nullable()->after('domain_expires_at');
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->index(['domain_expires_at', 'domain_days_until_expiration']);
        });
    }
};
