<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateKlasseRequest;
use App\Http\Requests\EditKlasseRequest;
use App\Models\Klasse;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class KlasseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->view('klassen.klassen',[
            'klassen' => Klasse::all()
        ]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CreateKlasseRequest $request)
    {
        Klasse::create($request->validated());

        return redirect()->back()->with([
            'type'  => "success",
            'Meldung'=> 'Klasse wurde angelegt.'
        ]);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Klasse  $klasse
     * @return \Illuminate\Http\Response
     */
    public function destroy($klasse)
    {
        $klasse = Klasse::find($klasse);
        $name = $klasse->name;
        $klasse->delete();

        return redirect()->back()->with([
            'type' => 'warning',
            'Meldung' => $name.' wurde gelöscht.'
        ]);
    }

    public function edit($klasse)
    {
        $klasse = Klasse::with(['schueler','paed_users'])->find($klasse);
        $paedUsers = User::permission('view paed diary')->orderBy('name')->get();
        return response()->view('klassen.edit',[
            'klasse' => $klasse,
            'paedUsers' => $paedUsers
        ]);
    }

    public function update(Request $request, Klasse $klassen)
    {

        $validatedData = $request->validate([
            'name' => ['required', 'unique:klassen,name,'.$klassen->id, 'max:255'],
            'kuerzel' => ['required', 'unique:klassen,kuerzel,'.$klassen->id, 'max:255'],
            'paed_user_ids' => ['nullable','array'],
            'paed_user_ids.*' => ['integer','exists:users,id']
        ]);

        $klassen->update($validatedData);

        if ($request->has('paed_user_ids')){
            $klassen->paed_users()->sync($request->get('paed_user_ids'));
        } else {
            $klassen->paed_users()->sync([]);
        }

        return redirect(url('klassen/'.$klassen->id.'/edit'))->with([
            'type' => 'success',
            'Meldung' => 'Klasse wurde aktualisiert.'
        ]);
    }
}
