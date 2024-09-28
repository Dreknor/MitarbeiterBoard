<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Carbon\Carbon;
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


        if (!$laravelUser) {
            $laravelUser = User::create([
                'username' => $user->nickname,
                'email' => $user->email,
                'password' => bcrypt(Carbon::now()->timestamp),
            ]);

            $groups = config('config.auth.set_groups') ?? [];
            $roles = config('config.auth.set_roles') ?? [];

            foreach ($user->user['memberof'] as $memberOf){
                $cn = explode('-',$memberOf);
                if (!is_null($cn) and count($cn) > 1){
                    foreach ($cn as $c){
                        if (!in_array($c, $groups)){
                            array_push($groups, $c);
                        }
                        if (!in_array($c, $roles)){
                            array_push($roles, $c);
                        }
                    }
                }
            }

            $laravelUser->groups_rel()->attach($groups);
            $laravelUser->roles()->attach($roles);
        }


        Auth::loginUsingId($laravelUser->id);
        session()->regenerate();

        return redirect(url('/'));
    }

}
