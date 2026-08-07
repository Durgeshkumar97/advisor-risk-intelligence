<?php

namespace App\Http\Controllers;

class PageController extends Controller
{
    public function home()
    {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('home');
    }
}
