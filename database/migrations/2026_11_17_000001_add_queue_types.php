<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing tickets and audit events belong to the original seating queue.
        // Defaults backfill those rows without changing numbers, access hashes or history.
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->string('queue_type', 20)->default('dine_in');
            $table->index(['queue_type', 'status', 'number']);
        });
        Schema::table('queue_events', function (Blueprint $table) {
            $table->string('queue_type', 20)->default('dine_in');
        });
    }

    public function down(): void
    {
        // Check both tables before any DDL: an empty call still has a queue identity.
        if (DB::table('queue_tickets')->where('queue_type', '!=', 'dine_in')->exists()
            || DB::table('queue_events')->where('queue_type', '!=', 'dine_in')->exists()) {
            throw new RuntimeException('Cannot roll back queue types while non-dine-in tickets or events exist; their classification must be preserved.');
        }

        Schema::table('queue_events', function (Blueprint $table) {
            $table->dropColumn('queue_type');
        });
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->dropIndex(['queue_type', 'status', 'number']);
            $table->dropColumn('queue_type');
        });
    }
};
