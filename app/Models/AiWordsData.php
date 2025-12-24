<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiWordsData extends Model
{
    use HasFactory;

    protected $fillable = [
        'allowed_words',   // لیست کلمات مجاز
        'blocked_words',   // لیست کلمات غیرمجاز
        'sensitivity',     // میزان حساسیت عددی
        'model_type',      // نوع مدل زمینه شده (morph)
        'model_id',        // آیدی مدل مربوطه
        'target_field',    // نام فیلد در مدل مقصد
    ];

    protected $casts = [
        'allowed_words' => 'array',
        'blocked_words' => 'array',
        'sensitivity'   => 'integer',
    ];

    public function model(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }
}
