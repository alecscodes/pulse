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
        Schema::table('monitor_checks', function (Blueprint $table) {
            $table->boolean('ssl_valid')->nullable()->after('content_valid');
            $table->string('ssl_issuer')->nullable()->after('ssl_valid');
            $table->timestamp('ssl_valid_from')->nullable()->after('ssl_issuer');
            $table->timestamp('ssl_valid_to')->nullable()->after('ssl_valid_from');
            $table->integer('ssl_days_until_expiration')->nullable()->after('ssl_valid_to');
            $table->text('ssl_error_message')->nullable()->after('ssl_days_until_expiration');

            $table->index(['ssl_valid', 'ssl_days_until_expiration']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitor_checks', function (Blueprint $table) {
            $table->dropIndex(['ssl_valid', 'ssl_days_until_expiration']);
            $table->dropColumn([
                'ssl_valid',
                'ssl_issuer',
                'ssl_valid_from',
                'ssl_valid_to',
                'ssl_days_until_expiration',
                'ssl_error_message',
            ]);
        });
    }
};
