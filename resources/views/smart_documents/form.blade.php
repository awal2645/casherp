@extends('layouts.app')
@section('title', $document ? 'Edit Smart Document' : 'Create Smart Document')

@php
    $isEdit = !empty($document);
    $formRoute = $isEdit ? ['smart-documents.update', $document] : 'smart-documents.store';
    $formMethod = $isEdit ? 'put' : 'post';
    $initialData = old('data', $isEdit ? ($document->data ?: []) : ($prefill['data'] ?? []));
    $initialLines = old('lines', $isEdit ? $document->lines->map(function ($line) {
        return [
            'line_type' => $line->line_type,
            'description' => $line->description,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
            'discount_amount' => $line->discount_amount,
            'tax_amount' => $line->tax_amount,
        ];
    })->all() : ($prefill['lines'] ?? [['line_type' => 'item', 'description' => '', 'quantity' => 1, 'unit_price' => 0, 'discount_amount' => 0, 'tax_amount' => 0]]));
    $initialSignatories = old('signatories', $isEdit ? $document->signatories->map(function ($item) {
        return collect($item->toArray())->only(['role', 'name', 'position', 'identifier', 'phone', 'email', 'address'])->all();
    })->all() : collect($defaultSignatoryRoles)->map(fn ($role) => ['role' => $role])->all());
    $selectedTypeId = (int) old('document_type_id', $isEdit ? $document->document_type_id : $selectedType->id);
    $selectedScenario = old('scenario_code', $isEdit ? $document->scenario_code : $scenario);
    $fieldValue = fn ($name, $fallback = null) => old($name, $isEdit ? data_get($document, $name, $fallback) : data_get($prefill, $name, $fallback));
@endphp

@section('content')
<section class="content-header">
    <h1>{{ $isEdit ? 'Edit Smart Document' : 'Create Smart Document' }} <small>The active company industry controls the available outputs</small></h1>
</section>

