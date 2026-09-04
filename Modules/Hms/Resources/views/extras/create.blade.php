<div class="modal-dialog" role="document">
    <div class="modal-content">
  
      {!! Form::open(['url' => action([\Modules\Hms\Http\Controllers\ExtraController::class, 'store']), 'method' => 'post', 'id' => 'add_extra' ]) !!}
  
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">@lang( 'hms::lang.add_extra' )</h4>
      </div>
  
      <div class="modal-body">
        <div class="form-group">
            {!! Form::label('name', __('hms::lang.name') . '*') !!}
            {!! Form::text('name', null, [
                'class' => 'form-control',
                'required',
                'placeholder' => __('hms::lang.name'),
            ]) !!}
        </div>
        <div class="form-group">
            {!! Form::label('price', __('hms::lang.price') . '*') !!}
            {!! Form::number('price', null, [
                'class' => 'form-control',
                'required',
                'step' => '0.01',
                'placeholder' => __('hms::lang.price'),
            ]) !!}
        </div>
        <div class="form-group">
            {!! Form::label('price_per', __('hms::lang.per'). '*') !!}
            {!! Form::select('price_per', $price_per, '', [
                'class' => 'form-control',
                'required',
            ]) !!}
        </div>
        <div class="form-group">
            {!! Form::label('financial_classification', 'Financial classification*') !!}
            {!! Form::select('financial_classification', $financial_classifications, 'revenue', [
                'class' => 'form-control',
                'required',
            ]) !!}
            <small class="help-block">A refundable security deposit is tracked as money held for the guest. It is excluded from accommodation revenue, tax, payment totals and invoice balances.</small>
        </div>
      </div>
  
      <div class="modal-footer">
        <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang( 'messages.save' )</button>
        <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang( 'messages.close' )</button>
      </div>
  
      {!! Form::close() !!}
  
    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->
