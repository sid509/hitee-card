<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupportRequest;
use App\Models\SupportRequest as SupportModel;
use App\Mail\SupportMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Yajra\DataTables\Facades\DataTables;

class SupportController extends Controller
{
    /**
     * Display a listing of the support requests (Admin Only)
     */
    public function index(Request $request)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        if ($request->ajax()) {
            $data = SupportModel::with('user')->select(['id', 'user_id', 'subject', 'message', 'status', 'created_at']);
            return DataTables::of($data)
                ->addIndexColumn()
                ->editColumn('user_name', function($row){
                    return $row->user->name;
                })
                ->editColumn('subject', function($row){
                    return $row->subject ?? 'General Query';
                })
                ->editColumn('status', function($row){
                    $class = $row->status === 'open' ? 'bg-label-success' : 'bg-label-secondary';
                    return '<span class="badge '.$class.'">'.ucfirst($row->status).'</span>';
                })
                ->editColumn('created_at', function($row){
                    return formatDate($row->created_at);
                })
                ->addColumn('action', function($row){
                    $actions = '';
                    // View Details Button with data-id
                    $actions .= '<button type="button" class="btn btn-icon btn-sm btn-dark me-1 btn-view-support" data-id="'.$row->id.'" title="View"><i class="bx bx-show"></i></button>';
                    
                    return $actions;
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        return view('modules.support.index');
    }

    /**
     * Show details of a support request (Admin Only)
     */
    public function show(SupportModel $support)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        
        $support->load('user');
        return apiResponse(true, 'Support details fetched', $support);
    }

    /**
     * Close a support request (Admin Only)
     */
    public function close(Request $request, SupportModel $support)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $request->validate([
            'closing_reason' => 'required|string|max:2000'
        ]);

        $support->update([
            'status' => 'closed',
            'closing_reason' => $request->closing_reason,
            'closed_at' => now()
        ]);

        return apiResponse(true, 'Support request closed successfully');
    }

    /**
     * Send support request from user
     */
    public function send(SupportRequest $request)
    {
        try {
            $user = auth()->user();
            
            // 1. Save to Database
            $support = SupportModel::create([
                'user_id' => $user->id,
                'message' => $request->message,
                'status' => 'open'
            ]);

            // 2. Send Email (Fixed recipient)
            $adminEmail = 'sudip@hitee.ai';
            $mailData = [
                'name' => $user->name,
                'email' => $user->email,
                'message' => $request->message
            ];
            
            Mail::to($adminEmail)->send(new SupportMail($mailData));

            return apiResponse(true, 'Your support request has been sent successfully.', '', 200);
        } catch (\Exception $e) {
            return apiResponse(false, 'Failed to send support request.', '', 500);
        }
    }
}
