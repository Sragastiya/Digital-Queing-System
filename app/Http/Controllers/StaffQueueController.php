<?php

namespace App\Http\Controllers;

use App\Enums\QueueType;
use App\Http\Requests\JoinQueueRequest;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use App\Services\QueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffQueueController extends Controller
{
    private function data(Request $request): array
    {
        $filter = $request->validate(['queue_type' => ['nullable', Rule::enum(QueueType::class)], 'q' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', 'in:waiting,called,arrived,completed,cancelled']], [
            'queue_type.enum' => 'Pilih jenis layanan yang tersedia.',
            'q.string' => 'Pencarian harus berupa teks.',
            'q.max' => 'Pencarian maksimal :max karakter.',
            'status.in' => 'Pilih status antrean yang tersedia.',
        ]);
        $queueType = QueueType::from($filter['queue_type'] ?? QueueType::DineIn->value);
        $queueQuery = QueueTicket::where('queue_type', $queueType);
        $query = clone $queueQuery;
        if ($q = $filter['q'] ?? null) {
            $query->where(fn ($builder) => $builder->where('customer_name', 'like', '%'.$q.'%')->orWhere('number', ltrim(str_ireplace('A-', '', $q), '0')));
        }
        if ($status = $filter['status'] ?? null) {
            $query->where('status', $status);
        }
        $counts = (clone $queueQuery)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $queueCounts = QueueTicket::where('status', 'waiting')->selectRaw('queue_type, COUNT(*) AS total')->groupBy('queue_type')->pluck('total', 'queue_type');

        return ['tickets' => $query->orderBy('number')->paginate(15)->withQueryString(),
            'pending' => (clone $queueQuery)->whereIn('status', ['called', 'arrived'])->orderBy('number')->get(),
            'counts' => $counts, 'queueCounts' => $queueCounts, 'queueType' => $queueType, 'queueTypes' => QueueType::cases(),
            'settings' => QueueSetting::findOrFail(1), 'operation' => (string) Str::uuid()];
    }

    public function index(Request $request)
    {
        return response()->view('staff.dashboard', $this->data($request))->header('Cache-Control', 'no-store, private');
    }

    public function poll(Request $request)
    {
        $data = $this->data($request);

        return response()->json(['queue_type' => $data['queueType']->value, 'html' => view('staff.partials.board', $data)->render()])->header('Cache-Control', 'no-store, private');
    }

    public function create(Request $request)
    {
        $data = $request->validate(['queue_type' => ['nullable', Rule::enum(QueueType::class)]]);

        return view('staff.add', ['queueTypes' => QueueType::cases(), 'selectedQueueType' => $data['queue_type'] ?? QueueType::DineIn->value, 'operation' => (string) Str::uuid(), 'token' => bin2hex(random_bytes(32))]);
    }

    public function store(JoinQueueRequest $request, QueueService $queue)
    {
        $data = $request->validated();
        $ticket = $queue->issue($data, $data['access_token'], $data['operation_id'], $request->user()->id);

        return redirect()->route('staff.index', ['queue_type' => $ticket->queue_type->value])->with('notice', $ticket->displayNumber().' ditambahkan ke antrean '.$ticket->queue_type->label().'.');
    }

    public function call(Request $request, QueueService $queue)
    {
        $data = $request->validate(['operation_id' => ['required', 'uuid'], 'queue_type' => ['required', Rule::enum(QueueType::class)]], [
            'queue_type.required' => 'Pilih jenis layanan sebelum memanggil pelanggan.',
            'queue_type.enum' => 'Pilih jenis layanan yang tersedia.',
        ]);
        $queueType = QueueType::from($data['queue_type']);
        $ticket = $queue->callNext($data['operation_id'], $request->user()->id, $queueType);

        return redirect()->route('staff.index', ['queue_type' => $queueType->value])->with('notice', $ticket ? $ticket->displayNumber().' dipanggil di antrean '.$queueType->label().'.' : 'Tidak ada pelanggan yang menunggu di antrean '.$queueType->label().'.');
    }

    public function transition(Request $request, QueueTicket $ticket, string $action, QueueService $queue)
    {
        $data = $request->validate(['operation_id' => ['required', 'uuid'], 'queue_type' => ['required', Rule::enum(QueueType::class)], 'reason' => ['required_if:action,cancel', 'nullable', 'in:staff,skipped,no_show']]);
        if ($data['queue_type'] !== $ticket->queue_type->value) {
            throw ValidationException::withMessages(['queue_type' => 'Tiket tidak termasuk antrean yang dipilih. Periksa jenis layanan tiket.']);
        }
        if ($action === 'cancel') {
            $request->validate(['reason' => ['required', 'in:staff,skipped,no_show']]);
        }
        $queue->transition($ticket, $action, $data['operation_id'], $request->user()->id, $data['reason'] ?? null);

        return redirect()->route('staff.index', ['queue_type' => $ticket->queue_type->value])->with('notice', 'Status '.$ticket->displayNumber().' diperbarui.');
    }

    public function settings(Request $request)
    {
        $data = $request->validate(['queue_type' => ['nullable', Rule::enum(QueueType::class)], 'estimate_interval_minutes' => ['nullable', 'integer', 'min:1', 'max:1440']], [
            'estimate_interval_minutes.integer' => 'Interval panggilan harus berupa bilangan bulat.',
            'estimate_interval_minutes.min' => 'Interval panggilan minimal :min menit.',
            'estimate_interval_minutes.max' => 'Interval panggilan maksimal :max menit.',
        ]);
        QueueSetting::findOrFail(1)->update(['estimate_interval_minutes' => $data['estimate_interval_minutes'] ?? null]);

        return redirect()->route('staff.index', ['queue_type' => $data['queue_type'] ?? QueueType::DineIn->value])->with('notice', 'Perkiraan tunggu diperbarui untuk kedua antrean. Nilai ini masih perlu validasi operasional.');
    }
}
