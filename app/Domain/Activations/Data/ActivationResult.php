<?php

namespace App\Domain\Activations\Data;

use App\Domain\Activations\Models\Activation;
use Carbon\CarbonInterface;
use SensitiveParameter;

readonly class ActivationResult
{
    public function __construct(
        public Activation $activation,
        #[SensitiveParameter] public string $plainTextToken,
        public CarbonInterface $expiresAt,
        public bool $created,
    ) {}
}
