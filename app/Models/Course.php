<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'professor_id',
        'semester',
    ];

    public function professor()
    {
        return $this->belongsTo(Professor::class);
    }

    public function surveys()
    {
        return $this->hasMany(Survey::class);
    }
}
