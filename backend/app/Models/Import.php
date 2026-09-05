<?php

namespace App\Models;

use App\Enums\CsvImportType;
use App\Enums\ImportStatus;
use App\Models\ImportUnmatchedRow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'filename',
        'disk',
        'path',
        'status',
        'checksum',
        'imported_by_user_id',
        'total_rows',
        'successful_rows',
        'failed_rows',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'type' => CsvImportType::class,
        'status' => ImportStatus::class,
    ];

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_user_id');
    }

    public function rowErrors(): HasMany
    {
        return $this->hasMany(ImportRowError::class);
    }


    public function unmatchedRows(): HasMany
    {
        return $this->hasMany(ImportUnmatchedRow::class);
    }
}
