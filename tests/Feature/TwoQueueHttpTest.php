<?php

namespace Tests\Feature;

use App\Enums\QueueType;
use App\Enums\TicketStatus;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TwoQueueHttpTest extends TestCase
{
    use RefreshDatabase;

    private function issue(QueueType $type, string $name): array
    {
        $token = bin2hex(random_bytes(32));
        $ticket = app(QueueService::class)->issue(['queue_type' => $type->value, 'customer_name' => $name, 'party_size' => 2], $token, (string) Str::uuid());

        return [$ticket, $token];
    }

    public function test_registration_requires_a_real_queue_and_private_status_uses_its_persisted_type(): void
    {
        $this->get('/')->assertOk()->assertSee('Makan di Tempat')->assertSee('Bawa Pulang');
        $data = ['customer_name' => 'Pelanggan HTTP', 'party_size' => 2, 'phone' => null, 'operation_id' => (string) Str::uuid(), 'access_token' => bin2hex(random_bytes(32))];
        $this->post('/antrean', $data)->assertSessionHasErrors('queue_type');
        $this->post('/antrean', array_merge($data, ['queue_type' => 'other']))->assertSessionHasErrors('queue_type');
        $this->assertSame(0, QueueTicket::count());

        foreach (QueueType::cases() as $type) {
            $data['operation_id'] = (string) Str::uuid();
            $data['access_token'] = bin2hex(random_bytes(32));
            $this->post('/antrean', array_merge($data, ['queue_type' => $type->value]))->assertRedirect('/tiket/'.$data['access_token']);
            $ticket = QueueTicket::where('access_token_hash', hash('sha256', $data['access_token']))->firstOrFail();
            $this->assertSame($type, $ticket->queue_type);
            $this->assertNull($ticket->phone);
            $this->get('/tiket/'.$data['access_token'].'/status?queue_type=other')->assertOk()
                ->assertJsonPath('queue_type', $type->value)->assertJsonPath('queue_label', $type->label())
                ->assertJsonPath('snapshot.waiting_count', 1)->assertJsonPath('snapshot.ahead', 0)
                ->assertJsonMissingPath('customer_name')->assertJsonMissingPath('phone');
        }
    }

    public function test_staff_calls_and_completes_takeaway_while_older_dine_in_waits(): void
    {
        [$dineIn] = $this->issue(QueueType::DineIn, 'Hanya Dine In');
        [$takeaway, $token] = $this->issue(QueueType::Takeaway, 'Hanya Takeaway');
        $this->get('/staf?queue_type=takeaway')->assertRedirect('/staf/masuk');
        $this->post('/staf/panggil', ['queue_type' => 'takeaway', 'operation_id' => (string) Str::uuid()])->assertRedirect('/staf/masuk');
        $this->actingAs(User::factory()->create());
        $this->post('/staf/panggil', ['operation_id' => (string) Str::uuid()])->assertSessionHasErrors('queue_type');
        $this->assertSame(TicketStatus::Waiting, $takeaway->fresh()->status);

        $this->post('/staf/panggil', ['queue_type' => 'takeaway', 'operation_id' => (string) Str::uuid()])->assertRedirect(route('staff.index', ['queue_type' => 'takeaway']));
        $this->assertSame(TicketStatus::Waiting, $dineIn->fresh()->status);
        $this->assertSame(TicketStatus::Called, $takeaway->fresh()->status);
        $this->get('/staf?queue_type=takeaway')->assertOk()->assertSee('Hanya Takeaway')->assertDontSee('Hanya Dine In');
        $this->get('/staf?queue_type=dine_in')->assertOk()->assertSee('Hanya Dine In')->assertDontSee('Hanya Takeaway');
        $this->get('/tiket/'.$token.'/status')->assertJsonPath('snapshot.latest_called', $takeaway->displayNumber());

        $this->post('/staf/tiket/'.$takeaway->id.'/arrive', ['queue_type' => 'dine_in', 'operation_id' => (string) Str::uuid()])->assertSessionHasErrors('queue_type');
        $this->assertSame(TicketStatus::Called, $takeaway->fresh()->status);
        foreach (['arrive', 'complete'] as $action) {
            $this->post('/staf/tiket/'.$takeaway->id.'/'.$action, ['queue_type' => 'takeaway', 'operation_id' => (string) Str::uuid()])->assertRedirect(route('staff.index', ['queue_type' => 'takeaway']));
        }
        $this->assertSame(TicketStatus::Completed, $takeaway->fresh()->status);
        $this->assertSame(TicketStatus::Waiting, $dineIn->fresh()->status);
        $this->get('/tiket/'.$token)->assertOk()->assertSee('Layanan selesai')->assertDontSee('diarahkan ke meja');
    }

    public function test_positions_and_cancel_or_skip_actions_do_not_change_the_other_queue(): void
    {
        [$dineFirst, $dineToken] = $this->issue(QueueType::DineIn, 'Dine pertama');
        [$takeFirst, $takeToken] = $this->issue(QueueType::Takeaway, 'Take pertama');
        [$dineNext, $dineNextToken] = $this->issue(QueueType::DineIn, 'Dine kedua');
        [$takeNext, $takeNextToken] = $this->issue(QueueType::Takeaway, 'Take kedua');
        $this->get('/tiket/'.$dineNextToken.'/status')->assertJsonPath('snapshot.ahead', 1)->assertJsonPath('snapshot.waiting_count', 2);
        $this->get('/tiket/'.$takeNextToken.'/status')->assertJsonPath('snapshot.ahead', 1)->assertJsonPath('snapshot.waiting_count', 2);
        $this->post('/tiket/'.$takeToken.'/batalkan', ['queue_type' => 'dine_in', 'operation_id' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame(TicketStatus::Cancelled, $takeFirst->fresh()->status);
        $this->get('/tiket/'.$dineNextToken.'/status')->assertJsonPath('snapshot.ahead', 1)->assertJsonPath('snapshot.waiting_count', 2);
        $this->get('/tiket/'.$takeNextToken.'/status')->assertJsonPath('snapshot.ahead', 0)->assertJsonPath('snapshot.waiting_count', 1);

        $this->actingAs(User::factory()->create())->post('/staf/tiket/'.$dineFirst->id.'/cancel', ['queue_type' => 'dine_in', 'reason' => 'skipped', 'operation_id' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame('skipped', $dineFirst->fresh()->cancellation_reason);
        $this->assertSame(TicketStatus::Waiting, $takeNext->fresh()->status);
        $this->get('/tiket/'.$takeNextToken.'/status')->assertJsonPath('snapshot.waiting_count', 1);
        $this->get('/tiket/'.$dineNextToken.'/status')->assertJsonPath('snapshot.ahead', 0);
    }

    public function test_staff_search_poll_and_pagination_remain_inside_the_selected_queue(): void
    {
        $this->issue(QueueType::DineIn, 'Other queue secret name');
        for ($i = 0; $i < 16; $i++) {
            $this->issue(QueueType::Takeaway, 'Take filter '.$i);
        }
        $this->actingAs(User::factory()->create());
        $response = $this->get('/staf/status?queue_type=takeaway&q=Take&status=waiting')->assertOk()->assertJsonPath('queue_type', 'takeaway');
        $html = $response->json('html');
        $this->assertStringNotContainsString('Other queue secret name', $html);
        $this->assertStringContainsString(e(route('staff.index', ['queue_type' => 'takeaway', 'q' => 'Take', 'status' => 'waiting', 'page' => 2])), $html);
        $this->get('/staf?queue_type=takeaway&q=A-001')->assertOk()->assertDontSee('Other queue secret name')->assertSee('Tidak ada antrean yang cocok');
        $this->get('/staf?queue_type=takeaway&page=2')->assertOk()->assertSee('16–16 dari 16 antrean');
        $this->get('/staf/tambah?queue_type=takeaway')->assertOk()->assertViewHas('selectedQueueType', 'takeaway');
    }

    public function test_staff_registration_returns_to_the_actual_queue_and_empty_calls_stay_there(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/staf/tambah', ['queue_type' => 'takeaway', 'customer_name' => 'Staf tambah take', 'party_size' => 3, 'operation_id' => (string) Str::uuid(), 'access_token' => bin2hex(random_bytes(32))])->assertRedirect(route('staff.index', ['queue_type' => 'takeaway']));
        $this->assertDatabaseHas('queue_tickets', ['queue_type' => 'takeaway', 'customer_name' => 'Staf tambah take']);
        $this->post('/staf/panggil', ['queue_type' => 'dine_in', 'operation_id' => (string) Str::uuid()])->assertRedirect(route('staff.index', ['queue_type' => 'dine_in']));
        $this->assertSame(TicketStatus::Waiting, QueueTicket::firstOrFail()->status);
    }
}
