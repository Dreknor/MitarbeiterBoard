<?php

namespace App\Http\Controllers;

use App\Mail\InvitationMail;
use App\Mail\ReminderMail;
use App\Mail\remindTaskMail;
use App\Models\Group;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Push;
use App\Notifications\PushNews;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
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
        $date = Carbon::now()->addDays(config('config.themes.addDays'));

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

    public function remindTaskMail(){
        $tasks = Task::where('completed', 0)->where('date', Carbon::now()->addDays(config('config.tasks.remind'))->format('Y-m-d'))->get();

        foreach ($tasks as $task){
            if($task->taskable instanceof Group){
                foreach ($task->taskable->users as $user){
                    Mail::to($user)->queue(new remindTaskMail($user->name, $task->date->format('d.m.Y'), $task->task, $task->theme->theme, true));
                }
            } else {
                Mail::to($task->taskable)->queue(new remindTaskMail($user->name, $task->date->format('d.m.Y'), $task->task, $task->theme->theme, false));
            }

        }
return view('welcome');
    }
}
