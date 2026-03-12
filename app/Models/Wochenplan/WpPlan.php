<?php
namespace App\Models\Wochenplan;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
class WpPlan extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;
    protected $table = 'wp_plaene';
    protected $fillable = [
        'name', 'gueltig_von', 'gueltig_bis',
        'klasse_id', 'schueler_id', 'parent_plan_id',
        'vorlage_id', 'formatvorlage_id',
        'selbsteinschaetzung', 'taegliche_uebungen_aktiv', 'is_vorlage', 'vorlage_name',
        'created_by',
    ];
    protected $casts = [
        'gueltig_von'                => 'date',
        'gueltig_bis'                => 'date',
        'selbsteinschaetzung'        => 'integer',
        'taegliche_uebungen_aktiv'   => 'boolean',
        'is_vorlage'                 => 'boolean',
    ];
    // ─── Scopes ──────────────────────────────────────────────────────────────
    public function scopeKlassenplaene($query)
    {
        return $query->whereNull('schueler_id')
                     ->where('is_vorlage', false);
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
        return $query->where('gueltig_von', '<=', now())
                     ->where('gueltig_bis', '>=', now());
    }
    public function scopeFuerKlasse($query, $klasseId)
    {
        return $query->where('klasse_id', $klasseId);
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
        return $this->hasManyThrough(
            WpAufgabe::class,
            WpPlanFach::class,
            'wp_plan_id',
            'wp_plan_fach_id',
            'id',
            'id'
        );
    }
    // ─── MediaLibrary ─────────────────────────────────────────────────────────
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('arbeitsblaetter');
    }
    // ─── Accessors ────────────────────────────────────────────────────────────
    public function getTypAttribute(): string
    {
        if ($this->is_vorlage) return 'Vorlage';
        if ($this->schueler_id) return 'Individuell';
        if ($this->klasse_id) return 'Klassenplan';
        return 'Allgemeiner Plan';
    }
    public function getZeitraumAttribute(): string
    {
        return $this->gueltig_von->format('d.m.')
             . ' - '
             . $this->gueltig_bis->format('d.m.Y');
    }
    // ─── Helper ───────────────────────────────────────────────────────────────
    public function isKlassenplan(): bool
    {
        // Ein Plan gilt als Basis für Schülerpläne, wenn er kein Schülerplan
        // und keine Vorlage ist (unabhängig davon, ob eine Klasse gesetzt ist).
        return $this->schueler_id === null && !$this->is_vorlage;
    }
    public function isSchuelerplan(): bool
    {
        return $this->schueler_id !== null;
    }
    public function isVorlage(): bool
    {
        return (bool) $this->is_vorlage;
    }
    public function getEffectiveFormatvorlage(): WpFormatvorlage
    {
        return $this->formatvorlage
            ?? WpFormatvorlage::where('is_default', true)->first()
            ?? WpFormatvorlage::first()
            ?? new WpFormatvorlage([
                'name'           => 'Standard',
                'schriftgroesse' => 'normal',
                'blade_template' => 'wochenplan.pdf.standard',
                'layout_config'  => [],
            ]);
    }
    // ─── Geschaeftslogik ──────────────────────────────────────────────────────
    /**
     * Dupliziert diesen Plan (als neuen Plan oder als Vorlage).
     * Kopiert Faecher und Aufgaben vollstaendig.
     */
    public function duplizieren(array $overrides = []): self
    {
        $neuerPlan = $this->replicate(['created_at', 'updated_at', 'deleted_at']);
        $neuerPlan->fill($overrides);
        $neuerPlan->created_by = auth()->id();
        $neuerPlan->save();
        $this->load('planFaecher.aufgaben');
        foreach ($this->planFaecher as $planFach) {
            $neuesFach = $planFach->replicate(['created_at', 'updated_at']);
            $neuesFach->wp_plan_id = $neuerPlan->id;
            $neuesFach->save();
            foreach ($planFach->aufgaben as $aufgabe) {
                $neueAufgabe = $aufgabe->replicate(['deleted_at', 'created_at', 'updated_at']);
                $neueAufgabe->wp_plan_fach_id = $neuesFach->id;
                $neueAufgabe->synced_from_id  = null;
                $neueAufgabe->save();
            }
        }
        // Tägliche Übungen kopieren
        $this->load('taeglicheUebungen');
        foreach ($this->taeglicheUebungen as $uebung) {
            $neueUebung = $uebung->replicate(['deleted_at', 'created_at', 'updated_at']);
            $neueUebung->wp_plan_id = $neuerPlan->id;
            $neueUebung->save();
        }
        return $neuerPlan;
    }
    /**
     * Erstellt einen individuellen Kinderplan basierend auf diesem Klassenplan.
     */
    public function erstelleSchuelerplan(Schueler $schueler, ?int $formatvorlageId = null): self
    {
        $neuerPlan = $this->duplizieren([
            'klasse_id'        => null,
            'schueler_id'      => $schueler->id,
            'parent_plan_id'   => $this->id,
            'is_vorlage'       => false,
            'vorlage_name'     => null,
            'formatvorlage_id' => $formatvorlageId,
            'name'             => $this->name . ' – ' . $schueler->vorname . ' ' . $schueler->nachname,
        ]);

        // Arbeitsblätter vom Klassenplan synchronisieren
        $neuerPlan->syncMediaVonParent();

        return $neuerPlan;
    }

    /**
     * Synchronisiert alle Arbeitsblätter vom Parent-Plan (Klassenplan).
     *
     * - Bestehende synchronisierte Dateien werden entfernt und neu kopiert
     * - Eigene (manuell hinzugefügte) Dateien bleiben erhalten
     * - Jede synchronisierte Datei erhält Custom Properties zur Nachverfolgbarkeit
     */
    public function syncMediaVonParent(): void
    {
        if (!$this->parent_plan_id) return;

        $parentPlan = $this->parentPlan;
        if (!$parentPlan) return;

        // 1. Bestehende synchronisierte Dateien entfernen (eigene behalten!)
        $this->getMedia('arbeitsblaetter')
            ->filter(fn($media) => $media->getCustomProperty('synced_from_plan_id') !== null)
            ->each(fn($media) => $media->delete());

        // 2. Alle Dateien vom Parent-Plan kopieren
        foreach ($parentPlan->getMedia('arbeitsblaetter') as $parentMedia) {
            // Physische Datei kopieren und als neues Medium registrieren
            $this->addMedia($parentMedia->getPath())
                ->preservingOriginal()  // Original-Datei nicht löschen!
                ->withCustomProperties([
                    'synced_from_plan_id'  => $parentPlan->id,
                    'synced_from_media_id' => $parentMedia->id,
                    'synced_at'            => now()->toIso8601String(),
                ])
                ->usingName($parentMedia->name)
                ->usingFileName($parentMedia->file_name)
                ->toMediaCollection('arbeitsblaetter');
        }
    }
    /**
     * Synchronisiert die Aufgaben eines bestimmten Fachs vom Parent-Plan.
     * Bestehende Aufgaben des Fachs werden durch die synchronisierten ersetzt.
     */
    public function syncFachVonParent(int $fachId): void
    {
        if (!$this->parent_plan_id) return;
        $this->load('planFaecher.aufgaben');
        $parentPlan = $this->parentPlan;
        if (!$parentPlan) return;
        $parentPlan->load('planFaecher.aufgaben');
        $parentFach = $parentPlan->planFaecher->firstWhere('wp_fach_id', $fachId);
        if (!$parentFach) return;
        $eigeneFach = $this->planFaecher->firstWhere('wp_fach_id', $fachId);
        if (!$eigeneFach) {
            $eigeneFach = $parentFach->replicate(['created_at', 'updated_at']);
            $eigeneFach->wp_plan_id = $this->id;
            $eigeneFach->save();
        }
        // Bestehende Aufgaben dieses Fachs loeschen (SoftDelete)
        $eigeneFach->aufgaben()->delete();
        // Aufgaben vom Parent kopieren mit synced_from_id Referenz
        foreach ($parentFach->aufgaben as $aufgabe) {
            $neueAufgabe = $aufgabe->replicate(['deleted_at', 'created_at', 'updated_at']);
            $neueAufgabe->wp_plan_fach_id = $eigeneFach->id;
            $neueAufgabe->synced_from_id  = $aufgabe->id;
            $neueAufgabe->save();
        }
    }
}
