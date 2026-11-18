<?php

namespace Tests\Feature;

use App\Enums\QueueType;
use App\Enums\TicketStatus;
use App\Models\QueueEvent;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\QueueService;
use App\Services\WaitEstimateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IndependentQueuesTest extends TestCase
{
    use RefreshDatabase;

    private function issue(QueueType $queueType): array
    {
        $token = bin2hex(random_bytes(32));
        $operation = (string) Str::uuid();
        $data = ['queue_type' => $queueType->value, 'customer_name' => 'Pelanggan uji', 'party_size' => 2];

        return [app(QueueService::class)->issue($data, $token, $operation), $token, $operation, $data];
    }

    private function assertRejected(callable $action, string $field = 'action'): void
    {
        try {
            $action();
            $this->fail('The invalid queue operation was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_types_bind_tickets_and_history_without_resetting_global_numbers(): void
    {
        [$dineIn] = $this->issue(QueueType::DineIn);
        [$takeaway] = $this->issue(QueueType::Takeaway);
        [$nextDineIn] = $this->issue(QueueType::DineIn);

        $this->assertSame(QueueType::DineIn, $dineIn->queue_type);
        $this->assertSame(QueueType::Takeaway, $takeaway->queue_type);
        $this->assertSame($dineIn->number + 1, $takeaway->number);
        $this->assertSame($takeaway->number + 1, $nextDineIn->number);
        $this->assertSame('A-001', $dineIn->displayNumber());
        $this->assertSame('A-002', $takeaway->displayNumber());
        $this->assertSame(4, QueueSetting::findOrFail(1)->next_number);
        $this->assertSame(1, QueueSetting::count());
        $this->assertSame(QueueType::DineIn, $dineIn->events()->firstOrFail()->queue_type);
        $this->assertSame(QueueType::Takeaway, $takeaway->events()->firstOrFail()->queue_type);
    }

    public function test_missing_or_invalid_types_are_rejected_before_issuing_any_ticket(): void
    {
        foreach ([[], ['queue_type' => null], ['queue_type' => 'other'], ['queue_type' => ['takeaway']]] as $type) {
            $data = array_merge(['customer_name' => 'Pelanggan uji', 'party_size' => 2], $type);
            $this->assertRejected(fn () => app(QueueService::class)->issue($data, bin2hex(random_bytes(32)), (string) Str::uuid()), 'queue_type');
        }

        $this->assertSame(0, QueueTicket::count());
        $this->assertSame(0, QueueEvent::count());
        $this->assertSame(1, QueueSetting::findOrFail(1)->next_number);
    }

    public function test_calling_each_type_uses_its_own_fifo_and_allows_multiple_pending_arrivals(): void
    {
        [$dineInFirst] = $this->issue(QueueType::DineIn);
        [$takeawayFirst] = $this->issue(QueueType::Takeaway);
        [$dineInSecond] = $this->issue(QueueType::DineIn);
        [$takeawaySecond] = $this->issue(QueueType::Takeaway);
        $staff = User::factory()->create();
        $queue = app(QueueService::class);

        $this->assertSame($takeawayFirst->id, $queue->callNext((string) Str::uuid(), $staff->id, QueueType::Takeaway)->id);
        $this->assertSame(TicketStatus::Waiting, $dineInFirst->fresh()->status);
        $this->assertSame($dineInFirst->id, $queue->callNext((string) Str::uuid(), $staff->id, QueueType::DineIn)->id);
        $this->assertSame($takeawaySecond->id, $queue->callNext((string) Str::uuid(), $staff->id, QueueType::Takeaway)->id);
        $this->assertSame(TicketStatus::Waiting, $dineInSecond->fresh()->status);
        $this->assertSame(3, QueueTicket::where('status', TicketStatus::Called)->count());
    }

    public function test_position_counts_eta_and_latest_calls_exclude_the_other_queue(): void
    {
        $this->freezeTime();
        QueueSetting::findOrFail(1)->update(['estimate_interval_minutes' => 4]);
        [$dineInFirst] = $this->issue(QueueType::DineIn);
        [$takeawayFirst] = $this->issue(QueueType::Takeaway);
        [$dineInSecond] = $this->issue(QueueType::DineIn);
        [$takeawaySecond] = $this->issue(QueueType::Takeaway);
        [$dineInThird] = $this->issue(QueueType::DineIn);
        $estimate = app(WaitEstimateService::class);
        $staff = User::factory()->create();
        $queue = app(QueueService::class);

        $dineIn = $estimate->snapshot(QueueType::DineIn, $dineInThird);
        $takeaway = $estimate->snapshot(QueueType::Takeaway, $takeawaySecond);
        $this->assertSame(3, $dineIn['waiting_count']);
        $this->assertSame(2, $dineIn['ahead']);
        $this->assertSame(8, $dineIn['estimate_minutes']);
        $this->assertSame(2, $takeaway['waiting_count']);
        $this->assertSame(1, $takeaway['ahead']);
        $this->assertSame(4, $takeaway['estimate_minutes']);
        $this->assertNull($dineIn['latest_called']);

        $queue->callNext((string) Str::uuid(), $staff->id, QueueType::Takeaway);
        $this->assertSame(8, $estimate->snapshot(QueueType::DineIn, $dineInThird)['estimate_minutes']);
        $this->assertNull($estimate->snapshot(QueueType::DineIn)['latest_called']);
        $this->assertSame(0, $estimate->snapshot(QueueType::Takeaway, $takeawaySecond)['estimate_minutes']);
        $this->assertSame($takeawayFirst->displayNumber(), $estimate->snapshot(QueueType::Takeaway)['latest_called']);

        $queue->callNext((string) Str::uuid(), $staff->id, QueueType::DineIn);
        $this->assertSame($dineInFirst->displayNumber(), $estimate->snapshot(QueueType::DineIn)['latest_called']);
        $this->assertSame($takeawayFirst->displayNumber(), $estimate->snapshot(QueueType::Takeaway)['latest_called']);
        $this->assertSame(4, $estimate->snapshot(QueueType::DineIn, $dineInThird)['estimate_minutes']);

        $queue->transition($dineInSecond, 'cancel', (string) Str::uuid(), null, 'plans_changed');
        $this->assertSame(0, $estimate->snapshot(QueueType::DineIn, $dineInThird)['ahead']);
        $this->assertSame(1, $estimate->snapshot(QueueType::Takeaway)['waiting_count']);
        $this->assertSame('takeaway', $takeaway['queue_type']);
        $this->assertSame('Bawa Pulang', $takeaway['queue_label']);
    }

    public function test_snapshot_rejects_a_ticket_from_another_queue_and_preserves_unknown_eta(): void
    {
        [$ticket] = $this->issue(QueueType::Takeaway);
        $estimate = app(WaitEstimateService::class);

        $this->assertNull($estimate->snapshot(QueueType::Takeaway, $ticket)['estimate_minutes']);
        $this->assertSame(0, $estimate->snapshot(QueueType::DineIn)['waiting_count']);
        $this->assertRejected(fn () => $estimate->snapshot(QueueType::DineIn, $ticket), 'queue_type');
    }

    public function test_registration_replay_requires_both_original_token_and_queue(): void
    {
        [$ticket, $token, $operation, $data] = $this->issue(QueueType::Takeaway);
        $queue = app(QueueService::class);

        $this->assertSame($ticket->id, $queue->issue($data, $token, $operation)->id);
        $changedType = array_replace($data, ['queue_type' => QueueType::DineIn->value]);
        $this->assertRejected(fn () => $queue->issue($changedType, $token, $operation));
        $this->assertRejected(fn () => $queue->issue($data, bin2hex(random_bytes(32)), $operation));
        $this->assertSame(1, QueueTicket::count());
        $this->assertSame(1, QueueEvent::count());
        $this->assertSame(2, QueueSetting::findOrFail(1)->next_number);
    }

    public function test_call_replay_is_bound_to_queue_even_when_the_original_queue_was_empty(): void
    {
        [$dineIn] = $this->issue(QueueType::DineIn);
        $staff = User::factory()->create();
        $queue = app(QueueService::class);
        $emptyOperation = (string) Str::uuid();

        $this->assertNull($queue->callNext($emptyOperation, $staff->id, QueueType::Takeaway));
        $event = QueueEvent::where('operation_id', $emptyOperation)->firstOrFail();
        $this->assertNull($event->queue_ticket_id);
        $this->assertSame(QueueType::Takeaway, $event->queue_type);
        [$takeaway] = $this->issue(QueueType::Takeaway);
        $this->assertNull($queue->callNext($emptyOperation, $staff->id, QueueType::Takeaway));
        $this->assertRejected(fn () => $queue->callNext($emptyOperation, $staff->id, QueueType::DineIn));
        $this->assertSame(TicketStatus::Waiting, $takeaway->fresh()->status);
        $this->assertSame(TicketStatus::Waiting, $dineIn->fresh()->status);

        $callOperation = (string) Str::uuid();
        $this->assertSame($dineIn->id, $queue->callNext($callOperation, $staff->id, QueueType::DineIn)->id);
        $this->assertSame($dineIn->id, $queue->callNext($callOperation, $staff->id, QueueType::DineIn)->id);
        $this->assertRejected(fn () => $queue->callNext($callOperation, $staff->id, QueueType::Takeaway));
        $this->assertSame(1, QueueEvent::where('operation_id', $callOperation)->count());
        $this->assertSame(TicketStatus::Waiting, $takeaway->fresh()->status);
    }

    public function test_takeaway_uses_existing_arrival_then_completion_stages_and_persisted_type(): void
    {
        [$dineIn] = $this->issue(QueueType::DineIn);
        [$takeaway] = $this->issue(QueueType::Takeaway);
        $staff = User::factory()->create();
        $queue = app(QueueService::class);
        $called = $queue->callNext((string) Str::uuid(), $staff->id, QueueType::Takeaway);
        $this->assertRejected(fn () => $queue->transition($called, 'complete', (string) Str::uuid(), $staff->id));

        $called->queue_type = QueueType::DineIn;
        $arrived = $queue->transition($called, 'arrive', (string) Str::uuid(), $staff->id);
        $this->assertSame(QueueType::Takeaway, $arrived->queue_type);
        $this->assertSame(TicketStatus::Arrived, $arrived->status);
        $this->assertRejected(fn () => $queue->transition($arrived, 'cancel', (string) Str::uuid(), null, 'plans_changed'));
        $this->assertRejected(fn () => $queue->transition($arrived, 'cancel', (string) Str::uuid(), $staff->id, 'skipped'));

        $completed = $queue->transition($arrived, 'complete', (string) Str::uuid(), $staff->id);
        $this->assertSame(TicketStatus::Completed, $completed->status);
        $this->assertSame(4, $takeaway->events()->count());
        $this->assertSame(0, $takeaway->events()->where('queue_type', '!=', QueueType::Takeaway->value)->count());
        $this->assertSame(TicketStatus::Waiting, $dineIn->fresh()->status);
    }

    public function test_takeaway_no_show_keeps_its_deadline_manual_and_accepts_overdue_arrival(): void
    {
        $this->freezeTime();
        [$ticket] = $this->issue(QueueType::Takeaway);
        $staff = User::factory()->create();
        $queue = app(QueueService::class);
        $called = $queue->callNext((string) Str::uuid(), $staff->id, QueueType::Takeaway);
        $this->assertSame(15, (int) $called->called_at->diffInMinutes($called->arrival_deadline));
        $this->assertRejected(fn () => $queue->transition($called, 'cancel', (string) Str::uuid(), $staff->id, 'no_show'));
        $this->travel(15)->minutes();
        $this->assertSame(TicketStatus::Called, $ticket->fresh()->status);
        $arrived = $queue->transition($called, 'arrive', (string) Str::uuid(), $staff->id);
        $this->assertSame(TicketStatus::Arrived, $arrived->status);

        [$next] = $this->issue(QueueType::Takeaway);
        $calledNext = $queue->callNext((string) Str::uuid(), $staff->id, QueueType::Takeaway);
        $this->assertSame($next->id, $calledNext->id);
        $this->travel(15)->minutes();
        $cancelled = $queue->transition($calledNext, 'cancel', (string) Str::uuid(), $staff->id, 'no_show');
        $this->assertSame(TicketStatus::Cancelled, $cancelled->status);
        $this->assertSame('no_show', $cancelled->cancellation_reason);
    }

    public function test_transition_replay_cannot_be_reused_for_a_ticket_in_another_queue(): void
    {
        [$dineIn] = $this->issue(QueueType::DineIn);
        [$takeaway] = $this->issue(QueueType::Takeaway);
        $queue = app(QueueService::class);
        $operation = (string) Str::uuid();
        $queue->transition($dineIn, 'cancel', $operation, null, 'plans_changed');
        $this->assertRejected(fn () => $queue->transition($takeaway, 'cancel', $operation, null, 'plans_changed'));
        $this->assertSame(TicketStatus::Waiting, $takeaway->fresh()->status);
        $dineIn->queue_type = QueueType::Takeaway;
        $this->assertSame(QueueType::DineIn, $queue->transition($dineIn, 'cancel', $operation, null, 'plans_changed')->queue_type);
        $this->assertSame(1, QueueEvent::where('operation_id', $operation)->count());
    }

    private function withLegacyDatabase(callable $assertions): void
    {
        $originalConnection = DB::getDefaultConnection();
        config(['database.connections.queue_migration_testing' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('queue_migration_testing');
        app()->forgetInstance('db.schema');
        Schema::clearResolvedInstance('db.schema');

        try {
            Schema::create('users', fn (Blueprint $table) => $table->id());
            $originalMigration = require database_path('migrations/2026_11_01_000001_create_queue_tables.php');
            $originalMigration->up();
            $newMigration = require database_path('migrations/2026_11_17_000001_add_queue_types.php');
            $assertions($newMigration);
        } finally {
            DB::purge('queue_migration_testing');
            DB::setDefaultConnection($originalConnection);
            app()->forgetInstance('db.schema');
            Schema::clearResolvedInstance('db.schema');
        }
    }

    private function issueOnPreviousSchema(QueueType $type): QueueTicket
    {
        // Historical migration fixtures must not call application code requiring newer columns.
        $number = QueueSetting::findOrFail(1)->next_number;
        $id = DB::table('queue_tickets')->insertGetId(['number' => $number, 'queue_type' => $type->value,
            'access_token_hash' => hash('sha256', bin2hex(random_bytes(32))), 'customer_name' => 'Data lama sintetis',
            'party_size' => 2, 'status' => 'waiting', 'joined_at' => now()]);
        DB::table('queue_events')->insert(['queue_ticket_id' => $id, 'queue_type' => $type->value, 'actor' => 'customer',
            'action' => 'issue', 'to_status' => 'waiting', 'operation_id' => (string) Str::uuid(), 'created_at' => now()]);
        QueueSetting::findOrFail(1)->increment('next_number');

        return QueueTicket::findOrFail($id);
    }

    public function test_forward_migration_preserves_legacy_numbers_private_access_history_and_counter(): void
    {
        $this->withLegacyDatabase(function ($migration) {
            $hash = hash('sha256', str_repeat('a', 64));
            $joinedAt = now()->subMinutes(20)->format('Y-m-d H:i:s');
            $ticketId = DB::table('queue_tickets')->insertGetId(['number' => 42, 'access_token_hash' => $hash, 'customer_name' => 'Data lama sintetis', 'party_size' => 3, 'status' => 'waiting', 'joined_at' => $joinedAt]);
            $operation = (string) Str::uuid();
            $eventId = DB::table('queue_events')->insertGetId(['queue_ticket_id' => $ticketId, 'actor' => 'customer', 'action' => 'issue', 'to_status' => 'waiting', 'operation_id' => $operation, 'created_at' => $joinedAt]);
            $emptyCallId = DB::table('queue_events')->insertGetId(['queue_ticket_id' => null, 'actor' => 'staff', 'action' => 'call', 'operation_id' => (string) Str::uuid(), 'created_at' => $joinedAt]);
            DB::table('queue_settings')->where('id', 1)->update(['next_number' => 57, 'estimate_interval_minutes' => 6]);

            $migration->up();

            $ticket = QueueTicket::findOrFail($ticketId);
            $this->assertSame('A-042', $ticket->displayNumber());
            $this->assertSame($hash, $ticket->access_token_hash);
            $this->assertSame(QueueType::DineIn, $ticket->queue_type);
            $this->assertSame(TicketStatus::Waiting, $ticket->status);
            $this->assertSame($joinedAt, $ticket->joined_at->format('Y-m-d H:i:s'));
            $this->assertSame(QueueType::DineIn, QueueEvent::findOrFail($eventId)->queue_type);
            $this->assertSame(QueueType::DineIn, QueueEvent::findOrFail($emptyCallId)->queue_type);
            $this->assertSame($operation, QueueEvent::findOrFail($eventId)->operation_id);
            $this->assertSame(57, QueueSetting::findOrFail(1)->next_number);
            $this->assertSame(6, QueueSetting::findOrFail(1)->estimate_interval_minutes);

            (require database_path('migrations/2026_11_18_000001_add_customer_queue_options.php'))->up();
            [$newTicket] = $this->issue(QueueType::Takeaway);
            $this->assertSame('A-057', $newTicket->displayNumber());
            $this->assertSame(QueueType::Takeaway, $newTicket->queue_type);
            $this->assertSame(58, QueueSetting::findOrFail(1)->next_number);
            $this->assertSame(1, QueueSetting::count());
        });
    }

    public function test_rollback_refuses_takeaway_tickets_or_empty_call_history_before_schema_changes(): void
    {
        $this->withLegacyDatabase(function ($migration) {
            $migration->up();
            $ticket = $this->issueOnPreviousSchema(QueueType::Takeaway);
            $counter = QueueSetting::findOrFail(1)->next_number;
            try {
                $migration->down();
                $this->fail('Rollback discarded takeaway classification.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('classification must be preserved', $exception->getMessage());
            }
            $this->assertTrue(Schema::hasColumn('queue_tickets', 'queue_type'));
            $this->assertTrue(Schema::hasColumn('queue_events', 'queue_type'));
            $this->assertSame(QueueType::Takeaway, $ticket->fresh()->queue_type);
            $this->assertSame($counter, QueueSetting::findOrFail(1)->next_number);
        });

        $this->withLegacyDatabase(function ($migration) {
            $migration->up();
            DB::table('queue_events')->insert(['queue_ticket_id' => null, 'queue_type' => 'takeaway', 'actor' => 'staff', 'action' => 'call', 'operation_id' => (string) Str::uuid(), 'created_at' => now()]);
            try {
                $migration->down();
                $this->fail('Rollback discarded the empty call queue identity.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('classification must be preserved', $exception->getMessage());
            }
            $this->assertTrue(Schema::hasColumn('queue_tickets', 'queue_type'));
            $this->assertTrue(Schema::hasColumn('queue_events', 'queue_type'));
            $this->assertSame('takeaway', DB::table('queue_events')->value('queue_type'));
        });
    }

    public function test_rollback_with_only_dine_in_data_preserves_original_records(): void
    {
        $this->withLegacyDatabase(function ($migration) {
            $migration->up();
            $ticket = $this->issueOnPreviousSchema(QueueType::DineIn);
            $hash = $ticket->access_token_hash;
            $migration->down();
            $this->assertFalse(Schema::hasColumn('queue_tickets', 'queue_type'));
            $this->assertFalse(Schema::hasColumn('queue_events', 'queue_type'));
            $this->assertSame($hash, DB::table('queue_tickets')->where('id', $ticket->id)->value('access_token_hash'));
            $this->assertSame(1, DB::table('queue_events')->count());
            $this->assertSame(2, QueueSetting::findOrFail(1)->next_number);
        });
    }
}
