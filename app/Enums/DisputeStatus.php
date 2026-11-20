<?php

namespace App\Enums;

enum DisputeStatus: string
{
    case Open = 'open';
    case ProviderResponded = 'provider_responded';
    case FixRequested = 'fix_requested';
    case Resolved = 'resolved';
}
