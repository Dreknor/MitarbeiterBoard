<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Vorlage eines Prozesses (§8.1). Ersetzt langfristig `procedures` mit
 * `started_at IS NULL`. Im Übergang zu Phase 4 koexistieren beide.
 */
class ProcedureTemplate extends Model
{
    use SoftDeletes, HasFactory;

    protected $table = 'procedure_templates';

    protected $fillable = [
        'name', 'description', 'category_id', 'author_id', 'color', 'legacy_procedure_id',
    ];

    public function category()
    {
        return $this->belongsTo(Procedure_Category::class, 'category_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function steps()
    {
        return $this->hasMany(ProcedureTemplateStep::class, 'template_id');
    }

    public function rootSteps()
    {
        return $this->steps()->whereNull('parent_id')->orderBy('sort_order');
    }

    /** Auf dieser Vorlage basierende laufende Prozesse. */
    public function procedures()
    {
        return $this->hasMany(Procedure::class, 'template_id');
    }

    /** Wiederkehrende Auslösungen dieser Vorlage. */
    public function recurring()
    {
        return $this->hasMany(RecurringProcedure::class, 'procedure_id', 'legacy_procedure_id');
    }
}

