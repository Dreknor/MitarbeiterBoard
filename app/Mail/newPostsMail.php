<?php

namespace App\Mail;

use App\Support\Collection;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class newPostsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $posts;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($posts)
    {
        $this->posts = $posts;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this
            ->subject('Mitteilungen im '.config('app.name'))
            ->view('mails.newPostsMail', [
                'posts'    =>$this->posts,
            ]);
    }
}
