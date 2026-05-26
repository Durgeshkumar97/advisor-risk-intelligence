<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClientIntake;

class AdminIntakeController extends Controller
{
    /**
     * Display list of leads with filtering + sorting
     */
    public function index(Request $request)
    {
        $query = ClientIntake::query();

        // ðŸ” Filter: minimum lead score
        if ($request->filled('min_score')) {
            $query->where('lead_score', '>=', (int) $request->min_score);
        }

        // ðŸ” Filter: recent (last 7 days)
        if ($request->filled('recent')) {
            $query->where('created_at', '>=', now()->subDays(7));
        }

        // ðŸ” Filter: email search
        if ($request->filled('email')) {
            $query->where('email', 'LIKE', '%' . $request->email . '%');
        }

        // Sorting (IMPORTANT)
        $intakes = $query
            ->orderByDesc('lead_score')   // highest priority first
            ->orderByDesc('created_at')   // newest next
            ->paginate(20)
            ->withQueryString();

        return view('admin.intakes.index', compact('intakes'));
    }

    /**
     * Show single lead
     */
    public function show($id)
    {
        $intake = ClientIntake::findOrFail($id);

        return view('admin.intakes.show', compact('intake'));
    }

    /**
     * Update lead status
     */
    public function updateStatus(\Illuminate\Http\Request $request, $id)
    {
        $intake = ClientIntake::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:new,contacted,qualified,converted,rejected',
        ]);

        $intake->update(['status' => $validated['status']]);

        return redirect()
            ->route('admin.intakes.show', $id)
            ->with('success', 'Status updated to ' . ucfirst($validated['status']) . '.');
    }
}
