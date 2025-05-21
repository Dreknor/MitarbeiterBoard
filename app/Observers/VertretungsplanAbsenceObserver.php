<?php

namespace App\Observers;

use App\Models\VertretungsplanAbsence;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VertretungsplanAbsenceObserver
{
    public function created(VertretungsplanAbsence $vertretungsplanAbsence): void
    {
        try {
            if (settings('vertretungsplan_send_elterninfoboard') == 1 and settings('elterninfoboard_url') != null) {
                $url = settings('elterninfoboard_url') . '/api/absences';
                $client = new \GuzzleHttp\Client();
                $response = $client->post( $url,[
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => [
                        'id' => $vertretungsplanAbsence->id,
                        'name' => $vertretungsplanAbsence->user->shortname,
                        'start_date' => $vertretungsplanAbsence->start_date->format('Y-m-d'),
                        'end_date' => $vertretungsplanAbsence->end_date->format('Y-m-d'),
                        'reason' => $vertretungsplanAbsence->reason,
                        'key' => settings('api_key_elterninfoboard')
                    ]
                ]);


            }
        } catch (\Exception $e) {
            Log::error('VertretungsplanAbsenceObserver: Senden an API fehlgeschlagen',
                [
                'exception' => $e->getMessage()
                ]);

        }
    }

    /**
     * Handle the Vertretung "updated" event.
     */
    public function updated(VertretungsplanAbsence $vertretungsplanAbsence): void
    {

        try {
            if (settings('vertretungsplan_send_elterninfoboard') ==  1 and settings('elterninfoboard_url') != null){
                $url = settings('elterninfoboard_url').'/api/absences/'.$vertretungsplanAbsence->id;


                $client = new \GuzzleHttp\Client();
                $response = $client->put( $url,[
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => [
                        'id' => $vertretungsplanAbsence->id,
                        'name' => $vertretungsplanAbsence->user->shortname,
                        'start_date' => $vertretungsplanAbsence->start_date,
                        'end_date' => $vertretungsplanAbsence->end_date,
                        'reason' => $vertretungsplanAbsence->reason ?? '',
                        'key' => settings('api_key_elterninfoboard')
                    ]
                ]);

            }
        } catch (\Exception $e) {
            Log::error('VertretungsplanAbsenceObserver: Senden an API fehlgeschlagen',
                [
                'exception' => $e->getMessage()
                ]);
        }

    }

    /**
     * Handle the Vertretung "deleted" event.
     */
    public function deleted(VertretungsplanAbsence $vertretungsplanAbsence): void
    {
        try {
            if (settings('vertretungsplan_send_elterninfoboard') ==  1 and settings('elterninfoboard_url') != null){
                $url = settings('elterninfoboard_url').'/api/absences/'.$vertretungsplanAbsence->id;


                $client = new \GuzzleHttp\Client();
                $response = $client->delete( $url,[
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => [
                        'key' => settings('api_key_elterninfoboard')
                    ]
                ]);

            }
        } catch (\Exception $e) {
           Log::error(
                'VertretungsplanAbsenceObserver: Senden an API fehlgeschlagen',
                [
                'exception' => $e->getMessage()
                ]
           );
        }


    }

}
