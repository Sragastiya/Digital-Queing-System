<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('queue_order')->default(0);
            $table->index(['queue_type', 'status', 'queue_order', 'number'], 'queue_waiting_order_index');
        });
        // Preserve existing FIFO and every original domain field, including timestamps.
        DB::table('queue_tickets')->update(['queue_order' => DB::raw('number')]);
        Schema::table('queue_events', function (Blueprint $table) {
            $table->foreignId('related_ticket_id')->nullable()->constrained('queue_tickets')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('queue_events')->whereIn('action', ['give_turn', 'switch_service'])->orWhereNotNull('related_ticket_id')->exists()
            || DB::table('queue_tickets')->whereColumn('queue_order', '!=', 'number')->exists()) {
            throw new RuntimeException('Customer queue options have been used. Rollback would lose queue order or linked ticket history.');
        }
        Schema::table('queue_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('related_ticket_id');
        });
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->dropIndex('queue_waiting_order_index');
            $table->dropColumn('queue_order');
        });
    }
};
