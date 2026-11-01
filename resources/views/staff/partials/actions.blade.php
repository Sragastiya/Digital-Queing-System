<div class="ticket-actions">@if($ticket->status===\App\Enums\TicketStatus::Called || $ticket->status===\App\Enums\TicketStatus::Arrived)
@php($next=$ticket->status===\App\Enums\TicketStatus::Called?'arrive':'complete')<form method="post" action="{{ route('staff.transition',[$ticket,$next]) }}">@csrf<input type="hidden" name="operation_id" value="{{ \Illuminate\Support\Str::uuid() }}">
<button class="{{ $compact?'button':'text-button' }}">{{ $next==='arrive'?'Konfirmasi hadir':'Tandai selesai' }}</button>
</form>
@endif

@if(!in_array($ticket->status,[\App\Enums\TicketStatus::Completed,\App\Enums\TicketStatus::Cancelled],true))<form method="post" action="{{ route('staff.transition',[$ticket,'cancel']) }}" data-confirm="Batalkan {{ $ticket->displayNumber() }}?">@csrf<input type="hidden" name="operation_id" value="{{ \Illuminate\Support\Str::uuid() }}">
<label class="sr-only" for="reason-{{ $compact?'pending':'table' }}-{{ $ticket->id }}">Alasan pembatalan {{ $ticket->displayNumber() }}</label>
<select name="reason" id="reason-{{ $compact?'pending':'table' }}-{{ $ticket->id }}">
<option value="staff">Dibatalkan staf</option>@if($ticket->status!==\App\Enums\TicketStatus::Arrived)<option value="skipped">Dilewati, keluar antrean</option>
@endif
 @if($ticket->status===\App\Enums\TicketStatus::Called && $ticket->arrival_deadline?->isPast())<option value="no_show">Tidak hadir melewati batas</option>
@endif
</select>
<button class="text-button danger-text">Batalkan</button>
</form>
@else
<span class="muted">—</span>
@endif
</div>
