<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Blocked = 'blocked';
    case Queued = 'queued';
    case Importing = 'importing';
    case Completed = 'completed';
    case Failed = 'failed';
}
