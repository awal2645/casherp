@extends('layouts.app')
@section('title', 'HR Audit')
@section('content')
@include('essentials::layouts.nav_hrm')
<section class="content-header"><h1>HR Audit <small>Immutable company-scoped change and access history</small></h1></section>
<section class="content"><div class="row"><div class="col-md-7">
@component('components.widget', ['class'=>'box-primary','title'=>'Change events'])
<div class="table-responsive"><table class="table table-bordered table-striped"><thead><tr><th>Time</th><th>Event</th><th>Subject</th><th>Actor</th><th>Reason</th></tr></thead><tbody>@forelse($events as $event)<tr><td>{{$event->occurred_at}}</td><td>{{$event->event_type}}</td><td>{{class_basename($event->subject_type)}} #{{$event->subject_id}}</td><td>{{$event->actor_user_id ?: 'System'}}<br><small>{{$event->actor_role}}</small></td><td>{{$event->reason}}</td></tr>@empty<tr><td colspan="5" class="text-muted">No HR changes have been recorded.</td></tr>@endforelse</tbody></table></div>{{$events->links()}}
@endcomponent
</div><div class="col-md-5">
@component('components.widget', ['title'=>'Sensitive data access'])
<div class="table-responsive"><table class="table table-bordered table-striped"><thead><tr><th>Time</th><th>Field group</th><th>Actor</th><th>Purpose</th></tr></thead><tbody>@forelse($accessLogs as $log)<tr><td>{{$log->accessed_at}}</td><td>{{$log->field_group}}</td><td>{{$log->actor_user_id}}</td><td>{{$log->purpose}}</td></tr>@empty<tr><td colspan="4" class="text-muted">No sensitive-field access has been recorded.</td></tr>@endforelse</tbody></table></div>{{$accessLogs->links()}}
@endcomponent
</div></div></section>
@endsection
