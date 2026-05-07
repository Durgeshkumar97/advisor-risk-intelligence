<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use App\Models\Portfolio;
use App\Models\UploadedFile;

class PortfolioUploadController extends Controller
{
    public function index()
    {
        $portfolios = Portfolio::where('user_id', Auth::id())
            ->latest()
            ->get();

        $files = UploadedFile::where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('user.uploads.index', compact(
            'portfolios',
            'files'
        ));
    }

    public function store(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $request->validate([

            'portfolio_id' => [
                'nullable',
                'exists:portfolios,id'
            ],

            'file' => [
                'required',
                'file',
                'mimes:csv,xlsx,xls,pdf',
                'max:10240'
            ]

        ]);

        /*
        |--------------------------------------------------------------------------
        | File Upload
        |--------------------------------------------------------------------------
        */

        $file = $request->file('file');

        $storedName = Str::uuid() . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs(
            'portfolio_uploads',
            $storedName,
            'public'
        );

        /*
        |--------------------------------------------------------------------------
        | Save Record
        |--------------------------------------------------------------------------
        */

        UploadedFile::create([

            'user_id' => Auth::id(),

            'portfolio_id' => $request->portfolio_id,

            'original_name' => $file->getClientOriginalName(),

            'stored_name' => $storedName,

            'file_path' => $path,

            'mime_type' => $file->getMimeType(),

            'file_size' => $file->getSize(),

            'status' => 'uploaded',

            'meta' => [
                'uploaded_at' => now()
            ]

        ]);

        return back()->with(
            'success',
            'Portfolio uploaded successfully.'
        );
    }
}