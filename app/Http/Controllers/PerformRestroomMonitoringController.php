<?php

namespace App\Http\Controllers;

use App\Models\Restroom;
use App\Models\RestroomMonitoring;
use App\Models\RestroomMonitoringInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PerformRestroomMonitoringController extends Controller
{
    public function index(Request $request)
    {
        $restrooms = Restroom::all();
        $latestRestroomMonitoringInstance =
            RestroomMonitoringInstance::with(['creator:EMPLOYID,FIRSTNAME,LASTNAME'])->latest()->first();

        $selectedRestroomId = $request->input('restroom_id', $restrooms->first()?->id);
        $selectedRestroom = Restroom::with('fixtures')->find($selectedRestroomId);

        return Inertia::render('PerformRestroomMonitoringPage', [
            'restrooms' => $restrooms,
            'selectedRestroom' => $selectedRestroom ?? null,
            'latestRestroomMonitoringInstance' => $latestRestroomMonitoringInstance
        ]);
    }

    public function recordResult(Request $request)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.fixture_id' => 'nullable|integer|exists:fixtures,id',
            'items.*.status' => 'required|string',
            'items.*.remarks' => 'nullable|string|max:500',
            'restroom_id' => 'required|integer|exists:restrooms,id',
        ]);

        $checkedBy = session('emp_data.emp_id');
        abort_if(!$checkedBy, 401, 'Session expired. Please log in again.');

        DB::transaction(function () use ($validated, $checkedBy) {
            $instance = RestroomMonitoringInstance::create([
                'created_by' => $checkedBy,
                'notes' => $validated['notes'] ?? null,
            ]);

            RestroomMonitoring::insert(array_map(fn($item) => [
                'restroom_id' => $validated['restroom_id'],
                'fixture_id' => $item['fixture_id'] ?? null,
                'checked_by' => $checkedBy,
                'status' => $item['status'],
                'remarks' => $item['remarks'] ?? null,
                'restroom_monitoring_instance_id' => $instance->id,
            ], array_values($validated['items'])));
        });

        return response()->json(['status' => 'ok']);
    }
}
