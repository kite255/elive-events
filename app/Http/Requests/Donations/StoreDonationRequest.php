<?php

namespace App\Http\Requests\Donations;

use Illuminate\Foundation\Http\FormRequest;

class StoreDonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'donor_name' => ['nullable', 'string', 'max:255'],
            'donor_phone' => ['nullable', 'string', 'max:30'],
            'donor_email' => ['nullable', 'email', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'is_anonymous' => ['nullable', 'boolean'],
            'public_display_consent' => ['nullable', 'boolean'],
        ];
    }
}
