<div class="box box-warning">
    <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-shield"></i> Refundable Security Deposit</h3>
        @if($securityDeposit)
            <span class="label label-{{ in_array($securityDeposit->status, ['settled', 'waived']) ? 'success' : ($securityDeposit->status === 'excess_due' ? 'danger' : 'warning') }} pull-right">{{ ucfirst(str_replace('_', ' ', $securityDeposit->status)) }}</span>
        @endif
    </div>
    <div class="box-body">
        <div class="alert alert-warning">
            <strong>Not income and not an invoice payment.</strong>
            This register is separate from revenue, tax, expenses and Payment Deposit (Advance) totals. It tracks refundable funds against {{ $contextTitle }} until damage settlement or refund.
        </div>

        @if(!$securityDeposit)
            @if($canManageSecurity)
                {!! Form::open(['url' => $depositRoutes['requirement'], 'method' => 'put', 'class' => 'row']) !!}
                    <div class="col-sm-4 form-group"><label>Required refundable amount</label><input type="number" name="required_amount" value="{{ $defaultRequired ?? 0 }}" min="0" step="0.0001" class="form-control" required></div>
                    <div class="col-sm-4 form-group"><label>Due date</label><input type="date" name="due_date" value="{{ now()->toDateString() }}" class="form-control"></div>
                    <div class="col-sm-4 form-group"><label>&nbsp;</label><button class="btn btn-warning btn-block">Create security-deposit register</button></div>
                {!! Form::close() !!}
            @else
                <p class="text-muted">No refundable security deposit is configured.</p>
            @endif
        @else
            <div class="row">
                @foreach([['Required', $securityDeposit->required_amount], ['Received', $securityDeposit->received_amount], ['Damage', $securityDeposit->damage_amount], ['Refunded', $securityDeposit->refunded_amount], ['Held / refundable', $securityDeposit->held_balance], ['Excess due', $securityDeposit->excess_damage_amount]] as $stat)
                    <div class="col-sm-2"><strong>{{ $stat[0] }}</strong><br><span class="display_currency" data-currency_symbol="true">{{ $stat[1] }}</span></div>
                @endforeach
            </div>

            @if($canManageSecurity)
                <hr>
                <div class="row">
                    <div class="col-md-4">
                        <h4>Receive held funds</h4>
                        {!! Form::open(['url' => $depositRoutes['receipt']]) !!}
                            <input type="hidden" name="idempotency_key" value="{{ Str::uuid() }}">
                            <div class="form-group"><input type="number" name="amount" min="0.0001" step="0.0001" class="form-control" required placeholder="Amount"></div>
                            <div class="form-group"><input type="date" name="occurred_on" value="{{ now()->toDateString() }}" class="form-control" required></div>
                            <div class="form-group">{!! Form::select('payment_method', $paymentMethods, null, ['class' => 'form-control', 'required', 'placeholder' => 'Payment method']) !!}</div>
                            <div class="form-group"><input name="reference" class="form-control" maxlength="191" placeholder="Reference"></div>
                            <button class="btn btn-success">Record security deposit</button>
                        {!! Form::close() !!}
                    </div>
                    <div class="col-md-4">
                        <h4>Damage / loss assessment</h4>
                        {!! Form::open(['url' => $depositRoutes['damage'], 'files' => true]) !!}
                            <div class="form-group"><input type="number" name="amount" min="0.0001" step="0.0001" class="form-control" required placeholder="Assessed amount"></div>
                            <div class="form-group"><input type="date" name="occurred_on" value="{{ now()->toDateString() }}" class="form-control" required></div>
                            <div class="form-group"><textarea name="description" class="form-control" required minlength="5" maxlength="3000" placeholder="Damage/loss and assessment basis"></textarea></div>
                            <div class="form-group"><input type="file" name="evidence" accept=".jpg,.jpeg,.png,.pdf" class="form-control"></div>
                            <button class="btn btn-warning">Record assessment</button>
                        {!! Form::close() !!}
                    </div>
                    <div class="col-md-4">
                        <h4>Refund held balance</h4>
                        {!! Form::open(['url' => $depositRoutes['refund']]) !!}
                            <input type="hidden" name="idempotency_key" value="{{ Str::uuid() }}">
                            <div class="form-group"><input type="number" name="amount" min="0.0001" max="{{ $securityDeposit->available_refund }}" step="0.0001" class="form-control" required placeholder="Refund amount"></div>
                            <div class="form-group"><input type="date" name="occurred_on" value="{{ now()->toDateString() }}" class="form-control" required></div>
                            <div class="form-group">{!! Form::select('payment_method', $paymentMethods, null, ['class' => 'form-control', 'required', 'placeholder' => 'Refund method']) !!}</div>
                            <div class="form-group"><input name="reference" class="form-control" maxlength="191" placeholder="Refund reference"></div>
                            <button class="btn btn-primary">Submit refund for approval</button>
                        {!! Form::close() !!}
                        @if($securityDeposit->received_amount <= 0 && !in_array($securityDeposit->status, ['waived', 'settled']) && !empty($depositRoutes['waive']))
                            <hr>
                            {!! Form::open(['url' => $depositRoutes['waive']]) !!}
                                <input name="reason" class="form-control" required minlength="5" maxlength="1000" placeholder="Reason requirement was waived">
                                <button class="btn btn-link text-danger">Waive uncollected requirement</button>
                            {!! Form::close() !!}
                        @endif
                    </div>
                </div>
            @endif

            <hr>
            <div class="table-responsive"><table class="table table-bordered table-condensed">
                <thead><tr><th>Date</th><th>Type</th><th>Description / evidence</th><th>Method</th><th>Amount</th><th>Status</th><th>Control</th></tr></thead>
                <tbody>
                    @forelse($securityDeposit->entries as $entry)
                        <tr class="{{ $entry->status === 'void' ? 'text-muted' : '' }}">
                            <td>{{ @format_date($entry->occurred_on) }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $entry->entry_type)) }}</td>
                            <td>{{ $entry->description }}<br><small>{{ $entry->reference }}</small>@if($entry->evidence_path)<br><a href="{{ Storage::disk('public')->url($entry->evidence_path) }}" target="_blank" rel="noopener">Evidence</a>@endif @if($entry->business_document_id)<br><a href="{{ route('smart-documents.show', $entry->business_document_id) }}"><i class="fa fa-file-text-o"></i> Linked damage invoice</a>@endif</td>
                            <td>{{ $paymentMethods[$entry->payment_method] ?? $entry->payment_method }}</td>
                            <td class="display_currency" data-currency_symbol="true">{{ $entry->amount }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $entry->status)) }}</td>
                            <td>
                                @if($entry->entry_type === 'refund' && $entry->status === 'pending_approval' && $canApproveSecurity && (int) $entry->created_by !== (int) auth()->id())
                                    {!! Form::open(['url' => str_replace('{entry}', $entry->id, $depositRoutes['approve'])]) !!}<button class="btn btn-xs btn-success">Approve refund</button>{!! Form::close() !!}
                                @elseif($entry->entry_type === 'refund' && $entry->status === 'pending_approval' && (int) $entry->created_by === (int) auth()->id())
                                    <small class="text-muted">Awaiting a different authorized approver</small>
                                @endif
                                @if($entry->entry_type === 'refund' && $entry->status === 'approved' && $canApproveSecurity)
                                    {!! Form::open(['url' => str_replace('{entry}', $entry->id, $depositRoutes['pay'])]) !!}<button class="btn btn-xs btn-primary">Confirm refund paid</button>{!! Form::close() !!}
                                @endif
                                @if($entry->status !== 'void' && $canApproveSecurity)
                                    <button class="btn btn-xs btn-danger" data-toggle="collapse" data-target="#void-security-{{ $entry->id }}">Void</button>
                                @endif
                            </td>
                        </tr>
                        @if($entry->status !== 'void' && $canApproveSecurity)
                            <tr id="void-security-{{ $entry->id }}" class="collapse"><td colspan="7">{!! Form::open(['url' => str_replace('{entry}', $entry->id, $depositRoutes['void']), 'class' => 'form-inline']) !!}<input name="reason" class="form-control" required minlength="3" maxlength="1000" placeholder="Audit reason"><button class="btn btn-danger">Confirm void</button>{!! Form::close() !!}</td></tr>
                        @endif
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">No security-deposit activity.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        @endif
    </div>
</div>
