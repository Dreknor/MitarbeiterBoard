<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

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
        dd($user->user);

        Auth::loginUsingId($laravelUser->id);

        session()->regenerate();

        return redirect(url('/'));
        //dd($laravelUser);
    }

}
