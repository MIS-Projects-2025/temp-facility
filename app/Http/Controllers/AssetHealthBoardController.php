<?php

namespace App\Http\Controllers;

use App\Models\Checklist;
use App\Repositories\CheckItemsResultRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssetHealthBoardController extends Controller
{
    public function index(Request $request)
    {
        $checklists = Checklist::all();
        $selectedChecklistId = $request->filled('checklist_id')
            ? (int) $request->input('checklist_id')
            : $checklists->first()?->id;
        $assetSearchName = $request->input('search', null);
        $selectedChecklist = Checklist::with('checklistItems.item')->find($selectedChecklistId);

        $latestResults = (new CheckItemsResultRepository())
            ->getLatestCheckItemsStatusByChecklist($selectedChecklistId, $assetSearchName);

        return Inertia::render('AssetHealthBoardPage', [
            'latestResults' => $latestResults,
            'checklists' => $checklists,
            'search' => $assetSearchName,
            'selectedChecklist' => $selectedChecklist
        ]);
    }
}