<section class="content">
    @if($errors->any())
        <div class="alert alert-danger"><strong>Please correct the highlighted information.</strong><ul class="tw-mt-2 tw-mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="callout callout-info">
        <h4><i class="fa fa-magic"></i> Smart document selection</h4>
        <p>Select the real business scenario first. CashERP recommends only the document types approved for this company’s industry. You can review every draft before it receives an issued status.</p>
    </div>

    @if($existingSourceDocuments->isNotEmpty())
        <div class="callout callout-warning">
            <h4><i class="fa fa-copy"></i> Existing outputs for this source</h4>
            <p>Review these records before saving another document. Different linked stages are valid, but an accidental duplicate should not be issued.</p>
            <ul>@foreach($existingSourceDocuments as $existing)<li><a href="{{ route('smart-documents.show', $existing) }}">{{ $existing->document_number }} — {{ $existing->title }}</a> ({{ $existing->status }})</li>@endforeach</ul>
        </div>
    @endif

    {!! Form::open(['route' => $formRoute, 'method' => $formMethod, 'id' => 'smart_document_form']) !!}
        <input type="hidden" name="source_type" value="{{ old('source_type', $isEdit ? $document->source_type : ($prefill['source_type'] ?? '')) }}">
        <input type="hidden" name="source_id" value="{{ old('source_id', $isEdit ? $document->source_id : ($prefill['source_id'] ?? '')) }}">
        <input type="hidden" name="parent_document_id" value="{{ old('parent_document_id', $isEdit ? $document->parent_document_id : ($prefill['parent_document_id'] ?? '')) }}">

        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">1. Document purpose and identity</h3></div>
            <div class="box-body row">
                <div class="col-md-4 form-group">
                    <label for="scenario_code">Business scenario:*</label>
                    <select id="scenario_code" name="scenario_code" class="form-control" required>
                        @foreach($scenarios as $code => $definition)
                            <option value="{{ $code }}" data-help="{{ $definition['help'] }}" @selected($selectedScenario === $code)>{{ $definition['name'] }}</option>
                        @endforeach
                    </select>
                    <p id="scenario_help" class="help-block"></p>
                </div>
                <div class="col-md-4 form-group">
                    <label for="document_type_id">Recommended document type:*</label>
                    <select id="document_type_id" name="document_type_id" class="form-control" required>
                        @foreach($types as $type)
                            <option value="{{ $type->id }}" @selected($selectedTypeId === (int) $type->id)>{{ $type->business_display_name ?: ($type->industry_display_name ?: $type->name) }}</option>
                        @endforeach
                    </select>
                    <p class="help-block">The selection changes the terminology and fields; it never changes another company’s data.</p>
                </div>
                <div class="col-md-4 form-group">
                    <label for="title">Document heading</label>
                    <input id="title" name="title" class="form-control" maxlength="255" value="{{ $fieldValue('title') }}" placeholder="Uses the approved type name if blank">
                </div>
                <div class="col-md-4 form-group">
                    <label for="location_id">Branch</label>
                    {!! Form::select('location_id', $locations, $fieldValue('location_id'), ['id' => 'location_id', 'class' => 'form-control select2', 'placeholder' => 'Use default permitted branch']) !!}
                </div>
                <div class="col-md-4 form-group">
                    <label for="contact_id">Customer / client / guest / tenant</label>
                    {!! Form::select('contact_id', $contacts, $fieldValue('contact_id'), ['id' => 'contact_id', 'class' => 'form-control select2', 'placeholder' => 'Select existing contact']) !!}
                </div>
                <div class="col-md-4 form-group">
                    <label for="subject">Subject or short description</label>
                    <input id="subject" name="subject" class="form-control" maxlength="255" value="{{ $fieldValue('subject') }}">
                </div>
                @if($assetType)
                <div class="col-md-8 form-group" id="event_asset_group" style="display:none">
                    <input type="hidden" name="asset_type" value="{{ $assetType }}">
                    <label for="asset_id">{{ $assetType === 'property_unit' ? 'Property / unit / rentable space' : ($assetType === 'hms_event_venue' ? 'Hotel property / event venue' : 'Responsible company location') }}:*</label>
                    {!! Form::select('asset_id', $assetOptions, old('asset_id', $isEdit ? $document->asset_id : ($prefill['asset_id'] ?? null)), ['id'=>'asset_id','class'=>'form-control select2','placeholder'=>'Select the exact asset or venue']) !!}
                    <p class="help-block">Required for weddings and venue hires. Deposits and alerts remain tied to this exact asset, company and branch.</p>
                </div>
                @endif
                <div class="col-md-3 form-group">
                    <label for="issue_date">Issue date:*</label>
                    <input type="date" id="issue_date" name="issue_date" class="form-control" required value="{{ $fieldValue('issue_date', now()->toDateString()) instanceof \Carbon\Carbon ? $fieldValue('issue_date')->toDateString() : $fieldValue('issue_date', now()->toDateString()) }}">
                </div>
                <div class="col-md-3 form-group">
                    <label for="valid_until">Valid until / due date</label>
                    <input type="date" id="valid_until" name="valid_until" class="form-control" value="{{ $fieldValue('valid_until') instanceof \Carbon\Carbon ? $fieldValue('valid_until')->toDateString() : $fieldValue('valid_until') }}">
                </div>
                <div class="col-md-3 form-group">
                    <label for="service_start_at">Service / occupancy starts</label>
                    <input type="datetime-local" id="service_start_at" name="service_start_at" class="form-control" value="{{ old('service_start_at', $isEdit && $document->service_start_at ? $document->service_start_at->format('Y-m-d\TH:i') : ($prefill['service_start_at'] ?? '')) }}">
                </div>
                <div class="col-md-3 form-group">
                    <label for="service_end_at">Service / occupancy ends</label>
                    <input type="datetime-local" id="service_end_at" name="service_end_at" class="form-control" value="{{ old('service_end_at', $isEdit && $document->service_end_at ? $document->service_end_at->format('Y-m-d\TH:i') : ($prefill['service_end_at'] ?? '')) }}">
                </div>
            </div>
        </div>

        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">2. Scenario details</h3></div>
            <div class="box-body"><div id="scenario_fields" class="row"></div><p class="help-block"><i class="fa fa-info-circle"></i> Required scenario fields are enforced when the document is issued, allowing incomplete work to be safely saved as a draft.</p></div>
        </div>

        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">3. Charges, services or payments</h3><div class="box-tools pull-right"><button type="button" class="btn btn-xs btn-default" id="add_line"><i class="fa fa-plus"></i> Add line</button></div></div>
            <div class="box-body table-responsive">
                <table class="table table-bordered" id="document_lines">
                    <thead><tr><th style="width:120px">Kind</th><th>Description</th><th style="width:100px">Qty</th><th style="width:140px">Unit price</th><th style="width:120px">Discount</th><th style="width:120px">Tax</th><th style="width:130px">Line total</th><th style="width:45px"></th></tr></thead>
                    <tbody>
                        @foreach($initialLines as $index => $line)
                            <tr>
                                <td><select class="form-control input-sm" name="lines[{{ $index }}][line_type]">@foreach(['item'=>'Item','service'=>'Service','charge'=>'Charge','payment'=>'Payment','deposit'=>'Payment Deposit (Advance)','tax'=>'Tax','discount'=>'Discount','note'=>'Note'] as $value=>$label)<option value="{{ $value }}" @selected(($line['line_type'] ?? 'item') === $value)>{{ $label }}</option>@endforeach</select></td>
                                <td><input class="form-control input-sm" name="lines[{{ $index }}][description]" maxlength="2000" value="{{ $line['description'] ?? '' }}" placeholder="Describe the room, rent period, event package, item or payment"></td>
                                <td><input type="number" step="0.0001" min="0.0001" class="form-control input-sm line-qty" name="lines[{{ $index }}][quantity]" value="{{ $line['quantity'] ?? 1 }}"></td>
                                <td><input type="number" step="0.0001" class="form-control input-sm line-price" name="lines[{{ $index }}][unit_price]" value="{{ $line['unit_price'] ?? 0 }}"></td>
                                <td><input type="number" step="0.0001" min="0" class="form-control input-sm line-discount" name="lines[{{ $index }}][discount_amount]" value="{{ $line['discount_amount'] ?? 0 }}"></td>
                                <td><input type="number" step="0.0001" min="0" class="form-control input-sm line-tax" name="lines[{{ $index }}][tax_amount]" value="{{ $line['tax_amount'] ?? 0 }}"></td>
                                <td class="line-total text-right">0.00</td>
                                <td><button type="button" class="btn btn-xs btn-danger remove-line" title="Remove line"><i class="fa fa-times"></i></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><th colspan="6" class="text-right">Calculated total</th><th id="document_total" class="text-right">0.00</th><th></th></tr></tfoot>
                </table>
            </div>
            <div class="box-body row">
                <div class="col-md-4 form-group"><label for="currency_id">Currency:*</label>{!! Form::select('currency_id', $currencies, $fieldValue('currency_id', session('business.currency_id')), ['id'=>'currency_id','class'=>'form-control select2','required']) !!}</div>
                <div class="col-md-4 form-group"><label for="exchange_rate">Exchange rate:*</label><input type="number" step="0.00000001" min="0.00000001" id="exchange_rate" name="exchange_rate" class="form-control" required value="{{ $fieldValue('exchange_rate', 1) }}"><p class="help-block">Rate against the company base currency.</p></div>
                <div class="col-md-4 form-group" id="payment_received_group"><label for="amount_paid">Payment already received</label><input type="number" step="0.0001" min="0" id="amount_paid" name="amount_paid" class="form-control" value="{{ $fieldValue('amount_paid', 0) }}"><p class="help-block">For events, rentals and bookings this is a Payment Deposit (Advance) and reduces the invoice balance. Never enter a refundable security deposit here.</p></div>
                @if(in_array($industryCode, ['property_management_rentals', 'hotel_lodge_guesthouse', 'hotel_with_restaurant'], true))
                    <div class="col-md-12">
                        @include('partials.advance_deposit_guidance', [
                            'advanceGuideId' => 'smart-document-advance-guide',
                            'advanceGuideTotalSelector' => '#document_total',
                            'advanceGuidePaidSelector' => '#amount_paid',
                            'advanceGuideScenarioSelector' => '#scenario_code',
                            'advanceGuideScenarios' => ['sale', 'rental', 'short_stay', 'long_stay', 'event', 'catering'],
                            'advanceGuideContext' => 'hospitality, property, or venue document',
                        ])
                    </div>
                @endif
            </div>
        </div>

        <div class="box box-primary" id="agreement_box">
            <div class="box-header with-border"><h3 class="box-title">4. Terms and signatories</h3></div>
            <div class="box-body">
                <div class="alert alert-warning agreement-warning" style="display:none"><i class="fa fa-warning"></i> This is an agreement. Its legal terms must be approved in <a href="{{ route('smart-documents.settings') }}">Document Settings</a> before it can be issued. CashERP supplies structure, not legal advice.</div>
                <div class="form-group"><label for="terms">Terms and conditions</label><textarea id="terms" name="terms" class="form-control" rows="8" maxlength="100000" placeholder="Use your company-approved terms; do not rely on a sample contract without local legal review.">{{ old('terms', $isEdit ? $document->terms : optional($settings->get($selectedTypeId))->default_terms) }}</textarea></div>
                <div id="signatories">
                    @foreach($initialSignatories as $index => $signatory)
                        <div class="row signatory-row">
                            <div class="col-md-3 form-group"><label>Role</label><input class="form-control" name="signatories[{{ $index }}][role]" value="{{ $signatory['role'] ?? '' }}" maxlength="255"></div>
                            <div class="col-md-3 form-group"><label>Name</label><input class="form-control" name="signatories[{{ $index }}][name]" value="{{ $signatory['name'] ?? '' }}" maxlength="255"></div>
                            <div class="col-md-2 form-group"><label>Position</label><input class="form-control" name="signatories[{{ $index }}][position]" value="{{ $signatory['position'] ?? '' }}" maxlength="255"></div>
                            <div class="col-md-2 form-group"><label>Phone / identifier</label><input class="form-control" name="signatories[{{ $index }}][phone]" value="{{ $signatory['phone'] ?? '' }}" maxlength="100"></div>
                            <div class="col-md-2 form-group"><label>Email</label><input type="email" class="form-control" name="signatories[{{ $index }}][email]" value="{{ $signatory['email'] ?? '' }}" maxlength="255"></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="box box-default">
            <div class="box-header with-border"><h3 class="box-title">5. Internal notes</h3></div>
            <div class="box-body"><textarea name="notes" class="form-control" rows="3" maxlength="20000" placeholder="Optional internal or document notes">{{ $fieldValue('notes') }}</textarea></div>
            <div class="box-footer"><a class="btn btn-default" href="{{ $isEdit ? route('smart-documents.show', $document) : route('smart-documents.index') }}">Cancel</a><button class="btn btn-primary pull-right" id="save_document"><i class="fa fa-save"></i> Save draft</button></div>
        </div>
    {!! Form::close() !!}
