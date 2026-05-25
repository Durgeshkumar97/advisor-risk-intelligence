<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
    public function index(Request $request): \Illuminate\View\View
    {
        $query = Payment::query()->with(['user', 'plan']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('email',      'LIKE', "%{$s}%")
                  ->orWhere('order_id',   'LIKE', "%{$s}%")
                  ->orWhere('payment_id', 'LIKE', "%{$s}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Revenue summary (paid only)
        $totalRevenue = Payment::where('status', 'paid')->sum('amount');
        $paidCount    = Payment::where('status', 'paid')->count();

        $payments = $query->latest()->paginate(30)->withQueryString();

        return view('admin.payments.index', compact('payments', 'totalRevenue', 'paidCount'));
    }
}
