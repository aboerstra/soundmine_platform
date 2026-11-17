<?php

namespace App\Domain\Activations\Enums;

enum ActivationStatus: string
{
    case Active = 'active';
    case Deactivated = 'deactivated';
    case Revoked = 'revoked';
}
