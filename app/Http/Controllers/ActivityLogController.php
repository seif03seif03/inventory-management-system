<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * ROUTE: GET /activity-logs -> activity-logs.index
     *
     * The audit trail, newest first. Admin only — see the route group.
     *
     * Read-only by design: there is no create, edit or delete. An audit log an
     * administrator can edit is not an audit log, so entries are only ever
     * written by the LogsActivity trait.
     */
    public function index(Request $request)
    {
        $query = ActivityLog::query()
            // Eager-load the actor so the table does not run a query per row.
            // subject is NOT eager-loaded: rows point at several different
            // tables and deleted subjects resolve to null anyway, which is
            // exactly why subject_label is stored on the row itself.
            ->with('user')
            ->latest();

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        if ($subjectType = $request->input('subject_type')) {
            $query->where('subject_type', $subjectType);
        }

        if ($search = $request->input('search')) {
            $query->where('subject_label', 'like', "%{$search}%");
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $query->paginate(30)->withQueryString();

        // Filter dropdowns come from what the log actually contains, so they
        // never offer a subject type that would return nothing.
        $subjectTypes = ActivityLog::query()
            ->select('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type');

        $users = User::select('id', 'name')->orderBy('name')->get();

        return view('activity-logs.index', compact('logs', 'users', 'subjectTypes'));
    }
}
