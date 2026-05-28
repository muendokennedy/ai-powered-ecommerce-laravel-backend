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
        Schema::table('admins', function (Blueprint $table) {
            //
                $table->json('preferences')->nullable()->after('status');
                $table->json('notifications')->nullable()->after('preferences');
                $table->timestamp('active_since')->nullable()->after('notifications');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            //
            $table->dropColumn(['preferences', 'notifications', 'active_since']); 
        });
    }
};
