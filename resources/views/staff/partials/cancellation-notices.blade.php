@if($calledCancellations->isEmpty())
    <p class="muted">Belum ada pembatalan setelah pelanggan dipanggil.</p>
@else
    <ul class="cancellation-notices">
        @foreach($calledCancellations as $event)
            <li>
                <div>
                    <strong>{{ $event->ticket->displayNumber() }}</strong> · {{ $event->queue_type->label() }}
                    <p>Pelanggan membatalkan setelah dipanggil.</p>
                    @if($event->relatedTicket)
                        <p><strong>{{ $event->relatedTicket->displayNumber() }}</strong> dipanggil otomatis.</p>
                    @endif
                </div>
                <time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->timezone('Asia/Makassar')->format('d/m H.i') }} WITA</time>
            </li>
        @endforeach
    </ul>
@endif
