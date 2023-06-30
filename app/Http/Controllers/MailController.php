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
use App\Support\Collection;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

class MailController extends Controller
{
    public function remind()
    {
        $role = Role::where('name', 'Leitung')->first();
        $users = $role->users;

        foreach ($users as $user) {
            Mail::to($user)->queue(new ReminderMail());
        }
    }

    public function invitation()
    {
        $groups = Group::where('protected', 1)->with(['users'])->get();

        foreach ($groups as $group) {
            $date = Carbon::today()->addDays(max(1,$group->InvationDays));
            $themes = $group->themes()->whereDate('date', $date)->where('completed', 0)->get();
            $themes = $themes->filter(function ($theme) use ($date) {
                return $theme->completed == 0 and $theme->date->startOfDay()->eq($date->startOfDay());
            })->sortByDesc([
                    ['priority', 'desc'],
                    ['theme', 'asc'],
            ]);

            if (isset($themes) and count($themes) > 0) {
                $users = $group->users;

                foreach ($users as $user) {
                    Mail::to($user)->queue(new InvitationMail($group->name, $date->format('d.m.Y'), $themes));
                }
            }

        }
    }

    public function remindTaskMail()
    {
        $users = User::whereHas('tasks', function ($query){
            return $query->where('completed', 0)
                ->where('date', '<=',Carbon::now()->addDays(config('config.tasks.remind'))->format('Y-m-d'));
        })
            ->orWhereHas('group_tasks')
            ->get();

        foreach ($users as $user){
            $tasks = $user->tasks()->where('date', '<=',Carbon::now()->addDays(config('config.tasks.remind'))->format('Y-m-d'))->get();
            $group_tasks = $user->group_tasks->load('task')->filter(function ($task) {
                if ($task->task?->date->lessThan(Carbon::now()->addDays(config('config.tasks.remind')))){
                    return $task;
                }
            });

            foreach ($group_tasks as $group_task){
                $tasks = $tasks->push($group_task->task);
            }

            Mail::to($user)->queue(new remindTaskMail($user->name, $tasks));
        }
    }
}
