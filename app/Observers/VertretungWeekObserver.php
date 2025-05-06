<?php

namespace App\Observers;

use App\Models\VertretungsplanWeek;
use Illuminate\Support\Facades\Log;

class VertretungWeekObserver
{
    /**
     * Handle the Vertretung "created" event.
     */
    public function created(VertretungsplanWeek $week): void
    {

        try {

            if (settings('vertretungsplan_send_elterninfoboard') ==  1 and settings('elterninfoboard_url') != null){
                $url = settings('elterninfoboard_url').'/api/week';


                $client = new \GuzzleHttp\Client();
                $response = $client->post( $url,[
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => [
                        'id' => $week->id,
                        'start' => $week->week->format('Y-m-d'),
                        'type' => $week->type,
                        'key' => settings('api_key_elterninfoboard')
                    ]
                ]);

            }
        } catch (\Exception $e) {
            Log::error('Vertretungsplan create-Event fehlgeschlagen',[
                'exception' => $e->getMessage()]);
        }
    }

    /**
     * Handle the Vertretung "updated" event.
     */
    public function updated(VertretungsplanWeek $week): void
    {
        try {
            if (settings('vertretungsplan_send_elterninfoboard') ==  1 and settings('elterninfoboard_url') != null){
                $url = settings('elterninfoboard_url').'/api/week/'.$week->id;

                $client = new \GuzzleHttp\Client();
                $response = $client->put( $url,[
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => [
                        'id' => $week->id,
                        'week' => $week->week->format('Y-m-d'),
                        'type' => $week->type,
                        'key' => settings('api_key_elterninfoboard')
                    ]
                ]);

            }
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
        }
    }



    /**
     * Handle the Vertretung "deleted" event.
     */
    public function deleted(VertretungsplanWeek $week): void
    {
        try {
            if (settings('vertretungsplan_send_elterninfoboard') ==  1 and settings('elterninfoboard_url') != null){
                $url = settings('elterninfoboard_url').'/api/week/'.$week->id;


                $client = new \GuzzleHttp\Client();
                $response = $client->delete( $url,[
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => [
                        'key' => settings('api_key_elterninfoboard')
                    ]
                ]);

            }
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
        }


    }

    /**
     * Handle the Vertretung "restored" event.
     */
    public function restored(VertretungsplanWeek $week): void
    {
        //
    }

    /**
     * Handle the Vertretung "force deleted" event.
     */
    public function forceDeleted(VertretungsplanWeek $week): void
    {
        //
    }
}
