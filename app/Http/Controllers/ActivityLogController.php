<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
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
                    return '<pre class="mb-0" style="font-size: 0.8rem;">' . json_encode($row->properties, JSON_PRETTY_PRINT) . '</pre>';
                })
                ->rawColumns(['properties'])
                ->make(true);
        }

        return view('modules.activity_logs.index');
    }
}
