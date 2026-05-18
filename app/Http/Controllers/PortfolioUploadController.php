<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPortfolioFile;

use App\Models\Portfolio;
use App\Models\PortfolioFile;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PortfolioUploadController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $user = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | USER PORTFOLIOS
        |--------------------------------------------------------------------------
        */

        $portfolios = Portfolio::query()

            ->where('user_id', $user->id)

            ->latest()

            ->get();

        /*
        |--------------------------------------------------------------------------
        | USER FILES
        |--------------------------------------------------------------------------
        */

        $files = PortfolioFile::query()

            ->where('user_id', $user->id)

            ->latest()

            ->get();

        /*
        |--------------------------------------------------------------------------
        | VIEW
        |--------------------------------------------------------------------------
        */

        return view('portfolio.upload', [

            'portfolios' => $portfolios,

            'files' => $files,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([

            'portfolio_id' => [
                'nullable',
                'exists:portfolios,id',
            ],

            'file' => [
                'required',
                'file',

                /*
                |--------------------------------------------------------------------------
                | ALLOWED TYPES
                |--------------------------------------------------------------------------
                */

                'mimes:pdf,csv,xlsx,xls',

                /*
                |--------------------------------------------------------------------------
                | MAX SIZE
                |--------------------------------------------------------------------------
                |
                | 10MB
                |
                */

                'max:10240',
            ],
        ]);

        try {

            $user = Auth::user();

            /*
            |--------------------------------------------------------------------------
            | STORE FILE
            |--------------------------------------------------------------------------
            */

            $storedPath = $request->file('file')->store(
                '',
                'portfolios'
            );

            /*
            |--------------------------------------------------------------------------
            | CREATE DB RECORD
            |--------------------------------------------------------------------------
            */

            $portfolioFile = PortfolioFile::create([

                'user_id' => $user->id,

                'portfolio_id' =>
                $validated['portfolio_id'] ?? null,

                'original_name' =>
                $request->file('file')
                    ->getClientOriginalName(),

                'stored_name' =>
                basename($storedPath),

                'path' => $storedPath,

                'mime_type' =>
                $request->file('file')
                    ->getMimeType(),

                'file_size' =>
                $request->file('file')
                    ->getSize(),

                'status' => 'pending',
            ]);

            /*
            |--------------------------------------------------------------------------
            | DISPATCH PROCESSING JOB
            |--------------------------------------------------------------------------
            */

            ProcessPortfolioFile::dispatch(
                $portfolioFile
            );

            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            return redirect()

                ->route('portfolio.upload')

                ->with(
                    'success',
                    'Portfolio uploaded successfully. Processing started.'
                );
        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | CLEANUP FAILED STORAGE
            |--------------------------------------------------------------------------
            */

            if (
                isset($storedPath)
                &&
                Storage::disk('portfolios')
                ->exists($storedPath)
            ) {

                Storage::disk('portfolios')
                    ->delete($storedPath);
            }

            /*
            |--------------------------------------------------------------------------
            | LOG ERROR
            |--------------------------------------------------------------------------
            */

            Log::error('Portfolio upload failed.', [

                'message' => $e->getMessage(),

                'trace' => $e->getTraceAsString(),

                'user_id' => Auth::id(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | RESPONSE
            |--------------------------------------------------------------------------
            */

            return back()

                ->withErrors([
                    'file' =>
                    'Upload failed. Please try again.',
                ])

                ->withInput();
        }
    }
}
