<?php

namespace App\Providers;

use Aacotroneo\Saml2\Events\Saml2LoginEvent;
use App\Listeners\LogEmail;
use App\Models\Group;
use App\Models\personal\Holiday;
use App\Models\personal\RosterEvents;
use App\Models\personal\WorkingTime;
use App\Models\Task;
use App\Models\User;
use App\Observers\HolidayObserver;
use App\Observers\RosterEventsObserver;
use App\Observers\TaskObserver;
use App\Observers\WorkingTimeObserver;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Event-Subscriber (hören auf mehrere Events).
     */
    protected $subscribe = [
        LogEmail::class,
    ];

    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        \SocialiteProviders\Manager\SocialiteWasCalled::class => [
            \SocialiteProviders\Keycloak\KeycloakExtendSocialite::class.'@handle',
        ],

        // Personal-Module Events (Listener-Implementierung folgt in späteren Phasen)
        \App\Events\Personal\EmploymentCreated::class => [
            \App\Listeners\Personal\CreateNextcloudFolder::class,
            \App\Listeners\Personal\InitMissingQualifications::class,
            \App\Listeners\Personal\StartOnboardingProcess::class,
            \App\Listeners\Personal\CreateProbationReminder::class,
        ],
        \App\Events\Personal\EmploymentTerminated::class => [
            \App\Listeners\Personal\StartOffboardingProcess::class,
            \App\Listeners\Personal\MoveNextcloudFolder::class,
            \App\Listeners\Personal\CreateRetentionReminders::class,
        ],
        \App\Events\Personal\ProcedureStepCompleted::class => [
            \App\Listeners\Personal\UpdateQualificationFromStep::class,
        ],
        \App\Events\Personal\EmployeeNameChanged::class => [
            \App\Listeners\Personal\RenameNextcloudFolder::class,
        ],
        \App\Events\Personal\EmploymentStatusChanged::class => [
            \App\Listeners\Personal\InvalidateScopeCache::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        RosterEvents::observe(RosterEventsObserver::class);
        WorkingTime::observe(WorkingTimeObserver::class);
        Task::observe(TaskObserver::class);

        Holiday::observe(HolidayObserver::class);

        Event::listen('Aacotroneo\Saml2\Events\Saml2LoginEvent', function (Saml2LoginEvent $event) {
            $messageId = $event->getSaml2Auth()->getLastMessageId();
            $user = $event->getSaml2User();
            $userData = [
                'id' => $user->getUserId(),
                'attributes' => $user->getAttributes(),
                'assertion' => $user->getRawSamlAssertion()
            ];




            $laravelUser = User::where('username', $userData['attributes']['uid'][0])
                ->orWhere('email', $userData['attributes']['mailPrimaryAddress'][0])
                ->first();

            if (!is_null($laravelUser)){
                if ($laravelUser->username == "")
                {
                    $laravelUser->update([
                        'username' => $userData['attributes']['uid'][0]
                    ]);
                } elseif ($laravelUser->username == $userData['attributes']['uid'] and $laravelUser->email != $userData['attributes']['mailPrimaryAddress']){
                    $laravelUser->update([
                        'email' => $userData['attributes']['mailPrimaryAddress'][0]
                    ]);
                }


            } else {
                $laravelUser = new User([
                    'email' => $userData['attributes']['mailPrimaryAddress'][0],
                    'username' => $userData['attributes']['uid'][0],
                    'name' => $userData['attributes']['givenName'][0].' '.$userData['attributes']['sn'][0],
                    'password' => Hash::make(Str::random()),
                    'changePassword' => 0
                ]);

                $laravelUser->save();

                $groups_cn = collect();
                $groups_env = collect();

                //get member_of groups
                foreach ($userData['attributes']['memberOf'] as $memberOf){
                    $arr = explode(',', $memberOf);
                    $cn = explode('-',$arr[0]);

                    if (!is_null($cn) and count($cn) > 1){
                        $groups_cn = Group::whereIn('name', config('config.auth.set_groups'))->get();
                        $laravelUser->groups_rel()->attach($groups_cn);
                    }

                }


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
        });
    }
}
