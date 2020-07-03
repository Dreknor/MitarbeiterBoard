<?php

namespace App\Http\Middleware;

use Closure;

class PasswordExpired
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = $request->user();

        if ($user->changePassword) {
            return redirect()->route('password.expired');
        }

        return $next($request);
    }

}
