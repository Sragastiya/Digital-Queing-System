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
}
