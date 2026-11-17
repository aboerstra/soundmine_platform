<?php

namespace App\Http\Requests;

use App\Domain\Activations\Exceptions\ActivationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ExchangeActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->isJson()) {
            throw new ActivationException('unsupported_media_type', 'Use an application/json request body.', 415);
        }
    }

    public function validationData(): array
    {
        return array_merge($this->json()->all(), ['request_id' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        return [
            'authorization_code' => ['required', 'string', 'regex:/^sm_auth_[A-Za-z0-9]{64}$/'],
            'request_id' => ['required', 'uuid'],
            'machine_hash' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'platform' => ['required', 'in:macos,windows'],
            'plugin_version' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => ['code' => 'validation_failed', 'message' => 'The activation request is invalid.'],
            'errors' => $validator->errors(),
        ], 422)->header('Cache-Control', 'no-store, private'));
    }
}
