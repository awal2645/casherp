<section class="no-print">
    <nav class="navbar-default tw-transition-all tw-duration-5000 tw-shrink-0 tw-rounded-2xl tw-m-[16px] tw-border-2 !tw-bg-white">
        <div class="container-fluid">
            <!-- Brand and toggle get grouped for better mobile display -->
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" aria-expanded="false" style="margin-top: 3px; margin-right: 3px;">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="{{action([\Modules\Hms\Http\Controllers\HmsController::class, 'index'])}}"><i class="fas fa-hotel"></i>@lang('hms::lang.hms')</a>
            </div>

            <!-- Collect the nav links, forms, and other content for toggling -->
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                @can('hms.manage_rooms')
                    <ul class="nav navbar-nav">
                        <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'rooms') class="active" @endif><a href="{{action([Modules\Hms\Http\Controllers\RoomController::class, 'index'])}}">@lang('hms::lang.rooms')</a></li>
                    </ul>
                @endcan
                @can('hms.manage_price')
                    <ul class="nav navbar-nav">
                        <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'room' && request()->segment(3) == 'pricing') class="active" @endif><a href="{{action([Modules\Hms\Http\Controllers\RoomController::class, 'pricing'])}}">@lang('hms::lang.prices')</a></li>
                    </ul>
                @endcan
                @if(
                    auth()->user()->can('superadmin')
                    || auth()->user()->can('hms.front_desk')
                    || auth()->user()->can('hms.manage_front_desk')
                )
                    <ul class="nav navbar-nav">
                        <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'front-desk') class="active" @endif>
                            <a href="{{ route('hms.front_desk.index') }}">@lang('hms::lang.front_desk')</a>
                        </li>
                    </ul>
                @endif
                @if(auth()->user()->can('superadmin') || auth()->user()->can('hms.room_status_board'))
                    <ul class="nav navbar-nav">
                        <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'room-status') class="active" @endif>
                            <a href="{{ route('hms.room_status.index') }}">Room Status</a>
                        </li>
                    </ul>
                @endif
                @if(
                    auth()->user()->can('superadmin')
                    || auth()->user()->can('hms.view_bookings')
                    || auth()->user()->can('hms.add_booking')
                    || auth()->user()->can('hms.edit_booking')
                    || auth()->user()->can('hms.delete_booking')
                    || auth()->user()->can('hms.front_desk')
                    || auth()->user()->can('hms.manage_front_desk')
                )
                    <ul class="nav navbar-nav">
                        <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'bookings') class="active" @endif><a href="{{action([Modules\Hms\Http\Controllers\HmsBookingController::class, 'index'])}}">@lang('hms::lang.bookings')</a></li>
                    </ul>
                    <ul class="nav navbar-nav">
                        <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'calendar') class="active" @endif><a href="{{action([Modules\Hms\Http\Controllers\HmsBookingController::class, 'calendar'])}}">@lang('hms::lang.calendar')</a></li>
                    </ul>
                @endif
                @if(
                    auth()->user()->can('superadmin')
                    || auth()->user()->can('hms.manage_rooms')
                    || auth()->user()->can('hms.manage_housekeeping')
                    || auth()->user()->can('hms.perform_housekeeping')
                    || auth()->user()->can('hms.inspect_housekeeping')
                )
                    <ul class="nav navbar-nav">
                        <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'housekeeping') class="active" @endif>
                            <a href="{{ route('hms.housekeeping.index') }}">
                                @lang('hms::lang.housekeeping')
                            </a>
                        </li>
                    </ul>
                @endif
                @php
                    $hmsBusinessId = (int) session('user.business_id');
                    $hmsSaas = app(\Modules\Hms\Services\HmsSaasService::class);
                    $canHotelOperations = auth()->user()->can('superadmin')
                        || auth()->user()->can('hms.manage_properties')
                        || auth()->user()->can('hms.manage_event_venues')
                        || auth()->user()->can('hms.manage_rate_plans')
                        || auth()->user()->can('hms.manage_groups')
                        || auth()->user()->can('hms.manage_folios')
                        || auth()->user()->can('hms.run_night_audit')
                        || auth()->user()->can('hms.manage_operations')
                        || auth()->user()->can('hms.manage_guests')
                        || auth()->user()->can('hms.view_revenue')
                        || auth()->user()->can('hms.manage_channels')
                        || auth()->user()->can('hms.manage_events');
                @endphp
                @if($canHotelOperations)
                    <ul class="nav navbar-nav">
                        <li class="dropdown {{ in_array(request()->segment(2), ['properties','event-venues','events','rate-plans','groups','folios','night-audit','cashier-shifts','hotel-services','guest-profiles','guest-messages','revenue','channels']) ? 'active' : '' }}">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                                @lang('hms::lang.hotel_operations') <span class="caret"></span>
                            </a>
                            <ul class="dropdown-menu">
                                @if(auth()->user()->can('superadmin') || auth()->user()->can('hms.manage_properties'))
                                    <li><a href="{{ route('hms.properties.index') }}">@lang('hms::lang.properties')</a></li>
                                @endif
                                @if(auth()->user()->can('superadmin') || auth()->user()->can('hms.manage_event_venues'))
                                    <li><a href="{{ route('hms.event_venues.index') }}">Event Venues</a></li>
                                @endif
                                @if((auth()->user()->can('superadmin') || auth()->user()->can('hms.manage_events')) && $hmsSaas->allows($hmsBusinessId, 'hms_event_operations'))
                                    <li><a href="{{ route('hms.events.index') }}">Events &amp; Banqueting</a></li>
                                @endif
                                @if((auth()->user()->can('superadmin') || auth()->user()->can('hms.manage_rate_plans')) && $hmsSaas->allows($hmsBusinessId, 'hms_revenue_operations'))
                                    <li><a href="{{ route('hms.rate_plans.index') }}">@lang('hms::lang.rate_plans')</a></li>
                                @endif
                                @if((auth()->user()->can('superadmin') || auth()->user()->can('hms.manage_groups')) && $hmsSaas->allows($hmsBusinessId, 'hms_group_operations'))
                                    <li><a href="{{ route('hms.groups.index') }}">@lang('hms::lang.group_bookings')</a></li>
                                @endif
                                @if((auth()->user()->can('superadmin') || auth()->user()->can('hms.manage_folios')) && $hmsSaas->allows($hmsBusinessId, 'hms_finance_operations'))
                                    <li><a href="{{ route('hms.folios.index') }}">@lang('hms::lang.folios')</a></li>
                                @endif
                                @if((auth()->user()->can('superadmin') || auth()->user()->can('hms.run_night_audit')) && $hmsSaas->allows($hmsBusinessId, 'hms_finance_operations'))
                                    <li><a href="{{ route('hms.night_audit.index') }}">@lang('hms::lang.night_audit')</a></li>
                                @endif
                                @if(auth()->user()->can('superadmin') || auth()->user()->can('hms.manage_operations'))
                                    <li><a href="{{ route('hms.operations.index') }}">@lang('hms::lang.hotel_services')</a></li>
                                @endif
                                @if((auth()->user()->can('superadmin') || auth()->user()->can('hms.manage_guests')) && $hmsSaas->allows($hmsBusinessId, 'hms_guest_experience'))
                                    <li><a href="{{ route('hms.guests.index') }}">@lang('hms::lang.guest_profiles')</a></li>
                                @endif
                                @if((auth()->user()->can('superadmin') || auth()->user()->can('hms.manage_guest_messages')) && $hmsSaas->allows($hmsBusinessId, 'hms_guest_experience'))
                                    <li><a href="{{ route('hms.guest_messages.index') }}">@lang('hms::lang.guest_messaging')</a></li>
                                @endif
                                @if((auth()->user()->can('superadmin') || auth()->user()->can('hms.view_revenue')) && $hmsSaas->allows($hmsBusinessId, 'hms_revenue_operations'))
                                    <li><a href="{{ route('hms.revenue.index') }}">@lang('hms::lang.revenue_management')</a></li>
                                @endif
                                @if((auth()->user()->can('superadmin') || auth()->user()->can('hms.manage_channels')) && $hmsSaas->allows($hmsBusinessId, 'hms_channel_manager'))
                                    <li><a href="{{ route('hms.channels.index') }}">@lang('hms::lang.channel_manager')</a></li>
                                @endif
                            </ul>
                        </li>
                    </ul>
                @endif
                @can('hms.manage_extra')
                    <ul class="nav navbar-nav">
                        <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'extras') class="active" @endif><a href="{{action([Modules\Hms\Http\Controllers\ExtraController::class, 'index'])}}">@lang('hms::lang.extras')</a></li>
                    </ul>
                @endcan
                @can('hms.manage_unavailable')
                    <ul class="nav navbar-nav">
                        <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'unavailables') class="active" @endif><a href="{{action([Modules\Hms\Http\Controllers\UnavailableController::class, 'index'])}}">@lang('hms::lang.unavailable')</a></li>
                    </ul>
                @endcan
                @can('hms.manage_coupon')
                    <ul class="nav navbar-nav">
                        <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'coupons') class="active" @endif><a href="{{action([Modules\Hms\Http\Controllers\HmsCouponController::class, 'index'])}}">@lang('hms::lang.coupons')</a></li>
                    </ul>
                @endcan
                @if(auth()->user()->can('superadmin') || auth()->user()->can('hms.view_reports'))
                    <ul class="nav navbar-nav">
                        <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'reports') class="active" @endif><a href="{{action([Modules\Hms\Http\Controllers\HmsReportController::class, 'index'])}}">@lang('hms::lang.reports')</a></li>
                    </ul>
                @endif
                @can('hms.manage_amenities')
                    <ul class="nav navbar-nav">
                            <li @if(request()->get('type') == 'amenities') class="active" @endif><a href="{{action([\App\Http\Controllers\TaxonomyController::class, 'index']) . '?type=amenities'}}">@lang('hms::lang.amenities')</a></li>
                    </ul>
                @endcan
                @can('hms.manage_settings')
                    <ul class="nav navbar-nav">
                        <li @if(request()->segment(1) == 'hms' && request()->segment(2) == 'settings') class="active" @endif><a href="{{action([Modules\Hms\Http\Controllers\HmsSettingController::class, 'index'])}}">@lang('messages.settings')</a></li>
                    </ul>
                @endcan
            </div><!-- /.navbar-collapse -->
        </div><!-- /.container-fluid -->
    </nav>
</section>
