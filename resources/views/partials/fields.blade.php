<input type="hidden" name="operation_id" value="{{ old('operation_id', $operation) }}">
<input type="hidden" name="access_token" value="{{ $token }}">

<label for="customer_name">Nama pelanggan</label>
<input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" autocomplete="name" maxlength="120" required placeholder="Nama Anda" @error('customer_name') aria-invalid="true" aria-describedby="customer-name-error" @enderror>
@error('customer_name')
    <p class="field-error" id="customer-name-error">{{ $message }}</p>
@enderror

<label for="phone">Nomor telepon <span class="optional">(opsional)</span></label>
<input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" maxlength="40" placeholder="08xx xxxx xxxx" aria-describedby="phone-help{{ $errors->has('phone') ? ' phone-error' : '' }}" @error('phone') aria-invalid="true" @enderror>
<small class="field-help" id="phone-help">Tidak diperlukan untuk mengambil nomor. Tidak ada SMS otomatis.</small>
@error('phone')
    <p class="field-error" id="phone-error">{{ $message }}</p>
@enderror

<label for="party_size">Jumlah orang</label>
<div class="stepper">
    <button type="button" data-step="-1" aria-label="Kurangi jumlah orang" aria-controls="party_size">−</button>
    <input id="party_size" name="party_size" type="number" inputmode="numeric" min="1" max="65535" value="{{ old('party_size', 2) }}" required aria-describedby="party-size-help{{ $errors->has('party_size') ? ' party-size-error' : '' }}" @error('party_size') aria-invalid="true" @enderror>
    <button type="button" data-step="1" aria-label="Tambah jumlah orang" aria-controls="party_size">+</button>
</div>
<small class="field-help" id="party-size-help">Hitung seluruh anggota rombongan, termasuk Anda.</small>
@error('party_size')
    <p class="field-error" id="party-size-error">{{ $message }}</p>
@enderror
