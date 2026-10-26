<?php

namespace App\Controllers;

class WebController extends BaseController
{
    public function login(): string
    {
        return view('auth/login');
    }

    public function index(): string
    {
        return view('app');
    }

    /**
     * Google OAuth2 Callback — halaman ini menerima redirect dari Google
     * setelah user memberi izin. Halaman mengirim code & state ke API endpoint
     * via fetch() lalu redirect ke SPA halaman Schedule.
     */
    public function googleOauthCallback(): string
    {
        return view('auth/google_oauth_callback');
    }
}
