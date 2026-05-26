<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Models\User;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminUserController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX — paginated user list with search + status filter
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $query = User::with('subscription.plan');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            match ($request->status) {
                'active'  => $query->whereHas('subscription', fn($q) => $q->where('status', 'active')),
                'trial'   => $query->whereHas('subscription', fn($q) => $q->where('status', 'trial')),
                'no_sub'  => $query->whereDoesntHave('subscription'),
                'admin'   => $query->where('is_admin', true),
                default   => null,
            };
        }

        $users = $query->latest()->paginate(30)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW — full user detail page
    |--------------------------------------------------------------------------
    */

    public function show(int $id)
    {
        $user        = User::with('subscription.plan')->findOrFail($id);
        $riskScore   = RiskScore::where('user_id', $id)->latest()->first();
        $portfolios  = Portfolio::withCount(['files', 'assets'])->where('user_id', $id)->latest()->get();
        $recentFiles = PortfolioFile::with('portfolio')->where('user_id', $id)->latest()->take(10)->get();
        $payments    = Payment::with('plan')
            ->where(function ($q) use ($id, $user) {
                $q->where('user_id', $id)->orWhere('email', $user->email);
            })
            ->latest()
            ->get();

        return view('admin.users.show', compact(
            'user', 'riskScore', 'portfolios', 'recentFiles', 'payments'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | SEND LOGIN LINK — generate a one-time magic login token
    |--------------------------------------------------------------------------
    */

    public function sendLoginLink(int $id)
    {
        $user  = User::findOrFail($id);
        $token = Str::random(64);

        $user->forceFill(['login_token' => $token])->save();

        $link = route('auto.login', $token);

        return redirect()
            ->route('admin.users.show', $id)
            ->with('login_link', $link)
            ->with('success', 'Magic login link generated. Copy it before navigating away.');
    }
}
