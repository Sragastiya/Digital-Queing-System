<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedInteger('arrival_grace_minutes')->default(15);
            $table->unsignedInteger('estimate_interval_minutes')->nullable();
            $table->unsignedInteger('near_turn_threshold')->default(2);
            $table->timestamps();
        });
        DB::table('queue_settings')->insert(['id' => 1, 'next_number' => 1, 'arrival_grace_minutes' => 15, 'near_turn_threshold' => 2, 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('queue_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('number')->unique();
            $table->char('access_token_hash', 64)->unique();
            $table->string('customer_name', 120);
            $table->unsignedSmallInteger('party_size');
            $table->string('phone', 40)->nullable();
            $table->string('status', 20)->default('waiting');
            $table->timestamp('joined_at');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('arrival_deadline')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 40)->nullable();
            $table->timestamps();
            $table->index(['status', 'number']);
        });
        Schema::create('queue_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_ticket_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor', 20);
            $table->string('action', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->string('reason', 40)->nullable();
            $table->uuid('operation_id')->unique();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_events');
        Schema::dropIfExists('queue_tickets');
        Schema::dropIfExists('queue_settings');
    }
};
