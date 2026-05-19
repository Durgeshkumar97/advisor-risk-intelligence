<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePortfolioUploadRequest;
use App\Jobs\ProcessPortfolioFile;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Services\PortfolioUploadException;
use App\Services\PortfolioUploadService;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PortfolioUploadController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct(
        private readonly PortfolioUploadService $uploadService
    ) {
    }

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

    public function store(
        StorePortfolioUploadRequest $request
    ) {
        try {

            $user = Auth::user();

            /*
            |--------------------------------------------------------------------------
            | HANDLE FILE UPLOAD
            |--------------------------------------------------------------------------
            */

            $portfolioFile = $this->uploadService
                ->handleUpload(

                    userId: $user->id,

                    file: $request->getFile(),

                    portfolioId:
                        $request->getPortfolioId(),
                );

            /*
            |--------------------------------------------------------------------------
            | DISPATCH BACKGROUND PROCESSING
            |--------------------------------------------------------------------------
            */

            ProcessPortfolioFile::dispatch(
                $portfolioFile
            );

            /*
            |--------------------------------------------------------------------------
            | SUCCESS RESPONSE
            |--------------------------------------------------------------------------
            */

            return redirect()

                ->route('portfolio.upload')

                ->with(
                    'success',
                    'Portfolio uploaded successfully. Processing started.'
                );

        } catch (PortfolioUploadException $e) {

            /*
            |--------------------------------------------------------------------------
            | EXPECTED BUSINESS ERROR
            |--------------------------------------------------------------------------
            */

            Log::warning(
                'Portfolio upload validation/business error.',
                [

                    'message' => $e->getMessage(),

                    'user_id' => Auth::id(),
                ]
            );

            return back()

                ->withErrors([

                    'file' =>
                        $e->getUserMessage(),
                ])

                ->withInput();

        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | UNEXPECTED SYSTEM ERROR
            |--------------------------------------------------------------------------
            */

            Log::error(
                'Portfolio upload failed unexpectedly.',
                [

                    'message' => $e->getMessage(),

                    'trace' => $e->getTraceAsString(),

                    'user_id' => Auth::id(),
                ]
            );

            return back()

                ->withErrors([

                    'file' =>
                        'Upload failed. Please try again.',
                ])

                ->withInput();
        }
    }
}
