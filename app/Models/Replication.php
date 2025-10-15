<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Replication extends Model
{
    use HasFactory;

    protected $fillable = [
        'organ_id',
        'letter_id',
    ];

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function organ(): BelongsTo
    {
        return $this->belongsTo(Organ::class);
    }
}
