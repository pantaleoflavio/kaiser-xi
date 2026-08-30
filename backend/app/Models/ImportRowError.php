<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRowError extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_id',
        'row_number',
        'row_data',
        'error_message',
        'errors',
    ];

    protected $casts = [
        'row_data' => 'array',
        'errors' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
