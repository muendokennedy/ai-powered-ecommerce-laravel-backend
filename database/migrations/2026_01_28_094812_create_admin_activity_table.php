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
        Schema::create('admin_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained()->onUpdate('cascade')->onDelete('cascade');
            $table->json('action')->nullable();
            $table->decimal('total_actions')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_activity');
    }
};
