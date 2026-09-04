<!-- Edit Order tax Modal -->
<div class="modal-dialog" role="document">
	<div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<h4 class="modal-title">@lang('lang_v1.view_invoice_url') - @lang('sale.invoice_no'): {{$transaction->invoice_no}}</h4>
		</div>
		<div class="modal-body">
			<div class="form-group">
				<input type="text" class="form-control" value="{{$url}}" id="invoice_url" readonly aria-label="Secure document link">
				<p class="help-block">@lang('lang_v1.invoice_url_help')</p>
				<p class="help-block"><i class="fa fa-shield"></i> The link is read-only. Only finalized invoices, quotations, pro-formas and sales orders intended for release may be shared.</p>
			</div>
		</div>
		<div class="modal-footer">
		    <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">
		    	@lang('messages.close')
		    </button>
			<button type="button" id="copy_invoice_url" class="tw-dw-btn tw-dw-btn-neutral tw-text-white">
				<i class="fas fa-copy"></i> Copy link
			</button>
			<button type="button" id="share_invoice_url" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-share-url="{{$url}}">
				<i class="fas fa-share-alt"></i> Share
			</button>

		    <a href="{{$url}}" id="view_invoice_url" target="_blank" rel="noopener" class="tw-dw-btn tw-dw-btn-primary tw-text-white">
				@lang('messages.view')
			</a>
		</div>
	</div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->

<script type="text/javascript">
	$('input#invoice_url').click(function(){
		$(this).select().focus();
	});
	$('#copy_invoice_url').on('click', function () {
		var input = document.getElementById('invoice_url');
		input.select();
		if (navigator.clipboard) {
			navigator.clipboard.writeText(input.value);
		} else {
			document.execCommand('copy');
		}
		$(this).html('<i class="fas fa-check"></i> Link copied');
	});
	$('#share_invoice_url').on('click', function () {
		var url = this.getAttribute('data-share-url');
		if (navigator.share) {
			navigator.share({title: document.title, url: url});
		} else {
			$('#copy_invoice_url').trigger('click');
		}
	});
</script>
