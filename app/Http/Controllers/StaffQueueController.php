<?php

namespace App\Http\Controllers;

use App\Http\Requests\JoinQueueRequest;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Services\QueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StaffQueueController extends Controller
{
    private function data(Request $request): array
    {
        $filter = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', 'in:waiting,called,arrived,completed,cancelled']]);
        $query = QueueTicket::query();
        if ($q = $filter['q'] ?? null) {
            $query->where(fn ($builder) => $builder->where('customer_name', 'like', '%'.$q.'%')->orWhere('number', ltrim(str_ireplace('A-', '', $q), '0')));
        }
        if ($status = $filter['status'] ?? null) {
            $query->where('status', $status);
        }
        $counts = QueueTicket::selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        return ['tickets' => $query->orderBy('number')->paginate(15)->withQueryString(),
            'pending' => QueueTicket::whereIn('status', ['called', 'arrived'])->orderBy('number')->get(),
            'counts' => $counts, 'settings' => QueueSetting::findOrFail(1), 'operation' => (string) Str::uuid()];
    }

    public function index(Request $request)
    {
        return response()->view('staff.dashboard', $this->data($request))->header('Cache-Control', 'no-store, private');
    }

    public function poll(Request $request)
    {
        return response()->json(['html' => view('staff.partials.board', $this->data($request))->render()])->header('Cache-Control', 'no-store, private');
    }

    public function create()
    {
        return view('staff.add', ['operation' => (string) Str::uuid(), 'token' => bin2hex(random_bytes(32))]);
    }

    public function store(JoinQueueRequest $request, QueueService $queue)
    {
        $data = $request->validated();
        $ticket = $queue->issue($data, $data['access_token'], $data['operation_id'], $request->user()->id);

        return redirect()->route('staff.index')->with('notice', $ticket->displayNumber().' ditambahkan.');
    }

    public function call(Request $request, QueueService $queue)
    {
        $data = $request->validate(['operation_id' => ['required', 'uuid']]);
        $ticket = $queue->callNext($data['operation_id'], $request->user()->id);

        return redirect()->route('staff.index')->with('notice', $ticket ? $ticket->displayNumber().' dipanggil.' : 'Tidak ada pelanggan yang menunggu.');
    }

    public function transition(Request $request, QueueTicket $ticket, string $action, QueueService $queue)
    {
        $data = $request->validate(['operation_id' => ['required', 'uuid'], 'reason' => ['required_if:action,cancel', 'nullable', 'in:staff,skipped,no_show']]);
        if ($action === 'cancel') {
            $request->validate(['reason' => ['required', 'in:staff,skipped,no_show']]);
        }
        $queue->transition($ticket, $action, $data['operation_id'], $request->user()->id, $data['reason'] ?? null);

        return redirect()->route('staff.index')->with('notice', 'Status '.$ticket->displayNumber().' diperbarui.');
    }

    public function settings(Request $request)
    {
        $data = $request->validate(['estimate_interval_minutes' => ['nullable', 'integer', 'min:1', 'max:1440']]);
        QueueSetting::findOrFail(1)->update($data);

        return redirect()->route('staff.index')->with('notice', 'Perkiraan tunggu diperbarui. Nilai ini masih perlu validasi operasional.');
    }
}
