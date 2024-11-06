<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SsoController extends Controller
{
    public function redirectToProvider()
    {
        return Socialite::driver('keycloak')->redirect();

    }

    public function handleProviderCallback()
    {
        $user = Socialite::driver('keycloak')->user();

    }
}
