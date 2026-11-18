<?php

namespace Tests\Feature;

use App\Enums\QueueType;
use App\Enums\TicketStatus;
use App\Models\QueueEvent;
use App\Models\QueueSetting;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalledCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function issue(QueueType $type): array
    {
        $token = bin2hex(random_bytes(32));
        $ticket = app(QueueService::class)->issue([
            'queue_type' => $type->value,
            'customer_name' => 'Pelanggan uji',
            'party_size' => 2,
        ], $token, (string) Str::uuid());

        return [$ticket, $token];
    }

    public function test_called_customer_cancellation_is_visible_after_refresh_even_when_auto_call_is_off(): void
    {
        [$cancelled, $token] = $this->issue(QueueType::DineIn);
        [$next] = $this->issue(QueueType::DineIn);
        $staff = User::factory()->create();
        $queue = app(QueueService::class);
        $this->assertFalse(QueueSetting::findOrFail(1)->auto_call_dine_in);
        $this->assertFalse(QueueSetting::findOrFail(1)->auto_call_takeaway);
        $queue->callNext((string) Str::uuid(), $staff->id, QueueType::DineIn);
        $operation = (string) Str::uuid();

        $this->post('/tiket/'.$token.'/batalkan', ['operation_id' => $operation, 'reason' => 'plans_changed'])->assertRedirect();

        $this->assertSame(TicketStatus::Cancelled, $cancelled->fresh()->status);
        $this->assertSame(TicketStatus::Waiting, $next->fresh()->status);
        $event = QueueEvent::where('operation_id', $operation)->firstOrFail();
        $this->assertNull($event->related_ticket_id);
        $this->assertFalse(QueueEvent::where('action', 'auto_call')->exists());

        $this->actingAs($staff);
        $params = '?queue_type=takeaway&q=TidakCocok&status=waiting';
        $this->get('/staf'.$params)->assertOk()->assertSee('Pembatalan setelah dipanggil')->assertSee($cancelled->displayNumber());
        $poll = $this->get('/staf/status'.$params)->assertOk()->assertJsonPath('latest_cancellation_id', $event->id);
        $this->assertStringContainsString($cancelled->displayNumber(), $poll->json('cancellations_html'));
        $this->assertStringContainsString(QueueType::DineIn->label(), $poll->json('cancellations_html'));
        $this->assertStringNotContainsString($token, $poll->json('cancellations_html'));
        $this->assertStringNotContainsString($cancelled->displayNumber(), $poll->json('html'));
    }

    public function test_notices_exclude_waiting_cancellations_staff_cancellations_and_transfers(): void
    {
        $queue = app(QueueService::class);
        $staff = User::factory()->create();
        [$dine] = $this->issue(QueueType::DineIn);
        [$takeaway] = $this->issue(QueueType::Takeaway);
        foreach (QueueType::cases() as $type) {
            $queue->callNext((string) Str::uuid(), $staff->id, $type);
        }
        $queue->transition($dine, 'cancel', (string) Str::uuid(), null, 'other');
        $lastNotice = (string) Str::uuid();
        $queue->transition($takeaway, 'cancel', $lastNotice, null, 'too_long');

        QueueSetting::findOrFail(1)->update(['auto_call_dine_in' => true, 'auto_call_takeaway' => true]);
        [$waiting] = $this->issue(QueueType::DineIn);
        [$dineNext] = $this->issue(QueueType::DineIn);
        $queue->transition($waiting, 'cancel', (string) Str::uuid(), null, 'other');
        [$staffCancelled] = $this->issue(QueueType::Takeaway);
        [$takeawayNext] = $this->issue(QueueType::Takeaway);
        $queue->callNext((string) Str::uuid(), $staff->id, QueueType::Takeaway);
        $queue->transition($staffCancelled, 'cancel', (string) Str::uuid(), $staff->id, 'staff');
        [$transferred, $token] = $this->issue(QueueType::Takeaway);
        $queue->switchService($transferred, $token, QueueType::DineIn, (string) Str::uuid());

        $this->assertSame(TicketStatus::Waiting, $dineNext->fresh()->status);
        $this->assertSame(TicketStatus::Waiting, $takeawayNext->fresh()->status);
        $this->assertFalse(QueueEvent::where('action', 'auto_call')->exists());
        $event = QueueEvent::where('operation_id', $lastNotice)->firstOrFail();
        $html = $this->actingAs($staff)->get('/staf/status?queue_type=dine_in')->assertOk()
            ->assertJsonPath('latest_cancellation_id', $event->id)->json('cancellations_html');
        $this->assertStringContainsString($dine->displayNumber(), $html);
        $this->assertStringContainsString($takeaway->displayNumber(), $html);
        foreach ([$waiting, $staffCancelled, $transferred] as $unrelated) {
            $this->assertStringNotContainsString($unrelated->displayNumber(), $html);
        }
    }

    public function test_auto_call_uses_current_same_service_order_and_a_fresh_deadline_once_per_cancellation(): void
    {
        $this->freezeSecond();
        QueueSetting::findOrFail(1)->update(['auto_call_dine_in' => true, 'arrival_grace_minutes' => 7]);
        [$cancelled, $token] = $this->issue(QueueType::DineIn);
        [$other] = $this->issue(QueueType::Takeaway);
        [$deferred] = $this->issue(QueueType::DineIn);
        [$next] = $this->issue(QueueType::DineIn);
        $staff = User::factory()->create();
        $queue = app(QueueService::class);
        $queue->callNext((string) Str::uuid(), $staff->id, QueueType::DineIn);
        $queue->giveTurn($deferred, (string) Str::uuid());
        $this->travel(2)->minutes();
        $payload = ['operation_id' => (string) Str::uuid(), 'reason' => 'plans_changed'];

        $this->post('/tiket/'.$token.'/batalkan', $payload)->assertRedirect();
        $this->post('/tiket/'.$token.'/batalkan', $payload)->assertRedirect();

        $next->refresh();
        $this->assertSame(TicketStatus::Cancelled, $cancelled->fresh()->status);
        $this->assertSame(TicketStatus::Called, $next->status);
        $this->assertTrue($next->called_at->equalTo(now()));
        $this->assertTrue($next->arrival_deadline->equalTo(now()->addMinutes(7)));
        $this->assertSame(TicketStatus::Waiting, $deferred->fresh()->status);
        $this->assertSame(TicketStatus::Waiting, $other->fresh()->status);
        $this->assertDatabaseHas('queue_events', [
            'operation_id' => $payload['operation_id'],
            'queue_ticket_id' => $cancelled->id,
            'related_ticket_id' => $next->id,
            'action' => 'cancel',
        ]);
        $this->assertDatabaseHas('queue_events', [
            'queue_ticket_id' => $next->id,
            'queue_type' => QueueType::DineIn->value,
            'actor' => 'system',
            'action' => 'auto_call',
            'from_status' => 'waiting',
            'to_status' => 'called',
        ]);
        $this->assertSame(1, QueueEvent::where('action', 'auto_call')->count());
        $html = $this->actingAs($staff)->get('/staf/status')->assertOk()->json('cancellations_html');
        $this->assertStringContainsString($cancelled->displayNumber(), $html);
        $this->assertStringContainsString($next->displayNumber(), $html);
    }

    public function test_an_empty_service_does_not_auto_call_a_later_registration_on_retry(): void
    {
        QueueSetting::findOrFail(1)->update(['auto_call_takeaway' => true]);
        [$cancelled, $token] = $this->issue(QueueType::Takeaway);
        [$other] = $this->issue(QueueType::DineIn);
        $staff = User::factory()->create();
        $queue = app(QueueService::class);
        $queue->callNext((string) Str::uuid(), $staff->id, QueueType::Takeaway);
        $payload = ['operation_id' => (string) Str::uuid(), 'reason' => 'other'];

        $this->post('/tiket/'.$token.'/batalkan', $payload)->assertRedirect();
        [$late] = $this->issue(QueueType::Takeaway);
        $this->post('/tiket/'.$token.'/batalkan', $payload)->assertRedirect();

        $this->assertSame(TicketStatus::Cancelled, $cancelled->fresh()->status);
        $this->assertSame(TicketStatus::Waiting, $other->fresh()->status);
        $this->assertSame(TicketStatus::Waiting, $late->fresh()->status);
        $this->assertFalse(QueueEvent::where('action', 'auto_call')->exists());
        $this->assertNull(QueueEvent::where('operation_id', $payload['operation_id'])->firstOrFail()->related_ticket_id);
    }

    public function test_auto_call_settings_require_staff_and_affect_only_the_selected_service(): void
    {
        $url = '/staf/pengaturan/panggilan-otomatis';
        $payload = ['queue_type' => QueueType::Takeaway->value, 'enabled' => '1'];
        QueueSetting::findOrFail(1)->update(['estimate_interval_minutes' => 5]);

        $this->get('/staf/status')->assertRedirect(route('login'));
        $this->post($url, $payload)->assertRedirect(route('login'));
        $this->assertFalse(QueueSetting::findOrFail(1)->auto_call_takeaway);

        $staff = User::factory()->create();
        $this->actingAs($staff);
        $this->postJson($url, ['queue_type' => 'other', 'enabled' => 'invalid'])->assertUnprocessable()
            ->assertJsonValidationErrors(['queue_type', 'enabled']);
        $this->post($url, $payload)->assertRedirect(route('staff.index', ['queue_type' => 'takeaway']));
        $settings = QueueSetting::findOrFail(1);
        $this->assertTrue($settings->auto_call_takeaway);
        $this->assertFalse($settings->auto_call_dine_in);
        $this->assertSame(5, $settings->estimate_interval_minutes);

        $this->post($url, array_replace($payload, ['enabled' => '0']))->assertRedirect();
        $this->assertFalse($settings->fresh()->auto_call_takeaway);
    }
}
