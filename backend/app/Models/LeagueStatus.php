<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeagueStatus extends Model
{
    public const DRAFT = 'draft';
    public const SETUP = 'setup';
    public const ACTIVE = 'active';
    public const COMPLETED = 'completed';
    public const ARCHIVED = 'archived';

    protected $fillable = [
        'key', 'label', 'sort_order',
    ];
}
