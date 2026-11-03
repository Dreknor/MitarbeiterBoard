<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * Schüler-Gerät (geteiltes iPad) nach dem Beitritt per Code.
 *
 * Besitzer des Sanctum-Tokens ist bewusst dieses Modell und nicht die Lehrkraft:
 * Ein Schüler-Token kann so weder Permissions noch Klassenzuordnungen eines Benutzers
 * erben. Alle Lehrkraft-Routen verlangen einen User (Middleware "api.staff").
 */
class GradingStudentDevice extends Model
{
    use HasApiTokens;

    protected $fillable = [
        'join_code_id',
        'session_id',
        'schueler_id',
        'device_name',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function joinCode()
    {
        return $this->belongsTo(GradingJoinCode::class, 'join_code_id');
    }

    public function session()
    {
        return $this->belongsTo(GradingDocumentationSession::class, 'session_id');
    }

    public function schueler()
    {
        return $this->belongsTo(Schueler::class);
    }

    /** Ability des Tokens: student-grading:{session_id}:{schueler_id} */
    public function ability(): string
    {
        return 'student-grading:' . $this->session_id . ':' . $this->schueler_id;
    }
}
