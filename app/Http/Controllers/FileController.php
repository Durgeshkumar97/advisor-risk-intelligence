<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\ClientIntake;

class FileController extends Controller
{
    public function view($id)
    {
        // must be logged in
        if (!Auth::check()) {
            abort(403, 'Unauthorized');
        }

        $user = Auth::user();

        $intake = ClientIntake::findOrFail($id);

        // admin OR owner
        if (
            !$user->is_admin &&
            $user->email !== $intake->email
        ) {
            abort(403, 'Access denied');
        }

        if (!$intake->document_path) {
            abort(404, 'File not found');
        }

        if (!Storage::exists($intake->document_path)) {
            abort(404, 'File missing');
        }

        return Storage::download($intake->document_path);
    }
}