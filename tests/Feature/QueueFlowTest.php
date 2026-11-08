<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\QueueService;
use App\Services\WaitEstimateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QueueFlowTest extends TestCase
{
    use RefreshDatabase;

    private function issue(string $name = 'Pelanggan'): array
    {
        $token = bin2hex(random_bytes(32));
        $op = (string) Str::uuid();

        return [app(QueueService::class)->issue(['customer_name' => $name, 'party_size' => 2], $token, $op), $token, $op];
    }

    private function staff(): User
    {
        return User::factory()->create();
    }

    public function test_customer_and_staff_complete_the_full_flow(): void
    {
        $this->get('/')->assertOk()->assertSee('Antre lebih nyaman.');
        $this->get('/staf')->assertRedirect('/staf/masuk');
        $token = bin2hex(random_bytes(32));
        $this->post('/antrean', ['customer_name' => 'Made', 'party_size' => 3, 'phone' => null, 'access_token' => $token, 'operation_id' => (string) Str::uuid()])->assertRedirect('/tiket/'.$token);
        $ticket = QueueTicket::firstOrFail();
        $this->assertNull($ticket->phone);
        $this->get('/tiket/'.$token)->assertOk()->assertSee('Made');
        $staff = $this->staff();
        $this->actingAs($staff)->post('/staf/panggil', ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame(TicketStatus::Called, $ticket->fresh()->status);
        $this->get('/staf')->assertOk()->assertSee('Konfirmasi hadir');
        $this->post('/staf/tiket/'.$ticket->id.'/arrive', ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $this->post('/staf/tiket/'.$ticket->id.'/complete', ['operation_id' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame(TicketStatus::Completed, $ticket->fresh()->status);
        $this->assertSame(4, $ticket->events()->count());
        $this->get('/tiket/'.$token)->assertSee('Selamat menikmati!');
    }

    public function test_call_retries_and_multiple_pending_calls_preserve_fifo(): void
    {
        [$first] = $this->issue();
        [$second] = $this->issue();
        $staff = $this->staff();
        $queue = app(QueueService::class);
        $op = (string) Str::uuid();
        $this->assertSame($first->id, $queue->callNext($op, $staff->id)->id);
        $this->assertSame($first->id, $queue->callNext($op, $staff->id)->id);
        $this->assertSame($second->id, $queue->callNext((string) Str::uuid(), $staff->id)->id);
        $this->assertSame(2, QueueTicket::where('status', 'called')->count());
    }

    public function test_empty_call_retry_does_not_call_later_registration(): void
    {
        $staff = $this->staff();
        $queue = app(QueueService::class);
        $op = (string) Str::uuid();
        $this->assertNull($queue->callNext($op, $staff->id));
        [$ticket] = $this->issue();
        $this->assertNull($queue->callNext($op, $staff->id));
        $this->assertSame(TicketStatus::Waiting, $ticket->fresh()->status);
    }

    public function test_registration_replay_requires_the_same_token(): void
    {
        [$ticket,$token,$op] = $this->issue();
        $queue = app(QueueService::class);
        $this->assertSame($ticket->id, $queue->issue(['customer_name' => 'Pelanggan', 'party_size' => 2], $token, $op)->id);
        $this->assertSame(1, QueueTicket::count());
        $this->expectException(ValidationException::class);
        $queue->issue(['customer_name' => 'Other', 'party_size' => 2], bin2hex(random_bytes(32)), $op);
    }

    public function test_changed_cancel_reason_is_rejected_on_replay(): void
    {
        [$ticket] = $this->issue();
        $staff = $this->staff();
        $op = (string) Str::uuid();
        $queue = app(QueueService::class);
        $queue->transition($ticket, 'cancel', $op, $staff->id, 'staff');
        $this->expectException(ValidationException::class);
        $queue->transition($ticket, 'cancel', $op, $staff->id, 'skipped');
    }

    public function test_waiting_position_excludes_called_and_cancelled_tickets(): void
    {
        [$a] = $this->issue();
        [$b] = $this->issue();
        [$c] = $this->issue();
        QueueSetting::find(1)->update(['estimate_interval_minutes' => 4]);
        $estimate = app(WaitEstimateService::class);
        $this->assertSame(8, $estimate->snapshot($c)['estimate_minutes']);
        $staff = $this->staff();
        $queue = app(QueueService::class);
        $queue->callNext((string) Str::uuid(), $staff->id);
        $queue->transition($b, 'cancel', (string) Str::uuid(), null);
        $this->assertSame(0, $estimate->snapshot($c)['ahead']);
        $this->assertSame(0, $estimate->snapshot($c)['estimate_minutes']);
        $this->assertNull($estimate->snapshot($a->fresh())['estimate_minutes']);
    }

    public function test_no_show_is_manual_and_eligible_at_the_deadline(): void
    {
        $this->freezeTime();
        [$ticket,$token] = $this->issue();
        $staff = $this->staff();
        $queue = app(QueueService::class);
        $ticket = $queue->callNext((string) Str::uuid(), $staff->id);
        try {
            $queue->transition($ticket, 'cancel', (string) Str::uuid(), $staff->id, 'no_show');
            $this->fail('Early cancellation accepted');
        } catch (ValidationException) {
        }
        $this->travel(15)->minutes();
        $this->get('/tiket/'.$token.'/status')->assertJsonPath('status', 'called');
        $queue->transition($ticket, 'cancel', (string) Str::uuid(), $staff->id, 'no_show');
        $this->assertSame(TicketStatus::Cancelled, $ticket->fresh()->status);
    }

    public function test_customer_cannot_cancel_arrived_ticket(): void
    {
        [$ticket,$token] = $this->issue();
        $staff = $this->staff();
        $queue = app(QueueService::class);
        $ticket = $queue->callNext((string) Str::uuid(), $staff->id);
        $queue->transition($ticket, 'arrive', (string) Str::uuid(), $staff->id);
        $this->post('/tiket/'.$token.'/batalkan', ['operation_id' => (string) Str::uuid()])->assertSessionHasErrors('action');
        $this->assertSame(TicketStatus::Arrived, $ticket->fresh()->status);
    }

    public function test_public_payload_does_not_leak_customer_details(): void
    {
        [$ticket,$token] = $this->issue('<script>alert(1)</script>');
        $ticket->update(['phone' => '08123456789']);
        $response = $this->get('/tiket/'.$token.'/status')->assertOk();
        $body = $response->getContent();
        $this->assertStringNotContainsString('customer_name', $body);
        $this->assertStringNotContainsString('08123456789', $body);
        $this->assertStringNotContainsString('access_token_hash', $body);
        $this->get('/tiket/'.$token)->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/tiket/'.str_repeat('a', 64))->assertNotFound();
        $this->get('/staf/status')->assertRedirect('/staf/masuk');
    }

    public function test_validation_and_staff_login_work(): void
    {
        $this->post('/antrean', ['customer_name' => '', 'party_size' => 0, 'operation_id' => (string) Str::uuid(), 'access_token' => str_repeat('a', 64)])->assertSessionHasErrors(['customer_name', 'party_size']);
        $this->assertArrayNotHasKey('access_token', session()->getOldInput());
        $staff = $this->staff();
        $this->post('/staf/masuk', ['email' => $staff->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/staf/masuk', ['email' => $staff->email, 'password' => 'password'])->assertRedirect('/staf');
        $this->assertAuthenticatedAs($staff);
        $this->post('/staf/keluar')->assertRedirect('/staf/masuk');
        $this->assertGuest();
    }

    public function test_numbering_does_not_reset_at_midnight_and_default_eta_is_unknown(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(15)->addMinutes(59));
        [$a] = $this->issue();
        $this->travel(2)->minutes();
        [$b] = $this->issue();
        $this->assertSame($a->number + 1, $b->number);
        $this->assertSame(TicketStatus::Waiting, $a->fresh()->status);
        $this->assertNull(app(WaitEstimateService::class)->snapshot($b)['estimate_minutes']);
    }

    public function test_illegal_staff_transition_and_foreign_operation_are_rejected(): void
    {
        [$a] = $this->issue();
        $staff = $this->staff();
        $other = $this->staff();
        $queue = app(QueueService::class);
        $op = (string) Str::uuid();
        $this->actingAs($staff)->post('/staf/tiket/'.$a->id.'/complete', ['operation_id' => (string) Str::uuid()])->assertSessionHasErrors('action');
        $queue->callNext($op, $staff->id);
        $this->expectException(ValidationException::class);
        $queue->callNext($op, $other->id);
    }

    public function test_status_polling_does_not_exhaust_staff_login_limit(): void
    {
        [$ticket,$token] = $this->issue();
        for ($i = 0; $i < 6; $i++) {
            $this->get('/tiket/'.$token.'/status')->assertOk();
        }
        $staff = $this->staff();
        $this->post('/staf/masuk', ['email' => $staff->email, 'password' => 'password'])->assertRedirect('/staf');
    }
}
