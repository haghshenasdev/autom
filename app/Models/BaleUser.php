<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BaleUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'is_sendnotif',
        'bale_id',
        'bale_username',
        'state',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
