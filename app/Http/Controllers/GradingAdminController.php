<?php

namespace App\Http\Controllers;

use App\Models\GradingDocumentationSession;
use App\Models\GradingStage;
use App\Models\GradingStudentAnswer;
use App\Models\GradingSystem;
use App\Models\GradingTeacherAssessment;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\SchuelerGradingHistory;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GradingAdminController extends Controller
{
    public function index()
    {
        $systems = GradingSystem::with(['stages', 'questions'])->orderBy('name')->get();
        return view('admin.grading.index', ['systems' => $systems]);
    }

    public function storeSystem(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:100'],
            'slug' => ['nullable','string','max:100']
        ]);
        $sys = GradingSystem::create(['name'=>$data['name'],'slug'=>$data['slug']??null]);
        return redirect()->route('admin.grading.index')->with('success','System angelegt');
    }

    public function destroySystem(GradingSystem $system)
    {
        // Löschen kaskadiert per FK auf Sessions, Antworten, Bewertungen und Stufen –
        // daher nur erlauben, solange noch keine Daten daran hängen.
        $stageIds = $system->stages()->pluck('id');
        $sessions = GradingDocumentationSession::where('grading_system_id', $system->id)->count();
        $schueler = $stageIds->isEmpty() ? 0 : Schueler::whereIn('grading_stage_id', $stageIds)->count();
        $historie = SchuelerGradingHistory::where('grading_system_id', $system->id)
            ->orWhereIn('grading_stage_id', $stageIds)
            ->orWhereIn('previous_grading_stage_id', $stageIds)
            ->count();

        $klassen = Klasse::where('grading_system_id', $system->id)->count();

        if ($sessions || $schueler || $historie || $klassen) {
            return redirect()->route('admin.grading.index')->withErrors([
                'system' => "„{$system->name}“ kann nicht gelöscht werden: {$klassen} Klasse(n), {$sessions} Dokumentation(en), "
                    . "{$schueler} Schüler mit Stufe und {$historie} Historieneintrag/-einträge hängen daran. "
                    . 'Deaktiviere stattdessen nicht mehr benötigte Fragen oder ordne den Klassen ein anderes System zu.',
            ]);
        }

        $system->delete();
        return redirect()->route('admin.grading.index')->with('success','System gelöscht');
    }

    public function storeStage(Request $request, GradingSystem $system)
    {
        $data = $request->validate([
            'name'=>['required','string','max:100'],
            'slug'=>['nullable','string','max:100'],
            'symbol'=>['nullable','string','max:255'],
            'sort_order'=>['nullable','integer'],
            'image'=>['nullable','file','image','mimes:png,jpg,jpeg,webp,gif','max:10240']
        ]);
        $path = null;
        if ($request->hasFile('image')){
            $path = $this->stufenbildSpeichern($request->file('image'));
        }

        // Wenn is_default gesetzt, alle anderen Stufen dieses Systems zurücksetzen
        // Read boolean value reliably (works with hidden input value 0 / checkbox value 1)
        $isDefault = $request->boolean('is_default');
        if ($isDefault) {
            GradingStage::where('grading_system_id', $system->id)->update(['is_default' => false]);
        }

        // Normalize slug: treat empty string as null to avoid unique constraint collisions
        $slug = isset($data['slug']) && strlen(trim($data['slug'])) ? trim($data['slug']) : null;

        $stage = GradingStage::create([
            'grading_system_id'=>$system->id,
            'name'=>$data['name'],
            'slug'=>$slug,
            'symbol'=>$data['symbol'] ?? null,
            'image'=>$path,
            'sort_order'=>$data['sort_order'] ?? 0,
            'is_default'=>$isDefault
        ]);
        return redirect()->route('admin.grading.index')->with('success','Stufe angelegt');
    }

    public function updateStage(Request $request, GradingStage $stage)
    {
        $data = $request->validate([
            'name'=>['required','string','max:100'],
            'slug'=>['nullable','string','max:100'],
            'symbol'=>['nullable','string','max:255'],
            'sort_order'=>['nullable','integer'],
            'image'=>['nullable','file','image','mimes:png,jpg,jpeg,webp,gif','max:10240']
        ]);
        if ($request->hasFile('image')){
            // delete old (public path) if exists, otherwise try storage public
            if ($stage->image) {
                $oldPublic = public_path($stage->image);
                if (file_exists($oldPublic)) { @unlink($oldPublic); }
                else { try { Storage::disk('public')->delete($stage->image); } catch (\Throwable $_) { /* ignore */ } }
            }
            $stage->image = $this->stufenbildSpeichern($request->file('image'));
        }

        // Read boolean value reliably (works with hidden input value 0 / checkbox value 1)
        $isDefault = $request->boolean('is_default');
        if ($isDefault) {
            GradingStage::where('grading_system_id', $stage->grading_system_id)->update(['is_default' => false]);
        }

        $stage->name = $data['name'];
        $stage->slug = isset($data['slug']) && strlen(trim($data['slug'])) ? trim($data['slug']) : null;
        $stage->symbol = $data['symbol'] ?? null;
        $stage->sort_order = $data['sort_order'] ?? 0;
        $stage->is_default = $isDefault;
        $stage->save();
        return redirect()->route('admin.grading.index')->with('success','Stufe aktualisiert');
    }

    public function destroyStage(GradingStage $stage)
    {
        // FK "set null" würde Schülern still ihre Stufe nehmen und die Historie unlesbar machen.
        $schueler = Schueler::where('grading_stage_id', $stage->id)->count();
        $historie = SchuelerGradingHistory::where('grading_stage_id', $stage->id)
            ->orWhere('previous_grading_stage_id', $stage->id)
            ->count();

        if ($schueler || $historie) {
            return redirect()->route('admin.grading.index')->withErrors([
                'stage' => "Die Stufe „{$stage->name}“ kann nicht gelöscht werden: {$schueler} Schüler haben sie aktuell, "
                    . "{$historie} Historieneintrag/-einträge verweisen darauf. Ändere die Stufe stattdessen (Name, Bild).",
            ]);
        }

        if ($stage->image) {
            $oldPublic = public_path($stage->image);
            if (file_exists($oldPublic)) { @unlink($oldPublic); }
            else { try { Storage::disk('public')->delete($stage->image); } catch (\Throwable $_) { /* ignore */ } }
        }
        $stage->delete();
        return redirect()->route('admin.grading.index')->with('success','Stufe gelöscht');
    }

    /**
     * Reorder stages via AJAX. Expects request->order = [stageId, stageId, ...]
     */
    public function reorderStages(Request $request, GradingSystem $system)
    {
        $data = $request->validate([
            'order' => ['required','array'],
            'order.*' => ['integer','exists:grading_stages,id']
        ]);

        $order = $data['order'];

        // Make sure all provided stages belong to this system
        $stages = GradingStage::whereIn('id', $order)->pluck('grading_system_id', 'id');
        foreach ($order as $index => $stageId) {
            if (!isset($stages[$stageId]) || $stages[$stageId] != $system->id) {
                return response()->json(['status' => 'error', 'message' => 'Ungültige Stufe in Reihenfolge'], 422);
            }
        }

        foreach ($order as $index => $stageId) {
            GradingStage::where('id', $stageId)->update(['sort_order' => $index]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Speichert eine neue Frage für ein Graduierungssystem
     */
    public function storeQuestion(Request $request, GradingSystem $system)
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $question = $system->questions()->create([
            'question' => $data['question'],
            'sort_order' => $data['sort_order'] ?? 0,
            'active' => true,
        ]);

        return redirect()->route('admin.grading.index')->with('success', 'Frage angelegt');
    }

    /**
     * Aktualisiert eine Frage
     */
    public function updateQuestion(Request $request, \App\Models\GradingQuestion $question)
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer'],
            'active' => ['boolean'],
        ]);

        $question->update([
            'question' => $data['question'],
            'sort_order' => $data['sort_order'] ?? $question->sort_order,
            'active' => $request->boolean('active'),
        ]);

        return redirect()->route('admin.grading.index')->with('success', 'Frage aktualisiert');
    }

    /**
     * Löscht eine Frage
     */
    public function destroyQuestion(\App\Models\GradingQuestion $question)
    {
        // Antworten und Bewertungen hängen per FK (cascade) an der Frage.
        $antworten = GradingStudentAnswer::where('question_id', $question->id)->count()
            + GradingTeacherAssessment::where('question_id', $question->id)->count();

        if ($antworten) {
            return redirect()->route('admin.grading.index')->withErrors([
                'question' => "Die Frage kann nicht gelöscht werden, weil bereits {$antworten} Antwort(en) bzw. Bewertung(en) existieren. "
                    . 'Deaktiviere sie stattdessen („Aktiv“ abwählen), dann wird sie in neuen Dokumentationen nicht mehr gestellt.',
            ]);
        }

        $question->delete();
        return redirect()->route('admin.grading.index')->with('success', 'Frage gelöscht');
    }

    /**
     * Sortiert Fragen per AJAX
     */
    public function reorderQuestions(Request $request, GradingSystem $system)
    {
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:grading_questions,id']
        ]);

        $order = $data['order'];

        // Prüfen ob alle Fragen zum System gehören
        $questions = \App\Models\GradingQuestion::whereIn('id', $order)->pluck('grading_system_id', 'id');
        foreach ($order as $index => $questionId) {
            if (!isset($questions[$questionId]) || $questions[$questionId] != $system->id) {
                return response()->json(['status' => 'error', 'message' => 'Ungültige Frage in Reihenfolge'], 422);
            }
        }

        foreach ($order as $index => $questionId) {
            \App\Models\GradingQuestion::where('id', $questionId)->update(['sort_order' => $index]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Speichert ein Stufenbild unter public/img/stages. Die Endung wird aus dem Dateiinhalt
     * bestimmt (nicht aus dem Client-Dateinamen), damit keine ausführbaren Dateien entstehen.
     */
    private function stufenbildSpeichern(UploadedFile $file): string
    {
        $ext = strtolower((string) $file->guessExtension());
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if (!in_array($ext, ['png', 'jpg', 'webp', 'gif'], true)) {
            throw ValidationException::withMessages(['image' => 'Ungültiges Bildformat.']);
        }

        $basename = time() . '_' . Str::random(8) . '.' . $ext;
        $dstDir = public_path('img/stages');
        if (!is_dir($dstDir)) { mkdir($dstDir, 0755, true); }
        $file->move($dstDir, $basename);

        return 'img/stages/' . $basename;
    }
}
