<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoinQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 65535 is the storage limit, not a restaurant party-size policy.
        return ['customer_name' => ['required', 'string', 'max:120'], 'party_size' => ['required', 'integer', 'min:1', 'max:65535'],
            'phone' => ['nullable', 'string', 'max:40'], 'operation_id' => ['required', 'uuid'], 'access_token' => ['required', 'regex:/^[a-f0-9]{64}$/']];
    }

    public function attributes(): array
    {
        return ['customer_name' => 'nama pelanggan', 'party_size' => 'jumlah orang', 'phone' => 'nomor telepon'];
    }

    public function messages(): array
    {
        return [
            'customer_name.required' => 'Nama pelanggan wajib diisi.',
            'customer_name.string' => 'Nama pelanggan harus berupa teks.',
            'customer_name.max' => 'Nama pelanggan maksimal :max karakter.',
            'party_size.required' => 'Jumlah orang wajib diisi.',
            'party_size.integer' => 'Jumlah orang harus berupa bilangan bulat.',
            'party_size.min' => 'Jumlah orang minimal :min.',
            'party_size.max' => 'Jumlah orang tidak boleh melebihi :max.',
            'phone.string' => 'Nomor telepon harus berupa teks.',
            'phone.max' => 'Nomor telepon maksimal :max karakter.',
            'operation_id.required' => 'Formulir tidak berlaku. Muat ulang halaman, lalu coba lagi.',
            'operation_id.uuid' => 'Formulir tidak berlaku. Muat ulang halaman, lalu coba lagi.',
            'access_token.required' => 'Formulir tidak berlaku. Muat ulang halaman, lalu coba lagi.',
            'access_token.regex' => 'Formulir tidak berlaku. Muat ulang halaman, lalu coba lagi.',
        ];
    }
}
