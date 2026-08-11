<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function loginForm(?string $error = null)
    {
        return view('login', compact('error'));
    }

    public function login(Request $request)
    {
        $login = trim($request->input('login', ''));
        $password = $request->input('password', '');

        $adminLogin = config('app.admin_login', 'admin');
        $adminPasswordHash = config('app.admin_password_hash', '');

        if ($login === $adminLogin && $adminPasswordHash !== '' && password_verify($password, $adminPasswordHash)) {
            session(['is_admin' => true]);
            return redirect('/agents');
        }

        return view('login', ['error' => 'Неверный логин или пароль']);
    }

    public function logout()
    {
        session()->forget('is_admin');
        session()->regenerate();

        return redirect('/login');
    }
}
