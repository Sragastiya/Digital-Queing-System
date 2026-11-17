@php($selectedType = old('queue_type', $selectedQueueType ?? ''))
<fieldset class="service-selector" @error('queue_type') aria-describedby="queue-type-error" @enderror>
    <legend>Jenis layanan</legend>
    <div class="service-options">
        @foreach($queueTypes as $type)
            <label class="service-option">
                <input type="radio" name="queue_type" value="{{ $type->value }}" required @checked($selectedType === $type->value) @error('queue_type') aria-invalid="true" @enderror>
                <span class="service-card">
                    <strong>{{ $type->label() }}</strong>
                    <small>{{ $type === \App\Enums\QueueType::DineIn ? 'Layanan di restoran' : 'Layanan bawa pulang' }}</small>
                </span>
            </label>
        @endforeach
    </div>
    @error('queue_type')<p class="field-error" id="queue-type-error">{{ $message }}</p>@enderror
</fieldset>
