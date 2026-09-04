@extends('layouts.guest')
@section('title', $title)
@section('content')

<div class="container">
    <div class="spacer"></div>
    <div class="row">
        <div class="col-md-12 text-right mb-12" >
            @if(!empty($payment_link))
                <a href="{{$payment_link}}" class="btn btn-info no-print" style="margin-right: 20px;"><i class="fas fa-money-check-alt" title="@lang('lang_v1.pay')"></i> @lang('lang_v1.pay')
                </a>
            @endif
            <button type="button" class="tw-dw-btn tw-dw-btn-primary tw-text-white no-print tw-dw-btn-sm" id="print_invoice" 
                 aria-label="Print"><i class="fas fa-print"></i> @lang( 'messages.print' )
            </button>
            @if(!empty($download_url))
                <a href="{{ $download_url }}" class="tw-dw-btn tw-dw-btn-neutral tw-text-white no-print tw-dw-btn-sm"><i class="fas fa-download"></i> @lang('lang_v1.download_pdf')</a>
            @endif
            @if(!empty($browser_share_url))
                <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white no-print tw-dw-btn-sm" id="share_invoice" data-share-url="{{ $browser_share_url }}"><i class="fas fa-share-alt"></i> Share</button>
            @endif
            @auth
                <a href="{{action([\App\Http\Controllers\SellController::class, 'index'])}}" class="tw-dw-btn tw-dw-btn-success tw-text-white no-print tw-dw-btn-sm" ><i class="fas fa-backward"></i>
                </a>
            @endauth
        </div>
    </div>
    <div class="row">
        <div class="col-md-8 col-md-offset-2 col-sm-12" style="border: 1px solid #ccc;">
            <div class="spacer"></div>
            <div id="invoice_content">
                {!! $receipt['html_content'] !!}
            </div>
            <div class="spacer"></div>
        </div>
    </div>
    <div class="spacer"></div>
</div>
@stop
@section('javascript')
<script type="text/javascript">
    $(document).ready(function(){
        $(document).on('click', '#print_invoice', function(){
            $('#invoice_content').printThis();
        });
        $(document).on('click', '#share_invoice', function(){
            var button = this;
            var shareUrl = button.getAttribute('data-share-url');
            if (navigator.share) {
                navigator.share({title: document.title, url: shareUrl});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(shareUrl).then(function(){ button.innerHTML = '<i class="fas fa-check"></i> Link copied'; });
            }
        });
    });
    @if(!empty($print_on_load) || !empty(request()->input('print_on_load')))
        $(window).on('load', function(){
            $('#invoice_content').printThis();
        });
    @endif
</script>
@endsection
