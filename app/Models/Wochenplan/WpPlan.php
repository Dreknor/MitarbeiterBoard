<?php

namespace App\Models\Wochenplan;

use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\User;
use App\Services\Wochenplan\WochenplanService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Wochenplan (Klassenplan, Kinderplan oder Vorlage).
 *
 * Geschäftslogik liegt in {@see WochenplanService}; die Methoden hier sind nur
 * Abkürzungen dorthin. Zugriff über {@see \App\Policies\WpPlanPolicy}.
 */
class WpPlan extends Model
{
    use HasFactory, SoftDeletes;

    public const NIVEAUS = [
        1 => 'Basis',
        2 => 'Standard',
        3 => 'Erweitert',
    ];

    protected $table = 'wp_plaene';

    protected $fillable = [
        'name', 'gueltig_von', 'gueltig_bis',
        'klasse_id', 'schueler_id', 'parent_plan_id',
        'vorlage_id', 'formatvorlage_id',
        'selbsteinschaetzung', 'niveau', 'taegliche_uebungen_aktiv', 'is_vorlage', 'vorlage_name',
        'created_by', 'version',
        'ablage_ordner', 'ablage_datei', 'ablage_status', 'ablage_fehler',
        'ablage_angefordert_at', 'abgelegt_at',
    ];

    protected $casts = [
        'gueltig_von' => 'date',
        'gueltig_bis' => 'date',
        'selbsteinschaetzung' => 'integer',
        'niveau' => 'integer',
        'taegliche_uebungen_aktiv' => 'boolean',
        'is_vorlage' => 'boolean',
        'version' => 'integer',
        'ablage_angefordert_at' => 'datetime',
        'abgelegt_at' => 'datetime',
    ];

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeKlassenplaene($query)
    {
        return $query->whereNull('schueler_id')->where('is_vorlage', false);
    }

    public function scopeSchuelerplaene($query)
    {
        return $query->whereNotNull('schueler_id')->where('is_vorlage', false);
    }

    public function scopeVorlagen($query)
    {
        return $query->where('is_vorlage', true);
    }

    public function scopeAktuell($query)
    {
        return $query->whereDate('gueltig_von', '<=', now())
            ->whereDate('gueltig_bis', '>=', now());
    }

    public function scopeFuerKlasse($query, $klasseId)
    {
        return $query->where('klasse_id', $klasseId);
    }

    /**
     * Pläne, die die Person sehen darf: Klassenpläne ihrer Klassen, Kinderpläne
     * von Kindern ihrer Klassen, eigene Pläne ohne Klasse. Vorlagen sind für alle sichtbar.
     */
    public function scopeSichtbarFuer(Builder $query, User $user): Builder
    {
        if (self::darfAlles($user)) {
            return $query;
        }

        $klassenIds = $user->paedKlassenIds()->all();

        return $query->where(function (Builder $q) use ($user, $klassenIds) {
            $q->where('is_vorlage', true)
                ->orWhere(function (Builder $k) use ($klassenIds) {
                    $k->whereNull('schueler_id')->whereIn('klasse_id', $klassenIds);
                })
                ->orWhere(function (Builder $s) use ($klassenIds) {
                    $s->whereNotNull('schueler_id')
                        ->whereIn('schueler_id', Schueler::query()->select('id')->whereIn('klasse_id', $klassenIds));
                })
                ->orWhere(function (Builder $o) use ($user) {
                    $o->whereNull('schueler_id')->whereNull('klasse_id')->where('created_by', $user->id);
                });
        });
    }

