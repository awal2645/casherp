@extends('layouts.app')
@section('title', __('hms::lang.add_housekeeping_task'))

@section('content')
    @include('hms::layouts.nav')

    <section class="content-header">
        <h1>@lang('hms::lang.add_housekeeping_task')</h1>
    </section>

    <section class="content">
        <div class="box box-primary">
            {!! Form::open(['route' => 'hms.housekeeping.store']) !!}
            <div class="box-body row">
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('hms_room_id', __('hms::lang.room').':*') !!}
                        <select name="hms_room_id" class="form-control select2" required>
                            <option value="">@lang('messages.please_select')</option>
                            @foreach($rooms as $room)
                                <option value="{{ $room->id }}" @selected(old('hms_room_id') == $room->id)>
                                    {{ $room->room_number }} — {{ optional($room->type)->type }}
                                    ({{ ucwords(str_replace('_', ' ', $room->housekeeping_status)) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('assigned_to', __('hms::lang.assigned_to').':') !!}
                        {!! Form::select('assigned_to', $staff, old('assigned_to'), [
                            'class' => 'form-control select2',
                        ]) !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('task_type', __('hms::lang.task_type').':*') !!}
                        {!! Form::select('task_type', $taskTypes, old('task_type', 'stayover_cleaning'), [
                            'class' => 'form-control',
                            'required',
                        ]) !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('priority', __('hms::lang.priority').':*') !!}
                        {!! Form::select('priority', [
                            'low' => __('hms::lang.low'),
                            'normal' => __('hms::lang.normal'),
                            'high' => __('hms::lang.high'),
                            'urgent' => __('hms::lang.urgent'),
                        ], old('priority', 'normal'), ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('scheduled_for', __('hms::lang.scheduled_for').':*') !!}
                        {!! Form::datetimeLocal(
                            'scheduled_for',
                            old('scheduled_for', now()->format('Y-m-d\TH:i')),
                            ['class' => 'form-control', 'required']
                        ) !!}
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        {!! Form::label('notes', __('hms::lang.instructions').':') !!}
                        {!! Form::textarea('notes', old('notes'), [
                            'class' => 'form-control',
                            'rows' => 3,
                            'maxlength' => 3000,
                        ]) !!}
                    </div>
                </div>
            </div>
            <div class="box-footer">
                <a href="{{ route('hms.housekeeping.index') }}" class="btn btn-default">
                    @lang('messages.cancel')
                </a>
                <button class="btn btn-primary pull-right">
                    @lang('messages.save')
                </button>
            </div>
            {!! Form::close() !!}
        </div>
    </section>
@endsection
