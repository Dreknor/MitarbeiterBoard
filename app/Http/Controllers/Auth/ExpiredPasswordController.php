<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordExpiredRequest;
use Illuminate\Support\Facades\Hash;

class ExpiredPasswordController extends Controller
{
    public function expired()
    {
        return view('auth.passwords.expired');
    }

    public function postExpired(PasswordExpiredRequest $request)
    {
        // Checking current password
        if (! Hash::check($request->current_password, $request->user()->password)) {
            return redirect()->back()->withErrors(['current_password' => 'Current password is not correct']);
        }

        $request->user()->update([
            'password' => bcrypt($request->password),
            'changePassword' => 0,
        ]);

        return redirect(url('home'))->with(['status' => 'Password changed successfully']);
    }
}
