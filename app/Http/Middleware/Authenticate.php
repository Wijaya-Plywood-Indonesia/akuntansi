<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // Sesuaikan dengan nama panel Filament Anda
        // Jika panel ID = 'admin' maka route-nya seperti di bawah
        return route('filament.admin.auth.login');
    }
}
