<?php

namespace App\Http\Controllers\TerminListen;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreListeTerminRequest;
use App\Http\Requests\TerminabsageRequest;
use App\Mail\TerminAbsage;
use App\Models\Liste;
use App\Models\ListenTermin;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Mail;

/**
 * Class ListenTerminController
 */
class ListenTerminController extends Controller
{
    /**
     * Speichert verfügbare Termine
     * @param Liste $liste
     * @param StoreListeTerminRequest $request
     * @return RedirectResponse
     */
    public function store(Liste $liste, StoreListeTerminRequest $request)
    {

        if ($liste->user_id != auth()->id()){
            return redirect()->back()->with([
                'type' => 'warning',
                'Meldung' => 'Keine Berechtigung'
            ]);
        }
        $datum = Carbon::createFromFormat('Y-m-d H:i', $request->termin . ' ' . $request->zeit);
        $termin = new ListenTermin([
            'listen_id' => $liste->id,
            'termin' => $datum,
            'comment' => $request->comment,
            'duration' => ($request->duration > 0)? $request->duration : $liste->duration
        ]);
        dd($termin);

        if ($request->weekly == 1) {
            for ($x = 1; $x < $request->repeat; $x++) {
                $newTermin = $termin->replicate();
                $newTermin->termin = $newTermin->termin->addWeeks($x);
                $newTermin->save();
            }
        } elseif ($request->weekly == 0 and $request->repeat > 1) {
            for ($x = 1; $x < $request->repeat; $x++) {
                $newTermin = $termin->replicate();
                $newTermin->termin = $newTermin->termin->addMinutes($x * $liste->duration);
                $newTermin->save();
            }
        }

        $termin->save();

        return redirect()->back()->with([
            'type'  => 'success',
            'Meldung'=> 'Termin erstellt',
        ]);
    }

    /**
     * @param listenTermin $listen_termine
     * @return RedirectResponse|Redirector
     */
    public function update(Request $request, listenTermin $listen_termine)
    {
        $Eintragungen = $request->user()->listen_eintragungen()->where('listen_id', $listen_termine->liste->id)->get();

        if (count($Eintragungen) > 0 and $listen_termine->liste->multiple != 1) {
            return redirect()->back()->with([
               'type'   => 'warning',
               'Meldung'    => 'Es kann nur ein Termin reserviert werden',
            ]);
        }

        $listen_termine->update([
            'reserviert_fuer' => $request->user()->id,
        ]);

        return redirect()->to(url('listen'))->with([
            'type'  => 'success',
            'Meldung'   => 'Termin wurde reserviert.',
        ]);
    }

    /**
     * @param listenTermin $listen_termine
     * @return RedirectResponse
     * @throws Exception
     */
    public function absagen(TerminabsageRequest $request, listenTermin $listen_termine)
    {

        if ($request->user()->id == $listen_termine->reserviert_fuer  or $request->user()->id == $listen_termine->liste->user_id) {
            Mail::to($listen_termine->liste->ersteller->email, $listen_termine->liste->ersteller->name)
                ->queue(new TerminAbsage($request->user(),
                    $listen_termine->liste,
                    $listen_termine->termin,
                    $request->text));

            Mail::to($listen_termine->eingetragenePerson->email, $listen_termine->eingetragenePerson->name)
                            ->queue(new TerminAbsage($request->user(),
                                $listen_termine->liste,
                                $listen_termine->termin,
                                $request->text));

            $listen_termine->update(['reserviert_fuer' => null]);


            return redirect()->back()->with([
                'type'  => 'success',
                'Meldung'=> 'Termin abgesagt',
            ]);
        }

        return redirect()->back()->with([
            'type'  => 'danger',
            'Meldung'=> 'Keine Recht den Termin abzusagen?',
        ]);
    }

    public function destroy(Request $request, listenTermin $listen_termine)
    {
        if ($request->user()->id == $listen_termine->liste->user_id) {
            if ($listen_termine->reserviert_fuer != null) {

                //E-Mail versenden
                Mail::to($listen_termine->eingetragenePerson->email, $listen_termine->eingetragenePerson->name)
                    ->queue(new TerminAbsage($listen_termine->eingetragenePerson->name, $listen_termine->liste, $listen_termine->termin, $request->user()));
                $listen_termine->update([
                    'reserviert_fuer'   => null,
                ]);
            } else {
                $listen_termine->delete();
            }

            return redirect()->back()->with([
                'type'  => 'success',
                'Meldung'=> 'Termin gelöscht bzw. abgesagt',
            ]);
        }

        return redirect()->back()->with([
            'type'  => 'danger',
            'Meldung'=> 'Keine Recht den Termin abzusagen?',
        ]);
    }
}
