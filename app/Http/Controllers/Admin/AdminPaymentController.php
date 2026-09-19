<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX — paginated payments with search + status filter
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $query = Payment::with(['user', 'plan'])->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('email', 'like', "%{$s}%")
                    ->orWhere('order_id', 'like', "%{$s}%")
                    ->orWhere('payment_id', 'like', "%{$s}%")
                    ->orWhere('name', 'like', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $payments = $query->paginate(30)->withQueryString();

        $totalRevenue = Payment::where('status', 'paid')->sum('amount');
        $paidCount = Payment::where('status', 'paid')->count();

        return view('admin.payments.index', compact('payments', 'totalRevenue', 'paidCount'));
    }
}
