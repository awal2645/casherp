<?php

namespace App\Http\Controllers;

use App\Business;
use App\BusinessDocument;
use App\BusinessDocumentPayment;
use App\BusinessDocumentSetting;
use App\BusinessLocation;
use App\Category;
use App\Contact;
use App\Currency;
use App\DocumentType;
use App\PropertyUnit;
use App\SecurityDeposit;
use App\SecurityDepositEntry;
use App\Http\Requests\StoreBusinessDocumentRequest;
use App\Transaction;
use App\User;
use App\Services\BusinessDocumentPdfService;
use App\Services\BusinessDocumentService;
use App\Services\BusinessDocumentAccessService;
use App\Services\DocumentSourceContextService;
use App\Services\DamageChargeDocumentService;
use App\Services\IndustryDocumentCatalogService;
use App\Services\PremiumModuleEntitlementService;
use App\Services\PropertyAccessService;
use App\Services\TransactionDocumentAccessService;
use App\Services\SecurityDepositService;
use App\Services\TenantPaymentMethodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Modules\Hms\Entities\HmsEventBooking;
use Modules\Hms\Entities\HmsEventVenue;
use Modules\Hms\Entities\HmsFolio;

class BusinessDocumentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAction('smart_documents.view');
        if (! $request->boolean('legacy')) {
            return response()->file(public_path('casherp-workspace.html'));
        }

        $business = $this->business();
        $catalog = app(IndustryDocumentCatalogService::class);
        $types = app(BusinessDocumentAccessService::class)
            ->permittedTypes($business, auth()->user(), $catalog->profileTypes($business));
        $creatableTypes = app(BusinessDocumentAccessService::class)
            ->permittedTypes($business, auth()->user(), $catalog->availableTypes($business));
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'issued', 'sent', 'accepted', 'completed', 'void'])],
            'document_type_id' => ['nullable', 'integer'],
            'scenario_code' => ['nullable', 'string', Rule::in(array_keys((array) config('smart_documents.scenarios', [])))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $query = BusinessDocument::forBusiness($business->id)
            ->with(['type', 'contact', 'location'])
            ->latest('issue_date')
            ->latest('id');
        if ($types->isEmpty()) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereIn('document_type_id', $types->pluck('id'));
        }
        $this->scopeToPermittedLocations($query);
        app(PropertyAccessService::class)->scopeBusinessDocuments($query, (int) $business->id, auth()->user());
        $permittedEventIds = (clone $query)->where('scenario_code', 'event')->pluck('id');
        $permittedHmsEventIds = (clone $query)
            ->where('scenario_code', 'event')
            ->where('source_type', 'hms_event_booking')
            ->whereNotNull('source_id')
            ->pluck('source_id');
        $query->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
        $query->when($validated['document_type_id'] ?? null, fn ($q, $id) => $q->where('document_type_id', $id));
        $query->when($validated['scenario_code'] ?? null, fn ($q, $scenario) => $q->where('scenario_code', $scenario));
        $query->when($validated['q'] ?? null, function ($q, $term) {
            $q->where(function ($nested) use ($term) {
                $nested->where('document_number', 'like', '%'.$term.'%')
                    ->orWhere('title', 'like', '%'.$term.'%')
                    ->orWhere('subject', 'like', '%'.$term.'%')
                    ->orWhereHas('contact', fn ($contact) => $contact->where('name', 'like', '%'.$term.'%'));
            });
        });

        return view('smart_documents.index', [
            'documents' => $query->paginate(25)->withQueryString(),
            'types' => $types,
            'creatableTypes' => $creatableTypes,
            'scenarios' => $this->availableScenariosForTypes($types),
            'securityAlerts' => app(SecurityDepositService::class)
                ->alertsForBusiness((int) $business->id, ['event_document', 'hms_event'])
                ->filter(function ($alert) use ($permittedEventIds, $permittedHmsEventIds) {
                    return $alert['deposit']->context_type === 'hms_event'
                        ? $permittedHmsEventIds->contains((int) $alert['deposit']->context_id)
                        : $permittedEventIds->contains((int) $alert['deposit']->context_id);
                })
                ->values(),
        ]);
    }

    public function create(
        Request $request,
        IndustryDocumentCatalogService $catalog,
        DocumentSourceContextService $sources
    ) {
        $this->authorizeAction('smart_documents.create');
        $business = $this->business();
        $sourceType = $request->query('source_type');
        $sourceId = $request->integer('source_id') ?: null;
        $this->authorizeSourceAccess($business, $sourceType, $sourceId);
        $prefill = $sources->resolve($business->id, $sourceType, $sourceId);
        if ($sourceType && $sourceId) {
            $prefill['source_type'] = $sourceType;
            $prefill['source_id'] = $sourceId;
        }
        $scenario = $request->query('scenario', $prefill['scenario_code'] ?? $catalog->defaultScenario($business));
        $context = ['preferred_type_code' => $request->query('type', $prefill['preferred_type_code'] ?? null)];
        $recommendations = app(BusinessDocumentAccessService::class)->permittedTypes(
            $business,
            auth()->user(),
            $catalog->recommendations($business, $scenario, $context)
        );
        $selectedType = $recommendations->first();

        return $this->formView($business, null, $selectedType, $scenario, $prefill, $catalog);
    }

    public function store(
        StoreBusinessDocumentRequest $request,
        BusinessDocumentService $service,
        PremiumModuleEntitlementService $entitlements
    ) {
        $this->authorizeAction('smart_documents.create');
        $business = $this->business();
        $validated = $request->validated();
        $this->authorizeDocumentReferences($business, $validated);
        $type = app(IndustryDocumentCatalogService::class)->assertAvailable($business, (int) $validated['document_type_id']);
        app(BusinessDocumentAccessService::class)->assertTypeAccess($business, auth()->user(), $type);
        $document = DB::transaction(function () use ($service, $business, $validated, $entitlements) {
            $document = $service->createDraft($business, auth()->id(), $validated);
            $entitlements->consume((int) $business->id, 'smart_documents');

            return $document;
        });

        return redirect()->route('smart-documents.show', $document)
            ->with('status', ['success' => 1, 'msg' => 'Document draft created. Review it before issue.']);
    }

    public function edit(
        BusinessDocument $smart_document,
        IndustryDocumentCatalogService $catalog
    ) {
        $this->authorizeAction('smart_documents.update');
        $business = $this->business();
        $document = $this->scopedDocument($smart_document, true);
        $document->load(['type', 'lines', 'signatories']);

        return $this->formView(
            $business,
            $document,
            $document->type,
            $document->scenario_code,
            [],
            $catalog
        );
    }

    public function update(
        StoreBusinessDocumentRequest $request,
        BusinessDocument $smart_document,
        BusinessDocumentService $service
    ) {
        $this->authorizeAction('smart_documents.update');
        $document = $this->scopedDocument($smart_document, true);
        $validated = $request->validated();
        $validated['source_type'] = $validated['source_type'] ?? $document->source_type;
        $validated['source_id'] = $validated['source_id'] ?? $document->source_id;
        $validated['parent_document_id'] = $validated['parent_document_id'] ?? $document->parent_document_id;
        $this->authorizeDocumentReferences($this->business(), $validated);
        $type = app(IndustryDocumentCatalogService::class)->assertAvailable($this->business(), (int) $validated['document_type_id']);
        app(BusinessDocumentAccessService::class)->assertTypeAccess($this->business(), auth()->user(), $type);
        $document = $service->updateDraft($document, $this->business(), auth()->id(), $validated);

        return redirect()->route('smart-documents.show', $document)
            ->with('status', ['success' => 1, 'msg' => 'Document draft updated.']);
    }

    public function show(BusinessDocument $smart_document)
    {
        $this->authorizeAction('smart_documents.view');
        $document = $this->scopedDocument($smart_document);
        $document->load(['type', 'lines', 'signatories', 'events', 'payments.currency', 'payments.creator', 'parent.type', 'children.type', 'securityDeposit.entries']);
        $business = $this->business();
        $access = app(BusinessDocumentAccessService::class);
        if ($document->parent && ! $access->canUseType($business, auth()->user(), $document->parent->type)) {
            $document->setRelation('parent', null);
        }
        $receiptedAmount = $document->children
            ->whereIn('status', ['issued', 'sent', 'accepted', 'completed'])
            ->filter(fn ($child) => str_contains(optional($child->type)->code ?: '', 'receipt'))
            ->sum('total_amount');
        $unreceiptedAmount = round(max(0, (float) $document->amount_paid - (float) $receiptedAmount), 4);
        $document->setRelation('children', $access
            ->permittedTypes($business, auth()->user(), $document->children->pluck('type')->filter()->unique('id'))
            ->pipe(function ($allowedTypes) use ($document) {
                $allowedIds = $allowedTypes->pluck('id');

                return $document->children->whereIn('document_type_id', $allowedIds)->values();
            }));
        $catalog = app(IndustryDocumentCatalogService::class);
        $convertTarget = $document->type->convert_to_code
            ? $catalog->availableTypes($business)->firstWhere('code', $document->type->convert_to_code)
            : null;
        $canConvertTarget = $convertTarget && $access->canUseType($business, auth()->user(), $convertTarget);
        $canCreateReceipt = $access->permittedTypes(
            $business,
            auth()->user(),
            $catalog->recommendations($business, 'payment')
        )->isNotEmpty();

        $securityDeposit = $document->scenario_code === 'event'
            ? app(SecurityDepositService::class)->findForEventDocument($document)
            : null;
        $paymentMethods = app(TenantPaymentMethodService::class)->settlementOptions((int) $business->id);

        return view('smart_documents.show', compact('document', 'convertTarget', 'canConvertTarget', 'canCreateReceipt', 'unreceiptedAmount', 'securityDeposit', 'paymentMethods'));
    }

    public function pdf(BusinessDocument $smart_document, BusinessDocumentPdfService $pdf)
    {
        return $this->documentPdfResponse($smart_document, $pdf, 'inline', 'preview');
    }

    public function preview(BusinessDocument $smart_document, BusinessDocumentPdfService $pdf)
    {
        return $this->documentPdfResponse($smart_document, $pdf, 'inline', 'preview');
    }

    public function print(BusinessDocument $smart_document, BusinessDocumentPdfService $pdf)
    {
        return $this->documentPdfResponse($smart_document, $pdf, 'inline', 'print');
    }

    public function download(BusinessDocument $smart_document, BusinessDocumentPdfService $pdf)
    {
        return $this->documentPdfResponse($smart_document, $pdf, 'attachment', 'download');
    }

    private function documentPdfResponse(
        BusinessDocument $smartDocument,
        BusinessDocumentPdfService $pdf,
        string $disposition,
        string $action
    ) {
        $this->authorizeAction('smart_documents.view');
        $document = $this->scopedDocument($smartDocument);

        return response($pdf->render($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$pdf->filename($document).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-CashERP-Document-Action' => $action,
        ]);
    }

    public function recommendations(Request $request, IndustryDocumentCatalogService $catalog)
    {
        $this->authorizeAction('smart_documents.create');
        $validated = $request->validate([
            'scenario' => ['required', Rule::in(array_keys((array) config('smart_documents.scenarios', [])))],
            'purpose' => ['nullable', 'string', 'max:50'],
            'preferred_type_code' => ['nullable', 'string', 'max:80'],
        ]);
        $business = $this->business();
        $types = app(BusinessDocumentAccessService::class)->permittedTypes(
            $business,
            auth()->user(),
            $catalog->recommendations($business, $validated['scenario'], $validated)
        )
            ->map(fn (DocumentType $type) => [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->business_display_name ?: ($type->industry_display_name ?: $type->name),
                'title' => $type->business_display_name ?: ($type->industry_display_name ?: $type->default_title),
                'category' => $type->category,
                'requires_acceptance' => $type->requires_acceptance,
                'fields' => $catalog->schemaFields($type),
                'signatory_roles' => $catalog->defaultSignatoryRoles($type),
                'default_terms' => $type->business_default_terms,
                'terms_reviewed' => ! empty($type->terms_reviewed_at),
            ]);

        return response()->json(['data' => $types->values()]);
    }

    public function transition(
        Request $request,
        BusinessDocument $smart_document,
        BusinessDocumentService $service,
        SecurityDepositService $depositService
    ) {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['issued', 'sent', 'accepted', 'completed', 'void'])],
        ]);
        $permission = $validated['status'] === 'void' ? 'smart_documents.void' : 'smart_documents.issue';
        $this->authorizeAction($permission);
        $scoped = $this->scopedDocument($smart_document);
        if ($validated['status'] === 'completed' && $scoped->scenario_code === 'event') {
            $depositService->assertEventDocumentCanClose($scoped);
        }
        $document = $service->transition($scoped, $validated['status'], auth()->id());
        if ($document->scenario_code === 'event' && in_array($validated['status'], ['issued', 'sent', 'accepted'], true)
            && (float) data_get($document->data, 'security_deposit', 0) > 0) {
            $depositService->forEventDocument($document, (float) data_get($document->data, 'security_deposit'), (int) auth()->id());
        }

        return redirect()->route('smart-documents.show', $document)
            ->with('status', ['success' => 1, 'msg' => 'Document status updated.']);
    }

    public function convert(
        BusinessDocument $smart_document,
        BusinessDocumentService $service,
        PremiumModuleEntitlementService $entitlements
    ) {
        $this->authorizeAction('smart_documents.create');
        $source = $this->scopedDocument($smart_document);
        $business = $this->business();
        $target = app(IndustryDocumentCatalogService::class)->availableTypes($business)
            ->firstWhere('code', optional($source->type)->convert_to_code);
        abort_unless($target, 422, 'The conversion target is not enabled for this company.');
        app(BusinessDocumentAccessService::class)->assertTypeAccess($business, auth()->user(), $target);
        $document = DB::transaction(function () use ($service, $source, $business, $entitlements) {
            $document = $service->convert($source, $business, auth()->id());
            $entitlements->consume((int) $business->id, 'smart_documents');

            return $document;
        });

        return redirect()->route('smart-documents.edit', $document)
            ->with('status', ['success' => 1, 'msg' => 'A linked draft was created. Confirm its dates, amounts and terms before issue.']);
    }

    public function share(
        Request $request,
        BusinessDocument $smart_document,
        BusinessDocumentService $service
    ) {
        $this->authorizeAction('smart_documents.share');
        $validated = $request->validate(['days' => ['nullable', 'integer', 'between:1,365']]);
        $document = $this->scopedDocument($smart_document);
        $token = $service->createShareToken($document, auth()->id(), (int) ($validated['days'] ?? 30));
        $url = route('public.smart-document.show', ['token' => $token]);

        return back()->with('status', ['success' => 1, 'msg' => 'A secure link was generated. It replaces any earlier link.'])
            ->with('smart_document_share_url', $url);
    }

    public function revokeShare(BusinessDocument $smart_document, BusinessDocumentService $service)
    {
        $this->authorizeAction('smart_documents.share');
        $service->revokeShare($this->scopedDocument($smart_document), auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'The public link was revoked.']);
    }

    public function recordPayment(
        Request $request,
        BusinessDocument $smart_document,
        BusinessDocumentService $service
    ) {
        $this->authorizeAction('smart_documents.payment.create');
        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'method' => ['required', 'string', 'max:50'],
            'payment_purpose' => ['required', Rule::in(['invoice_payment', 'payment_deposit'])],
            'reference' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        app(TenantPaymentMethodService::class)->assertSettlementMethod((int) session('user.business_id'), $validated['method']);
        $document = $service->recordPayment($this->scopedDocument($smart_document), auth()->id(), $validated);

        return redirect()->route('smart-documents.show', $document)
            ->with('status', ['success' => 1, 'msg' => 'Payment recorded. A receipt can now be created for the unreceipted amount.']);
    }

    public function reversePayment(
        Request $request,
        BusinessDocument $smart_document,
        BusinessDocumentPayment $payment,
        BusinessDocumentService $service
    ) {
        $this->authorizeAction('smart_documents.payment.reverse');
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);
        $document = $service->reversePayment(
            $this->scopedDocument($smart_document),
            $payment,
            auth()->id(),
            $validated['reason']
        );

        return redirect()->route('smart-documents.show', $document)
            ->with('status', ['success' => 1, 'msg' => 'Payment reversed and the document balance recalculated.']);
    }

    public function updateEventSecurityDeposit(Request $request, BusinessDocument $smart_document, SecurityDepositService $service)
    {
        $this->authorizeEventDeposit(false);
        $document = $this->scopedDocument($smart_document);
        $data = $request->validate(['required_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999999'], 'due_date' => ['nullable', 'date']]);
        $deposit = $service->forEventDocument($document, (float) $data['required_amount'], (int) auth()->id());
        if ($deposit) {
            $deposit->update(['due_date' => $data['due_date'] ?? $deposit->due_date, 'updated_by' => auth()->id()]);
        }

        return back()->with('status', ['success' => 1, 'msg' => 'Event security-deposit requirement updated in the separate refundable register.']);
    }

    public function receiptEventSecurityDeposit(Request $request, BusinessDocument $smart_document, SecurityDepositService $service, TenantPaymentMethodService $methods)
    {
        $this->authorizeEventDeposit(false);
        $document = $this->scopedDocument($smart_document);
        abort_unless(in_array($document->status, ['issued', 'sent', 'accepted', 'completed'], true), 422, 'Issue the event document before receiving its security deposit.');
        $deposit = $service->forEventDocument($document);
        abort_unless($deposit, 422, 'Configure the refundable event security deposit first.');
        $data = $this->depositMoneyInput($request);
        $methods->assertSettlementMethod((int) $document->business_id, $data['payment_method']);
        $service->receipt($deposit, $data, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Refundable event security deposit recorded without changing invoice income or paid totals.']);
    }

    public function damageEventSecurityDeposit(
        Request $request,
        BusinessDocument $smart_document,
        SecurityDepositService $service,
        DamageChargeDocumentService $damageDocuments
    )
    {
        $this->authorizeEventDeposit(false);
        $document = $this->scopedDocument($smart_document);
        $deposit = $service->forEventDocument($document);
        abort_unless($deposit, 422, 'Configure the refundable event security deposit first.');
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'occurred_on' => ['required', 'date'], 'description' => ['required', 'string', 'min:5', 'max:3000'], 'reference' => ['nullable', 'string', 'max:191'], 'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240']]);
        if ($request->hasFile('evidence')) {
            $data['evidence_path'] = $request->file('evidence')->store('security-deposit-evidence/'.$document->business_id, 'public');
        }
        $damageDocument = DB::transaction(function () use ($service, $damageDocuments, $deposit, $document, $data) {
            $entry = $service->damage($deposit, $data, (int) auth()->id());

            return $damageDocuments->forEventDocument($document, $entry, (int) auth()->id());
        }, 3);

        return back()->with('status', [
            'success' => 1,
            'msg' => 'Event venue damage assessment recorded against the refundable deposit. Linked draft Damage Charge Invoice '.$damageDocument->document_number.' is ready for independent review and issue.',
        ]);
    }

    public function refundEventSecurityDeposit(Request $request, BusinessDocument $smart_document, SecurityDepositService $service, TenantPaymentMethodService $methods)
    {
        $this->authorizeEventDeposit(false);
        $document = $this->scopedDocument($smart_document);
        $deposit = $service->forEventDocument($document);
        abort_unless($deposit, 422);
        $data = $this->depositMoneyInput($request);
        $methods->assertSettlementMethod((int) $document->business_id, $data['payment_method']);
        $service->requestRefund($deposit, $data, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Event security-deposit refund submitted for approval.']);
    }

    public function approveEventSecurityDepositRefund($entry, SecurityDepositService $service)
    {
        $this->authorizeEventDeposit(true);
        $model = SecurityDepositEntry::where('business_id', session('user.business_id'))->where('entry_type', 'refund')->findOrFail($entry);
        $this->scopedEventDepositForEntry($model);
        $service->approveRefund($model, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Event security-deposit refund approved. It remains uncleared until payment is confirmed.']);
    }

    public function payEventSecurityDepositRefund($entry, SecurityDepositService $service)
    {
        $this->authorizeEventDeposit(true);
        $model = SecurityDepositEntry::where('business_id', session('user.business_id'))->where('entry_type', 'refund')->findOrFail($entry);
        $this->scopedEventDepositForEntry($model);
        $service->payRefund($model, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Event security-deposit refund payment confirmed and the held balance updated.']);
    }

    public function voidEventSecurityDepositEntry(Request $request, $entry, SecurityDepositService $service)
    {
        $this->authorizeEventDeposit(true);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $model = SecurityDepositEntry::where('business_id', session('user.business_id'))->findOrFail($entry);
        $this->scopedEventDepositForEntry($model);
        $service->void($model, $data['reason'], (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Event security-deposit entry voided with an audit reason.']);
    }

    public function waiveEventSecurityDeposit(Request $request, BusinessDocument $smart_document, SecurityDepositService $service)
    {
        $this->authorizeEventDeposit(true);
        $document = $this->scopedDocument($smart_document);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $deposit = $service->forEventDocument($document);
        abort_unless($deposit, 422);
        $service->waiveUncollected($deposit, $data['reason'], (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Uncollected event security-deposit requirement waived with an audit reason.']);
    }

    public function settings(IndustryDocumentCatalogService $catalog)
    {
        $this->authorizeAction('smart_documents.settings');
        $business = $this->business();
        $catalog->provisionForBusiness($business);
        $types = $business->industry->documentTypes()
            ->wherePivot('enabled_by_default', true)
            ->where('document_types.is_active', true)
            ->orderBy('industry_document_types.sort_order')
            ->get();
        $settings = BusinessDocumentSetting::where('business_id', $business->id)
            ->whereIn('document_type_id', $types->pluck('id'))
            ->get()
            ->keyBy('document_type_id');
        [$roles, $departments, $users] = $this->accessChoices($business->id);
        $paymentMethods = app(TenantPaymentMethodService::class)->all((int) $business->id);

        return view('smart_documents.settings', compact('types', 'settings', 'roles', 'departments', 'users', 'paymentMethods'));
    }

    public function updateSettings(Request $request)
    {
        $this->authorizeAction('smart_documents.settings');
        $business = $this->business();
        $validated = $request->validate([
            'settings' => ['required', 'array', 'max:100'],
            'settings.*.is_enabled' => ['nullable', 'boolean'],
            'settings.*.display_name' => ['nullable', 'string', 'max:255'],
            'settings.*.prefix' => ['required', 'regex:/^[A-Za-z0-9-]{2,20}$/'],
            'settings.*.default_terms' => ['nullable', 'string', 'max:100000'],
            'settings.*.terms_reviewed' => ['nullable', 'boolean'],
            'settings.*.allowed_role_ids' => ['nullable', 'array', 'max:100'],
            'settings.*.allowed_role_ids.*' => ['integer'],
            'settings.*.allowed_department_ids' => ['nullable', 'array', 'max:100'],
            'settings.*.allowed_department_ids.*' => ['integer'],
            'settings.*.allowed_user_ids' => ['nullable', 'array', 'max:200'],
            'settings.*.allowed_user_ids.*' => ['integer'],
            'payment_methods' => ['nullable', 'array', 'max:20'],
            'payment_methods.*.is_enabled' => ['nullable', 'boolean'],
            'payment_methods.*.name' => ['required', 'string', 'max:100'],
        ]);
        $enabledPrefixes = collect($validated['settings'])
            ->filter(fn ($values) => ! empty($values['is_enabled']))
            ->pluck('prefix')
            ->map(fn ($prefix) => strtoupper($prefix));
        if ($enabledPrefixes->duplicates()->isNotEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'settings' => 'Each enabled document type must have a unique number prefix.',
            ]);
        }
        $allowedTypeIds = $business->industry->documentTypes()
            ->wherePivot('enabled_by_default', true)
            ->pluck('document_types.id')->map(fn ($id) => (int) $id)->all();
        [$roles, $departments, $users] = $this->accessChoices($business->id);
        $allowedAccessIds = [
            'allowed_role_ids' => $roles->keys()->map(fn ($id) => (int) $id)->all(),
            'allowed_department_ids' => $departments->keys()->map(fn ($id) => (int) $id)->all(),
            'allowed_user_ids' => $users->keys()->map(fn ($id) => (int) $id)->all(),
        ];
        foreach ($validated['settings'] as $typeId => $values) {
            foreach ($allowedAccessIds as $key => $allowedIds) {
                $submittedIds = collect($values[$key] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
                if (! empty(array_diff($submittedIds, $allowedIds))) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "settings.$typeId.$key" => 'One or more selected access subjects do not belong to the active company.',
                    ]);
                }
            }
        }

        DB::transaction(function () use ($validated, $allowedTypeIds, $business) {
            foreach ($validated['settings'] as $typeId => $values) {
                abort_unless(in_array((int) $typeId, $allowedTypeIds, true), 422, 'Invalid document type setting.');
                $terms = $values['default_terms'] ?? null;
                $reviewed = ! empty($values['terms_reviewed']) && filled($terms);
                $setting = BusinessDocumentSetting::firstOrNew([
                    'business_id' => $business->id,
                    'document_type_id' => (int) $typeId,
                ]);
                $configuration = (array) $setting->settings;
                $configuration['access'] = [
                    'role_ids' => collect($values['allowed_role_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all(),
                    'department_ids' => collect($values['allowed_department_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all(),
                    'user_ids' => collect($values['allowed_user_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all(),
                ];
                BusinessDocumentSetting::updateOrCreate(
                    ['business_id' => $business->id, 'document_type_id' => (int) $typeId],
                    [
                        'is_enabled' => ! empty($values['is_enabled']),
                        'source' => 'business_override',
                        'display_name' => $values['display_name'] ?? null,
                        'prefix' => strtoupper($values['prefix']),
                        'default_terms' => $terms,
                        'settings' => $configuration,
                        'terms_reviewed_at' => $reviewed ? now() : null,
                        'terms_reviewed_hash' => $reviewed ? $this->termsHash($terms) : null,
                        'terms_reviewed_by' => $reviewed ? auth()->id() : null,
                        'updated_by' => auth()->id(),
                    ]
                );
            }
            app(TenantPaymentMethodService::class)->update((int) $business->id, $validated['payment_methods'] ?? []);
        });

        return back()->with('status', ['success' => 1, 'msg' => 'Document settings updated.']);
    }

    private function formView(
        Business $business,
        ?BusinessDocument $document,
        ?DocumentType $selectedType,
        string $scenario,
        array $prefill,
        IndustryDocumentCatalogService $catalog
    ) {
        abort_unless($selectedType, 422, 'No document type is enabled for this scenario.');
        $allTypes = app(BusinessDocumentAccessService::class)
            ->permittedTypes($business, auth()->user(), $catalog->availableTypes($business));
        $scenarioTypeIds = app(BusinessDocumentAccessService::class)
            ->permittedTypes($business, auth()->user(), $catalog->recommendations($business, $scenario, [
                'preferred_type_code' => $selectedType->code,
            ]))
            ->pluck('id');
        $types = $allTypes->whereIn('id', $scenarioTypeIds)->values();
        abort_unless($types->contains('id', $selectedType->id), 403, 'You are not authorized to use this document type for the selected scenario.');
        $settings = BusinessDocumentSetting::where('business_id', $business->id)
            ->whereIn('document_type_id', $allTypes->pluck('id'))
            ->get()
            ->keyBy('document_type_id');
        $contacts = Contact::where('business_id', $business->id)
            ->whereIn('type', ['customer', 'both', 'lead'])
            ->orderBy('name')
            ->pluck('name', 'id');
        $locations = BusinessLocation::forDropdown($business->id, false, false, true, true);
        $assetType = null;
        $assetOptions = collect();
        if (optional($business->industry)->code === 'property_management_rentals') {
            $assetType = 'property_unit';
            $assetOptions = app(PropertyAccessService::class)
                ->scopeProperties(\App\Property::query(), (int) $business->id, auth()->user(), 'documents')
                ->with(['businessLocation', 'units'])->get()->flatMap(function ($property) {
                    return $property->units->mapWithKeys(fn ($unit) => [$unit->id => (optional($property->businessLocation)->name ?: 'Location').' — '.$property->name.' / '.$unit->unit_code]);
                });
        } elseif (in_array(optional($business->industry)->code, ['hotel_lodge_guesthouse', 'hotel_with_restaurant'], true)) {
            $assetType = 'hms_event_venue';
            $assetOptions = HmsEventVenue::where('business_id', $business->id)->where('is_active', true)
                ->with('property.location')->get()->mapWithKeys(fn ($venue) => [$venue->id => (optional(optional($venue->property)->location)->name ?: 'Location').' — '.optional($venue->property)->name.' / '.$venue->name]);
        } else {
            $assetType = 'business_location';
            $assetOptions = collect($locations);
        }
        $currencies = Currency::orderBy('country')->get()->mapWithKeys(function ($currency) {
            return [$currency->id => trim($currency->country.' - '.$currency->currency.' ('.$currency->code.')')];
        });
        $typeMeta = $allTypes->mapWithKeys(function (DocumentType $type) use ($catalog, $settings) {
            $setting = $settings->get($type->id);

            return [$type->id => [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->business_display_name ?: ($type->industry_display_name ?: $type->name),
                'title' => $type->business_display_name ?: ($type->industry_display_name ?: $type->default_title),
                'category' => $type->category,
                'scenario' => $type->scenario_code,
                'is_financial' => (bool) $type->is_financial,
                'requires_acceptance' => (bool) $type->requires_acceptance,
                'fields' => $catalog->schemaFields($type),
                'signatory_roles' => $catalog->defaultSignatoryRoles($type),
                'default_terms' => optional($setting)->default_terms,
            ]];
        });
        $sourceType = $document ? $document->source_type : ($prefill['source_type'] ?? null);
        $sourceId = $document ? $document->source_id : ($prefill['source_id'] ?? null);
        $existingSourceDocuments = collect();
        if ($sourceType && $sourceId) {
            $existingSourceDocuments = BusinessDocument::forBusiness($business->id)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->whereIn('document_type_id', $allTypes->pluck('id'))
                ->when($document, fn ($query) => $query->where('id', '!=', $document->id))
                ->where('status', '!=', 'void')
                ->with('type')
                ->latest('id')
                ->take(10)
                ->get();
        }

        return view('smart_documents.form', [
            'document' => $document,
            'selectedType' => $selectedType,
            'types' => $types,
            'settings' => $settings,
            'schemaFields' => $catalog->schemaFields($selectedType),
            'defaultSignatoryRoles' => $catalog->defaultSignatoryRoles($selectedType),
            'scenario' => $scenario,
            'scenarios' => $this->availableScenariosForTypes($allTypes),
            'contacts' => $contacts,
            'locations' => $locations,
            'assetType' => $assetType,
            'assetOptions' => $assetOptions,
            'paymentArrangements' => app(TenantPaymentMethodService::class)->all((int) $business->id)->where('is_enabled', true)->pluck('name', 'code'),
            'industryCode' => optional($business->industry)->code,
            'currencies' => $currencies,
            'typeMeta' => $typeMeta,
            'existingSourceDocuments' => $existingSourceDocuments,
            'prefill' => $prefill,
        ]);
    }

    private function business(): Business
    {
        return Business::with(['industry', 'currency', 'locations'])
            ->findOrFail((int) session('user.business_id'));
    }

    private function scopedDocument(
        BusinessDocument $document,
        bool $mustBeDraft = false,
        int $sourceDepth = 0
    ): BusinessDocument
    {
        abort_if($sourceDepth > 5, 422, 'The linked document chain is invalid.');
        abort_unless((int) $document->business_id === (int) session('user.business_id'), 403);
        $document->loadMissing('type');
        app(BusinessDocumentAccessService::class)
            ->assertTypeAccess($this->business(), auth()->user(), $document->type);
        if ($mustBeDraft) {
            abort_unless($document->status === 'draft', 422, 'Only draft documents can be edited.');
        }
        if ($document->location_id) {
            $permitted = auth()->user()->permitted_locations((int) $document->business_id);
            abort_unless($permitted === 'all' || in_array((int) $document->location_id, array_map('intval', $permitted), true), 403);
        }
        if (in_array($document->source_type, ['property_lease', 'property_rent_due', 'property_rent_payment'], true)
            && $document->source_id) {
            app(PropertyAccessService::class)->assertSourceAccess(
                (int) $document->business_id,
                auth()->user(),
                $document->source_type,
                (int) $document->source_id,
                'documents'
            );
        } elseif ($document->source_type === 'hms_event_booking' && $document->source_id) {
            $this->authorizeSourceAccess($this->business(), 'hms_event_booking', (int) $document->source_id);
        } elseif ($document->source_type === 'business_document' && $document->source_id) {
            abort_if((int) $document->source_id === (int) $document->id, 422, 'A document cannot reference itself.');
            $this->scopedDocument(
                BusinessDocument::forBusiness($document->business_id)
                    ->findOrFail((int) $document->source_id),
                false,
                $sourceDepth + 1
            );
        }

        return $document;
    }

    private function authorizeAction(string $permission): void
    {
        $businessId = (int) session('user.business_id');
        $isAdmin = auth()->user()->hasRole('Admin#'.$businessId) || auth()->user()->can('superadmin');
        abort_unless(
            $isAdmin || auth()->user()->canForBusiness($permission, $businessId),
            403,
            'Unauthorized action.'
        );
    }

    private function scopeToPermittedLocations($query): void
    {
        $permitted = auth()->user()->permitted_locations();
        if ($permitted !== 'all') {
            $query->where(function ($locationQuery) use ($permitted) {
                $locationQuery->whereIn('location_id', $permitted)->orWhereNull('location_id');
            });
        }
    }

    private function termsHash(string $terms): string
    {
        return hash('sha256', str_replace(["\r\n", "\r"], "\n", trim($terms)));
    }

    private function accessChoices(int $businessId): array
    {
        $roles = Role::where('business_id', $businessId)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($role) => [$role->id => str_replace('#'.$businessId, '', $role->name)]);
        $departments = Category::forDropdown($businessId, 'hrm_department');
        $users = User::forBusiness($businessId)
            ->whereNull('users.deleted_at')
            ->orderBy('first_name')
            ->get()
            ->mapWithKeys(fn ($user) => [$user->id => $user->user_full_name]);

        return [$roles, $departments, $users];
    }

    private function authorizeDocumentReferences(Business $business, array $input): void
    {
        if (! empty($input['location_id'])) {
            $location = BusinessLocation::where('business_id', $business->id)
                ->findOrFail((int) $input['location_id']);
            $permitted = auth()->user()->permitted_locations((int) $business->id);
            abort_unless(
                $permitted === 'all' || in_array((int) $location->id, array_map('intval', (array) $permitted), true),
                403,
                'The selected location is not available to your account.'
            );
        }
        $this->authorizeSourceAccess(
            $business,
            $input['source_type'] ?? null,
            ! empty($input['source_id']) ? (int) $input['source_id'] : null
        );
        if (! empty($input['parent_document_id'])) {
            $this->scopedDocument(BusinessDocument::forBusiness($business->id)->findOrFail((int) $input['parent_document_id']));
        }
        if (($input['scenario_code'] ?? null) === 'event') {
            abort_unless(
                ! empty($input['asset_type']) && ! empty($input['asset_id']),
                422,
                'Select the exact property unit, hotel event venue, or responsible operating location.'
            );
        }
        if (($input['asset_type'] ?? null) === 'property_unit' && ! empty($input['asset_id'])) {
            $unit = PropertyUnit::whereHas('property', fn ($query) => $query->where('business_id', $business->id))->with('property')->findOrFail((int) $input['asset_id']);
            app(PropertyAccessService::class)->assertAccess($unit->property, auth()->user(), (int) $business->id, 'documents');
            abort_if(! empty($input['location_id']) && (int) $unit->property->business_location_id !== (int) $input['location_id'], 422, 'The selected unit does not belong to the selected branch.');
        } elseif (($input['asset_type'] ?? null) === 'hms_event_venue' && ! empty($input['asset_id'])) {
            $venue = HmsEventVenue::where('business_id', $business->id)->where('is_active', true)->with('property')->findOrFail((int) $input['asset_id']);
            abort_if(! empty($input['location_id']) && (int) optional($venue->property)->location_id !== (int) $input['location_id'], 422, 'The selected venue does not belong to the selected branch.');
        } elseif (($input['asset_type'] ?? null) === 'business_location' && ! empty($input['asset_id'])) {
            $location = BusinessLocation::where('business_id', $business->id)->findOrFail((int) $input['asset_id']);
            abort_if(! empty($input['location_id']) && (int) $location->id !== (int) $input['location_id'], 422, 'The responsible location must match the selected branch.');
        }

        if (($input['source_type'] ?? null) === 'hms_event_booking' && ! empty($input['source_id'])) {
            $event = HmsEventBooking::where('business_id', $business->id)
                ->with('property')
                ->findOrFail((int) $input['source_id']);
            $eventLocationId = $event->location_id ?: optional($event->property)->location_id;
            abort_unless(($input['scenario_code'] ?? null) === 'event', 422, 'A hotel event booking can only create an event or venue-hire document.');
            abort_unless(
                ($input['asset_type'] ?? null) === 'hms_event_venue'
                    && (int) ($input['asset_id'] ?? 0) === (int) $event->hms_event_venue_id,
                422,
                'The document venue must match the selected hotel event booking.'
            );
            abort_if($eventLocationId && (int) ($input['location_id'] ?? 0) !== (int) $eventLocationId, 422, 'The document branch must match the selected hotel event booking.');
            abort_if($event->contact_id && (int) ($input['contact_id'] ?? 0) !== (int) $event->contact_id, 422, 'The document customer must match the selected hotel event booking.');
            foreach (['service_start_at' => 'starts_at', 'service_end_at' => 'ends_at'] as $inputField => $eventField) {
                abort_if(
                    empty($input[$inputField])
                        || \Carbon\Carbon::parse($input[$inputField])->format('Y-m-d H:i') !== optional($event->{$eventField})->format('Y-m-d H:i'),
                    422,
                    'The document event dates must match the selected hotel event booking.'
                );
            }
        }
    }

    private function authorizeSourceAccess(Business $business, ?string $sourceType, ?int $sourceId): void
    {
        if (! $sourceType || ! $sourceId) {
            return;
        }

        $user = auth()->user();
        $isAdmin = app(BusinessDocumentAccessService::class)->isBusinessAdmin($user, $business->id);
        if ($sourceType === 'business_document') {
            $this->scopedDocument(BusinessDocument::forBusiness($business->id)->findOrFail($sourceId));

            return;
        }
        if (in_array($sourceType, ['property_lease', 'property_rent_due', 'property_rent_payment'], true)) {
            abort_unless(
                $isAdmin || $user->canForBusiness('property.view', (int) $business->id),
                403,
                'You are not authorized to use property records as document sources.'
            );
            app(PropertyAccessService::class)->assertSourceAccess(
                (int) $business->id,
                $user,
                $sourceType,
                $sourceId,
                'documents'
            );

            return;
        }
        if ($sourceType === 'hms_folio') {
            abort_unless($isAdmin || $user->can('hms.manage_folios'), 403, 'You are not authorized to use hotel folios as document sources.');
            $folio = HmsFolio::where('business_id', $business->id)->with('property')->findOrFail($sourceId);
            $locationId = optional($folio->property)->location_id;
            if ($locationId) {
                $permitted = $user->permitted_locations((int) $business->id);
                abort_unless($permitted === 'all' || in_array((int) $locationId, array_map('intval', (array) $permitted), true), 403);
            }

            return;
        }
        if ($sourceType === 'hms_event_booking') {
            $event = HmsEventBooking::where('business_id', $business->id)->with('property')->findOrFail($sourceId);
            abort_unless($isAdmin || $user->can('hms.manage_events'), 403, 'You are not authorized to use event bookings as document sources.');
            $locationId = $event->location_id ?: optional($event->property)->location_id;
            if ($locationId) {
                $permitted = $user->permitted_locations((int) $business->id);
                abort_unless($permitted === 'all' || in_array((int) $locationId, array_map('intval', (array) $permitted), true), 403);
            }

            return;
        }
        if ($sourceType === 'transaction') {
            $transaction = Transaction::where('business_id', $business->id)->findOrFail($sourceId);
            if ($transaction->type === 'hms_booking') {
                abort_unless($isAdmin || $user->hasAnyPermission([
                    'hms.view_bookings', 'hms.add_booking', 'hms.edit_booking',
                    'hms.delete_booking', 'hms.front_desk', 'hms.manage_front_desk',
                ]), 403, 'You are not authorized to use hotel bookings as document sources.');
            } else {
                app(TransactionDocumentAccessService::class)->authorize(
                    $transaction,
                    $user,
                    (int) $business->id,
                    'preview'
                );
            }

            if ($transaction->location_id) {
                $permitted = $user->permitted_locations((int) $business->id);
                abort_unless($permitted === 'all' || in_array((int) $transaction->location_id, array_map('intval', (array) $permitted), true), 403);
            }
        }
    }

    private function availableScenariosForTypes($types): array
    {
        $scenarioCodes = $types->pluck('scenario_code')->unique()->all();

        return collect((array) config('smart_documents.scenarios', []))
            ->filter(fn ($definition, $code) => in_array($code, $scenarioCodes, true))
            ->all();
    }

    private function authorizeEventDeposit(bool $approval): void
    {
        $businessId = (int) session('user.business_id');
        $user = auth()->user();
        $admin = $user->can('superadmin') || $user->hasRole('Admin#'.$businessId);
        $permissions = $approval
            ? ['property.deposit.refund', 'hms.approve_security_deposit_refunds', 'smart_documents.payment.reverse']
            : ['property.deposit.manage', 'hms.manage_security_deposits', 'smart_documents.payment.create'];
        abort_unless($admin || collect($permissions)->contains(fn ($permission) => $user->canForBusiness($permission, $businessId) || $user->can($permission)), 403, 'You are not authorized to manage this refundable deposit.');
    }

    private function scopedEventDepositForEntry(SecurityDepositEntry $entry): SecurityDeposit
    {
        $deposit = SecurityDeposit::forBusiness((int) session('user.business_id'))
            ->whereIn('context_type', ['event_document', 'hms_event'])
            ->findOrFail($entry->security_deposit_id);

        if ($deposit->context_type === 'hms_event') {
            $this->authorizeSourceAccess($this->business(), 'hms_event_booking', (int) $deposit->context_id);
        } else {
            $this->scopedDocument(
                BusinessDocument::forBusiness($deposit->business_id)->findOrFail($deposit->context_id)
            );
        }

        return $deposit;
    }

    private function depositMoneyInput(Request $request): array
    {
        return $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'occurred_on' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'uuid'],
        ]);
    }
}
