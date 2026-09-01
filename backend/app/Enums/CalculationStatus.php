<?php

namespace App\Enums;

enum CalculationStatus: string
{
    case Queued = 'queued';
    case Calculating = 'calculating';
    case Completed = 'completed';
    case Failed = 'failed';
}
