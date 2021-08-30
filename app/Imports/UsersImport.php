<?php

namespace App\Imports;

use App\Models\Group;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UsersImport implements ToCollection, WithHeadingRow
{
    /**
    * @param Collection $collection
    */
    public function collection(Collection $collection)
    {
        foreach ($collection as $row){
            if (!is_null($row['name']) and !is_null($row['email'])) {
                $user = User::firstOrCreate(
                    [
                        'email' => $row['email']
                    ],
                    [
                        'name' => $row['name'],
                        'changePassword' => 1,
                        'password' => !is_null($row['password']) ? Hash::make($row['password'] ): Hash::make(Carbon::now()->format('Ymd'))
                    ]);

                if (!is_null($row['roles'])){

                    //Leerzeichen entfernen
                    $searchString = " ";
                    $replaceString = "";
                    $outputString = str_replace($searchString, $replaceString, $row['roles']);
                    $roles = explode(',', $outputString);
                    $user->syncRoles($roles);
                }

                if (!is_null($row['groups'])){
                    //Leerzeichen entfernen
                    $searchString = " ";
                    $replaceString = "";
                    $outputString = str_replace($searchString, $replaceString, $row['groups']);

                    $groups = explode(',', $outputString);
                    $groups = Group::whereIn('name', $groups)->get();
                    $user->groups_rel()->detach();
                    $user->groups_rel()->attach($groups);
                }

            }
        }
    }
}
