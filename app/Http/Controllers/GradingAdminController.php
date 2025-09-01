<?php

namespace App\Http\Controllers;

use App\Models\GradingSystem;
use App\Models\GradingStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GradingAdminController extends Controller
{
    public function index()
    {
        $systems = GradingSystem::with('stages')->orderBy('name')->get();
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
            'image'=>['nullable','file','image','max:10240']
        ]);
        $path = null;
        if ($request->hasFile('image')){
            $path = $request->file('image')->store('grading_stages','public');
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
            'image'=>['nullable','file','image','max:10240']
        ]);
        if ($request->hasFile('image')){
            // delete old
            if ($stage->image){ Storage::disk('public')->delete($stage->image); }
            $stage->image = $request->file('image')->store('grading_stages','public');
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
        if ($stage->image){ Storage::disk('public')->delete($stage->image); }
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
}
