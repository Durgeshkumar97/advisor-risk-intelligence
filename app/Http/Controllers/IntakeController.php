<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class IntakeController extends Controller
{
    public function submit(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'portfolio_value' => 'required',
            'holdings' => 'required|file|mimes:pdf,xlsx,xls,csv,jpg,jpeg,png,webp|max:10240'
        ]);

        $filePath = $request->file('holdings')->store('uploads');

        try {
            Mail::raw(
                "New portfolio intake received:

Name: {$request->name}
Email: {$request->email}
Portfolio Value: {$request->portfolio_value}
File: storage/app/{$filePath}",
                function ($message) {
                    $message->to('durgeshduklan5@gmail.com')
                            ->subject('New Portfolio Intake Submission');
                }
            );
        } catch (\Exception $e) {
            dd($e->getMessage());
        }
        return back()->with('success', 'Submission received successfully.');
    }
}

