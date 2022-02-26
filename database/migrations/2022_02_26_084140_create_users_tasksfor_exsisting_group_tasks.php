<?php

use App\Models\GroupTaskUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tasks = \App\Models\Task::where('taskable_type', 'App\Models\Group')->whereDate('date', '>', \Carbon\Carbon::today())->get();
        foreach ($tasks as $task){
            $taskable = $task->taskable;
            $taskable_user = $taskable->users;

            foreach ($taskable_user as $user){
                $usersTask=new GroupTaskUser([
                    'taskable_id' => $task->id,
                    'users_id' => $user->id,
                ]);
                $usersTask->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users_tasksfor_exsisting_group_tasks');
    }
};
