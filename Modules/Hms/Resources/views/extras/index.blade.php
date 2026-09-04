@extends('layouts.app')
@section('title', __('hms::lang.extras'))
@section('content')
    @include('hms::layouts.nav')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black"> @lang('hms::lang.extras')
        </h1>
        <p><i class="fa fa-info-circle"></i> @lang('hms::lang.extra_help_text') </p>
    </section>

    <!-- Main content -->
    <section class="content">
        @component('components.widget')
                <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right btn-modal-extra"
                    href="{{ action([\Modules\Hms\Http\Controllers\ExtraController::class, 'create']) }}">
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
                            @lang('hms::lang.name')
                        </th>
                        <th>
                            @lang('hms::lang.price')
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
        <div class="modal fade view_modal_extra" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
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
                ajax: {
                    url: "{{ action([\Modules\Hms\Http\Controllers\ExtraController::class, 'index']) }}",
                },
                aaSorting: [
                    [2, 'desc']
                ],
                columns: [{
                        data: 'name',
                        name: 'hms_extras.name'
                    },
                    {
                        data: 'price',
                        name: 'hms_extras.price'
                    },
                    {
                        data: 'created_at',
                        name: 'hms_extras.created_at'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        sorting: false,
                    }
                ],
            });

            $(document).on('click', '.btn-modal-extra', function(e) {
                e.preventDefault();
                $.ajax({
                    url: $(this).attr('href'),
                    dataType: 'html',
                    success: function(result) {
                        $('.view_modal_extra')
                            .html(result)
                            .modal('show');
                    },
                });
            });

            $(document).on('click', 'button.delete_extra_confirmation', function(e) {
                e.preventDefault();
                swal({
                    title: LANG.sure,
                    text: "Once deleted, you will not be able to recover this Extra !",
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
