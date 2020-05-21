<?php

namespace App\Http\Controllers;

use App\Mail\InvitationMail;
use App\Mail\ReminderMail;
use App\Models\Group;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

class MailController extends Controller
{
    public function remind(){
        $role = Role::where('name', 'Leitung')->first();
        $users = $role->users;

        foreach ($users as $user){
            Mail::to($user)->queue(new ReminderMail());
        }
    }

    public function invitation(){
        $groups = Group::with(['themes', 'users'])->get();
        $date = Carbon::now()->addDays(4);

        foreach ($groups as $group){
            $themes = $group->themes;
            $themes = $themes->filter(function ($theme) use ($date){
               return $theme->completed == 0 and $theme->date->startOfDay()->eq($date->startOfDay());
            });

            if (isset($themes) and count($themes)>0){
                $users = $group->users;
                foreach ($users as $user){
                    Mail::to($user)->queue(new InvitationMail($group->name, $date->format('d.m.Y'), $themes));
                }
            }


        }
    }
}
