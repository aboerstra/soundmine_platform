<?php

namespace App\Domain\Customers\Data;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User;

final readonly class GoogleProfile
{
    private function __construct(public string $subject, public string $email) {}

    public static function fromUser(User $user): self
    {
        $email = $user->getEmail();
        $validator = Validator::make([
            'subject' => $user->getId(),
            'email' => is_string($email) ? Str::lower(trim($email)) : null,
            'verified' => ($user->getRaw()['email_verified'] ?? null) === true,
        ], [
            'subject' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'verified' => ['accepted'],
        ]);
        if ($validator->fails()) {
            throw ValidationException::withMessages(['google' => 'Google must provide a valid identity and a verified email address.']);
        }
        $data = $validator->validated();

        return new self($data['subject'], $data['email']);
    }
}
