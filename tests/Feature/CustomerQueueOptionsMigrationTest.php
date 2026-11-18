<?php

namespace Tests\Feature;

use App\Enums\QueueType;
use App\Models\QueueEvent;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Services\QueueService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerQueueOptionsMigrationTest extends TestCase
{
    private function withPreviousSchema(callable $assertions): void
    {
        $originalConnection = DB::getDefaultConnection();
        $connection = 'customer_options_migration_testing';
        config(['database.connections.'.$connection => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::purge($connection);
        DB::setDefaultConnection($connection);
        app()->forgetInstance('db.schema');
        Schema::clearResolvedInstance('db.schema');

        try {
            $this->assertSame(':memory:', DB::connection()->getDatabaseName());
            Schema::create('users', fn (Blueprint $table) => $table->id());
            (require database_path('migrations/2026_11_01_000001_create_queue_tables.php'))->up();
            (require database_path('migrations/2026_11_17_000001_add_queue_types.php'))->up();
            $this->seedPreviousRecords();
            $migration = require database_path('migrations/2026_11_18_000001_add_customer_queue_options.php');
            $assertions($migration);
        } finally {
            DB::purge($connection);
            DB::setDefaultConnection($originalConnection);
            app()->forgetInstance('db.schema');
            Schema::clearResolvedInstance('db.schema');
        }
    }

    private function tokenFor(int $number): string
    {
        return str_pad(dechex($number), 64, '0', STR_PAD_LEFT);
    }

    private function seedPreviousRecords(): void
    {
        $stamp = '2026-11-17 01:00:00';
        $records = [[9, 'dine_in', 'waiting'], [12, 'takeaway', 'waiting'], [17, 'dine_in', 'waiting'], [21, 'dine_in', 'called'],
            [28, 'takeaway', 'cancelled'], [34, 'takeaway', 'waiting'], [37, 'dine_in', 'arrived'], [45, 'takeaway', 'completed']];
        foreach ($records as [$number, $type, $status]) {
            $called = in_array($status, ['called', 'arrived', 'completed'], true);
            $ticketId = DB::table('queue_tickets')->insertGetId(['number' => $number, 'queue_type' => $type,
                'access_token_hash' => hash('sha256', $this->tokenFor($number)), 'customer_name' => 'Data lama sintetis '.$number,
                'party_size' => 3, 'phone' => '0800000000', 'status' => $status, 'joined_at' => $stamp,
                'called_at' => $called ? $stamp : null, 'arrival_deadline' => $called ? '2026-11-17 01:17:00' : null,
                'arrived_at' => in_array($status, ['arrived', 'completed'], true) ? $stamp : null,
                'completed_at' => $status === 'completed' ? $stamp : null, 'cancelled_at' => $status === 'cancelled' ? $stamp : null,
                'cancellation_reason' => $status === 'cancelled' ? 'customer' : null, 'created_at' => $stamp, 'updated_at' => $stamp]);
            DB::table('queue_events')->insert(['queue_ticket_id' => $ticketId, 'queue_type' => $type, 'actor' => 'customer',
                'action' => 'issue', 'to_status' => 'waiting', 'operation_id' => (string) Str::uuid(), 'created_at' => $stamp]);
        }
        DB::table('queue_events')->insert(['queue_ticket_id' => null, 'queue_type' => 'takeaway', 'actor' => 'staff',
            'action' => 'call', 'operation_id' => (string) Str::uuid(), 'created_at' => $stamp]);
        DB::table('queue_settings')->where('id', 1)->update(['next_number' => 99, 'arrival_grace_minutes' => 17,
            'estimate_interval_minutes' => 5, 'near_turn_threshold' => 2, 'created_at' => $stamp, 'updated_at' => $stamp]);
    }

    private function records(string $table, array $excluded = []): array
    {
        return DB::table($table)->orderBy('id')->get()->map(fn ($row) => array_diff_key((array) $row, array_flip($excluded)))->all();
    }

    private function assertRollbackRefused($migration): void
    {
        $tickets = $this->records('queue_tickets');
        $events = $this->records('queue_events');
        $settings = $this->records('queue_settings');
        try {
            $migration->down();
            $this->fail('Rollback discarded customer queue ordering or transfer history.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Rollback would lose', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('queue_tickets', 'queue_order'));
        $this->assertTrue(Schema::hasColumn('queue_events', 'related_ticket_id'));
        $this->assertSame($tickets, $this->records('queue_tickets'));
        $this->assertSame($events, $this->records('queue_events'));
        $this->assertSame($settings, $this->records('queue_settings'));
    }

    public function test_forward_migration_preserves_every_legacy_field_and_backfills_both_service_orders(): void
    {
        $this->withPreviousSchema(function ($migration) {
            $tickets = $this->records('queue_tickets');
            $events = $this->records('queue_events');
            $settings = $this->records('queue_settings');

            $migration->up();

            $this->assertSame($tickets, $this->records('queue_tickets', ['queue_order']));
            $this->assertSame($events, $this->records('queue_events', ['related_ticket_id']));
            $this->assertSame($settings, $this->records('queue_settings'));
            $this->assertSame(0, DB::table('queue_tickets')->whereColumn('queue_order', '!=', 'number')->count());
            $this->assertSame(0, DB::table('queue_events')->whereNotNull('related_ticket_id')->count());
            foreach (['dine_in' => [9, 17], 'takeaway' => [12, 34]] as $type => $numbers) {
                $this->assertSame($numbers, DB::table('queue_tickets')->where('queue_type', $type)->where('status', 'waiting')->orderBy('queue_order')->pluck('number')->all());
            }
            $new = app(QueueService::class)->issue(['queue_type' => 'takeaway', 'customer_name' => 'Tiket baru sintetis', 'party_size' => 2], bin2hex(random_bytes(32)), (string) Str::uuid());
            $this->assertSame(99, $new->number);
            $this->assertSame(99, $new->queue_order);
            $this->assertSame(100, QueueSetting::findOrFail(1)->next_number);
        });
    }

    public function test_unused_rollback_preserves_legacy_records_including_both_queues_and_old_cancellations(): void
    {
        $this->withPreviousSchema(function ($migration) {
            $tickets = $this->records('queue_tickets');
            $events = $this->records('queue_events');
            $settings = $this->records('queue_settings');
            $migration->up();
            $migration->down();
            $this->assertFalse(Schema::hasColumn('queue_tickets', 'queue_order'));
            $this->assertFalse(Schema::hasColumn('queue_events', 'related_ticket_id'));
            $this->assertSame($tickets, $this->records('queue_tickets'));
            $this->assertSame($events, $this->records('queue_events'));
            $this->assertSame($settings, $this->records('queue_settings'));
        });
    }

    public function test_rejected_give_turn_at_tail_does_not_block_an_unused_rollback(): void
    {
        $this->withPreviousSchema(function ($migration) {
            $tickets = $this->records('queue_tickets');
            $events = $this->records('queue_events');
            $settings = $this->records('queue_settings');
            $migration->up();
            try {
                app(QueueService::class)->giveTurn(QueueTicket::where('number', 17)->firstOrFail(), (string) Str::uuid());
                $this->fail('A tail ticket unexpectedly gave another party its turn.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('action', $exception->errors());
            }
            $this->assertSame(0, QueueEvent::where('action', 'give_turn')->count());
            $migration->down();
            $this->assertSame($tickets, $this->records('queue_tickets'));
            $this->assertSame($events, $this->records('queue_events'));
            $this->assertSame($settings, $this->records('queue_settings'));
        });
    }

    public function test_rollback_refuses_an_executed_give_turn_before_any_schema_or_data_changes(): void
    {
        $this->withPreviousSchema(function ($migration) {
            $migration->up();
            app(QueueService::class)->giveTurn(QueueTicket::where('number', 9)->firstOrFail(), (string) Str::uuid());
            $this->assertRollbackRefused($migration);
        });
    }

    public function test_rollback_refuses_a_transfer_and_preserves_its_destination_link_and_private_access(): void
    {
        $this->withPreviousSchema(function ($migration) {
            $migration->up();
            $source = QueueTicket::where('number', 9)->firstOrFail();
            $operation = (string) Str::uuid();
            $result = app(QueueService::class)->switchService($source, $this->tokenFor(9), QueueType::Takeaway, $operation);
            $this->assertRollbackRefused($migration);
            $event = QueueEvent::where('operation_id', $operation)->firstOrFail();
            $this->assertSame($result['ticket']->id, $event->related_ticket_id);
            $this->assertSame(hash('sha256', $result['token']), $result['ticket']->fresh()->access_token_hash);
            $this->assertSame(hash('sha256', $this->tokenFor(9)), $source->fresh()->access_token_hash);
        });
    }

    public function test_rollback_refuses_changed_order_even_if_no_customer_action_event_exists(): void
    {
        $this->withPreviousSchema(function ($migration) {
            $migration->up();
            DB::table('queue_tickets')->where('number', 9)->update(['queue_order' => 999]);
            $this->assertSame(0, QueueEvent::whereIn('action', ['give_turn', 'switch_service'])->count());
            $this->assertRollbackRefused($migration);
        });
    }
}
