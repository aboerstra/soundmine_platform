<?php

namespace App\Domain\Licensing\Enums;

enum LicenseStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
}
