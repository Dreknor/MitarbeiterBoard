<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\GradingStage;
use App\Models\SchuelerGradingHistory;

class Schueler extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'schueler';

    protected $fillable = [
        'vorname',
        'nachname',
        'geburtsdatum',
        'klasse_id',
        'import_key',
        'grading_stage_id'
    ];

    protected $casts = [
        'geburtsdatum' => 'date'
    ];

    public function klasse()
    {
        return $this->belongsTo(Klasse::class, 'klasse_id');
    }

    // Neue Relation: aktuelle Stufe
    public function grading_stage()
    {
        return $this->belongsTo(GradingStage::class, 'grading_stage_id');
    }

    // Historie der Stufenänderungen
    public function grading_history()
    {
        return $this->hasMany(SchuelerGradingHistory::class, 'schueler_id')->orderByDesc('created_at');
    }

    public function getNameAttribute(): string
    {
        return $this->vorname.' '.$this->nachname;
    }

    // Neue Wochenplan-Relation (neues System)
    public function wpPlaene()
    {
        return $this->hasMany(\App\Models\Wochenplan\WpPlan::class, 'schueler_id');
    }

    // Optionaler Accessor für Symbol (falls Stufe gesetzt)
    public function getStageSymbolAttribute(): ?string
    {
        return $this->grading_stage?->symbol ?? null;
    }

    // PaedDiary-Abwesenheiten
    public function paedDiaryAbsences()
    {
        return $this->hasMany(\App\Models\PaedDiarySchuelerAbsence::class);
    }
}
