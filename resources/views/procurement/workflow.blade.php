@extends('layouts.app')
@section('title', 'Procurement approval')

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        {{ $transaction->type === 'purchase_requisition' ? 'Purchase requisition' : 'Purchase order' }}
        <small>#{{ $transaction->ref_no }}</small>
    </h1>
</section>

<section class="content">
    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="tw-mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if(!$document)
        <div class="alert alert-warning">
            This is a legacy procurement record created before approval tracking was enabled. Its original operational status remains unchanged.
        </div>
    @else
        <div class="row">
            <div class="col-md-8">
                @component('components.widget', ['class' => 'box-solid', 'title' => 'Request details'])
                    <div class="row">
                        <div class="col-sm-4"><strong>Reference</strong><br>#{{ $transaction->ref_no }}</div>
                        <div class="col-sm-4"><strong>Company location</strong><br>{{ optional($transaction->location)->name }}</div>
                        <div class="col-sm-4"><strong>Requester</strong><br>{{ optional($document->requester)->user_full_name }}</div>
                        <div class="col-sm-4 tw-mt-3"><strong>Department</strong><br>{{ optional($document->department)->name ?: 'Not assigned' }}</div>
                        <div class="col-sm-4 tw-mt-3"><strong>Project</strong><br>{{ $projectName ?: 'Not assigned' }}</div>
                        <div class="col-sm-4 tw-mt-3"><strong>Priority</strong><br><span class="label {{ $document->priority === 'urgent' ? 'bg-red' : ($document->priority === 'high' ? 'bg-yellow' : 'bg-blue') }}">{{ ucfirst($document->priority) }}</span></div>
                        <div class="col-sm-4 tw-mt-3"><strong>Submitted</strong><br>{{ optional($document->submitted_at)->format('Y-m-d H:i') }}</div>
                        <div class="col-sm-4 tw-mt-3"><strong>Required / delivery date</strong><br>{{ $transaction->delivery_date ? \Carbon\Carbon::parse($transaction->delivery_date)->format('Y-m-d H:i') : 'Not set' }}</div>
                        <div class="col-sm-4 tw-mt-3">
                            <strong>Estimated budget</strong><br>
                            @if($document->budget_amount !== null)
                                @num_format($document->budget_amount)
                            @else
                                Not set
                            @endif
                        </div>
                        <div class="col-sm-12 tw-mt-3"><strong>Business purpose</strong><p class="tw-mb-0">{{ $document->purpose ?: 'Not provided' }}</p></div>
                    </div>
                @endcomponent

                @component('components.widget', ['class' => 'box-solid', 'title' => 'Items'])
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead><tr><th>Product</th><th class="text-right">Quantity</th></tr></thead>
                            <tbody>
                            @foreach($transaction->purchase_lines as $line)
                                <tr>
                                    <td>{{ optional($line->product)->name }} @if($line->variations) &mdash; {{ $line->variations->name }} @endif</td>
                                    <td class="text-right">@format_quantity($line->quantity) {{ optional(optional($line->product)->unit)->short_name }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endcomponent

                @if($document->document_type === 'requisition')
                    @component('components.widget', ['class' => 'box-solid', 'title' => 'Supplier price comparison'])
                        @if($document->quotes->isEmpty())
                            <div class="alert alert-info tw-mb-0">No supplier quotations have been entered yet.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead><tr><th>Supplier</th><th>Quote</th><th>Currency</th><th class="text-right">Subtotal</th><th class="text-right">Tax</th><th class="text-right">Shipping</th><th class="text-right">Quote total</th><th class="text-right">Company-currency total</th><th>Delivery</th><th>Action</th></tr></thead>
                                    <tbody>
                                    @foreach($document->quotes->sortBy(fn ($quote) => (float) $quote->total * (float) $quote->exchange_rate) as $quote)
                                        <tr class="{{ $document->selected_quote_id === $quote->id ? 'success' : '' }}">
                                            <td>{{ optional($quote->supplier)->name }}</td>
                                            <td>{{ $quote->quote_reference ?: '—' }} @if($quote->attachment)<br><a href="{{ asset('uploads/documents/'.$quote->attachment) }}" target="_blank" rel="noopener">Attachment</a>@endif</td>
                                            <td>{{ optional($quote->currency)->code }}<br><small>Rate: {{ rtrim(rtrim(number_format($quote->exchange_rate, 8, '.', ''), '0'), '.') }}</small></td>
                                            <td class="text-right">{{ optional($quote->currency)->symbol }} {{ number_format($quote->subtotal, 2) }}</td>
                                            <td class="text-right">{{ optional($quote->currency)->symbol }} {{ number_format($quote->tax_amount, 2) }}</td>
                                            <td class="text-right">{{ optional($quote->currency)->symbol }} {{ number_format($quote->shipping_amount, 2) }}</td>
                                            <td class="text-right"><strong>{{ optional($quote->currency)->symbol }} {{ number_format($quote->total, 2) }}</strong></td>
                                            <td class="text-right"><strong>@format_currency((float) $quote->total * (float) $quote->exchange_rate)</strong></td>
                                            <td>{{ optional($quote->delivery_date)->format('Y-m-d') ?: '—' }}</td>
                                            <td>
                                                @if($document->selected_quote_id === $quote->id)
                                                    <span class="label bg-green"><i class="fa fa-check" aria-hidden="true"></i> Selected</span>
                                                @elseif($quote->valid_until && $quote->valid_until->lt(today()))
                                                    <span class="label bg-red">Expired</span>
                                                @elseif($canManageQuotes && in_array($document->approval_status, ['pending_manager', 'pending_finance', 'rejected'], true))
                                                    <form method="POST" action="{{ route('procurement.quotes.select', [$transaction->id, $quote->id]) }}">
                                                        @csrf
                                                        <button class="btn btn-primary btn-xs" type="submit">Select</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if($canManageQuotes && in_array($document->approval_status, ['pending_manager', 'pending_finance', 'rejected'], true))
                            <hr>
                            <h4>Add supplier quotation</h4>
                            <form method="POST" enctype="multipart/form-data" action="{{ route('procurement.quotes.store', $transaction->id) }}" data-quote-form>
                                @csrf
                                <div class="row">
                                    <div class="col-sm-4"><div class="form-group">{!! Form::label('supplier_id', 'Supplier:*') !!}{!! Form::select('supplier_id', $suppliers, old('supplier_id'), ['class' => 'form-control select2', 'required', 'placeholder' => __('messages.please_select')]) !!}</div></div>
                                    <div class="col-sm-4"><div class="form-group">{!! Form::label('quote_reference', 'Supplier quote number:') !!}{!! Form::text('quote_reference', old('quote_reference'), ['class' => 'form-control', 'maxlength' => 100]) !!}</div></div>
                                    <div class="col-sm-4"><div class="form-group">{!! Form::label('currency_id', 'Quote currency:*') !!}{!! Form::select('currency_id', $currencies, old('currency_id', $document->currency_id), ['class' => 'form-control select2', 'required', 'placeholder' => __('messages.please_select')]) !!}</div></div>
                                    <div class="col-sm-4"><div class="form-group">{!! Form::label('exchange_rate', 'Rate to company currency:*') !!}{!! Form::number('exchange_rate', old('exchange_rate', 1), ['class' => 'form-control', 'required', 'min' => '0.00000001', 'step' => '0.00000001']) !!}<span class="help-block">1 quote-currency unit × rate = company-currency value.</span></div></div>
                                    <div class="col-sm-4"><div class="form-group">{!! Form::label('tax_id', 'Tax rule:') !!}{!! Form::select('tax_id', $taxes, old('tax_id'), ['class' => 'form-control select2', 'placeholder' => 'No tax']) !!}</div></div>
                                    <div class="col-sm-4"><div class="form-group">{!! Form::label('shipping_amount', 'Shipping amount:') !!}{!! Form::number('shipping_amount', old('shipping_amount', 0), ['class' => 'form-control', 'min' => 0, 'step' => '0.01']) !!}</div></div>
                                    <div class="col-sm-4"><div class="form-group">{!! Form::label('delivery_date', 'Expected delivery:') !!}{!! Form::date('delivery_date', old('delivery_date'), ['class' => 'form-control']) !!}</div></div>
                                    <div class="col-sm-4"><div class="form-group">{!! Form::label('valid_until', 'Quote valid until:') !!}{!! Form::date('valid_until', old('valid_until'), ['class' => 'form-control']) !!}</div></div>
                                    <div class="col-sm-4"><div class="form-group">{!! Form::label('attachment', 'Quote attachment:') !!}{!! Form::file('attachment', ['class' => 'form-control', 'accept' => '.pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx']) !!}</div></div>
                                    <div class="col-sm-12"><div class="table-responsive"><table class="table table-condensed"><thead><tr><th>Item</th><th>Quantity</th><th>Unit price in quote currency</th></tr></thead><tbody>
                                        @foreach($transaction->purchase_lines as $line)
                                            @php $quotedQuantity = (float) $line->quantity > 0 ? $line->quantity : $line->secondary_unit_quantity; @endphp
                                            <tr><td>{{ optional($line->product)->name }} @if($line->variations) &mdash; {{ $line->variations->name }} @endif</td><td>@format_quantity($quotedQuantity)</td><td><input class="form-control" type="number" name="line_prices[{{ $line->id }}]" value="{{ old('line_prices.'.$line->id) }}" min="0" step="0.0001" required inputmode="decimal"></td></tr>
                                        @endforeach
                                    </tbody></table></div></div>
                                    <div class="col-sm-12"><div class="form-group">{!! Form::label('notes', 'Supplier / evaluation notes:') !!}{!! Form::textarea('notes', old('notes'), ['class' => 'form-control', 'rows' => 2, 'maxlength' => 3000]) !!}</div></div>
                                    <div class="col-sm-12 text-right"><button class="btn btn-primary" type="submit"><i class="fa fa-plus" aria-hidden="true"></i> Add quotation</button></div>
                                </div>
                            </form>
                        @endif
                    @endcomponent
                @else
                    @component('components.widget', ['class' => 'box-solid', 'title' => 'Purchase order links'])
                        <p><strong>Supplier:</strong> {{ optional($transaction->contact)->name }}</p>
                        @if($document->parent_transaction_id)<p><strong>Source requisition:</strong> <a href="{{ route('procurement.workflow.show', $document->parent_transaction_id) }}">Open requisition</a></p>@endif
                        @if($document->selectedQuote)<p><strong>Selected quotation:</strong> {{ $document->selectedQuote->quote_reference ?: '#'.$document->selectedQuote->id }} from {{ optional($document->selectedQuote->supplier)->name }}</p>@endif
                        @if($document->autoExpense)<p class="tw-mb-0"><strong>Automatic expense:</strong> {{ $document->autoExpense->ref_no }} &mdash; <span class="display_currency" data-currency_symbol="true">{{ $document->autoExpense->final_total }}</span></p>@endif
                    @endcomponent
                @endif
            </div>

            <div class="col-md-4">
                @component('components.widget', ['class' => 'box-solid', 'title' => 'Approval status'])
                    @php
                        $statusClass = $document->approval_status === 'approved' ? 'bg-green' : ($document->approval_status === 'rejected' ? 'bg-red' : 'bg-yellow');
                    @endphp
                    <p><span class="label {{ $statusClass }} tw-text-sm">{{ ucwords(str_replace('_', ' ', $document->approval_status)) }}</span></p>
                    <ol class="list-unstyled tw-mb-0">
                        @foreach($document->approvals as $step)
                            <li class="tw-py-2 tw-border-b tw-border-gray-100">
                                <i class="fa {{ $step->status === 'approved' ? 'fa-check-circle text-green' : ($step->status === 'rejected' ? 'fa-times-circle text-red' : 'fa-clock-o text-muted') }}" aria-hidden="true"></i>
                                <strong>{{ ucfirst($step->stage) }}</strong>
                                <span class="pull-right">{{ ucfirst($step->status) }}</span>
                                @if($step->actor)<br><small>{{ $step->actor->user_full_name }} &mdash; {{ optional($step->acted_at)->format('Y-m-d H:i') }}</small>@endif
                                @if($step->note)<br><small>{{ $step->note }}</small>@endif
                            </li>
                        @endforeach
                    </ol>
                @endcomponent

                @if($canAct)
                    @component('components.widget', ['class' => 'box-solid', 'title' => ucfirst($document->current_stage).' decision'])
                        <form method="POST" action="{{ route('procurement.workflow.approve', $transaction->id) }}" class="tw-mb-3" data-decision-form data-confirm="Approve this {{ $document->document_type === 'requisition' ? 'requisition' : 'purchase order' }} and advance its workflow?">
                            @csrf
                            <div class="form-group"><label for="approval_note">Approval note</label><textarea id="approval_note" name="note" class="form-control" rows="2" maxlength="3000"></textarea></div>
                            <button type="submit" class="btn btn-success btn-block"><i class="fa fa-check" aria-hidden="true"></i> Approve</button>
                        </form>
                        <form method="POST" action="{{ route('procurement.workflow.reject', $transaction->id) }}" data-decision-form data-confirm="Reject this document and return it to the requester for correction?">
                            @csrf
                            <div class="form-group"><label for="rejection_note">Reason for rejection:*</label><textarea id="rejection_note" name="note" class="form-control" rows="2" required minlength="5" maxlength="3000"></textarea></div>
                            <button type="submit" class="btn btn-danger btn-block"><i class="fa fa-times" aria-hidden="true"></i> Reject</button>
                        </form>
                    @endcomponent
                @elseif($document->approval_status === 'rejected' && auth()->user()->can('procurement.submit') && ((int) $document->requested_by === (int) auth()->id() || auth()->user()->hasRole('Admin#'.$document->business_id) || auth()->user()->can('superadmin')))
                    @component('components.widget', ['class' => 'box-solid', 'title' => 'Correct and resubmit'])
                        @if($document->document_type === 'requisition' && auth()->user()->can('purchase_requisition.create'))
                            <a class="btn btn-default btn-block tw-mb-3" href="{{ action([\App\Http\Controllers\PurchaseRequisitionController::class, 'edit'], [$transaction->id]) }}"><i class="fa fa-edit" aria-hidden="true"></i> Edit requisition corrections</a>
                        @elseif($document->document_type === 'purchase_order' && auth()->user()->can('purchase_order.update'))
                            <a class="btn btn-default btn-block tw-mb-3" href="{{ action([\App\Http\Controllers\PurchaseOrderController::class, 'edit'], [$transaction->id]) }}"><i class="fa fa-edit" aria-hidden="true"></i> Edit order corrections</a>
                        @endif
                        <form method="POST" action="{{ route('procurement.workflow.resubmit', $transaction->id) }}">
                            @csrf
                            <div class="form-group"><label for="resubmit_note">Correction note</label><textarea id="resubmit_note" name="note" class="form-control" rows="2" maxlength="3000"></textarea></div>
                            <button type="submit" class="btn btn-primary btn-block">Resubmit for approval</button>
                        </form>
                    @endcomponent
                @endif

                @component('components.widget', ['class' => 'box-solid', 'title' => 'Audit trail'])
                    <ul class="list-unstyled tw-mb-0">
                        @forelse($document->events as $event)
                            <li class="tw-py-2 tw-border-b tw-border-gray-100">
                                <strong>{{ ucwords(str_replace('_', ' ', $event->event)) }}</strong>
                                <br><small>{{ $event->created_at->format('Y-m-d H:i') }} @if($event->user)&mdash; {{ $event->user->user_full_name }}@endif</small>
                                @if($event->note)<br><span>{{ $event->note }}</span>@endif
                            </li>
                        @empty
                            <li>No events recorded.</li>
                        @endforelse
                    </ul>
                @endcomponent
            </div>
        </div>
    @endif
</section>
@endsection

@section('javascript')
<script>
$(function () {
    $('.select2').select2({ width: '100%' });
    $('[data-decision-form], [data-quote-form]').on('submit', function (event) {
        var confirmation = $(this).data('confirm');
        if (confirmation && !window.confirm(confirmation)) {
            event.preventDefault();
            return;
        }
        $(this).find('button[type="submit"]').prop('disabled', true).attr('aria-busy', 'true');
    });
});
</script>
@endsection
