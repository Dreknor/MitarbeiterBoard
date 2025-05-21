<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;

class KeycloakLoginController extends Controller
{

    public function login()
    {
        try {
            return Socialite::driver('keycloak')->scopes([
                'openid',
                'profile',
                'email',
                'roles',
                'memberof'
            ])->redirect();
        } catch (\Exception $e) {
            Log::error('OIDC: Login failed for redirect', [
                'exception' => $e->getMessage(),
                'url' => url()->current(),
            ]);

            return redirect()->route('login')->with([
                'type' => 'danger',
                'Meldung' => 'Login failed']);
        }


    }
    public function auth()
    {
        try {
            $user = Socialite::driver('keycloak')->user();

        } catch (\Exception $e) {
            Log::error('OIDC: Answer Login failed: No User', [
                'exception' => $e->getMessage(),
                'url' => url()->current(),
            ]);

            return redirect()->route('login')->with([
                'type' => 'danger',
                'Meldung' => 'Login failed']);
        }


        $laravelUser = User::where('username', $user->nickname)
            ->orWhere('email', $user->email)
            ->first();


        if (!$laravelUser) {

            Log::info('OIDC: User not found: ' . $user->nickname,[
                'user' => $user,
                'email' => $user->email,
                'nickname' => $user->nickname,
            ]);



            if (!$user->nickname) {
                $name = explode('@', $user->email);
                $name = explode('.', $name[0]);
                $name = implode(' ', $name);
                $name = Str::title($name);

            } else {
                $name = $user->nickname;
            }

            $laravelUser = User::create([
                'username' => $name,
                'email' => $user->email,
                'password' => bcrypt(Carbon::now()->timestamp),
            ]);

            Log::info('OIDC: User created: ' . $laravelUser->username,[
                'user' => $user,
                'email' => $user->email,
                'nickname' => $user->nickname,
                'memberOf' => $user->user['memberof'],
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

            Log::info('OIDC: Groups created: ' . implode(',', $groups),[
                'user' => $user,
                'email' => $user->email,
                'nickname' => $user->nickname,
                'memberOf' => $user->user['memberof'],
                'groups' => $groups,
                'roles' => $roles,
            ]);

            $laravelUser->groups_rel()->attach($groups);
            $laravelUser->roles()->attach($roles);
        }


        Log::info('OIDC: User logged in by KeyCloak: ' . $laravelUser->username);

        Auth::loginUsingId($laravelUser->id);
        session()->regenerate();

        return redirect(url('/'));
    }

}
