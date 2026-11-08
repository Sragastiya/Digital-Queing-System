<?php

namespace App\Http\Controllers;

use App\Http\Requests\JoinQueueRequest;
use App\Models\QueueTicket;
use App\Services\QueueService;
use App\Services\WaitEstimateService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerQueueController extends Controller
{
    public function index(WaitEstimateService $estimate)
    {
        return view('customer.join', ['snapshot' => $estimate->snapshot(), 'operation' => (string) Str::uuid(), 'token' => bin2hex(random_bytes(32))]);
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

    public function show(string $token, WaitEstimateService $estimate)
    {
        $ticket = $this->find($token);

        return response()->view('customer.ticket', ['ticket' => $ticket, 'snapshot' => $estimate->snapshot($ticket), 'token' => $token, 'operation' => (string) Str::uuid()])->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function status(string $token, WaitEstimateService $estimate)
    {
        $ticket = $this->find($token);

        return response()->json(['status' => $ticket->status->value, 'label' => $ticket->status->label(), 'deadline' => $ticket->arrival_deadline?->toIso8601String(), 'snapshot' => $estimate->snapshot($ticket)])->header('Cache-Control', 'no-store, private');
    }

    public function cancel(Request $request, string $token, QueueService $queue)
    {
        $request->validate(['operation_id' => ['required', 'uuid']]);
        $queue->transition($this->find($token), 'cancel', $request->string('operation_id')->toString(), null);

        return redirect()->route('ticket.show', ['token' => $token])->with('notice', 'Antrean dibatalkan. Untuk kembali, ambil nomor baru.');
    }
}
