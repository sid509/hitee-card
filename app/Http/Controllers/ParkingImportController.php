<?php

namespace App\Http\Controllers;

use App\Services\ParkingImportService;
use Illuminate\Http\Request;

class ParkingImportController extends Controller
{
    /**
     * Show the JSON import form.
     */
    public function create(ParkingImportService $importer)
    {
        $merchant = $importer->defaultMerchant();

        return view('modules.parkings.import', compact('merchant'));
    }

    /**
     * Handle the uploaded parking-lots JSON export.
     */
    public function store(Request $request, ParkingImportService $importer)
    {
        $request->validate([
            'file' => 'required|file|mimes:json,txt|max:5120',
        ]);

        // Downloading and processing remote images can be slow and heavy.
        set_time_limit(600);
        ini_set('memory_limit', '512M');

        $lots = json_decode($request->file('file')->get(), true);

        if (!is_array($lots) || !array_is_list($lots)) {
            return back()
                ->withInput()
                ->with('error', 'Invalid file — expected a JSON array of parking lots.');
        }

        $result = $importer->import(
            $lots,
            $importer->defaultMerchant(),
            $request->boolean('with_images')
        );

        return redirect()
            ->route('parkings.index')
            ->with('success', "Import finished — {$result['created']} created, {$result['updated']} updated, {$result['images']} images downloaded, {$result['failed']} failed.")
            ->with('import_errors', $result['errors']);
    }
}
