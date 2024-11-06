<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateChecklistRequest;
use App\Models\Checklist;
use App\Models\Group;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($groupname)
    {
        $group = Group::where('name', $groupname)->first();

        if (!$group) {
            return redirect()->back()->with([
                'type' => 'danger',
                'message' => 'Gruppe nicht gefunden'
            ]);
        }

        if (!$group->users->contains(auth()->user())) {
            return redirect()->back()->with([
                'type' => 'danger',
                'message' => 'Du bist nicht in dieser Gruppe'
            ]);
        }

        return view('checklists.index', [
            'group' => $group,
            'checklists' => $group->checklists()
                ->where('is_archived', false)
                ->orderBy('is_template', 'desc')
                ->orderBy('is_active', 'desc')
                ->orderBy('is_completed', 'asc')
                ->get()
        ]);

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create($groupname)
    {
        $group = Group::where('name', $groupname)->first();

        if (!$group) {
            return redirect()->back()->with([
                'type' => 'danger',
                'message' => 'Gruppe nicht gefunden'
            ]);
        }

        if (!$group->users->contains(auth()->user())) {
            return redirect()->back()->with([
                'type' => 'danger',
                'message' => 'Du bist nicht in dieser Gruppe'
            ]);
        }

        return view('checklists.create', [
            'group' => $group
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateChecklistRequest $request)
    {
        $group = Group::where('name', $request->groupname)->first();

        if (!$group) {
            return redirect()->back()->with([
                'type' => 'danger',
                'message' => 'Gruppe nicht gefunden'
            ]);
        }

        if (!$group->users->contains(auth()->user())) {
            return redirect()->back()->with([
                'type' => 'danger',
                'message' => 'Du bist nicht in dieser Gruppe'
            ]);
        }

        $checklist = new Checklist($request->validated());
        $checklist->group_id = $group->id;
        $checklist->user_id = auth()->id();
        $checklist->save();

        return redirect(url($group->name.'/checklists/'.$checklist->id) )->with([
            'type' => 'success',
            'message' => 'Checkliste erstellt'
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show($groupname, Checklist $checklist)
    {

        $group = Group::where('name', $groupname)->first();

        if (!$group) {
            return redirect()->back()->with([
                'type' => 'danger',
                'message' => 'Gruppe nicht gefunden'
            ]);
        }

        if (!$group->users->contains(auth()->user())) {
            return redirect()->back()->with([
                'type' => 'danger',
                'message' => 'Du bist nicht in dieser Gruppe'
            ]);
        }

        return view('checklists.show', [
            'group' => $group,
            'checklist' => $checklist
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($groupname, Checklist $checklist)
    {
        $group = Group::where('id', $checklist->group_id)->first();

        if (!$group) {
            return redirect()->back()->with([
                'type' => 'danger',
                'message' => 'Gruppe nicht gefunden'
            ]);
        }

        if (!$group->users->contains(auth()->user())) {
            return redirect()->back()->with([
                'type' => 'danger',
                'message' => 'Du bist nicht in dieser Gruppe'
            ]);
        }

        return view('checklists.edit', [
            'group' => $group,
            'checklist' => $checklist->load('categories.items')
        ]);

    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CreateChecklistRequest $request, Checklist $checklist)
    {
        $group = Group::where('id', $checklist->group_id)->first();

        if (!$group) {
            return redirect()->back()->with([
                'type' => 'danger',
                'message' => 'Gruppe nicht gefunden'
            ]);
        }

        if (!$group->users->contains(auth()->user())) {
            return redirect()->back()->with([
                'type' => 'danger',
                'message' => 'Du bist nicht in dieser Gruppe'
            ]);
        }

        $checklist->update($request->validated());

        return redirect(url($group->groupname.'/checklist/'.$checklist->id.'/edit') )->with([
            'type' => 'success',
            'message' => 'Checkliste aktualisiert'
        ]);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(checklist $checklist)
    {
        $group = Group::where('id', $checklist->group_id)->first();

        if (!$group) {
            return redirect()->back()->with([
                'type' => 'danger',
                'message' => 'Gruppe nicht gefunden'
            ]);
        }

        if (!$group->users->contains(auth()->user())) {
            return redirect()->back()->with([
                'type' => 'danger',
                'message' => 'Du bist nicht in dieser Gruppe'
            ]);
        }

        if ($checklist->is_template and  $checklist->author_id != auth()->id()) {
            return redirect()->back()->with([
                'type' => 'danger',
                'message' => 'Checkliste ist ein Template. Diese können nur vom Author gelöscht werden.'
            ]);
        }

        $checklist->items()->delete();
        $checklist->delete();

        return redirect(url($group->groupname.'/checklist') )->with([
            'type' => 'success',
            'message' => 'Checkliste gelöscht'
        ]);
    }

    public function publish(Checklist $checklist)
    {
        $group = Group::where('id', $checklist->group_id)->first();

        if (!$group) {
            return redirect()->back()->with([
                'type' => 'danger',
                'Meldung' => 'Gruppe nicht gefunden'
            ]);
        }

        if (!$checklist->is_template ) {
            return redirect()->back()->with([
                'type' => 'danger',
                'Meldung' => 'Checkliste ist bereits veröffentlicht'
            ]);
        }

        $newChecklist = $checklist->replicate();
        $newChecklist->is_template = false;
        $newChecklist->is_active = true;
        $newChecklist->user_id = auth()->id();
        $newChecklist->end_date = now()->addMonth();

        foreach ($checklist->categories as $category) {
            $newCategory = $category->replicate();
            $newCategory->checklist_id = $newChecklist->id;
            $newCategory->save();

            foreach ($category->items as $item) {
                $newItem = $item->replicate();
                $newItem->checklist_id = $newChecklist->id;
                $newItem->category_id = $newCategory->id;
                $newItem->save();
            }
        }

        return redirect(url($group->groupname.'/checklist/'.$checklist->id.'/edit') )->with([
            'type' => 'success',
            'Meldung' => 'Checkliste veröffentlicht'
        ]);
    }

    public function archive(Checklist $checklist)
    {
        $group = Group::where('id', $checklist->group_id)->first();

        if (!$group) {
            return redirect()->back()->with([
                'type' => 'danger',
                'message' => 'Gruppe nicht gefunden'
            ]);
        }

        if ($checklist->is_template and  $checklist->author_id != auth()->id()) {
            return redirect()->back()->with([
                'type' => 'danger',
                'message' => 'Checkliste ist ein Template. Diese können nur vom Author archiviert werden.'
            ]);
        }

        $checklist->is_archived = true;
        $checklist->save();

        return redirect(url($group->groupname.'/checklist/'.$checklist->id.'/edit') )->with([
            'type' => 'success',
            'message' => 'Checkliste archiviert'
        ]);
    }



    public function complete(Checklist $checklist)
    {
        $group = Group::where('id', $checklist->group_id)->first();

        if (!$group) {
            return redirect()->back()->with([
                'type' => 'danger',
                'message' => 'Gruppe nicht gefunden'
            ]);
        }

        if ($checklist->is_template) {
            return redirect()->back()->with([
                'type' => 'danger',
                'message' => 'Checkliste ist ein Template. Diese können nicht abgeschlossen werden.'
            ]);
        }

        $checklist->is_completed = true;
        $checklist->save();

        return redirect(url($group->groupname.'/checklist/'.$checklist->id.'/edit') )->with([
            'type' => 'success',
            'message' => 'Checkliste abgeschlossen'
        ]);
    }
}
