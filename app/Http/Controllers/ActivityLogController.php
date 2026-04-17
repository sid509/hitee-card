<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of the activity logs.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = ActivityLog::with('user')->latest();

            return DataTables::of($data)
                ->addIndexColumn()
                ->editColumn('created_at', function($row){
                    return $row->created_at->format('Y-m-d H:i:s');
                })
                ->addColumn('user_name', function($row){
                    return $row->user ? $row->user->name : 'System';
                })
                ->editColumn('action', function($row){
                    return strtoupper(str_replace('_', ' ', $row->action));
                })
                ->editColumn('properties', function($row){
                    if (!$row->properties) return '-';
                    // Properties might be JSON string if synced from file
                    $props = is_string($row->properties) ? json_decode($row->properties, true) : $row->properties;
                    return '<pre class="mb-0" style="font-size: 0.8rem;">' . json_encode($props, JSON_PRETTY_PRINT) . '</pre>';
                })
                ->rawColumns(['properties'])
                ->make(true);
        }

        return view('modules.activity_logs.index');
    }

    /**
     * Sync buffered logs from file to database.
     */
    public function sync(Request $request)
    {
        $filePath = 'activity_buffer.jsonl';
        
        if (!Storage::disk('local')->exists($filePath)) {
            return response()->json([
                'status' => false,
                'message' => 'No buffered logs found to sync.'
            ]);
        }

        $content = Storage::disk('local')->get($filePath);
        $lines = explode("\n", trim($content));
        $logsToInsert = [];

        foreach ($lines as $line) {
            if (empty($line)) continue;
            $data = json_decode($line, true);
            if ($data) {
                $logsToInsert[] = $data;
            }
        }

        if (!empty($logsToInsert)) {
            // Efficient bulk insert
            ActivityLog::insert($logsToInsert);
            // Clear the buffer
            Storage::disk('local')->delete($filePath);
        }

        return response()->json([
            'status' => true,
            'message' => count($logsToInsert) . ' activity logs synced successfully.'
        ]);
    }
}
