<?php

namespace App\Observers;

use App\Models\Vertretung;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VertretungObserver
{
    /**
     * Handle the Vertretung "created" event.
     */
    public function created(Vertretung $vertretung): void
    {
        try {
            if (settings('vertretungsplan_send_elterninfoboard') ==  1 and settings('elterninfoboard_url') != null){
                $url = settings('elterninfoboard_url').'/api/vertretungen';



                $client = new \GuzzleHttp\Client();
                $response = $client->post( $url,[
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => [
                        'id' => $vertretung->id,
                        'date' => $vertretung->date->format('Y-m-d'),
                        'klasse' => $vertretung->klasse->name,
                        'stunde' => Str::before($vertretung->stunde, '..').'.',
                        'altFach' => $vertretung->altFach,
                        'neuFach' => $vertretung->neuFach,
                        'lehrer' => optional($vertretung->lehrer)->shortname,
                        'comment' => $vertretung->comment,
                        'key' => settings('api_key_elterninfoboard')
                    ]
                ]);

                Log::info('VertretungsplanObserver: ', [
                    'id' => $vertretung->id,
                    'date' => $vertretung->date->format('Y-m-d'),
                    'klasse' => $vertretung->klasse->name,
                    'stunde' => Str::before($vertretung->stunde, '..').'.',
                    'altFach' => $vertretung->altFach,
                    'neuFach' => $vertretung->neuFach,
                    'lehrer' => optional($vertretung->lehrer)->shortname,
                    'comment' => $vertretung->comment,
                    'url' => $url,
                    'Antwort' => $response->getBody(),
                ]);



            }
        } catch (\Exception $e) {
            Log::error('VertretungsplanObserver: Senden an API nicht möglich', [
                'Meldung' => $e->getMessage()
            ]);
        }

    }

    /**
     * Handle the Vertretung "updated" event.
     */
    public function updated(Vertretung $vertretung): void
    {

        try {
            if (settings('vertretungsplan_send_elterninfoboard') ==  1 and settings('elterninfoboard_url') != null){
                $url = settings('elterninfoboard_url').'/api/vertretungen/'.$vertretung->id;


                $client = new \GuzzleHttp\Client();
                $response = $client->put( $url,[
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => [
                        'id' => $vertretung->id,
                        'date' => $vertretung->date->format('Y-m-d'),
                        'klasse' => $vertretung->klasse->name,
                        'stunde' => $vertretung->stunde,
                        'altFach' => $vertretung->altFach,
                        'neuFach' => $vertretung->neuFach,
                        'lehrer' => optional($vertretung->lehrer)->shortname,
                        'comment' => $vertretung->comment,
                        'key' => settings('api_key_elterninfoboard')
                    ]
                ]);

            }
        } catch (\Exception $e) {
            Log::error('VertretungsplanObserver: Senden an API nicht möglich', [
                'Meldung' => $e->getMessage()
            ]);
        }

    }

    /**
     * Handle the Vertretung "deleted" event.
     */
    public function deleted(Vertretung $vertretung): void
    {
        try {
            if (settings('vertretungsplan_send_elterninfoboard') ==  1 and settings('elterninfoboard_url') != null){
                $url = settings('elterninfoboard_url').'/api/vertretungen/'.$vertretung->id;


                $client = new \GuzzleHttp\Client();
                $response = $client->delete( $url,[
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => [
                        'key' => settings('api_key_elterninfoboard')
                    ]
                ]);

            }
        } catch (\Exception $e) {
            Log::error('VertretungsplanObserver: Senden an API nicht möglich', [
                'Meldung' => $e->getMessage()
            ]);
        }


    }

    /**
     * Handle the Vertretung "restored" event.
     */
    public function restored(Vertretung $vertretung): void
    {
        //
    }

    /**
     * Handle the Vertretung "force deleted" event.
     */
    public function forceDeleted(Vertretung $vertretung): void
    {
        //
    }
}
