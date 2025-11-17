<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use JaOcero\FilaChat\Traits\HasFilaChat;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasAvatar,FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable , HasRoles,LogsActivity,HasFilaChat;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'avatar_url',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function letters(): BelongsToMany
    {
        return $this->belongsToMany(Letter::class,'cartables');
    }

    public function referral(): HasMany
    {
        return $this->hasMany(Referral::class,'to_user_id');
    }

    public function cartable(): HasMany
    {
        return $this->hasMany(Cartable::class,'user_id');
    }

    public function minutes(): HasMany
    {
        return $this->hasMany(Minutes::class,'typer_id');
    }

    public function task_created()
    {
        return $this->hasMany(Task::class,'created_by');
    }

    public function task_responsible()
    {
        return $this->hasMany(Task::class,'responsible_id');
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url ? Storage::disk('profile-photos')->url($this->avatar_url) : null ;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return str_ends_with($this->email, '@example.com') or str_ends_with($this->email, '@m.ir');
    }
}
