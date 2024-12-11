<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Mail\newTaskMail;
use App\Models\Group;
use App\Models\GroupTaskUser;
use App\Models\Task;
use App\Models\Theme;
use App\Models\User;
use App\Notifications\Push;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class TaskController extends Controller
{
    private $group;

    public function __construct(Request $request)
    {
        $this->group = Group::where('name', $request->route('groupname'))->first();
    }

    public function store($groupname, Theme $theme, TaskRequest $request)
    {
        if (! auth()->user()->groups()->contains($this->group)) {
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => 'Kein Zugriff auf diese Gruppe',
            ]);
        }

        if ($this->group->name == $request->input('taskable')) {
            $taskable = $this->group;
            $group = true;
            $taskable_user = $this->group->users;
        } else {
            $group = false;
            $user = User::where('id', $request->input('taskable'))->first();
            $taskable_user = collect();
            $taskable_user->push($user);

            if (! $this->group->users->contains($user)) {
                return redirect()->back()->with([
                    'type'    => 'warning',
                    'Meldung' => 'Benutzer ist nicht in der Gruppe',
                ]);
            } else {
                $taskable = $user;
            }
        }

        $task = new Task($request->validated());
        $task->theme_id = $theme->id;

        $taskable->tasks()->save($task);

        foreach ($taskable_user as $user) {
            if ($group) {
                $usersTask=new GroupTaskUser([
                    'taskable_id' => $task->id,
                    'users_id' => $user->id,
                ]);
                $usersTask->save();
                $text = 'Du hast eine neue Gruppenaufgabe im MitarbeiterBoard';
            } else {
                $text = 'Du hast eine neue persönliche Aufgabe im MitarbeiterBoard';
            }

            if ($user->id != \auth()->id()) {
                if ($user->send_mails_if_absence == true or (!$user->hasAbsence(now()) and !$user->hasHoliday(now()))) {
                    Notification::send($user, new Push('neue Aufgabe', $text));
                    Mail::to($user)->queue(new newTaskMail($user->name, $task->date->format('d.m.Y'), $task->task, $task->theme->theme, $group, $this->group->name));

                }
            }
        }

        return redirect()->back();
    }

    public function complete(Task $task)
    {
        $theme = $task->theme;
        if ($task->taskable->name == auth()->user()->name) {
            $task->update([
                'completed' => 1,
            ]);

            return redirect()->back()->with(
                [
                    'type'    => 'success',
                    'Meldung' => 'Aufgabe erledigt',
                ]
            );
        } elseif ($task->taskable_type == "App\Models\Group"){
            $task->taskUsers()
                ->where('users_id', auth()->id())
                ->where('taskable_id', $task->id)
                ->delete();

            return redirect()->back()->with(
                [
                    'type'    => 'success',
                    'Meldung' => 'Aufgabe erledigt',
                ]
            );
        }

        if (session()->previousUrl() and session()->previousUrl() != null){
            return redirect()->back();
        } else {
            return redirect()->url('/groups/'.$theme->group->name.'/themes/'.$theme->id);
        }
    }
}
