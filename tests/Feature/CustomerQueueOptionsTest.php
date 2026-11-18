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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerQueueOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function issue(QueueType $type = QueueType::DineIn, string $name = 'Pelanggan pilihan'): array
    {
        $token = bin2hex(random_bytes(32));
        $ticket = app(QueueService::class)->issue(['queue_type' => $type->value, 'customer_name' => $name, 'party_size' => 3, 'phone' => '0800000000'], $token, (string) Str::uuid());

        return [$ticket->fresh(), $token];
    }

    private function waitingIds(QueueType $type): array
    {
        return QueueTicket::where('queue_type', $type)->where('status', TicketStatus::Waiting)->orderBy('queue_order')->orderBy('number')->pluck('id')->all();
    }

    private function assertRejected(callable $action): void
    {
        try {
            $action();
            $this->fail('An invalid customer queue action was accepted.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_give_turn_passes_three_waiting_parties_and_preserves_other_queue_and_called_deadline(): void
    {
        $this->freezeTime();
        $queue = app(QueueService::class);
        $staff = User::factory()->create();
        QueueSetting::findOrFail(1)->update(['estimate_interval_minutes' => 4]);
        [$called] = $this->issue();
        $called = $queue->callNext((string) Str::uuid(), $staff->id, QueueType::DineIn);
        $calledState = $called->getRawOriginal();
        [$source] = $this->issue();
        [$cancelled] = $this->issue();
        $queue->transition($cancelled, 'cancel', (string) Str::uuid(), null, 'plans_changed');
        [$other] = $this->issue(QueueType::Takeaway);
        $otherState = $other->getRawOriginal();
        $followers = [];
        for ($i = 0; $i < 4; $i++) {
            [$followers[]] = $this->issue();
        }
        $sourceNumber = $source->number;
        $sourceHash = $source->access_token_hash;
        $joinedAt = $source->joined_at;
        $nextNumber = QueueSetting::findOrFail(1)->next_number;
        $operation = (string) Str::uuid();

        $queue->giveTurn($source, $operation);

        $expected = [$followers[0]->id, $followers[1]->id, $followers[2]->id, $source->id, $followers[3]->id];
        $this->assertSame($expected, $this->waitingIds(QueueType::DineIn));
        $this->assertSame([$other->id], $this->waitingIds(QueueType::Takeaway));
        $this->assertSame($otherState, $other->fresh()->getRawOriginal());
        $this->assertSame($calledState, $called->fresh()->getRawOriginal());
        $this->assertSame($sourceNumber, $source->fresh()->number);
        $this->assertSame($sourceHash, $source->fresh()->access_token_hash);
        $this->assertTrue($joinedAt->equalTo($source->fresh()->joined_at));
        $this->assertSame($nextNumber, QueueSetting::findOrFail(1)->next_number);
        $snapshot = app(WaitEstimateService::class)->snapshot(QueueType::DineIn, $source->fresh());
        $this->assertSame(3, $snapshot['ahead']);
        $this->assertSame(12, $snapshot['estimate_minutes']);
        $this->assertSame(5, $snapshot['waiting_count']);
        $event = QueueEvent::where('operation_id', $operation)->firstOrFail();
        $this->assertSame('give_turn', $event->action);
        $this->assertSame($source->id, $event->queue_ticket_id);
        $this->assertSame(QueueType::DineIn, $event->queue_type);

        [$newArrival] = $this->issue();
        $expected[] = $newArrival->id;
        foreach ($expected as $id) {
            $this->assertSame($id, $queue->callNext((string) Str::uuid(), $staff->id, QueueType::DineIn)->id);
        }
        $this->assertSame(TicketStatus::Waiting, $other->fresh()->status);
    }

    public function test_give_turn_handles_short_queue_and_replay_without_moving_twice(): void
    {
        $queue = app(QueueService::class);
        foreach (QueueType::cases() as $type) {
            [$source] = $this->issue($type);
            [$next] = $this->issue($type);
            [$last] = $this->issue($type);
            $operation = (string) Str::uuid();
            $queue->giveTurn($source, $operation);
            $this->assertSame([$next->id, $last->id, $source->id], $this->waitingIds($type));
            $orders = QueueTicket::orderBy('id')->pluck('queue_order')->all();
            $events = QueueEvent::count();
            $this->assertSame($source->id, $queue->giveTurn($source, $operation)->id);
            $this->assertSame($orders, QueueTicket::orderBy('id')->pluck('queue_order')->all());
            $this->assertSame($events, QueueEvent::count());
            $this->assertRejected(fn () => $queue->giveTurn($source, (string) Str::uuid()));
            $this->assertSame($events, QueueEvent::count());
        }
    }

    public function test_distinct_give_turn_operations_can_move_the_same_ticket_again(): void
    {
        [$source] = $this->issue();
        $followers = [];
        for ($i = 0; $i < 7; $i++) {
            [$followers[]] = $this->issue();
        }
        $queue = app(QueueService::class);
        $queue->giveTurn($source, (string) Str::uuid());
        $queue->giveTurn($source, (string) Str::uuid());
        $this->assertSame(6, app(WaitEstimateService::class)->snapshot(QueueType::DineIn, $source->fresh())['ahead']);
        $this->assertSame([...array_map(fn ($ticket) => $ticket->id, array_slice($followers, 0, 6)), $source->id, $followers[6]->id], $this->waitingIds(QueueType::DineIn));
        $this->assertSame(2, $source->events()->where('action', 'give_turn')->count());
        $this->assertSame(8, QueueTicket::count());
        $this->assertSame(9, QueueSetting::findOrFail(1)->next_number);
    }

    public function test_give_turn_replay_is_bound_to_source_and_action_and_uses_persisted_type(): void
    {
        [$source, $token] = $this->issue();
        [$follower] = $this->issue();
        [$other] = $this->issue(QueueType::Takeaway);
        $queue = app(QueueService::class);
        $operation = (string) Str::uuid();
        $source->queue_type = QueueType::Takeaway;
        $queue->giveTurn($source, $operation);
        $this->assertSame([$follower->id, $source->id], $this->waitingIds(QueueType::DineIn));
        $this->assertSame(QueueType::DineIn, $source->fresh()->queue_type);
        $this->assertRejected(fn () => $queue->giveTurn($follower, $operation));
        $this->assertRejected(fn () => $queue->giveTurn($other, $operation));
        $this->assertRejected(fn () => $queue->switchService($source, $token, QueueType::Takeaway, $operation));
        $this->assertSame(1, QueueEvent::where('operation_id', $operation)->count());
        $this->assertSame(3, QueueTicket::count());
    }

    public function test_switch_service_issues_a_new_global_number_at_destination_tail_in_both_directions(): void
    {
        $queue = app(QueueService::class);
        foreach (QueueType::cases() as $origin) {
            $destination = $origin === QueueType::DineIn ? QueueType::Takeaway : QueueType::DineIn;
            [$source, $token] = $this->issue($origin, 'Pelanggan pindah');
            [$existingDestination] = $this->issue($destination);
            [$originFollower] = $this->issue($origin);
            $destinationBefore = $this->waitingIds($destination);
            $sourceNumber = $source->number;
            $sourceHash = $source->access_token_hash;
            $sourceJoined = $source->joined_at;
            $nextNumber = QueueSetting::findOrFail(1)->next_number;
            $operation = (string) Str::uuid();

            $result = $queue->switchService($source, $token, $destination, $operation);

            $replacement = $result['ticket'];
            $this->assertNotSame($source->id, $replacement->id);
            $this->assertSame($nextNumber, $replacement->number);
            $this->assertSame($destination, $replacement->queue_type);
            $this->assertSame(TicketStatus::Waiting, $replacement->status);
            $this->assertSame($source->customer_name, $replacement->customer_name);
            $this->assertSame($source->party_size, $replacement->party_size);
            $this->assertSame($source->phone, $replacement->phone);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['token']);
            $this->assertNotSame($token, $result['token']);
            $this->assertSame(hash('sha256', $result['token']), $replacement->access_token_hash);
            $this->assertNull($replacement->called_at);
            $this->assertNull($replacement->arrival_deadline);
            $this->assertNull($replacement->cancelled_at);
            $this->assertSame(TicketStatus::Cancelled, $source->fresh()->status);
            $this->assertSame('service_switch', $source->fresh()->cancellation_reason);
            $this->assertSame($origin, $source->fresh()->queue_type);
            $this->assertSame($sourceNumber, $source->fresh()->number);
            $this->assertSame($sourceHash, $source->fresh()->access_token_hash);
            $this->assertTrue($sourceJoined->equalTo($source->fresh()->joined_at));
            $this->assertNotNull($source->fresh()->cancelled_at);
            $this->assertSame([...$destinationBefore, $replacement->id], $this->waitingIds($destination));
            $this->assertContains($originFollower->id, $this->waitingIds($origin));
            $this->assertSame($nextNumber + 1, QueueSetting::findOrFail(1)->next_number);
            $event = QueueEvent::where('operation_id', $operation)->firstOrFail();
            $this->assertSame('switch_service', $event->action);
            $this->assertSame('service_switch', $event->reason);
            $this->assertSame($source->id, $event->queue_ticket_id);
            $this->assertSame($replacement->id, $event->related_ticket_id);
            $this->assertSame($origin, $event->queue_type);
            $history = QueueEvent::query()->get()->toJson();
            $this->assertStringNotContainsString($token, $history);
            $this->assertStringNotContainsString($result['token'], $history);
            $this->get('/tiket/'.$token.'/status')->assertJsonPath('status', 'cancelled')->assertJsonPath('queue_type', $origin->value);
            $this->get('/tiket/'.$result['token'].'/status')->assertJsonPath('status', 'waiting')->assertJsonPath('queue_type', $destination->value);
        }
    }

    public function test_transfer_replay_returns_the_same_private_ticket_and_rejects_changed_context(): void
    {
        [$source, $token] = $this->issue();
        [$other, $otherToken] = $this->issue();
        $queue = app(QueueService::class);
        $operation = (string) Str::uuid();
        $first = $queue->switchService($source, $token, QueueType::Takeaway, $operation);
        $events = QueueEvent::count();
        $counter = QueueSetting::findOrFail(1)->next_number;
        $retry = $queue->switchService($source, $token, QueueType::Takeaway, $operation);
        $this->assertSame($first['ticket']->id, $retry['ticket']->id);
        $this->assertSame($first['token'], $retry['token']);
        $this->assertRejected(fn () => $queue->switchService($source, bin2hex(random_bytes(32)), QueueType::Takeaway, $operation));
        $this->assertRejected(fn () => $queue->switchService($source, $token, QueueType::DineIn, $operation));
        $this->assertRejected(fn () => $queue->switchService($other, $otherToken, QueueType::Takeaway, $operation));
        $this->assertRejected(fn () => $queue->giveTurn($source, $operation));
        $this->assertSame($events, QueueEvent::count());
        $this->assertSame($counter, QueueSetting::findOrFail(1)->next_number);
        $this->assertSame(3, QueueTicket::count());
        $this->assertSame(TicketStatus::Waiting, $other->fresh()->status);
    }

    public function test_transfer_rejects_wrong_secret_and_same_service_before_any_writes(): void
    {
        [$source, $token] = $this->issue();
        $queue = app(QueueService::class);
        $state = $source->getRawOriginal();
        $this->assertRejected(fn () => $queue->switchService($source, bin2hex(random_bytes(32)), QueueType::Takeaway, (string) Str::uuid()));
        $this->assertRejected(fn () => $queue->switchService($source, $token, QueueType::DineIn, (string) Str::uuid()));
        $this->assertSame($state, $source->fresh()->getRawOriginal());
        $this->assertSame(1, QueueTicket::count());
        $this->assertSame(1, QueueEvent::count());
        $this->assertSame(2, QueueSetting::findOrFail(1)->next_number);
    }

    public function test_only_waiting_tickets_can_switch_or_give_turn_including_stale_forms(): void
    {
        $queue = app(QueueService::class);
        $staff = User::factory()->create();
        foreach ([TicketStatus::Called, TicketStatus::Arrived, TicketStatus::Completed, TicketStatus::Cancelled] as $status) {
            [$source, $token] = $this->issue(QueueType::Takeaway);
            $stale = clone $source;
            $called = $queue->callNext((string) Str::uuid(), $staff->id, QueueType::Takeaway);
            if (in_array($status, [TicketStatus::Arrived, TicketStatus::Completed], true)) {
                $queue->transition($called, 'arrive', (string) Str::uuid(), $staff->id);
            }
            if ($status === TicketStatus::Completed) {
                $queue->transition($source, 'complete', (string) Str::uuid(), $staff->id);
            } elseif ($status === TicketStatus::Cancelled) {
                $queue->transition($source, 'cancel', (string) Str::uuid(), null, 'other');
            }
            $state = $source->fresh()->getRawOriginal();
            $events = QueueEvent::count();
            $counter = QueueSetting::findOrFail(1)->next_number;
            $this->assertRejected(fn () => $queue->giveTurn($stale, (string) Str::uuid()));
            $this->assertRejected(fn () => $queue->switchService($stale, $token, QueueType::DineIn, (string) Str::uuid()));
            $this->assertSame($state, $source->fresh()->getRawOriginal());
            $this->assertSame($events, QueueEvent::count());
            $this->assertSame($counter, QueueSetting::findOrFail(1)->next_number);
        }
    }

    public function test_customer_cancellation_records_each_reason_for_waiting_and_called_and_binds_replay_reason(): void
    {
        $queue = app(QueueService::class);
        $staff = User::factory()->create();
        foreach ([TicketStatus::Waiting, TicketStatus::Called] as $status) {
            foreach (['too_long', 'found_elsewhere', 'plans_changed', 'other'] as $reason) {
                [$source] = $this->issue();
                if ($status === TicketStatus::Called) {
                    $queue->callNext((string) Str::uuid(), $staff->id, QueueType::DineIn);
                }
                $operation = (string) Str::uuid();
                $queue->transition($source, 'cancel', $operation, null, $reason);
                $this->assertSame(TicketStatus::Cancelled, $source->fresh()->status);
                $this->assertSame($reason, $source->fresh()->cancellation_reason);
                $event = QueueEvent::where('operation_id', $operation)->firstOrFail();
                $this->assertSame($reason, $event->reason);
                $this->assertSame('customer', $event->actor);
                $this->assertSame($status->value, $event->from_status);
                $this->assertSame($source->id, $queue->transition($source, 'cancel', $operation, null, $reason)->id);
                $this->assertSame(1, QueueEvent::where('operation_id', $operation)->count());
                $changedReason = $reason === 'other' ? 'too_long' : 'other';
                $this->assertRejected(fn () => $queue->transition($source, 'cancel', $operation, null, $changedReason));
            }
        }
    }

    public function test_customer_cancellation_requires_an_explicit_allowed_reason(): void
    {
        [$source] = $this->issue();
        $queue = app(QueueService::class);
        foreach ([null, '', 'customer', 'staff', 'no_show', 'skipped', 'service_switch', 'invalid'] as $reason) {
            $this->assertRejected(fn () => $queue->transition($source, 'cancel', (string) Str::uuid(), null, $reason));
        }
        $this->assertSame(TicketStatus::Waiting, $source->fresh()->status);
        $this->assertSame(1, QueueEvent::count());
    }

    public function test_private_options_are_read_only_and_require_the_ticket_secret(): void
    {
        [$source, $token] = $this->issue();
        $state = $source->getRawOriginal();
        $response = $this->get('/tiket/'.$token.'/pilihan')->assertOk()->assertSee('Makan di Tempat')->assertSee('Bawa Pulang')->assertSee('Tetap');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get('/tiket/invalid/pilihan')->assertNotFound();
        $this->get('/tiket/'.bin2hex(random_bytes(32)).'/pilihan')->assertNotFound();
        $this->assertSame($state, $source->fresh()->getRawOriginal());
        $this->assertSame(1, QueueEvent::count());
    }

    public function test_http_give_turn_uses_persisted_service_and_updates_private_position(): void
    {
        [$source, $token] = $this->issue();
        [$follower] = $this->issue();
        [$other] = $this->issue(QueueType::Takeaway);
        $operation = (string) Str::uuid();
        $this->post('/tiket/'.$token.'/beri-giliran', ['operation_id' => $operation, 'queue_type' => 'takeaway'])->assertRedirect();
        $this->post('/tiket/'.$token.'/beri-giliran', ['operation_id' => $operation])->assertRedirect();
        $this->assertSame([$follower->id, $source->id], $this->waitingIds(QueueType::DineIn));
        $this->assertSame([$other->id], $this->waitingIds(QueueType::Takeaway));
        $this->get('/tiket/'.$token.'/status')->assertJsonPath('queue_type', 'dine_in')->assertJsonPath('snapshot.ahead', 1)->assertJsonMissingPath('phone')->assertJsonMissingPath('access_token_hash');
        $this->assertSame(1, QueueEvent::where('operation_id', $operation)->count());
    }

    public function test_http_transfer_redirects_to_the_same_replacement_private_link_on_retry(): void
    {
        [$source, $token] = $this->issue();
        [$destination] = $this->issue(QueueType::Takeaway);
        $payload = ['operation_id' => (string) Str::uuid(), 'queue_type' => 'takeaway'];
        $first = $this->post('/tiket/'.$token.'/ubah-layanan', $payload)->assertRedirect();
        $location = $first->headers->get('Location');
        $this->assertMatchesRegularExpression('#/tiket/[a-f0-9]{64}$#', $location);
        $this->post('/tiket/'.$token.'/ubah-layanan', $payload)->assertRedirect($location);
        $newToken = basename(parse_url($location, PHP_URL_PATH));
        $this->assertNotSame($token, $newToken);
        $this->get($location)->assertOk()->assertSee('Bawa Pulang');
        $this->get('/tiket/'.$newToken.'/status')->assertJsonPath('queue_type', 'takeaway')->assertJsonPath('snapshot.ahead', 1)->assertJsonMissingPath('customer_name')->assertJsonMissingPath('phone');
        $this->get('/tiket/'.$token.'/status')->assertJsonPath('status', 'cancelled');
        $this->assertSame(3, QueueTicket::count());
        $this->assertSame(TicketStatus::Waiting, $destination->fresh()->status);
        $this->assertSame('service_switch', $source->fresh()->cancellation_reason);
    }

    public function test_http_validates_action_payloads_and_never_mutates_a_ticket_without_its_secret(): void
    {
        [$source, $token] = $this->issue();
        [$follower] = $this->issue();
        $this->post('/tiket/'.$token.'/beri-giliran', [])->assertSessionHasErrors('operation_id');
        $this->post('/tiket/'.$token.'/beri-giliran', ['operation_id' => 'invalid'])->assertSessionHasErrors('operation_id');
        foreach ([null, 'invalid', ['takeaway']] as $destination) {
            $this->post('/tiket/'.$token.'/ubah-layanan', ['operation_id' => (string) Str::uuid(), 'queue_type' => $destination])->assertSessionHasErrors('queue_type');
        }
        $this->post('/tiket/'.$token.'/batalkan', ['operation_id' => (string) Str::uuid()])->assertSessionHasErrors('reason');
        $this->post('/tiket/'.$token.'/batalkan', ['operation_id' => (string) Str::uuid(), 'reason' => 'staff'])->assertSessionHasErrors('reason');
        foreach (['beri-giliran', 'ubah-layanan', 'batalkan'] as $path) {
            $this->post('/tiket/'.bin2hex(random_bytes(32)).'/'.$path, ['operation_id' => (string) Str::uuid(), 'queue_type' => 'takeaway', 'reason' => 'other'])->assertNotFound();
        }
        $this->assertSame([$source->id, $follower->id], $this->waitingIds(QueueType::DineIn));
        $this->assertSame(2, QueueEvent::count());
        $this->assertSame(3, QueueSetting::findOrFail(1)->next_number);
    }

    public function test_called_customer_can_cancel_but_cannot_switch_or_give_turn_over_http(): void
    {
        [$source, $token] = $this->issue();
        [$follower] = $this->issue();
        $queue = app(QueueService::class);
        $queue->callNext((string) Str::uuid(), User::factory()->create()->id, QueueType::DineIn);
        $deadline = $source->fresh()->arrival_deadline;
        $this->post('/tiket/'.$token.'/beri-giliran', ['operation_id' => (string) Str::uuid()])->assertSessionHasErrors('action');
        $this->post('/tiket/'.$token.'/ubah-layanan', ['operation_id' => (string) Str::uuid(), 'queue_type' => 'takeaway'])->assertSessionHasErrors('action');
        $this->assertSame(TicketStatus::Called, $source->fresh()->status);
        $this->assertTrue($deadline->equalTo($source->fresh()->arrival_deadline));
        $this->post('/tiket/'.$token.'/batalkan', ['operation_id' => (string) Str::uuid(), 'reason' => 'found_elsewhere'])->assertRedirect();
        $this->assertSame('found_elsewhere', $source->fresh()->cancellation_reason);
        $this->assertSame([$follower->id], $this->waitingIds(QueueType::DineIn));
    }

    public function test_staff_waiting_list_and_calls_follow_reordered_tickets(): void
    {
        [$source] = $this->issue(QueueType::DineIn, 'Pelanggan mundur');
        [$next] = $this->issue(QueueType::DineIn, 'Pelanggan berikutnya');
        $queue = app(QueueService::class);
        $queue->giveTurn($source, (string) Str::uuid());
        $staff = User::factory()->create();
        $this->actingAs($staff)->get('/staf?queue_type=dine_in&status=waiting')->assertOk()->assertViewHas('tickets', fn ($tickets) => $tickets->pluck('id')->all() === [$next->id, $source->id]);
        $this->post('/staf/panggil', ['queue_type' => 'dine_in', 'operation_id' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame(TicketStatus::Called, $next->fresh()->status);
        $this->assertSame(TicketStatus::Waiting, $source->fresh()->status);
        $this->assertSame(0, app(WaitEstimateService::class)->snapshot(QueueType::DineIn, $source->fresh())['ahead']);
    }

    public function test_old_transfer_page_links_to_replacement_without_exposing_it_in_status_json(): void
    {
        [$source, $token] = $this->issue();
        $result = app(QueueService::class)->switchService($source, $token, QueueType::Takeaway, (string) Str::uuid());
        $this->get('/tiket/'.$token)->assertOk()->assertSee('Sudah pindah layanan')
            ->assertSee(route('ticket.show', ['token' => $result['token']]), false);
        $this->get('/tiket/'.$token.'/status')->assertOk()->assertDontSee($result['token']);
        $this->get('/tiket/'.$token.'/pilihan')->assertRedirect('/tiket/'.$token);
    }

    public function test_called_options_hide_transfer_and_give_turn_and_keep_cancellation_reasons(): void
    {
        [$source, $token] = $this->issue();
        app(QueueService::class)->callNext((string) Str::uuid(), User::factory()->create()->id, QueueType::DineIn);
        $this->get('/tiket/'.$token.'/pilihan')->assertOk()->assertSee('Alasan pembatalan')
            ->assertDontSee('action="'.route('ticket.switch', ['token' => $token]).'"', false)
            ->assertDontSee('action="'.route('ticket.give', ['token' => $token]).'"', false);
        $this->assertSame(TicketStatus::Called, $source->fresh()->status);
    }

    public function test_transfer_rolls_back_every_change_when_destination_creation_fails(): void
    {
        [$source, $token] = $this->issue();
        $queue = app(QueueService::class);
        $operation = (string) Str::uuid();
        // A conflicting destination hash forces a real database constraint failure.
        $queue->issue(['queue_type' => 'takeaway', 'customer_name' => 'Constraint fixture', 'party_size' => 1], $queue->transferToken($token, $operation), (string) Str::uuid());
        $before = $source->fresh()->getRawOriginal();
        $counter = QueueSetting::findOrFail(1)->next_number;
        $events = QueueEvent::count();
        try {
            $queue->switchService($source, $token, QueueType::Takeaway, $operation);
            $this->fail('A duplicate destination access hash was accepted.');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertSame($before, $source->fresh()->getRawOriginal());
            $this->assertSame($counter, QueueSetting::findOrFail(1)->next_number);
            $this->assertSame($events, QueueEvent::count());
            $this->assertSame(2, QueueTicket::count());
        }
    }
}
