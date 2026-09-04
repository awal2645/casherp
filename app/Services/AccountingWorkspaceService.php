<?php

namespace App\Services;

use App\Account;
use App\AccountTransaction;
use App\Business;
use App\BusinessLocation;
use App\Transaction;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountingWorkspaceService
{
    private const VIEWS = ['overview', 'accounts', 'ledger', 'receivables', 'payables'];

    public function payload(Request $request, array $filters): array
    {
        $businessId = (int) $request->session()->get('user.business_id');
        $user = $request->user();
        abort_if($businessId < 1 || ! $user->canAccessBusiness($businessId), 403);
        abort_unless($user->can('account.access'), 403, 'Your role does not include Accounting access.');

        $business = Business::with(['industry:id,name,code', 'currency:id,code,symbol'])->findOrFail($businessId);
        $accountIds = $this->permittedAccountIds($user, $businessId);
        $view = $filters['view'] ?? 'overview';
        abort_unless(in_array($view, self::VIEWS, true), 404);
        if (! empty($filters['account_id']) && $accountIds !== null && ! in_array((int) $filters['account_id'], $accountIds, true)) {
            abort(403, 'This payment account is outside your assigned location access.');
        }

        [$start, $end] = $this->period($filters);
        $accounts = $this->accounts($businessId, $accountIds);

        return [
            'workspace' => app(ReactWorkspaceContextService::class)->context($request, $businessId),
            'csrf_token' => csrf_token(),
            'company' => [
                'id' => $business->id,
                'name' => $business->name,
                'industry' => optional($business->industry)->only(['name', 'code']),
            ],
            'currency' => [
                'code' => optional($business->currency)->code,
                'symbol' => optional($business->currency)->symbol,
            ],
            'selected_view' => $view,
            'available_views' => self::VIEWS,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'account_id' => isset($filters['account_id']) ? (int) $filters['account_id'] : null,
                'type' => $filters['type'] ?? '',
                'status' => $filters['status'] ?? '',
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'per_page' => (int) ($filters['per_page'] ?? 25),
            ],
            'accounts' => $accounts->map(fn ($account) => [
                'id' => $account->id,
                'name' => $account->name,
                'number' => $account->account_number,
                'type' => $account->account_type_name,
                'is_closed' => (bool) $account->is_closed,
                'balance' => round((float) $account->balance, 4),
            ])->values(),
            'summary' => $this->summary($businessId, $accountIds, $start, $end),
            'records' => $this->records($view, $businessId, $accountIds, $filters, $accounts),
            'trend' => $this->cashMovementTrend($businessId, $accountIds, $start, $end),
            'links' => [
                'create_account' => action([\App\Http\Controllers\AccountController::class, 'create']),
                'balance_sheet' => action([\App\Http\Controllers\AccountReportsController::class, 'balanceSheet']),
                'trial_balance' => action([\App\Http\Controllers\AccountReportsController::class, 'trialBalance']),
                'cash_flow' => action([\App\Http\Controllers\AccountController::class, 'cashFlow']),
                'payment_account_report' => action([\App\Http\Controllers\AccountReportsController::class, 'paymentAccountReport']),
                'legacy_accounts' => url('/account/account?legacy=1'),
            ],
            'governance' => [
                'security_deposits_excluded' => true,
                'pending_security_deposits' => $this->pendingSecurityDeposits($businessId),
                'message' => 'Refundable security deposits are monitored separately and never included in revenue, receivables, account balances or cash-movement figures in this workspace.',
            ],
        ];
    }

    private function accounts(int $businessId, ?array $accountIds): Collection
    {
        $query = Account::query()
            ->leftJoin('account_types as ats', 'accounts.account_type_id', '=', 'ats.id')
            ->where('accounts.business_id', $businessId)
            ->select([
                'accounts.id', 'accounts.name', 'accounts.account_number', 'accounts.note',
                'accounts.is_closed', 'accounts.created_by', 'ats.name as account_type_name',
            ])->selectRaw("COALESCE((SELECT SUM(CASE WHEN ledger.type = 'credit' THEN ledger.amount ELSE -ledger.amount END) FROM account_transactions AS ledger WHERE ledger.account_id = accounts.id AND ledger.deleted_at IS NULL), 0) AS balance")
            ->orderBy('accounts.is_closed')->orderBy('accounts.name');
        if ($accountIds !== null) {
            $query->whereIn('accounts.id', $accountIds ?: [0]);
        }

        return $query->get();
    }

    private function records(string $view, int $businessId, ?array $accountIds, array $filters, Collection $accounts): array
    {
        return match ($view) {
            'accounts' => $this->accountRows($accounts, $filters),
            'ledger' => $this->ledgerRows($businessId, $accountIds, $filters),
            'receivables' => $this->openTransactions('sell', $businessId, $filters),
            'payables' => $this->openTransactions('purchase', $businessId, $filters),
            default => $this->ledgerRows($businessId, $accountIds, array_merge($filters, ['per_page' => 10])),
        };
    }

    private function accountRows(Collection $accounts, array $filters): array
    {
        $rows = $accounts->filter(function ($account) use ($filters) {
            $status = $filters['status'] ?? null;
            if ($status === 'open' && $account->is_closed) {
                return false;
            }
            if ($status === 'closed' && ! $account->is_closed) {
                return false;
            }
            $q = trim((string) ($filters['q'] ?? ''));
            return $q === '' || str_contains(strtolower($account->name.' '.$account->account_number.' '.$account->account_type_name), strtolower($q));
        })->values();
        $page = max(1, (int) request('page', 1));
        $perPage = (int) ($filters['per_page'] ?? 25);
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return $this->page($paginator, fn ($account) => [
            'id' => $account->id,
            'name' => $account->name,
            'number' => $account->account_number,
            'type' => $account->account_type_name ?: 'Unclassified',
            'status' => $account->is_closed ? 'closed' : 'open',
            'balance' => round((float) $account->balance, 4),
            'note' => $account->note,
            'actions' => [
                'ledger' => action([\App\Http\Controllers\AccountController::class, 'show'], [$account->id]),
                'edit' => ! $account->is_closed ? action([\App\Http\Controllers\AccountController::class, 'edit'], [$account->id]) : null,
            ],
        ]);
    }

    private function ledgerRows(int $businessId, ?array $accountIds, array $filters): array
    {
        $query = AccountTransaction::query()
            ->join('accounts', 'accounts.id', '=', 'account_transactions.account_id')
            ->leftJoin('users', 'users.id', '=', 'account_transactions.created_by')
            ->where('accounts.business_id', $businessId)
            ->whereNull('accounts.deleted_at')
            ->select([
                'account_transactions.id', 'account_transactions.account_id', 'account_transactions.type',
                'account_transactions.sub_type', 'account_transactions.amount', 'account_transactions.reff_no',
                'account_transactions.operation_date', 'account_transactions.transaction_id',
                'account_transactions.transaction_payment_id', 'account_transactions.note',
                'accounts.name as account_name', 'accounts.account_number',
                'users.surname', 'users.first_name', 'users.last_name',
            ]);
        if ($accountIds !== null) {
            $query->whereIn('accounts.id', $accountIds ?: [0]);
        }
        $query->when($filters['account_id'] ?? null, fn ($builder, $id) => $builder->where('account_transactions.account_id', $id));
        $query->when($filters['type'] ?? null, fn ($builder, $type) => $builder->where('account_transactions.type', $type));
        $query->when($filters['start'] ?? null, fn ($builder, $date) => $builder->whereDate('account_transactions.operation_date', '>=', $date));
        $query->when($filters['end'] ?? null, fn ($builder, $date) => $builder->whereDate('account_transactions.operation_date', '<=', $date));
        $query->when($filters['q'] ?? null, function ($builder, $term) {
            $builder->where(function ($nested) use ($term) {
                $nested->where('accounts.name', 'like', '%'.$term.'%')
                    ->orWhere('accounts.account_number', 'like', '%'.$term.'%')
                    ->orWhere('account_transactions.reff_no', 'like', '%'.$term.'%')
                    ->orWhere('account_transactions.note', 'like', '%'.$term.'%');
            });
        });
        $paginator = $query->latest('account_transactions.operation_date')->latest('account_transactions.id')
            ->paginate((int) ($filters['per_page'] ?? 25));

        return $this->page($paginator, fn ($entry) => [
            'id' => $entry->id,
            'account_id' => $entry->account_id,
            'account' => $entry->account_name,
            'account_number' => $entry->account_number,
            'type' => $entry->type,
            'sub_type' => $entry->sub_type,
            'amount' => round((float) $entry->amount, 4),
            'reference' => $entry->reff_no ?: ($entry->transaction_id ? 'Transaction #'.$entry->transaction_id : 'Ledger #'.$entry->id),
            'date' => optional($entry->operation_date)->toAtomString(),
            'note' => $entry->note,
            'created_by' => trim(collect([$entry->surname, $entry->first_name, $entry->last_name])->filter()->implode(' ')),
            'actions' => [
                'account' => action([\App\Http\Controllers\AccountController::class, 'show'], [$entry->account_id]),
            ],
        ]);
    }

    private function openTransactions(string $type, int $businessId, array $filters): array
    {
        $paidSql = $this->paidSubquery('open_tp');
        $query = Transaction::query()
            ->where('transactions.business_id', $businessId)
            ->where('transactions.type', $type)
            ->whereIn('transactions.status', $type === 'sell' ? ['final'] : ['received'])
            ->whereIn('transactions.payment_status', ['due', 'partial'])
            ->with(['contact:id,name,supplier_business_name,mobile,email', 'location:id,name'])
            ->select('transactions.*')->selectRaw('('.$paidSql.') AS total_paid');
        $this->scopeTransactionLocations($query, auth()->user(), $businessId);
        $query->when($filters['status'] ?? null, function ($builder, $status) {
            if (in_array($status, ['paid', 'partial', 'due'], true)) {
                $builder->where('transactions.payment_status', $status);
            }
        });
        $query->when($filters['start'] ?? null, fn ($builder, $date) => $builder->whereDate('transactions.transaction_date', '>=', $date));
        $query->when($filters['end'] ?? null, fn ($builder, $date) => $builder->whereDate('transactions.transaction_date', '<=', $date));
        $query->when($filters['q'] ?? null, function ($builder, $term) {
            $builder->where(function ($nested) use ($term) {
                $nested->where('transactions.invoice_no', 'like', '%'.$term.'%')
                    ->orWhere('transactions.ref_no', 'like', '%'.$term.'%')
                    ->orWhereHas('contact', fn ($contact) => $contact->where('name', 'like', '%'.$term.'%')->orWhere('supplier_business_name', 'like', '%'.$term.'%'));
            });
        });
        $paginator = $query->latest('transactions.transaction_date')->latest('transactions.id')
            ->paginate((int) ($filters['per_page'] ?? 25));

        return $this->page($paginator, function (Transaction $transaction) use ($type) {
            $paid = round((float) $transaction->total_paid, 4);
            $total = round((float) $transaction->final_total, 4);
            return [
                'id' => $transaction->id,
                'kind' => $type === 'sell' ? 'Receivable' : 'Payable',
                'number' => $transaction->invoice_no ?: ($transaction->ref_no ?: '#'.$transaction->id),
                'party' => optional($transaction->contact)->supplier_business_name ?: (optional($transaction->contact)->name ?: 'Not assigned'),
                'location' => optional($transaction->location)->name ?: 'Not assigned',
                'date' => $transaction->transaction_date ? Carbon::parse($transaction->transaction_date)->toAtomString() : null,
                'total' => $total,
                'paid' => $paid,
                'due' => max(0, round($total - $paid, 4)),
                'status' => $transaction->payment_status,
                'actions' => [
                    'view' => $type === 'sell'
                        ? action([\App\Http\Controllers\SellController::class, 'show'], [$transaction->id])
                        : action([\App\Http\Controllers\PurchaseController::class, 'show'], [$transaction->id]),
                ],
            ];
        });
    }

    private function summary(int $businessId, ?array $accountIds, Carbon $start, Carbon $end): array
    {
        $ledger = AccountTransaction::query()->join('accounts', 'accounts.id', '=', 'account_transactions.account_id')
            ->where('accounts.business_id', $businessId)->whereNull('accounts.deleted_at');
        if ($accountIds !== null) {
            $ledger->whereIn('accounts.id', $accountIds ?: [0]);
        }
        $balance = (clone $ledger)->selectRaw("COALESCE(SUM(CASE WHEN account_transactions.type = 'credit' THEN account_transactions.amount ELSE -account_transactions.amount END), 0) AS balance")->value('balance');
        $period = (clone $ledger)->whereBetween('account_transactions.operation_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->where(function ($query) {
                $query->whereNull('account_transactions.sub_type')->orWhere('account_transactions.sub_type', '!=', 'fund_transfer');
            });
        $movement = $period->selectRaw("COALESCE(SUM(CASE WHEN account_transactions.type = 'credit' THEN account_transactions.amount ELSE 0 END), 0) AS inflow, COALESCE(SUM(CASE WHEN account_transactions.type = 'debit' THEN account_transactions.amount ELSE 0 END), 0) AS outflow")->first();

        return [
            'account_balance' => round((float) $balance, 4),
            'period_inflow' => round((float) optional($movement)->inflow, 4),
            'period_outflow' => round((float) optional($movement)->outflow, 4),
            'receivables' => $this->outstandingTotal('sell', $businessId),
            'payables' => $this->outstandingTotal('purchase', $businessId),
        ];
    }

    private function outstandingTotal(string $type, int $businessId): float
    {
        $paidSql = $this->paidSubquery('summary_tp');
        $query = Transaction::query()->where('transactions.business_id', $businessId)->where('transactions.type', $type)
            ->whereIn('transactions.status', $type === 'sell' ? ['final'] : ['received'])
            ->whereIn('transactions.payment_status', ['due', 'partial']);
        $this->scopeTransactionLocations($query, auth()->user(), $businessId);
        $row = $query->selectRaw('COALESCE(SUM(transactions.final_total - ('.$paidSql.')), 0) AS outstanding')->first();

        return round(max(0, (float) optional($row)->outstanding), 4);
    }

    private function cashMovementTrend(int $businessId, ?array $accountIds, Carbon $start, Carbon $end): array
    {
        $query = AccountTransaction::query()->join('accounts', 'accounts.id', '=', 'account_transactions.account_id')
            ->where('accounts.business_id', $businessId)->whereNull('accounts.deleted_at')
            ->whereBetween('account_transactions.operation_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->where(function ($builder) {
                $builder->whereNull('account_transactions.sub_type')->orWhere('account_transactions.sub_type', '!=', 'fund_transfer');
            });
        if ($accountIds !== null) {
            $query->whereIn('accounts.id', $accountIds ?: [0]);
        }
        $driver = DB::connection()->getDriverName();
        $dateExpression = $driver === 'sqlite'
            ? "strftime('%Y-%m', account_transactions.operation_date)"
            : "DATE_FORMAT(account_transactions.operation_date, '%Y-%m')";

        return $query->selectRaw($dateExpression.' AS period')
            ->selectRaw("SUM(CASE WHEN account_transactions.type = 'credit' THEN account_transactions.amount ELSE 0 END) AS inflow")
            ->selectRaw("SUM(CASE WHEN account_transactions.type = 'debit' THEN account_transactions.amount ELSE 0 END) AS outflow")
            ->groupBy('period')->orderBy('period')->get()->map(fn ($row) => [
                'period' => $row->period,
                'inflow' => round((float) $row->inflow, 4),
                'outflow' => round((float) $row->outflow, 4),
            ])->values()->all();
    }

    private function pendingSecurityDeposits(int $businessId): int
    {
        if (! Schema::hasTable('security_deposits')) {
            return 0;
        }

        return DB::table('security_deposits')->where('business_id', $businessId)
            ->whereNotIn('status', ['settled', 'waived'])->count();
    }

    private function paidSubquery(string $alias): string
    {
        $purpose = Schema::hasColumn('transaction_payments', 'payment_purpose')
            ? " AND ({$alias}.payment_purpose IS NULL OR {$alias}.payment_purpose <> 'security_deposit')"
            : '';

        return "SELECT COALESCE(SUM(CASE WHEN {$alias}.is_return = 1 THEN -{$alias}.amount ELSE {$alias}.amount END), 0) FROM transaction_payments AS {$alias} WHERE {$alias}.transaction_id = transactions.id{$purpose}";
    }

    private function permittedAccountIds(User $user, int $businessId): ?array
    {
        $admin = $user->can('superadmin') || $user->hasRole('Admin#'.$businessId);
        $locations = $user->permitted_locations($businessId);
        if ($admin || $locations === 'all') {
            return null;
        }
        $accountIds = [];
        foreach (BusinessLocation::where('business_id', $businessId)->whereIn('id', (array) $locations)->get(['default_payment_accounts']) as $location) {
            foreach ((array) json_decode((string) $location->default_payment_accounts, true) as $account) {
                if (! empty($account['is_enabled']) && ! empty($account['account'])) {
                    $accountIds[] = (int) $account['account'];
                }
            }
        }

        return array_values(array_unique($accountIds));
    }

    private function scopeTransactionLocations($query, User $user, int $businessId): void
    {
        $locations = $user->permitted_locations($businessId);
        if ($locations !== 'all') {
            $query->whereIn('transactions.location_id', array_map('intval', (array) $locations));
        }
    }

    private function period(array $filters): array
    {
        $start = ! empty($filters['start']) ? Carbon::createFromFormat('Y-m-d', $filters['start'])->startOfDay() : now()->startOfMonth();
        $end = ! empty($filters['end']) ? Carbon::createFromFormat('Y-m-d', $filters['end'])->endOfDay() : now()->endOfDay();

        return [$start, $end];
    }

    private function page(LengthAwarePaginator $paginator, callable $map): array
    {
        return [
            'data' => collect($paginator->items())->map($map)->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}
