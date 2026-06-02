<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'type',
        'course_id',
        'status',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function questions()
    {
        return $this->hasMany(Question::class)->orderBy('order');
    }

    public function responses()
    {
        return $this->hasMany(Response::class);
    }

    public function participations()
    {
        return $this->hasMany(SurveyParticipation::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'active'
            && (is_null($this->starts_at) || $this->starts_at->isPast())
            && (is_null($this->ends_at) || $this->ends_at->isFuture());
    }
}
