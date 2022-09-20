<?php

namespace App\Http\Controllers\TerminListen;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateListeRequest;
use App\Models\Group;
use App\Models\Liste;
use App\Models\ListenTermin;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use function Clue\StreamFilter\fun;

class ListenController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return View|RedirectResponse
     */
    public function index(Request $request)
    {

        if (!auth()->user()->can('see terminlisten')){
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Keine Berechtigung'
            ]);
        }

        $oldListen = auth()->user()->listen()->where('active', 1)->where('ende', '<', Carbon::now())->paginate(5);
        $listen = auth()->user()->listen()->whereDate('ende', '>=', Carbon::now())->active()->with('eintragungen')->get();

        return view('listen.index', [
            'listen' => $listen,
            'archiv' => $oldListen
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return View
     */
    public function create()
    {
        if (!auth()->user()->can('create Terminliste')){
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Keine Berechtigung'
            ]);
        }

        $groups = auth()->user()->groups();
        return view('listen.create', [
            'gruppen'   => $groups,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(CreateListeRequest $request)
    {
        if (!auth()->user()->can('create Terminliste')){
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Keine Berechtigung'
            ]);
        }

        $Liste = new Liste($request->validated());
        $Liste->user_id = $request->user()->id;

        $Liste->save();

        $gruppen = Group::whereIn('id',$request->gruppen)->get();
        $Liste->groups()->attach($gruppen);

        return redirect(url("listen/$Liste->id"));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Liste  $Liste
     * @return RedirectResponse|View
     */
    public function show(Liste $terminListe)
    {
        if (!auth()->user()->can('see terminlisten') or !auth()->user()->listen->contains($terminListe)){
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Keine Berechtigung'
            ]);
        }
        $terminListe->load('eintragungen');
        $terminListe->eintragungen->sortBy('termin');

        if ($terminListe->type == 'termin') {
            return view('listen.terminAuswahl', [
                'liste' => $terminListe,
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Liste $terminListe
     * @return RedirectResponse|View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function edit(Liste $terminListe)
    {

        if (auth()->id() != $terminListe->user_id){
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Keine Berechtigung'
            ]);
        }

        $groups = auth()->user()->groups();

        return view('listen.edit', [
            'liste'    => $terminListe,
            'gruppen'=> $groups,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Liste $terminListe
     * @return RedirectResponse|Response
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(CreateListeRequest $request, Liste $terminListe)
    {
        if ($terminListe->user_id != auth()->id()){
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Keine Berechtigung'
            ]);
        }

        $terminListe->update($request->all());

        $gruppen = $request->gruppen;

        $terminListe->groups()->detach();
        $terminListe->groups()->attach($gruppen);

        return redirect()->to(url('listen'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Liste $terminListe
     * @return Response
     */
    public function destroy(Liste $terminListe)
    {
        //
    }

    public function activate(Liste $liste)
    {
        if (auth()->id()!= $liste->user_id){
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Keine Berechtigung'
            ]);
        }
        $liste->update([
            'active' => 1,
        ]);

        return redirect()->back();
    }

    public function deactivate(Liste $liste)
    {
        if (auth()->id()!= $liste->user_id){
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Keine Berechtigung'
            ]);
        }
        $liste->update([
            'active' => 0,
        ]);

        return redirect()->back();
    }



    public function refresh(Liste $liste)
    {
        if (auth()->id()!= $liste->user_id){
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Keine Berechtigung'
            ]);
        }

        $liste->update([
            'ende' => Carbon::now()->addWeeks(2)
        ]);

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Liste verlängert'
        ]);
    }

    public function archiv(Liste $liste)
    {
        if (auth()->id()!= $liste->user_id){
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Keine Berechtigung'
            ]);
        }

        $liste->update([
            'ende' => Carbon::now()->subDay()
        ]);

        return redirect()->back()->with([
            'type' => 'success',
            'Meldung' => 'Liste beendet'
        ]);
    }
}
