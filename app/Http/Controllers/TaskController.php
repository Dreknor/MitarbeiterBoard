<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Models\Group;
use App\Models\Task;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        } else {
            $user = User::where('id', $request->input('taskable'))->first();

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

        return redirect()->back();

    }
}
