<?php

namespace App\Http\Controllers;

use App\Services\AccountingWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountingWorkspaceController extends Controller
{
    public function shell(Request $request)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        abort_if($businessId < 1 || ! $request->user()->canAccessBusiness($businessId), 403);
        abort_unless($request->user()->can('account.access'), 403, 'Your role does not include Accounting access.');

        return response()->file(public_path('casherp-workspace.html'));
    }

    public function workspace(Request $request, AccountingWorkspaceService $accounting)
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $data = $request->validate([
            'view' => ['nullable', Rule::in(['overview', 'accounts', 'ledger', 'receivables', 'payables'])],
            'q' => ['nullable', 'string', 'max:100'],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('business_id', $businessId)],
            'type' => ['nullable', Rule::in(['credit', 'debit'])],
            'status' => ['nullable', Rule::in(['open', 'closed', 'paid', 'partial', 'due'])],
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
        ]);

        return response()->json(['data' => $accounting->payload($request, $data)]);
    }
}
