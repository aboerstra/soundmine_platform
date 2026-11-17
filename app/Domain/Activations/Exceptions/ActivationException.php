<?php

namespace App\Domain\Activations\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class ActivationException extends RuntimeException
{
    public function __construct(public string $errorCode, string $message, public int $status)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['error' => ['code' => $this->errorCode, 'message' => $this->getMessage()]], $this->status)
            ->header('Cache-Control', 'no-store, private');
    }
}
