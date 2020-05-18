<?php

namespace App\Http\Controllers;

use App\Mail\ReminderMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailController extends Controller
{
    public function remind(){
        $users = User::all();
        foreach ($users as $user){
            Mail::to($user)->queue(new ReminderMail());
        }


        return view('home');
    }
}
