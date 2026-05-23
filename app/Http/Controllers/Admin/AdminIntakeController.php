<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;

use Illuminate\Http\Request;

class AdminIntakeController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX — paginated + filterable leads list
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): \Illuminate\View\View
    {
        $query = Lead::query();

        /*
        |--------------------------------------------------------------------------
        | FILTERS
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name',  'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('plan') && $request->plan !== 'all') {
            $query->where('selected_plan', $request->plan);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('days')) {
            $query->where('created_at', '>=', now()->subDays((int) $request->days));
        }

        /*
        |--------------------------------------------------------------------------
        | SORT + PAGINATE
        |--------------------------------------------------------------------------
        */

        $leads = $query
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.intakes.index', compact('leads'));
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW — single lead detail
    |--------------------------------------------------------------------------
    */

    public function show(int $id): \Illuminate\View\View
    {
        $lead = Lead::findOrFail($id);

        return view('admin.intakes.show', compact('lead'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE STATUS (AJAX-friendly)
    |--------------------------------------------------------------------------
    */

    public function updateStatus(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:new,contacted,converted,lost',
        ]);

        Lead::findOrFail($id)->update([
            'status'       => $validated['status'],
            'contacted_at' => in_array($validated['status'], ['contacted', 'converted'])
                ? now()
                : null,
        ]);

        return redirect()->route('admin.intakes.show', $id)
            ->with('success', 'Lead status updated.');
    }
}
