<input type="hidden" name="operation_id" value="{{ old('operation_id',$operation) }}">
<input type="hidden" name="access_token" value="{{ $token }}">
<label for="customer_name">Nama pelanggan</label>
<input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" autocomplete="name" maxlength="120" required placeholder="Nama Anda">
<label for="phone">Nomor telepon <span class="optional">(opsional)</span>
</label>
<input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" maxlength="40" placeholder="08xx xxxx xxxx">
<small class="field-help">Tidak diperlukan untuk mengambil nomor. Tidak ada SMS otomatis.</small>
<label for="party_size">Jumlah orang</label>
<div class="stepper">
<button type="button" data-step="-1" aria-label="Kurangi jumlah orang">−</button>
<input id="party_size" name="party_size" type="number" min="1" max="65535" value="{{ old('party_size',2) }}" required aria-label="Jumlah orang">
<button type="button" data-step="1" aria-label="Tambah jumlah orang">+</button>
</div>
