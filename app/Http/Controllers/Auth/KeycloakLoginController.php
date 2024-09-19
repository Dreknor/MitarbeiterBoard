<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;

class KeycloakLoginController extends Controller
{

    public function login()
    {
        return Socialite::driver('keycloak')->scopes([
        'openid',
        'profile',
        'email',
        'roles',
        'memberof'
    ])->redirect();

    }
    public function auth()
    {
        $user = Socialite::driver('keycloak')->user();

        $laravelUser = User::where('username', $user->nickname)
            ->orWhere('email', $user->email)
            ->first();

        foreach ($user->user['memberof'] as $memberOf){
            $cn = explode('-',$memberOf);
            dd($cn);
            if (!is_null($cn) and count($cn) > 1){
                $groups_cn = Group::whereIn('name', config('config.auth.set_groups'))->get();
                $laravelUser->groups_rel()->attach($groups_cn);
            }

        }
        if (!$laravelUser) {
            $laravelUser = User::create([
                'username' => $user->nickname,
                'email' => $user->email,
                'password' => bcrypt($user->sub),
            ]);




                if (config('config.auth.set_groups') != "" and is_array(config('config.auth.set_groups') )){
                    $groups_env = Group::whereIn('name', config('config.auth.set_groups'))->get();
                    $laravelUser->groups_rel()->attach($groups_env);
                }


            if (config('config.auth.set_roles') != "" and is_array(config('config.auth.set_roles') )){
                $roles = Role::whereIn('name', config('config.auth.set_roles'))->get();
                $laravelUser->roles()->attach($roles);

            }

        }




        Auth::loginUsingId($laravelUser->id);

        session()->regenerate();

        return redirect(url('/'));
        //dd($laravelUser);
    }

}
