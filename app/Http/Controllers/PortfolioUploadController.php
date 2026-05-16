<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class PortfolioUploadController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        return view('portfolio.upload', [

            'portfolios' => [],

            'files' => [],
        ]);
    }
}
