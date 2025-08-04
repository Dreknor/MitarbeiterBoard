<?php

namespace App\Mail;

use App\Models\Meeting;
use App\Models\Group;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MeetingInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $meeting;
    public $group;
    public $user;
    public $messageText;
    public $absender;

    /**
     * Create a new message instance.
     */
    public function __construct(Meeting $meeting, Group $group, User $user, $messageText = null, $absender = null)
    {
        $this->meeting = $meeting;
        $this->group = $group;
        $this->user = $user;
        $this->messageText = $messageText;
        $this->absender = $absender ?: '';
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Einladung zum Meeting: ' . $this->meeting->title)
            ->view('mails.meeting_invitation');
    }
}

