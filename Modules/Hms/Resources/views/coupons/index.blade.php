@extends('layouts.app')
@section('title', __('hms::lang.coupons'))
@section('content')
    @include('hms::layouts.nav')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black"> @lang('hms::lang.coupons')
        </h1>
        <p><i class="fa fa-info-circle"></i> @lang('hms::lang.coupon_help_text') </p>
    </section>

    <!-- Main content -->
    <section class="content">
        @component('components.widget')
            <div class="box-tools tw-flex tw-justify-end tw-gap-2.5 tw-mb-4">
                <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right btn-modal-coupon"
                    href="{{ action([\Modules\Hms\Http\Controllers\HmsCouponController::class, 'create']) }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M12 5l0 14" />
                        <path d="M5 12l14 0" />
                    </svg> @lang('messages.add')
                </a>
            </div>
            <table class="table table-bordered table-striped" id="extras_table">
                <thead>
                    <tr>
                        <th>
                            @lang('hms::lang.type')
                        </th>
                        <th>
                            @lang('hms::lang.coupon_code')
                        </th>
                        <th>
                            @lang('hms::lang.date_from')
                        </th>
                        <th>
                            @lang('hms::lang.date_to')
                        </th>
                        <th>
                            @lang('hms::lang.discount')
                        </th>
                        <th>
                            @lang('hms::lang.discount_type')
                        </th>
                        <th>
                            @lang('lang_v1.created_at')
                        </th>
                        <th>
                            @lang('messages.action')
                        </th>
                    </tr>
                </thead>
            </table>
        @endcomponent



        <!-- Add HMS Extra Modal -->
        <div class="modal fade view_modal_coupon" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
        </div>
        </div>

    </section>
    <!-- /.content -->

@endsection

@section('javascript')

    <script type="text/javascript">
        $(document).ready(function() {
            superadmin_business_table = $('#extras_table').DataTable({
                processing: true,
                serverSide: true,
                fixedHeader:false,
                ajax: {
                    url: "{{ action([\Modules\Hms\Http\Controllers\HmsCouponController::class, 'index']) }}",
                },
                aaSorting: [
                    [6, 'desc']
                ],
                columns: [{
                        data: 'type',
                        name: 'type.type'
                    },
                    {
                        data: 'coupon_code',
                        name: 'coupon_code'
                    },
                    {
                        data: 'start_date',
                        name: 'start_date'
                    },
                    {
                        data: 'end_date',
                        name: 'end_date'
                    },
                    {
                        data: 'discount',
                        name: 'discount'
                    },
                    {
                        data: 'discount_type',
                        name: 'discount_type'
                    },
                    {
                        data: 'created_at',
                        name: 'created_at'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        sorting: false,
                    }
                ],
            });

            $(document).on('click', '.btn-modal-coupon', function(e) {
                e.preventDefault();
                $.ajax({
                    url: $(this).attr('href'),
                    dataType: 'html',
                    success: function(result) {
                        $('.view_modal_coupon')
                            .html(result)
                            .modal('show');
                    },
                });
            });

            $(".view_modal_coupon").on("show.bs.modal", function() {
                var currentDate = new Date();
                var currentDateTime = moment(currentDate);

                $('.date_picker').datetimepicker({
                    format: moment_date_format,
                    ignoreReadonly: true,
                    defaultDate: currentDateTime
                });
            });

            $(document).on('click', 'button.delete_coupon_confirmation', function(e) {
                e.preventDefault();
                swal({
                    title: LANG.sure,
                    text: "Once deleted, you will not be able to recover this Coupon !",
                    icon: "warning",
                    buttons: true,
                    dangerMode: true,
                }).then((confirmed) => {
                    if (confirmed) {
                        $(this).closest('form').submit();
                    }
                });
            });
        });
    </script>
@endsection
