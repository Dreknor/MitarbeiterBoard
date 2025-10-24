<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class StepErinnerungMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;
    public $steps;


    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($name, Array $steps)
    {
        $this->name = $name;
        $this->steps = $steps;

        Log::debug('StepErinnerungMail constructor', [
            'name' => $this->name,
            'steps' => $this->steps,
        ]);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // Eindeutige Markierung, damit man nach dem Deploy schnell erkennt, welche Version verwendet wird.
        $marker = '[deploy-check ' . Carbon::now()->format('Y-m-d H:i:s') . ']';

        Log::debug('StepErinnerungMail build', [
            'marker' => $marker,
            'name' => $this->name,
            'steps' => $this->steps,
        ]);
        // Use warning level to ensure it's logged also on higher log levels in production
        Log::warning('StepErinnerungMail build (deploy-check)', [
            'marker' => $marker,
            'name' => $this->name,
            'steps' => $this->steps,
        ]);

        // Subject mit Marker anhängen und Marker auch an die View übergeben.
        $m = $this->subject('Offene Prozessschritte ' . $marker)
            ->view('mails.remindStepMail', [
                'name' => $this->name,
                'steps' => $this->steps,
                'deploy_marker' => $marker,
            ]);

        // Add a custom header so the deploy marker is visible in the raw email headers
        $m->withSymfonyMessage(function ($message) use ($marker) {
            if (method_exists($message, 'getHeaders')) {
                $message->getHeaders()->addTextHeader('X-Deploy-Marker', $marker);
            }
        });

        return $m;
    }
}
