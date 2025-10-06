<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    public function organs()
    {
        return $this->hasMany(Organ::class);
    }
}
