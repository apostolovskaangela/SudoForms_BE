<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Records THAT a student completed a survey (never WHAT they answered).
 * See the create_survey_participations migration for the anonymity rationale.
 */
class SurveyParticipation extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'user_id',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
