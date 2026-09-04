@extends('layouts.app')
@section('title', __('hms::lang.housekeeping'))

@section('content')
    @include('hms::layouts.nav')

    <section class="content-header">
        <h1>
            @lang('hms::lang.housekeeping')
            <small>@lang('hms::lang.housekeeping_subtitle')</small>
        </h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-2 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-red"><i class="fa fa-bed"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">@lang('hms::lang.dirty')</span>
                        <span class="info-box-number">{{ $summary['dirty'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-yellow"><i class="fa fa-refresh"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">@lang('hms::lang.cleaning')</span>
                        <span class="info-box-number">{{ $summary['cleaning'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-aqua"><i class="fa fa-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">@lang('hms::lang.awaiting_inspection')</span>
                        <span class="info-box-number">{{ $summary['cleaned'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-green"><i class="fa fa-check-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">@lang('hms::lang.ready')</span>
                        <span class="info-box-number">{{ $summary['ready'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-12">
                <div class="info-box">
                    <span class="info-box-icon bg-red"><i class="fa fa-clock-o"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">@lang('hms::lang.overdue_tasks')</span>
                        <span class="info-box-number">{{ $summary['overdue'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">@lang('hms::lang.housekeeping_filters')</h3>
                @if(
                    auth()->user()->can('superadmin')
                    || auth()->user()->can('hms.manage_rooms')
                    || auth()->user()->can('hms.manage_housekeeping')
                )
                    <div class="box-tools pull-right">
                        <a class="btn btn-primary btn-sm" href="{{ route('hms.housekeeping.create') }}">
                            <i class="fa fa-plus"></i> @lang('hms::lang.add_housekeeping_task')
                        </a>
                    </div>
                @endif
            </div>
            <div class="box-body">
                {!! Form::open([
                    'route' => 'hms.housekeeping.index',
                    'method' => 'get',
                    'class' => 'form-inline',
                ]) !!}
                    <div class="form-group">
                        {!! Form::label('status', __('hms::lang.status')) !!}
                        {!! Form::select('status', [
                            '' => __('lang_v1.all'),
                            'pending' => __('hms::lang.pending'),
                            'in_progress' => __('hms::lang.in_progress'),
                            'cleaned' => __('hms::lang.cleaned'),
                            'ready' => __('hms::lang.ready'),
                        ], request('status'), ['class' => 'form-control']) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('assigned_to', __('hms::lang.assigned_to')) !!}
                        {!! Form::select('assigned_to', $staff, request('assigned_to'), [
                            'class' => 'form-control select2',
                        ]) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('scheduled_date', __('hms::lang.scheduled_date')) !!}
                        {!! Form::date('scheduled_date', request('scheduled_date'), [
                            'class' => 'form-control',
                        ]) !!}
                    </div>
                    <button class="btn btn-primary">
                        <i class="fa fa-filter"></i> @lang('hms::lang.filter')
                    </button>
                    <a href="{{ route('hms.housekeeping.index') }}" class="btn btn-default">
                        @lang('hms::lang.reset')
                    </a>
                {!! Form::close() !!}
            </div>
        </div>

        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">@lang('hms::lang.housekeeping_tasks')</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>@lang('hms::lang.scheduled_for')</th>
                            <th>@lang('hms::lang.room')</th>
                            <th>@lang('hms::lang.task_type')</th>
                            <th>@lang('hms::lang.priority')</th>
                            <th>@lang('hms::lang.assigned_to')</th>
                            <th>@lang('hms::lang.status')</th>
                            <th>@lang('messages.action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tasks as $task)
                            <tr>
                                <td>
                                    {{ $task->scheduled_for ? @format_datetime($task->scheduled_for) : '-' }}
                                    @if(
                                        $task->scheduled_for
                                        && $task->scheduled_for->isPast()
                                        && in_array($task->status, ['pending', 'in_progress'])
                                    )
                                        <br><span class="label bg-red">@lang('hms::lang.overdue')</span>
                                    @endif
                                </td>
                                <td>
                                    {{ optional($task->room)->room_number }}
                                    <br>
                                    <small>{{ optional(optional($task->room)->type)->type }}</small>
                                </td>
                                <td>{{ ucwords(str_replace('_', ' ', $task->task_type)) }}</td>
                                <td>{{ ucfirst($task->priority) }}</td>
                                <td>{{ optional($task->assignedTo)->user_full_name ?: __('lang_v1.none') }}</td>
                                <td>
                                    <span class="label {{ $task->status === 'ready' ? 'bg-green' : ($task->status === 'cleaned' ? 'bg-aqua' : ($task->status === 'in_progress' ? 'bg-yellow' : 'bg-red')) }}">
                                        {{ ucwords(str_replace('_', ' ', $task->status)) }}
                                    </span>
                                </td>
                                <td>
                                    <a
                                        class="btn btn-xs btn-primary"
                                        href="{{ route('hms.housekeeping.show', $task) }}"
                                    >
                                        @lang('messages.view')
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">
                                    @lang('hms::lang.no_housekeeping_tasks')
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="box-footer clearfix">
                <div class="pull-right">{{ $tasks->links() }}</div>
            </div>
        </div>

        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">@lang('hms::lang.room_readiness_board')</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>@lang('hms::lang.room')</th>
                            <th>@lang('hms::lang.room_type')</th>
                            <th>@lang('hms::lang.housekeeping_status')</th>
                            <th>@lang('hms::lang.last_cleaned')</th>
                            <th>@lang('hms::lang.last_inspected')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rooms as $room)
                            <tr>
                                <td>{{ $room->room_number }}</td>
                                <td>{{ optional($room->type)->type }}</td>
                                <td>
                                    <span class="label {{ $room->housekeeping_status === 'ready' ? 'bg-green' : ($room->housekeeping_status === 'occupied' ? 'bg-blue' : ($room->housekeeping_status === 'cleaned' ? 'bg-aqua' : ($room->housekeeping_status === 'cleaning' ? 'bg-yellow' : 'bg-red'))) }}">
                                        {{ ucwords(str_replace('_', ' ', $room->housekeeping_status)) }}
                                    </span>
                                </td>
                                <td>{{ $room->last_cleaned_at ? @format_datetime($room->last_cleaned_at) : '-' }}</td>
                                <td>{{ $room->last_inspected_at ? @format_datetime($room->last_inspected_at) : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center">@lang('hms::lang.no_rooms_found')</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
