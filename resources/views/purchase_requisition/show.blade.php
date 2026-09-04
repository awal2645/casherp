<div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close no-print" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="modalTitle"> @lang('lang_v1.purchase_requisition_details') (<b>@lang('purchase.ref_no'):</b> #{{ $purchase->ref_no }})
            </h4>
        </div>
        <div class="modal-body">
            <div class="row">
                <div class="col-md-6">
                    <strong>@lang('messages.location'): </strong> {{$purchase->location->name}}<br>
                    <strong>@lang('purchase.ref_no'): </strong> {{$purchase->ref_no}}
                </div>
                <div class="col-md-6">
                    <strong>@lang('lang_v1.required_by_date'): </strong> @if(!empty($purchase->delivery_date)){{@format_datetime($purchase->delivery_date)}}@endif <br>
                    <strong>@lang('lang_v1.added_by'): </strong> {{$purchase->sales_person->user_full_name}}
                </div>
            </div>
            <div class="row mt-5">
                <div class="col-md-12">
                    <table class="table bg-gray">
                        <thead>
                            <tr class="bg-green">
                                <th>@lang('sale.product')</th>
                                <th>@lang('lang_v1.required_quantity')</th>
                                <th>@lang( 'lang_v1.quantity_remaining' )</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($purchase->purchase_lines as $purchase_line)
                                <tr>
                                <td>
                                    {{$purchase_line->product->name}}
                                    @if($purchase_line->product->type == 'single')
                                     ({{$purchase_line->product->sku}})
                                    @else
                                        - {{$purchase_line->variations->product_variation->name}} - {{$purchase_line->variations->name}} ({{$purchase_line->variations->sub_sku}})
                                    @endif
                                </td>
                                <td>
                                    {{@format_quantity($purchase_line->quantity)}} {{$purchase_line->product->unit->short_name}}

                                    @if(!empty($purchase_line->product->second_unit) && !empty($purchase_line->secondary_unit_quantity))
                                        <br>
                                        {{@format_quantity($purchase_line->secondary_unit_quantity)}} {{$purchase_line->product->second_unit->short_name}}
                                    @endif
                                </td>
                                <td>{{@format_quantity( $purchase_line->quantity - $purchase_line->po_quantity_purchased)}} {{$purchase_line->product->unit->short_name}}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="tw-dw-btn tw-dw-btn-primary tw-text-white no-print"
                onclick="$(this).closest('div.modal-content').printThis();"><i class="fa fa-print"></i> @lang('messages.print')</button>
            <a href="{{ route('purchase-requisition.print', ['purchase_requisition' => $purchase->id]) }}" target="_blank" rel="noopener"
                class="tw-dw-btn tw-dw-btn-neutral tw-text-white no-print"><i class="fa fa-eye"></i> Print preview</a>
            <a href="{{ route('purchase-requisition.download', ['purchase_requisition' => $purchase->id]) }}"
                class="tw-dw-btn tw-dw-btn-neutral tw-text-white no-print"><i class="fa fa-download"></i> @lang('lang_v1.download_pdf')</a>
            @if($canShareDocument)
                <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white no-print share-requisition-link"
                    data-share-url="{{ action([\App\Http\Controllers\PurchaseRequisitionController::class, 'show'], [$purchase->id]) }}"><i class="fa fa-share-alt"></i> Share internally</button>
            @endif
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white no-print" data-dismiss="modal">@lang( 'messages.close' )</button>
        </div>
  </div>
</div>
<script>
$(document).off('click.cashErpRequisitionShare', '.share-requisition-link').on('click.cashErpRequisitionShare', '.share-requisition-link', function () {
    var button = this;
    var url = button.getAttribute('data-share-url');
    if (navigator.share) {
        navigator.share({title: 'Purchase requisition {{ $purchase->ref_no }}', url: url});
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function () {
            button.innerHTML = '<i class="fa fa-check"></i> Authorized link copied';
        });
    }
});
</script>
