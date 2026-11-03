<?php

namespace App\Models\personal;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Anschrift eines Mitarbeitenden. Änderungen werden wie die übrigen Stammdaten
 * über owen-it/laravel-auditing protokolliert (Änderungsverlauf der Personalakte).
 */
class Address extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['employe_id', 'plz', 'ort', 'nr', 'strasse', 'land'];

    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }
}
