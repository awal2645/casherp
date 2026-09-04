@extends('layouts.app')
@section('title', __('hms::lang.housekeeping_task'))

@section('content')
    @include('hms::layouts.nav')

    <section class="content-header">
        <h1>
            @lang('hms::lang.housekeeping_task') #{{ $task->id }}
            <small>
                @lang('hms::lang.room') {{ optional($task->room)->room_number }}
            </small>
        </h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-7">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">@lang('hms::lang.task_details')</h3>
                        <div class="box-tools pull-right">
                            <span class="label {{ $task->status === 'ready' ? 'bg-green' : ($task->status === 'cleaned' ? 'bg-aqua' : ($task->status === 'in_progress' ? 'bg-yellow' : 'bg-red')) }}">
                                {{ ucwords(str_replace('_', ' ', $task->status)) }}
                            </span>
                        </div>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered">
                            <tr>
                                <th style="width: 35%">@lang('hms::lang.room')</th>
                                <td>
                                    {{ optional($task->room)->room_number }}
                                    — {{ optional(optional($task->room)->type)->type }}
                                </td>
                            </tr>
                            <tr>
                                <th>@lang('hms::lang.task_type')</th>
                                <td>{{ ucwords(str_replace('_', ' ', $task->task_type)) }}</td>
                            </tr>
                            <tr>
                                <th>@lang('hms::lang.priority')</th>
                                <td>{{ ucfirst($task->priority) }}</td>
                            </tr>
                            <tr>
                                <th>@lang('hms::lang.scheduled_for')</th>
                                <td>{{ $task->scheduled_for ? @format_datetime($task->scheduled_for) : '-' }}</td>
                            </tr>
                            <tr>
                                <th>@lang('hms::lang.assigned_to')</th>
                                <td>{{ optional($task->assignedTo)->user_full_name ?: __('lang_v1.none') }}</td>
                            </tr>
                            <tr>
                                <th>@lang('hms::lang.booking')</th>
                                <td>
                                    @if($task->booking)
                                        #{{ $task->booking->id }}
                                        — {{ optional($task->booking->contact)->name }}
                                    @else
                                        @lang('lang_v1.none')
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>@lang('hms::lang.instructions')</th>
                                <td>{!! nl2br(e($task->notes ?: '-')) !!}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="box-footer">
                        <a href="{{ route('hms.housekeeping.index') }}" class="btn btn-default">
                            <i class="fa fa-arrow-left"></i> @lang('messages.go_back')
                        </a>
                    </div>
                </div>

                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">@lang('hms::lang.task_history')</h3>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered">
                            <tr>
                                <th>@lang('hms::lang.created')</th>
                                <td>
                                    {{ @format_datetime($task->created_at) }}
                                    — {{ optional($task->creator)->user_full_name }}
                                </td>
                            </tr>
                            <tr>
                                <th>@lang('hms::lang.started')</th>
                                <td>{{ $task->started_at ? @format_datetime($task->started_at) : '-' }}</td>
                            </tr>
                            <tr>
                                <th>@lang('hms::lang.cleaned')</th>
                                <td>
                                    {{ $task->cleaned_at ? @format_datetime($task->cleaned_at) : '-' }}
                                    @if($task->completedBy)
                                        — {{ $task->completedBy->user_full_name }}
                                    @endif
                                    @if($task->completion_notes)
                                        <br><small>{!! nl2br(e($task->completion_notes)) !!}</small>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>@lang('hms::lang.inspected')</th>
                                <td>
                                    {{ $task->inspected_at ? @format_datetime($task->inspected_at) : '-' }}
                                    @if($task->inspectedBy)
                                        — {{ $task->inspectedBy->user_full_name }}
                                    @endif
                                    @if($task->inspection_notes)
                                        <br><small>{!! nl2br(e($task->inspection_notes)) !!}</small>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                @if($canManage && $task->status !== 'ready')
                    <div class="box box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title">@lang('hms::lang.assign_housekeeping')</h3>
                        </div>
                        {!! Form::open(['route' => ['hms.housekeeping.assign', $task]]) !!}
                        <div class="box-body">
                            <div class="form-group">
                                {!! Form::label('assigned_to', __('hms::lang.assigned_to').':') !!}
                                {!! Form::select('assigned_to', $staff, $task->assigned_to, [
                                    'class' => 'form-control select2',
                                ]) !!}
                            </div>
                        </div>
                        <div class="box-footer">
                            <button class="btn btn-primary pull-right">
                                @lang('messages.update')
                            </button>
                        </div>
                        {!! Form::close() !!}
                    </div>
                @endif

                @if($canPerform && $task->status === 'pending')
                    <div class="box box-warning">
                        <div class="box-header with-border">
                            <h3 class="box-title">@lang('hms::lang.start_cleaning')</h3>
                        </div>
                        <div class="box-body">
                            <p>@lang('hms::lang.start_cleaning_help')</p>
                            {!! Form::open(['route' => ['hms.housekeeping.start', $task]]) !!}
                                <button class="btn btn-warning">
                                    <i class="fa fa-play"></i> @lang('hms::lang.start_task')
                                </button>
                            {!! Form::close() !!}
                        </div>
                    </div>
                @endif

                @if($canPerform && in_array($task->status, ['pending', 'in_progress']))
                    <div class="box box-info">
                        <div class="box-header with-border">
                            <h3 class="box-title">@lang('hms::lang.mark_room_cleaned')</h3>
                        </div>
                        {!! Form::open(['route' => ['hms.housekeeping.cleaned', $task]]) !!}
                        <div class="box-body">
                            <div class="form-group">
                                {!! Form::label('completion_notes', __('hms::lang.completion_notes').':') !!}
                                {!! Form::textarea('completion_notes', null, [
                                    'class' => 'form-control',
                                    'rows' => 3,
                                    'maxlength' => 3000,
                                ]) !!}
                            </div>
                        </div>
                        <div class="box-footer">
                            <button class="btn btn-info pull-right">
                                <i class="fa fa-check"></i> @lang('hms::lang.mark_cleaned')
                            </button>
                        </div>
                        {!! Form::close() !!}
                    </div>
                @endif

                @if($canInspect && $task->status === 'cleaned')
                    <div class="box box-success">
                        <div class="box-header with-border">
                            <h3 class="box-title">@lang('hms::lang.room_inspection')</h3>
                        </div>
                        {!! Form::open(['route' => ['hms.housekeeping.inspect', $task]]) !!}
                        <div class="box-body">
                            <div class="form-group">
                                {!! Form::label('inspection_result', __('hms::lang.inspection_result').':*') !!}
                                {!! Form::select('inspection_result', [
                                    'passed' => __('hms::lang.passed_room_ready'),
                                    'failed' => __('hms::lang.failed_return_cleaning'),
                                ], null, ['class' => 'form-control', 'required']) !!}
                            </div>
                            <div class="form-group">
                                {!! Form::label('inspection_notes', __('hms::lang.inspection_notes').':') !!}
                                {!! Form::textarea('inspection_notes', null, [
                                    'class' => 'form-control',
                                    'rows' => 3,
                                    'maxlength' => 3000,
                                ]) !!}
                            </div>
                        </div>
                        <div class="box-footer">
                            <button class="btn btn-success pull-right">
                                <i class="fa fa-clipboard"></i> @lang('hms::lang.save_inspection')
                            </button>
                        </div>
                        {!! Form::close() !!}
                    </div>
                @endif

                @if($task->status === 'ready')
                    <div class="callout callout-success">
                        <h4><i class="fa fa-check-circle"></i> @lang('hms::lang.room_ready')</h4>
                        <p>@lang('hms::lang.room_ready_help')</p>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
