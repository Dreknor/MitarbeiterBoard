<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;

class LogLevelServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {

        try {
            $logLevel = DB::table('settings')->where('setting', 'log_level')->value('value');
            $logChannel = DB::table('settings')->where('setting', 'log_channel')->value('value');
            if ($logChannel) {

                if ($logChannel == 'stack') {
                    Config::set('logging.default', 'stack');

                    foreach (Config::get('logging.channels.stack') as $key => $value) {
                        if ($key == 'channels') {
                            foreach ($value as $channel) {
                                Config::set('logging.channels.' . $channel . '.level', $logLevel);
                            }
                        }
                    }
                } else {
                    Config::set('logging.default', $logChannel);
                    Config::set('logging.channels.' . $logChannel . '.level', $logLevel);
                }
            }
        } catch (\Exception $e) {
            Log::error('Fehler beim Laden des Loglevels aus der Datenbank: ' . $e->getMessage());
        }
    }
}
