<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Payment;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use App\Models\RiskScore;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AdminUserController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX — paginated + searchable users list
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): \Illuminate\View\View
    {
        $query = User::query()->with(['subscription.plan']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name',  'LIKE', "%{$s}%")
                  ->orWhere('email', 'LIKE', "%{$s}%")
                  ->orWhere('phone', 'LIKE', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            match ($request->status) {
                'admin'  => $query->where('is_admin', true),
                'active' => $query->whereHas('subscription', fn($q) => $q->where('status', 'active')),
                'trial'  => $query->whereHas('subscription', fn($q) => $q->where('status', 'trial')),
                'no_sub' => $query->doesntHave('subscription'),
                default  => null,
            };
        }

        $users = $query->latest()->paginate(30)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW — single user detail
    |--------------------------------------------------------------------------
    */

    public function show(int $id): \Illuminate\View\View
    {
        $user = User::with(['subscription.plan'])->findOrFail($id);

        // Latest risk score
        $riskScore = RiskScore::where('user_id', $id)->latest()->first();

        // Portfolios with file/asset counts
        $portfolios = Portfolio::withCount(['files', 'assets'])
            ->where('user_id', $id)
            ->latest()
            ->get();

        // Recent files (last 10)
        $recentFiles = PortfolioFile::with('portfolio')
            ->where('user_id', $id)
            ->latest()
            ->take(10)
            ->get();

        // All payments
        $payments = Payment::with('plan')
            ->where('user_id', $id)
            ->orWhere('email', $user->email)
            ->latest()
            ->get();

        return view('admin.users.show', compact(
            'user', 'riskScore', 'portfolios', 'recentFiles', 'payments'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | SEND LOGIN LINK — generate a magic link and show it to the admin
    |--------------------------------------------------------------------------
    |
    | Admins sometimes need to log in as a user for support purposes.
    | We generate a one-time login_token and display the link.
    | We do NOT send an email here — admin copies and uses the link directly.
    |
    */

    public function sendLoginLink(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $user = User::findOrFail($id);

        $token = Str::random(64);

        $user->forceFill([
            'login_token'            => $token,
            'login_token_expires_at' => now()->addMinutes(15),
        ])->save();

        $link = route('auto.login', $token);

        AdminLog::create([
            'user_id'        => Auth::id(),
            'target_user_id' => $user->id,
            'token_hash'     => hash('sha256', $token),
            'event'          => 'impersonation_link_minted',
            'ip'             => $request->ip(),
            'user_agent'     => $request->userAgent(),
        ]);

        return redirect()->route('admin.users.show', $id)
            ->with('login_link', $link)
            ->with('success', 'Magic login link generated. Copy it from the box below (valid for one use, expires in 15 minutes).');
    }
}
