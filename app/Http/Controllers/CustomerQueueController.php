<?php

namespace App\Http\Controllers;

use App\Enums\CustomerCancellationReason;
use App\Enums\QueueType;
use App\Enums\TicketStatus;
use App\Http\Requests\JoinQueueRequest;
use App\Models\QueueTicket;
use App\Services\QueueService;
use App\Services\WaitEstimateService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerQueueController extends Controller
{
    public function index(WaitEstimateService $estimate)
    {
        $snapshots = [];
        foreach (QueueType::cases() as $queueType) {
            $snapshots[$queueType->value] = $estimate->snapshot($queueType);
        }

        return view('customer.join', ['snapshots' => $snapshots, 'queueTypes' => QueueType::cases(), 'operation' => (string) Str::uuid(), 'token' => bin2hex(random_bytes(32))]);
    }

    public function store(JoinQueueRequest $request, QueueService $queue)
    {
        $data = $request->validated();
        $queue->issue($data, $data['access_token'], $data['operation_id']);

        return redirect()->route('ticket.show', ['token' => $data['access_token']]);
    }

    private function find(string $token): QueueTicket
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token), 404);

        return QueueTicket::where('access_token_hash', hash('sha256', $token))->firstOrFail();
    }

    public function show(string $token, WaitEstimateService $estimate, QueueService $queue)
    {
        $ticket = $this->find($token);

        $replacement = null;
        $transfer = $ticket->events()->where('action', 'switch_service')->with('relatedTicket')->first();
        if ($transfer?->relatedTicket) {
            $replacementToken = $queue->transferToken($token, $transfer->operation_id);
            if (hash_equals($transfer->relatedTicket->access_token_hash, hash('sha256', $replacementToken))) {
                $replacement = ['ticket' => $transfer->relatedTicket, 'url' => route('ticket.show', ['token' => $replacementToken])];
            }
        }

        return response()->view('customer.ticket', $this->optionData($ticket, $token) + ['snapshot' => $estimate->snapshot($ticket->queue_type, $ticket), 'replacement' => $replacement])
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    private function optionData(QueueTicket $ticket, string $token): array
    {
        return ['ticket' => $ticket, 'token' => $token, 'customerReasons' => CustomerCancellationReason::cases(),
            'destination' => $ticket->queue_type === QueueType::DineIn ? QueueType::Takeaway : QueueType::DineIn,
            'operations' => ['cancel' => (string) Str::uuid(), 'switch' => (string) Str::uuid(), 'give' => (string) Str::uuid()]];
    }

    public function options(string $token)
    {
        $ticket = $this->find($token);
        if (! in_array($ticket->status, [TicketStatus::Waiting, TicketStatus::Called], true)) {
            return redirect()->route('ticket.show', ['token' => $token])->header('Cache-Control', 'no-store, private');
        }

        return response()->view('customer.options', $this->optionData($ticket, $token))
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function status(string $token, WaitEstimateService $estimate)
    {
        $ticket = $this->find($token);

        return response()->json(['queue_type' => $ticket->queue_type->value, 'queue_label' => $ticket->queue_type->label(), 'status' => $ticket->status->value, 'label' => $ticket->status->label(), 'deadline' => $ticket->arrival_deadline?->toIso8601String(), 'snapshot' => $estimate->snapshot($ticket->queue_type, $ticket)])->header('Cache-Control', 'no-store, private');
    }

    public function cancel(Request $request, string $token, QueueService $queue)
    {
        $request->validate(['operation_id' => ['required', 'uuid'], 'reason' => ['required', Rule::enum(CustomerCancellationReason::class)]], [
            'reason.required' => 'Pilih alasan pembatalan.', 'reason.enum' => 'Pilih alasan pembatalan yang tersedia.',
        ]);
        $queue->transition($this->find($token), 'cancel', $request->string('operation_id')->toString(), null, $request->string('reason')->toString());

        return redirect()->route('ticket.show', ['token' => $token])->with('notice', 'Antrean dibatalkan. Untuk kembali, ambil nomor baru.');
    }

    public function giveTurn(Request $request, string $token, QueueService $queue)
    {
        $request->validate(['operation_id' => ['required', 'uuid']]);
        $queue->giveTurn($this->find($token), $request->string('operation_id')->toString());

        return redirect()->route('ticket.show', ['token' => $token])->with('notice', 'Posisi diperbarui setelah memberi giliran. Nomor tiket Anda tetap sama.');
    }

    public function switchService(Request $request, string $token, QueueService $queue)
    {
        $data = $request->validate(['operation_id' => ['required', 'uuid'], 'queue_type' => ['required', Rule::enum(QueueType::class)]], [
            'queue_type.required' => 'Pilih layanan tujuan.', 'queue_type.enum' => 'Pilih layanan yang tersedia.',
        ]);
        $result = $queue->switchService($this->find($token), $token, QueueType::from($data['queue_type']), $data['operation_id']);

        return redirect()->route('ticket.show', ['token' => $result['token']])
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer')
            ->with('notice', 'Berhasil pindah ke '.$result['ticket']->queue_type->label().'. Gunakan nomor baru dan simpan tautan tiket ini.');
    }
}
