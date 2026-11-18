<?php

namespace App\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NewUiAuthController extends Controller
{
    public function create()
    {
        if (Auth::check()) {
            return redirect()->route('newui');
        }

        return view('newui.auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'ایمیل را وارد کنید.',
            'email.email' => 'ایمیل معتبر نیست.',
            'password.required' => 'گذرواژه را وارد کنید.',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'ایمیل یا گذرواژه صحیح نیست.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user = $request->user();
        $panel = Filament::getPanel('admin');

        if (! $panel || ! $user->canAccessPanel($panel)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors(['email' => 'این حساب اجازه ورود به سامانه را ندارد.'])->onlyInput('email');
        }

        return redirect()->intended(route('newui'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
