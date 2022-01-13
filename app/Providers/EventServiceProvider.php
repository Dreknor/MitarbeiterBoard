<?php

namespace App\Providers;

use Aacotroneo\Saml2\Events\Saml2LoginEvent;
use App\Listeners\LogEmail;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        MessageSending::class => [
           LogEmail::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {

        Event::listen('Aacotroneo\Saml2\Events\Saml2LoginEvent', function (Saml2LoginEvent $event) {
            $messageId = $event->getSaml2Auth()->getLastMessageId();
            // Add your own code preventing reuse of a $messageId to stop replay attacks

            $user = $event->getSaml2User();
            $userData = [
                'id' => $user->getUserId(),
                'attributes' => $user->getAttributes(),
                'assertion' => $user->getRawSamlAssertion()
            ];


            $laravelUser = User::where('username', $userData['attributes']['uid'])
                ->orWhere('email', $userData['attributes']['mailPrimaryAddress'])
                ->first();

            if (!is_null($laravelUser)){
                if ($laravelUser->username == "")
                {
                    $laravelUser->update([
                        'username' => $userData['attributes']['uid'][0]
                    ]);
                } elseif ($laravelUser->username == $userData['attributes']['uid'] and $laravelUser->email != $userData['attributes']['mailPrimaryAddress']){
                    $laravelUser->update([
                        'email' => $userData['attributes']['mailPrimaryAddress']
                    ]);
                }


            } else {
                $laravelUser = new User([
                    'email' => $userData['attributes']['mailPrimaryAddress'],
                    'username' => $userData['attributes']['uid'][0],
                    'name' => $userData['attributes']['givenName'][0].' '.$userData['attributes']['sn'][0],
                    'password' => Hash::make(Str::random(16)),
                    'changePassword' => 1
                ]);

                ddd($laravelUser);
                $laravelUser->save();

            }

            Auth::loginUsingId($laravelUser->id);

            session()->regenerate();

            return redirect(url('/'));
        });
    }
}
