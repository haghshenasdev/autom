<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cartable extends Model
{
    use HasFactory;

    protected $fillable = [
        'letter_id',
        'checked',
        'user_id',
    ];

    public function letter()
    {
        return $this->belongsTo(letter::class);
    }
}
