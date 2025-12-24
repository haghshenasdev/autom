<?php

namespace App\Models;

use App\Models\Traits\HasStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Project extends Model
{
    use HasFactory,HasStatus,LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'organ_id',
        'city_id',
        'group_id',
        'required_amount',
        'status',
        'amount', // کل اعتبار اخذ شده
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organ(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organ::class);
    }

    public function city(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Task::class);
    }

    public function approvs(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Approve::class);
    }

    public function letters()
    {
        return $this->belongsToMany(Letter::class, 'letter_project')->withPivot('summary');
    }

    public function group()
    {
        return $this->belongsToMany(ProjectGroup::class);
    }

    public function ai_words_data()
    {
        return $this->morphMany(AiWordsData::class, 'ai_words_data');
    }

    protected static function boot()
    {
        parent::boot();

//        static::creating(function ($model) {
//            $model->user_id = Auth::id();
//        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

}
