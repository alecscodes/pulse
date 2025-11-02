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
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['website', 'ip'])->default('website');
            $table->string('url');
            $table->enum('method', ['GET', 'POST'])->default('GET');
            $table->json('headers')->nullable();
            $table->json('parameters')->nullable();
            $table->boolean('enable_content_validation')->default(false);
            $table->string('expected_title')->nullable();
            $table->text('expected_content')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('check_interval')->default(60); // seconds
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
