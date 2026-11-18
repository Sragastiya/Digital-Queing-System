<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_settings', function (Blueprint $table) {
            $table->boolean('auto_call_dine_in')->default(false);
            $table->boolean('auto_call_takeaway')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('queue_settings', function (Blueprint $table) {
            $table->dropColumn(['auto_call_dine_in', 'auto_call_takeaway']);
        });
    }
};
