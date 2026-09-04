@extends('layouts.app')
@section('title', 'Events & Banqueting')
@section('content')
@include('hms::layouts.nav')
<section class="content-header"><h1>Events &amp; Banqueting <small>venue availability, operational status and folio charges</small></h1></section>
<section class="content">
    <div class="callout callout-info"><h4>Deposits remain distinct</h4><p>The payment deposit is an advance against the event price and reduces the event folio balance. A refundable security deposit remains a separate liability and must use the Security Deposit workflow. The 50% advance is guidance only, not an enforced rule.</p></div>
    <div class="row"><div class="col-md-4"><div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">New event booking</h3></div>
        <form method="post" action="{{ route('hms.events.store') }}">@csrf<div class="box-body">
            <div class="form-group"><label>Property:*</label>{!! Form::select('hms_property_id',$properties,old('hms_property_id'),['class'=>'form-control select2','required','placeholder'=>'Select property']) !!}</div>
            <div class="form-group"><label>Venue:*</label>{!! Form::select('hms_event_venue_id',$venues->mapWithKeys(fn($venue)=>[$venue->id=>optional($venue->property)->name.' · '.$venue->name]),old('hms_event_venue_id'),['class'=>'form-control select2','required','placeholder'=>'Select venue']) !!}</div>
            <div class="form-group"><label>Customer / organiser:*</label>{!! Form::select('contact_id',$contacts,old('contact_id'),['class'=>'form-control select2','required','placeholder'=>'Select customer']) !!}</div>
            <div class="form-group"><label>Event name:*</label><input name="event_name" value="{{ old('event_name') }}" class="form-control" required maxlength="255"></div>
            <div class="form-group"><label>Event type:*</label>{!! Form::select('event_type',['wedding'=>'Wedding','meeting'=>'Meeting','conference'=>'Conference','party'=>'Party / celebration','exhibition'=>'Exhibition','training'=>'Training','catering'=>'Off-site catering','other'=>'Other'],old('event_type','meeting'),['class'=>'form-control','required']) !!}</div>
            <div class="row"><div class="col-xs-6"><div class="form-group"><label>Starts:*</label><input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" class="form-control" required></div></div><div class="col-xs-6"><div class="form-group"><label>Ends:*</label><input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="form-control" required></div></div></div>
            <div class="form-group"><label>Expected guests:*</label><input type="number" name="expected_guests" value="{{ old('expected_guests',1) }}" min="1" class="form-control" required></div>
            <div class="row"><div class="col-xs-6"><div class="form-group"><label>Agreed price:*</label><input id="event-agreed-amount" type="number" step="0.0001" min="0" name="agreed_amount" value="{{ old('agreed_amount',0) }}" class="form-control" required></div></div><div class="col-xs-6"><div class="form-group"><label>Payment deposit guide</label><input id="event-deposit-required" type="number" step="0.0001" min="0" name="payment_deposit_required" value="{{ old('payment_deposit_required',0) }}" class="form-control"><small id="event-deposit-guide" class="help-block"></small></div></div></div>
            <div class="form-group"><label>Special requests</label><textarea name="special_requests" class="form-control" rows="2" maxlength="5000">{{ old('special_requests') }}</textarea></div>
        </div><div class="box-footer"><button class="btn btn-primary"><i class="fa fa-calendar-plus-o"></i> Create event booking</button></div></form>
    </div></div>
    <div class="col-md-8"><div class="box box-primary"><div class="box-header with-border"><h3 class="box-title">Event schedule and folios</h3></div><div class="box-body table-responsive">
        <table class="table table-bordered table-striped"><thead><tr><th>Event</th><th>Venue / time</th><th>Financial position</th><th>Workflow</th></tr></thead><tbody>
        @forelse($events as $event)<tr>
            <td><strong>{{ $event->event_number }}</strong><br>{{ $event->event_name }} · {{ ucfirst($event->event_type) }}<br><small>{{ $event->expected_guests }} expected guests</small></td>
            <td>{{ optional($event->property)->name }} · {{ optional($event->venue)->name }}<br><small>{{ $event->starts_at->format('Y-m-d H:i') }} → {{ $event->ends_at->format('Y-m-d H:i') }}</small></td>
            <td>Agreed: {{ number_format((float)$event->agreed_amount,2) }}<br>Advance guide: {{ number_format((float)$event->payment_deposit_required,2) }}<br>Folio: @if($event->folio)<a href="{{ route('hms.folios.show',$event->folio->id) }}"><strong>{{ $event->folio->folio_number }}</strong></a> · balance {{ number_format((float)$event->folio->balance,2) }}@else — @endif
                <div class="btn-group tw-mt-2">
                    <a class="btn btn-xs btn-default" href="{{ route('smart-documents.create', ['source_type'=>'hms_event_booking','source_id'=>$event->id,'scenario'=>'event','type'=>'event_quotation']) }}">A4 quotation</a>
                    <a class="btn btn-xs btn-default" href="{{ route('smart-documents.create', ['source_type'=>'hms_event_booking','source_id'=>$event->id,'scenario'=>'event','type'=>'event_reservation_contract']) }}">Contract</a>
                    <a class="btn btn-xs btn-primary" href="{{ route('smart-documents.create', ['source_type'=>'hms_event_booking','source_id'=>$event->id,'scenario'=>'event','type'=>'event_booking_invoice']) }}">A4 invoice</a>
                </div>
                <form method="post" action="{{ route('hms.events.charges.store',$event->id) }}" class="form-inline tw-mt-2">@csrf
                    {!! Form::select('category',['venue'=>'Venue charge','food'=>'Food','beverage'=>'Beverage','equipment'=>'Equipment','service'=>'Service','payment_deposit'=>'Payment deposit (advance)','discount'=>'Discount','other'=>'Other'],null,['class'=>'form-control input-sm','required']) !!}
                    <input name="description" class="form-control input-sm" maxlength="255" required placeholder="Description">
                    <input type="number" name="amount" step="0.0001" min="0.0001" class="form-control input-sm" required placeholder="Amount">
                    <input name="payment_method" class="form-control input-sm" maxlength="40" placeholder="Payment method for advance">
                    <button class="btn btn-default btn-sm">Post</button>
                </form>
            </td>
            <td><span class="label label-info">{{ ucfirst(str_replace('_',' ',$event->status)) }}</span>
                @php($next = ['tentative'=>['confirmed'=>'Confirm','cancelled'=>'Cancel'],'confirmed'=>['in_progress'=>'Start event','cancelled'=>'Cancel'],'in_progress'=>['completed'=>'Complete']][$event->status] ?? [])
                @foreach($next as $status=>$label)<form method="post" action="{{ route('hms.events.transition',$event->id) }}" style="display:inline">@csrf<input type="hidden" name="status" value="{{ $status }}"><button class="btn btn-xs {{ $status==='cancelled' ? 'btn-danger':'btn-primary' }}">{{ $label }}</button></form>@endforeach
            </td>
        </tr>@empty<tr><td colspan="4" class="text-center text-muted">No event bookings yet.</td></tr>@endforelse
        </tbody></table>{{ $events->links() }}
    </div></div></div></div>
</section>
@endsection
@section('javascript')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var total = document.getElementById('event-agreed-amount'), deposit = document.getElementById('event-deposit-required'), guide = document.getElementById('event-deposit-guide');
    function updateGuide() { var recommended = Math.max(0, Number(total.value || 0) * .5); guide.textContent = recommended ? 'Guidance: 50% is '+recommended.toFixed(2)+'. You may proceed with another agreed amount.' : ''; }
    total.addEventListener('input', updateGuide); deposit.addEventListener('input', updateGuide); updateGuide();
});
</script>
@endsection
