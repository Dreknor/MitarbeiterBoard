<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogEmail
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(MessageSending $event)
    {
        $message = $event->message;

        $seeting = Cache::remember('email_setting', 360, function () {
            $setting = DB::table('settings')->where('setting', 'mail_log')->value('value');
            return $setting;
        });

        if ($seeting == '1') {

            $log = [
                'to' => $message->getTo()[0]->getAddress(),
                'subject' => $message->getSubject(),
                'body' => $message->getBody(),
                'headers' => $message->getHeaders(),
            ];

            Log::info('Email sent to '. $message->getTo()[0]->getName(), $log);
        }
    }
}
