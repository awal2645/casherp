@component('components.widget', ['title' => $title])
    <div class="table-responsive">
        <table class="table table-bordered table-striped table-hover">
            <thead>
                <tr>
                    <th>@lang('hms::lang.booking_no')</th>
                    <th>@lang('hms::lang.guest')</th>
                    <th>@lang('hms::lang.property')</th>
                    <th>@lang('hms::lang.rooms')</th>
                    <th>@lang('hms::lang.arrival_date')</th>
                    <th>@lang('hms::lang.departure_date')</th>
                    <th>@lang('hms::lang.booking_source')</th>
                    <th>@lang('hms::lang.status')</th>
                    <th>@lang('messages.action')</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bookings as $booking)
                    <tr>
                        <td>
                            <a href="{{ action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'show'], ['booking' => $booking->id]) }}">
                                {{ $booking->ref_no }}
                            </a>
                            @if($booking->hms_external_reference)
                                <br><small>{{ $booking->hms_external_reference }}</small>
                            @endif
                        </td>
                        <td>{{ optional($booking->contact)->name }}</td>
                        <td>{{ optional($booking->hms_property)->name }}</td>
                        <td>
                            {{ $booking->hms_booking_lines->map(fn($line) => optional($line->room)->room_number)->filter()->implode(', ') }}
                        </td>
                        <td>@format_datetime($booking->hms_booking_arrival_date_time)</td>
                        <td>@format_datetime($booking->hms_booking_departure_date_time)</td>
                        <td>{{ __('hms::lang.source_'.$booking->hms_booking_source) }}</td>
                        <td>
                            <span class="label label-info">
                                {{ __('hms::lang.status_'.$booking->hms_booking_status) }}
                            </span>
                        </td>
                        <td>
                            @if(auth()->user()->can('superadmin') || auth()->user()->can('hms.manage_front_desk'))
                                @if($action === 'check_in' && $booking->hms_booking_status === 'reserved')
                                    <form method="POST" action="{{ route('hms.front_desk.check_in', $booking->id) }}" class="form-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-xs">
                                            <i class="fas fa-sign-in-alt"></i> @lang('hms::lang.check_in')
                                        </button>
                                    </form>
                                @elseif($action === 'check_out')
                                    <form method="POST" action="{{ route('hms.front_desk.check_out', $booking->id) }}" class="form-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-warning btn-xs">
                                            <i class="fas fa-sign-out-alt"></i> @lang('hms::lang.check_out')
                                        </button>
                                    </form>
                                @elseif($action === 'no_show')
                                    <form method="POST" action="{{ route('hms.front_desk.no_show', $booking->id) }}" class="form-inline">
                                        @csrf
                                        <input type="text" name="reason" class="form-control input-sm" required minlength="3" maxlength="2000" placeholder="@lang('hms::lang.reason')">
                                        <button type="submit" class="btn btn-danger btn-xs">
                                            @lang('hms::lang.mark_no_show')
                                        </button>
                                    </form>
                                @endif
                                @if($action === 'check_in' && in_array($booking->hms_booking_status, ['tentative', 'reserved']))
                                    <form method="POST" action="{{ route('hms.front_desk.cancel', $booking->id) }}" class="form-inline" style="margin-top: 4px;">
                                        @csrf
                                        <input type="text" name="reason" class="form-control input-sm" required minlength="3" maxlength="2000" placeholder="@lang('hms::lang.cancellation_reason')">
                                        <button type="submit" class="btn btn-danger btn-xs">
                                            @lang('hms::lang.cancel_booking')
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center">@lang('hms::lang.no_bookings_found')</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endcomponent