</section>
@endsection

@section('javascript')
<script>
(function () {
    var typeMeta = @json($typeMeta);
    var scenarioValues = @json($scenarios);
    var existingData = @json($initialData);
    var recommendationUrl = @json(route('smart-documents.recommendations'));
    var lineIndex = {{ count($initialLines) }};
    var csrf = document.querySelector('meta[name="csrf-token"]');

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>'"]/g, function (char) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char];
        });
    }

    function currentMeta() {
        return typeMeta[document.getElementById('document_type_id').value] || null;
    }

    function captureScenarioData() {
        document.querySelectorAll('#scenario_fields [name^="data["]').forEach(function (input) {
            var match = input.name.match(/^data\[([^\]]+)\]$/);
            if (match) existingData[match[1]] = input.value;
        });
    }

    function renderFields(meta) {
        var target = document.getElementById('scenario_fields');
        if (!meta) { target.innerHTML = '<div class="col-md-12 text-muted">No enabled document type is available.</div>'; return; }
        var fields = meta.fields || [];
        if (meta.code === 'room_booking_quotation') {
            fields = fields.filter(function (field) {
                return ['payment_deposit_amount', 'payment_date', 'payment_method', 'payment_reference'].indexOf(field.key) === -1;
            });
        }
        target.innerHTML = fields.map(function (field) {
            var value = existingData[field.key] === undefined ? '' : existingData[field.key];
            var required = field.required ? ' <span class="text-red">*</span>' : '';
            var input;
            if (field.type === 'textarea') {
                input = '<textarea class="form-control" rows="3" name="data[' + escapeHtml(field.key) + ']">' + escapeHtml(value) + '</textarea>';
            } else if (field.type === 'select') {
                var options = '<option value="">Please select</option>';
                Object.keys(field.options || {}).forEach(function (key) {
                    options += '<option value="' + escapeHtml(key) + '"' + (String(value) === String(key) ? ' selected' : '') + '>' + escapeHtml(field.options[key]) + '</option>';
                });
                input = '<select class="form-control" name="data[' + escapeHtml(field.key) + ']">' + options + '</select>';
            } else {
                var inputType = ['date','time','number'].indexOf(field.type) !== -1 ? field.type : 'text';
                input = '<input type="' + inputType + '"' + (inputType === 'number' ? ' step="0.0001"' : '') + ' class="form-control" name="data[' + escapeHtml(field.key) + ']" value="' + escapeHtml(value) + '">';
            }
            return '<div class="col-md-' + (field.type === 'textarea' ? '6' : '4') + ' form-group"><label>' + escapeHtml(field.label) + required + '</label>' + input + '</div>';
        }).join('') || '<div class="col-md-12 text-muted">No additional details are required for this document.</div>';

        var agreement = !!meta.requires_acceptance;
        document.querySelectorAll('.agreement-warning').forEach(function (el) { el.style.display = agreement ? 'block' : 'none'; });
        if (!document.getElementById('title').value) document.getElementById('title').placeholder = meta.title || meta.name;
    }

    function renderSignatories(roles) {
        var container = document.getElementById('signatories');
        if (!roles || !roles.length) { container.innerHTML = ''; return; }
        container.innerHTML = roles.map(function (role, index) {
            return '<div class="row signatory-row"><div class="col-md-3 form-group"><label>Role</label><input class="form-control" name="signatories['+index+'][role]" value="'+escapeHtml(role)+'" maxlength="255"></div><div class="col-md-3 form-group"><label>Name</label><input class="form-control" name="signatories['+index+'][name]" maxlength="255"></div><div class="col-md-2 form-group"><label>Position</label><input class="form-control" name="signatories['+index+'][position]" maxlength="255"></div><div class="col-md-2 form-group"><label>Phone / identifier</label><input class="form-control" name="signatories['+index+'][phone]" maxlength="100"></div><div class="col-md-2 form-group"><label>Email</label><input type="email" class="form-control" name="signatories['+index+'][email]" maxlength="255"></div></div>';
        }).join('');
    }

    function updateScenarioHelp() {
        var select = document.getElementById('scenario_code');
        var selected = select.options[select.selectedIndex];
        document.getElementById('scenario_help').textContent = selected ? selected.getAttribute('data-help') || '' : '';
    }

    function toggleEventAsset() {
        var group = document.getElementById('event_asset_group');
        if (!group) return;
        var isEvent = document.getElementById('scenario_code').value === 'event';
        group.style.display = isEvent ? '' : 'none';
        var select = document.getElementById('asset_id');
        if (select) select.required = isEvent;
    }

    document.getElementById('scenario_code').addEventListener('change', function () {
        captureScenarioData();
        updateScenarioHelp();
        toggleEventAsset();
        var url = recommendationUrl + '?scenario=' + encodeURIComponent(this.value);
        fetch(url, {headers: {'Accept':'application/json','X-CSRF-TOKEN': csrf ? csrf.content : ''}})
            .then(function (response) { if (!response.ok) throw new Error('Unable to load recommendations'); return response.json(); })
            .then(function (payload) {
                var types = payload.data || [];
                var select = document.getElementById('document_type_id');
                select.innerHTML = '';
                types.forEach(function (type) {
                    typeMeta[type.id] = type;
                    var option = document.createElement('option'); option.value = type.id; option.textContent = type.name; select.appendChild(option);
                });
                renderFields(currentMeta());
                renderSignatories((currentMeta() || {}).signatory_roles || []);
                recalculate();
            })
            .catch(function () { window.toastr ? toastr.error('Document recommendations could not be refreshed. Your current selection was kept.') : null; });
    });

    document.getElementById('document_type_id').addEventListener('change', function () {
        captureScenarioData();
        var meta = currentMeta(); renderFields(meta); renderSignatories((meta || {}).signatory_roles || []); recalculate();
        var terms = document.getElementById('terms'); if (!terms.value && meta && meta.default_terms) terms.value = meta.default_terms;
    });

    function lineRow(index) {
        return '<tr><td><select class="form-control input-sm" name="lines['+index+'][line_type]"><option value="item">Item</option><option value="service">Service</option><option value="charge">Charge</option><option value="payment">Payment</option><option value="deposit">Payment Deposit (Advance)</option><option value="tax">Tax</option><option value="discount">Discount</option><option value="note">Note</option></select></td><td><input class="form-control input-sm" name="lines['+index+'][description]" maxlength="2000" placeholder="Description"></td><td><input type="number" step="0.0001" min="0.0001" class="form-control input-sm line-qty" name="lines['+index+'][quantity]" value="1"></td><td><input type="number" step="0.0001" class="form-control input-sm line-price" name="lines['+index+'][unit_price]" value="0"></td><td><input type="number" step="0.0001" min="0" class="form-control input-sm line-discount" name="lines['+index+'][discount_amount]" value="0"></td><td><input type="number" step="0.0001" min="0" class="form-control input-sm line-tax" name="lines['+index+'][tax_amount]" value="0"></td><td class="line-total text-right">0.00</td><td><button type="button" class="btn btn-xs btn-danger remove-line"><i class="fa fa-times"></i></button></td></tr>';
    }

    function recalculate() {
        var total = 0;
        document.querySelectorAll('#document_lines tbody tr').forEach(function (row) {
            var qty = parseFloat((row.querySelector('.line-qty') || {}).value) || 0;
            var price = parseFloat((row.querySelector('.line-price') || {}).value) || 0;
            var discount = parseFloat((row.querySelector('.line-discount') || {}).value) || 0;
            var tax = parseFloat((row.querySelector('.line-tax') || {}).value) || 0;
            var value = qty * price - discount + tax;
            row.querySelector('.line-total').textContent = value.toFixed(2); total += value;
        });
        document.getElementById('document_total').textContent = total.toFixed(2);
        var amountPaid = document.getElementById('amount_paid');
        var paymentGroup = document.getElementById('payment_received_group');
        var meta = currentMeta();
        var isReceipt = meta && String(meta.code || '').indexOf('receipt') !== -1;
        var isFinancial = meta && meta.is_financial;
        if (!isFinancial) {
            amountPaid.value = '0';
            amountPaid.readOnly = true;
            amountPaid.dataset.nonFinancial = '1';
            if (paymentGroup) paymentGroup.style.display = 'none';
        } else if (isReceipt) {
            if (paymentGroup) paymentGroup.style.display = '';
            amountPaid.value = Math.max(0, total).toFixed(4);
            amountPaid.readOnly = true;
            amountPaid.dataset.receiptSync = '1';
            delete amountPaid.dataset.nonFinancial;
        } else {
            if (paymentGroup) paymentGroup.style.display = '';
            amountPaid.readOnly = false;
            if (amountPaid.dataset.nonFinancial === '1') amountPaid.value = '0';
            delete amountPaid.dataset.receiptSync;
            delete amountPaid.dataset.nonFinancial;
        }
        if (window.refreshAdvanceDepositGuides) window.refreshAdvanceDepositGuides();
    }

    document.getElementById('add_line').addEventListener('click', function () {
        document.querySelector('#document_lines tbody').insertAdjacentHTML('beforeend', lineRow(lineIndex++)); recalculate();
    });
    document.getElementById('document_lines').addEventListener('click', function (event) {
        var button = event.target.closest('.remove-line'); if (!button) return;
        if (document.querySelectorAll('#document_lines tbody tr').length > 1) button.closest('tr').remove(); recalculate();
    });
    document.getElementById('document_lines').addEventListener('input', recalculate);
    document.getElementById('smart_document_form').addEventListener('submit', function () {
        var button = document.getElementById('save_document'); button.disabled = true; button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving draft...';
    });

    updateScenarioHelp(); toggleEventAsset(); renderFields(currentMeta()); recalculate();
}());
</script>
@endsection
