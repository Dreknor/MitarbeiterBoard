<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProtocolRequest;
use App\Models\Group;
use App\Models\Protocol;
use App\Models\Theme;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ProtocolController extends Controller
{


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($groupname, Theme $theme)
    {
       return view('protocol.create', [
           'theme'  => $theme
       ]);
    }

    public function edit($groupname, Protocol $protocol)
    {
       return view('protocol.edit', [
           'theme'  => $protocol->theme,
           'protocol'  => $protocol,
       ]);
    }

    public function update($groupname, ProtocolRequest $request, Protocol $protocol)
    {
        $protocol->update($request->validated());

        if ($request->completed == 1){
            $protocol->theme->update([
                'completed' => 1
            ]);

            $protocol = new Protocol([
                'creator_id' => auth()->id(),
                'theme_id'   => $protocol->theme->id,
                'protocol'   => "Thema geschlossen",
            ]);
            $protocol->save();



            return redirect(url($groupname.'/themes'))->with([
                'type'  => 'success',
                'Meldung'=> 'Protokoll gespeichert und Thema geschlossen'
            ]);
        }
        return redirect(url($groupname."/themes/".$protocol->theme_id))->with([
            'type'  => "success",
            'Meldung'   => "Protokoll geändert"
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store($groupname, ProtocolRequest $request, Theme $theme)
    {
        $protocol = new Protocol([
           'creator_id' => auth()->id(),
           'theme_id'   => $theme->id,
           'protocol'   => $request->protocol,
        ]);
        $protocol->save();

        if ($request->hasFile('files')) {
            $files = $request->files->all();
            foreach ($files['files'] as $file){

                $theme
                    ->addMedia($file)
                    ->toMediaCollection();
            }

        }


        if ($request->completed == 1){
            $theme->update([
                'completed' => 1
            ]);

            $protocol = new Protocol([
                'creator_id' => auth()->id(),
                'theme_id'   => $theme->id,
                'protocol'   => "Thema geschlossen",
            ]);
            $protocol->save();



            return redirect(url($theme->group->name.'/themes'))->with([
                'type'  => 'success',
                'Meldung'=> 'Protokoll gespeichert und Thema geschlossen'
            ]);
        }

        return redirect(url($theme->group->name.'/themes/'.$theme->id))->with([
            'type'  => 'success',
            'Meldung'=> 'Protokoll gespeichert'
        ]);
    }

    /**
     * make paper protocol
     */
    public function createSheet($groupname, $date = ""){
        $group = Group::where('name', $groupname)->first();

        if (!auth()->user()->groups->contains($group)){
            return redirect(url('home'))->with([
                'type'    => 'warning',
                'Meldung' => "Kein Zugriff auf diese Gruppe"
            ]);
        }



        if ($date != ""){
            $date = Carbon::createFromFormat('Y-m-d',$date);
        } else {
            $date = Carbon::now();
        }


        $themes = $group->themes()->WhereHas('protocols', function ($query) use ($date){
            $query->whereDate('created_at', '=', $date);
        })->get();

        $themes->load(['group', 'protocols']);
        return view('protocol.export')->with([
            'themes'    => $themes,
            'date'  => $date
        ]);
    }
}
