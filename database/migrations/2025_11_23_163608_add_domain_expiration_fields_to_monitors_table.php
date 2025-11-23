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
            $table->date('domain_expires_at')->nullable()->after('check_interval');
            $table->integer('domain_days_until_expiration')->nullable()->after('domain_expires_at');
            $table->text('domain_error_message')->nullable()->after('domain_days_until_expiration');
            $table->timestamp('domain_last_checked_at')->nullable()->after('domain_error_message');
            $table->index(['domain_expires_at', 'domain_days_until_expiration']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropIndex(['domain_expires_at', 'domain_days_until_expiration']);
            $table->dropColumn([
                'domain_expires_at',
                'domain_days_until_expiration',
                'domain_error_message',
                'domain_last_checked_at',
            ]);
        });
    }
};
