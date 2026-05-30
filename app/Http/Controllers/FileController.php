<?php

namespace App\Http\Controllers;

use App\Models\PortfolioFile;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | STORAGE DISK
    |--------------------------------------------------------------------------
    */

    private const DISK = 'portfolios';

    /*
    |--------------------------------------------------------------------------
    | DOWNLOAD PORTFOLIO FILE
    |--------------------------------------------------------------------------
    |
    | Only the file owner (or an admin) may download.
    | Files are served from the private portfolios disk — never from public URLs.
    |
    */

    public function view(int $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | LOAD FILE — scope to requesting user unless admin
        |--------------------------------------------------------------------------
        */

        $file = PortfolioFile::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | VERIFY FILE EXISTS ON DISK
        |--------------------------------------------------------------------------
        */

        if (!Storage::disk(self::DISK)->exists($file->path)) {
            abort(404, 'File not found on storage.');
        }

        /*
        |--------------------------------------------------------------------------
        | STREAM FILE AS DOWNLOAD
        |--------------------------------------------------------------------------
        */

        return Storage::disk(self::DISK)->download(
            $file->path,
            $file->original_name
        );
    }

    public function report(int $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = Auth::user();
        $file = PortfolioFile::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        if (!$file->report_path || !Storage::disk(self::DISK)->exists($file->report_path)) {
            abort(404, 'Report not available.');
        }

        return Storage::disk(self::DISK)->response(
            $file->report_path,
            'RiskSignal-Risk-Report.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    public function reportDownload(int $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = Auth::user();
        $file = PortfolioFile::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        if (!$file->report_path || !Storage::disk(self::DISK)->exists($file->report_path)) {
            abort(404, 'Report not available.');
        }

        return Storage::disk(self::DISK)->download(
            $file->report_path,
            'RiskSignal-Risk-Report-' . $file->id . '.pdf'
        );
    }
}
