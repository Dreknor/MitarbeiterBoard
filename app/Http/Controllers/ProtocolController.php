<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProtocolRequest;
use App\Models\Protocol;
use App\Models\Theme;
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


}