    public static function darfAlles(User $user): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        try {
            return $user->hasPermissionTo('manage all wochenplaene');
        } catch (\Throwable) {
            return false;
        }
    }

    // ─── Relationen ──────────────────────────────────────────────────────────

    public function klasse()
    {
        return $this->belongsTo(Klasse::class, 'klasse_id');
    }

    public function schueler()
    {
        return $this->belongsTo(Schueler::class, 'schueler_id');
    }

    public function parentPlan()
    {
        return $this->belongsTo(self::class, 'parent_plan_id');
    }

    public function kinderPlaene()
    {
        return $this->hasMany(self::class, 'parent_plan_id');
    }

    public function formatvorlage()
    {
        return $this->belongsTo(WpFormatvorlage::class, 'formatvorlage_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function planFaecher()
    {
        return $this->hasMany(WpPlanFach::class, 'wp_plan_id')->orderBy('sort_order');
    }

    public function taeglicheUebungen()
    {
        return $this->hasMany(WpTaeglicheUebung::class, 'wp_plan_id')->orderBy('sort_order');
    }

    public function aufgaben()
    {
        return $this->hasManyThrough(WpAufgabe::class, WpPlanFach::class, 'wp_plan_id', 'wp_plan_fach_id', 'id', 'id');
    }

    /** Eigene Arbeitsblätter des Plans (ohne die des Klassenplans). */
    public function dateien()
    {
        return $this->hasMany(WpDatei::class, 'wp_plan_id')->orderBy('name');
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getTypAttribute(): string
    {
        if ($this->is_vorlage) {
            return 'Vorlage';
        }
        if ($this->schueler_id) {
            return 'Kinderplan';
        }
        if ($this->klasse_id) {
            return 'Klassenplan';
        }

        return 'Plan ohne Klasse';
    }

    public function getZeitraumAttribute(): string
    {
        if (! $this->gueltig_von || ! $this->gueltig_bis) {
            return '';
        }

        return $this->gueltig_von->format('d.m.').' - '.$this->gueltig_bis->format('d.m.Y');
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    public function isKlassenplan(): bool
    {
        return $this->schueler_id === null && ! $this->is_vorlage;
    }

    public function isSchuelerplan(): bool
    {
        return $this->schueler_id !== null;
    }

    public function isVorlage(): bool
    {
        return (bool) $this->is_vorlage;
    }

    /** Klasse, über die der Zugriff geregelt wird (beim Kinderplan die aktuelle Klasse des Kindes). */
    public function zugriffsKlasseId(): ?int
    {
        if ($this->isSchuelerplan()) {
            return $this->schueler?->klasse_id ? (int) $this->schueler->klasse_id : null;
        }

        return $this->klasse_id ? (int) $this->klasse_id : null;
    }

    /**
     * Arbeitsblätter für Anzeige und Export: die des Klassenplans (bei Kinderplänen) und die eigenen.
     * Klassenplan-Dateien, für die der Kinderplan eine angepasste Fassung hat
     * (`ersetzt_wp_datei_id`), werden ausgeblendet – die Fassung steht an ihrer Stelle.
     */
    public function alleDateien()
    {
        $eigene = $this->relationLoaded('dateien') ? $this->dateien : $this->dateien()->get();

        if ($this->parent_plan_id && $this->parentPlan) {
            $ersetzt = $eigene->pluck('ersetzt_wp_datei_id')->filter()->map(fn ($id) => (int) $id)->all();

            return $this->parentPlan->dateien()->get()
                ->reject(fn (WpDatei $d) => in_array((int) $d->id, $ersetzt, true))
                ->each(fn (WpDatei $d) => $d->setAttribute('vom_klassenplan', true))
                ->concat($eigene)
                ->values();
        }

        return $eigene;
    }

    public function getEffectiveFormatvorlage(): WpFormatvorlage
    {
        return $this->formatvorlage
            ?? WpFormatvorlage::where('is_default', true)->first()
            ?? WpFormatvorlage::first()
            ?? new WpFormatvorlage([
                'name' => 'Standard',
                'schriftgroesse' => 'normal',
                'blade_template' => 'wochenplan.pdf.standard',
                'layout_config' => [],
            ]);
    }

    // ─── Abkürzungen in den Service ──────────────────────────────────────────

    public function duplizieren(array $overrides = []): self
    {
        return app(WochenplanService::class)->duplizieren($this, $overrides);
    }

    public function erstelleSchuelerplan(Schueler $schueler, ?int $formatvorlageId = null, ?int $niveau = null): self
    {
        return app(WochenplanService::class)->kinderplanErstellen($this, $schueler, $formatvorlageId, $niveau);
    }

    public function syncFachVonParent(int $fachId): void
    {
        app(WochenplanService::class)->fachAbgleichen($this, $fachId);
    }
}
