<?php

namespace App\Observers;

use App\Models\DailyNews;
use Illuminate\Support\Facades\Log;

class VertretungNewsObserver
{
    /**
     * Handle the Vertretung "created" event.
     */
    public function created(DailyNews $news): void
    {

        try {

            if (settings('vertretungsplan_send_elterninfoboard') ==  1 and settings('elterninfoboard_url') != null){
                $url = settings('elterninfoboard_url').'/api/news';


                $client = new \GuzzleHttp\Client();
                $response = $client->post( $url,[
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => [
                        'id' => $news->id,
                        'start' => $news->date_start->format('Y-m-d'),
                        'end' => $news->date_end->format('Y-m-d'),
                        'news' => $news->news,
                        'key' => settings('api_key_elterninfoboard')
                    ]
                ]);

            }
        } catch (\Exception $e) {
            Log::error('VertretungsplanEventObserver: Senden an API fehlgeschlagen',[
                'exception' => $e->getMessage()]);
        }
    }

    /**
     * Handle the Vertretung "updated" event.
     */
    public function updated(DailyNews $news): void
    {

    }

    /**
     * Handle the Vertretung "deleted" event.
     */
    public function deleted(DailyNews $news): void
    {
        try {
            if (settings('vertretungsplan_send_elterninfoboard') ==  1 and settings('elterninfoboard_url') != null){
                $url = settings('elterninfoboard_url').'/api/news/'.$news->id;


                $client = new \GuzzleHttp\Client();
                $response = $client->delete( $url,[
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => [
                        'key' => settings('api_key_elterninfoboard')
                    ]
                ]);

            }
        } catch (\Exception $e) {
            Log::error('VertretungsplanEventObserver: Senden an API fehlgeschlagen',[
                'exception' => $e->getMessage()]);
        }


    }

    /**
     * Handle the Vertretung "restored" event.
     */
    public function restored(DailyNews $news): void
    {
        //
    }

    /**
     * Handle the Vertretung "force deleted" event.
     */
    public function forceDeleted(DailyNews $news): void
    {
        //
    }
}
