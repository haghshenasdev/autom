<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

//ارجاع
class Referral extends Model
{
    use HasFactory;

    protected $fillable = [
        'rule',
        'by_user_id',
        'to_user_id',
        'checked',
    ];

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function users(): BelongsTo
    {
        return $this->belongsTo(User::class,'to_user_id');
    }

    public function by_users(): BelongsTo
    {
        return $this->belongsTo(User::class,'by_user_id');
    }
}
