<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Card;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class AuditController extends Controller
{
    /**
     * Dashboard for various user audits and reports.
     */
    public function index()
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $stats = [
            'pending_approval' => User::where('status', User::STATUS_PENDING)->whereNotNull('email_verified_at')->count(),
            'without_cards'    => User::whereDoesntHave('cards')->count(),
            'low_balance'      => User::all()->filter(fn($u) => $u->balance() < 50)->count(),
            'unverified_email' => User::whereNull('email_verified_at')->count(),
            'orphan_cards'     => Card::whereNull('user_id')->count(),
        ];

        return view('modules.audit.index', compact('stats'));
    }

    /**
     * Report: Users pending admin approval (Verified but not Active).
     */
    public function pendingApproval(Request $request)
    {
        if ($request->ajax()) {
            $data = User::where('status', User::STATUS_PENDING)
                ->whereNotNull('email_verified_at')
                ->latest();
            return $this->userTable($data);
        }
        return view('modules.audit.reports.pending_approval');
    }

    /**
     * Report: Users without any linked cards.
     */
    public function withoutCards(Request $request)
    {
        if ($request->ajax()) {
            $data = User::whereDoesntHave('cards')->with(['roles'])->latest();
            return $this->userTable($data);
        }
        return view('modules.audit.reports.user_generic', [
            'title' => 'No Active Cards',
            'subtitle' => 'Users without any linked cards',
            'ajaxUrl' => route('audit.without-cards')
        ]);
    }

    /**
     * Report: Users with balance less than 50.
     */
    public function lowBalance(Request $request)
    {
        if ($request->ajax()) {
            // We use a small chunk or specific subset if possible, but for 50 threshold we need to check balance
            $users = User::with(['cards', 'roles'])->get()->filter(fn($u) => $u->balance() < 50);
            return $this->userTable(collect($users));
        }
        return view('modules.audit.reports.user_generic', [
            'title' => 'Low Balance',
            'subtitle' => 'Users with balance under 50 pts',
            'ajaxUrl' => route('audit.low-balance')
        ]);
    }

    /**
     * Report: Users who haven't validated their email.
     */
    public function unverifiedEmail(Request $request)
    {
        if ($request->ajax()) {
            $data = User::whereNull('email_verified_at')->with(['roles'])->latest();
            return $this->userTable($data);
        }
        return view('modules.audit.reports.user_generic', [
            'title' => 'Unverified Emails',
            'subtitle' => 'Registered but not validated',
            'ajaxUrl' => route('audit.unverified-email')
        ]);
    }

    /**
     * Report: Cards not assigned to any user.
     */
    public function orphanCards(Request $request)
    {
        if ($request->ajax()) {
            $data = Card::whereNull('user_id')->latest();
            return DataTables::of($data)
                ->addIndexColumn()
                ->editColumn('status', function($row){
                    return '<span class="badge bg-label-secondary">'.strtoupper($row->status).'</span>';
                })
                ->addColumn('balance', function($row){
                    return '<span class="fw-medium text-success">Rs. '.number_format($row->balance(), 2).'</span>';
                })
                ->addColumn('action', function($row){
                    return '<a href="'.route('cards.edit', $row->id).'" class="btn btn-sm btn-primary">Assign</a>';
                })
                ->rawColumns(['status', 'balance', 'action'])
                ->make(true);
        }
        return view('modules.audit.reports.orphan_cards');
    }

    /**
     * Helper to generate standardized user table.
     */
    private function userTable($query)
    {
        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('user_info', function($row){
                return '<div>
                            <span class="fw-medium">'.$row->name.'</span><br>
                            <small class="text-muted">'.$row->email.'</small>
                        </div>';
            })
            ->addColumn('status_label', function($row){
                if ($row->status == User::STATUS_PENDING) {
                    if (!$row->email_verified_at) return '<span class="badge bg-label-secondary">Needs Verification</span>';
                    return '<span class="badge bg-label-warning">Awaiting Approval</span>';
                }
                if ($row->status == User::STATUS_ACTIVE) return '<span class="badge bg-label-success">Active</span>';
                return '<span class="badge bg-label-danger">Inactive</span>';
            })
            ->editColumn('last_notified_at', function($row){
                return $row->last_notified_at ? formatDate($row->last_notified_at, true) : '<span class="text-muted italic">Never</span>';
            })
            ->addColumn('action', function($row){
                $btn = '<div class="d-flex">';
                $btn .= '<a href="'.route('users.show', $row->id).'" class="btn btn-sm btn-icon btn-dark me-1" title="View"><i class="bx bx-show"></i></a>';
                if ($row->status == User::STATUS_PENDING && $row->email_verified_at) {
                    $btn .= '<button type="button" class="btn btn-sm btn-success approve-user-btn" data-id="'.$row->id.'"><i class="bx bx-check-shield me-1"></i> Approve</button>';
                }
                $btn .= '</div>';
                return $btn;
            })
            ->rawColumns(['user_info', 'status_label', 'last_notified_at', 'action'])
            ->make(true);
    }
}
