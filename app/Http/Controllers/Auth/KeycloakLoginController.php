<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
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
        dd($user);
    }

}
