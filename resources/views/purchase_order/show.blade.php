<div class="modal-dialog modal-xl" role="document">
  <div class="modal-content">
    @include('purchase.partials.show_details')
    <div class="modal-footer">
      @if($canReleaseDocument)
        <button type="button" class="tw-dw-btn tw-dw-btn-primary tw-text-white no-print" aria-label="Print"
        onclick="$(this).closest('div.modal-content').printThis();"><i class="fa fa-print"></i> @lang( 'messages.print' )
        </button>
        <a href="{{ route('purchaseOrder.downloadPdf', [$purchase->id]) }}" class="tw-dw-btn tw-dw-btn-neutral tw-text-white no-print">
          <i class="fa fa-download"></i> @lang('lang_v1.download_pdf')
        </a>
      @else
        <span class="text-muted no-print"><i class="fa fa-lock"></i> Printing and download unlock after final approval.</span>
      @endif
      @if($canShareDocument)
        <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white no-print btn-modal"
          data-href="{{ action([\App\Http\Controllers\NotificationController::class, 'getTemplate'], ['transaction_id' => $purchase->id, 'template_for' => 'purchase_order']) }}"
          data-container=".view_modal"><i class="fa fa-share-alt"></i> Share with supplier</button>
      @endif
      <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white no-print" data-dismiss="modal">@lang( 'messages.close' )</button>
    </div>
  </div>
</div>

<script type="text/javascript">
	$(document).ready(function(){
		var element = $('div.modal-xl');
		__currency_convert_recursively(element);
	});
</script>
