<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Anonymous response container. Has NO link to a user by design — see the
 * create_responses migration. Joinable to participations only on survey_id.
 */
class Response extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }

    public function answers()
    {
        return $this->hasMany(Answer::class);
    }
}
