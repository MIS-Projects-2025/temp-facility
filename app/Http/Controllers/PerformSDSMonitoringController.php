<?php

namespace App\Http\Controllers;

use App\Models\Chemicals;
use App\Models\ChemicalSDS;
use App\Models\ChemicalSDSMonitoringInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PerformSDSMonitoringController extends Controller
{
    public function index(Request $request)
    {
        $chemicals = Chemicals::all();
        $latestSDSMonitoringInstance =
            ChemicalSDSMonitoringInstance::with(['creator:EMPLOYID,FIRSTNAME,LASTNAME'])->latest()->first();

        return Inertia::render('PerformChemicalSDSPage', [
            'chemicals' => $chemicals,
            'latestSDSMonitoringInstance' => $latestSDSMonitoringInstance
        ]);
    }

    public function recordResult(Request $request)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.status' => 'required|string',
            'items.*.remarks' => 'nullable|string|max:500',
            'items.*.chemical_id' => 'required|integer|exists:chemicals,id',
        ]);

        $checkedBy = session('emp_data.emp_id');
        abort_if(!$checkedBy, 401, 'Session expired. Please log in again.');

        DB::transaction(function () use ($validated, $checkedBy) {
            $instance = ChemicalSDSMonitoringInstance::create([
                'created_by' => $checkedBy,
                'notes' => $validated['notes'] ?? null,
            ]);

            ChemicalSDS::insert(array_map(fn($item) => [
                'chemical_id' => $item['chemical_id'],
                'checked_by' => $checkedBy,
                'status' => $item['status'],
                'remarks' => $item['remarks'] ?? null,
                'chemical_sds_id' => $instance->id,
            ], array_values($validated['items'])));
        });

        return response()->json(['status' => 'ok']);
    }
}
