<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'group_id',
        'required_amount',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tasks(){
        return $this->belongsToMany(Task::class);
    }

    public function letters()
    {
        return $this->belongsToMany(Letter::class, 'letter_project');
    }

    public function group()
    {
        return $this->belongsToMany(ProjectGroup::class);
    }

    protected static function boot()
    {
        parent::boot();

//        static::creating(function ($model) {
//            $model->user_id = Auth::id();
//        });
    }

}
