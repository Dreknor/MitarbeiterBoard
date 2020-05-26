<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Models\Group;
use App\Models\Task;
use App\Models\Theme;
use App\Models\User;
use App\Notifications\Push;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class TaskController extends Controller
{
    private $group;

    public function __construct(Request $request)
    {
        $this->group = Group::where('name', $request->route('groupname'))->first();
    }

    public function store($groupname,Theme $theme, TaskRequest $request){

        if (!auth()->user()->groups->contains($this->group)){
            return redirect()->back()->with([
                'type'    => 'warning',
                'Meldung' => "Kein Zugriff auf diese Gruppe"
            ]);
        }

        if ($this->group->name == $request->input('taskable')){
            $taskable = $this->group;
            $taskable_user = $this->group->users;
        } else {
            $user = User::where('id', $request->input('taskable'))->first();
            $taskable_user = $user;
            if (!$this->group->users->contains($user)){
                return redirect()->back()->with([
                    'type'    => 'warning',
                    'Meldung' => "Benutzer ist nicht in der Gruppe"
                ]);
            } else {
               $taskable = $user;
            }
        }

        $task = new Task($request->validated());
        $task->theme_id = $theme->id;

        $taskable->tasks()->save($task);

        Notification::send($taskable_user , new Push('neue Aufgabe', $task->task. " bis ".$task->date->format('d.m.Y')));

        return redirect()->back();

    }

    public function complete(Task $task){
        if ($task->taskable->name == auth()->user()->name){
            $task->update([
                'completed' => 1
            ]);

        }
        return redirect()->back();
    }
}
