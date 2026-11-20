<?php

namespace App\Enums;

enum JobRequestStatus: string
{
    case Open = 'open';
    case Quoted = 'quoted';
    case Booked = 'booked';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
