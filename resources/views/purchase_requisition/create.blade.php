@extends('layouts.app')
@section('title', isset($purchase) ? 'Correct purchase requisition' : __('lang_v1.add_purchase_requisition'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ isset($purchase) ? 'Correct purchase requisition' : __('lang_v1.add_purchase_requisition') }}</h1>
</section>

<!-- Main content -->
<section class="content">
	@if($errors->any())
		<div class="alert alert-danger" role="alert">
			<strong>Please correct the requisition information.</strong>
			<ul class="tw-mb-0 tw-mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
		</div>
	@endif
	@php
		$editing = isset($purchase);
	@endphp
	{!! Form::open(['url' => $editing ? action([\App\Http\Controllers\PurchaseRequisitionController::class, 'update'], [$purchase->id]) : action([\App\Http\Controllers\PurchaseRequisitionController::class, 'store']), 'method' => $editing ? 'put' : 'post', 'id' => 'add_purchase_requisition_form' ]) !!}
	@component('components.widget', ['class' => 'box-solid'])
		<div class="row">
			<div class="col-sm-4">
          		<div class="form-group">
            		{!! Form::label('brand_id', __('product.brand') . ':') !!}
              		{!! Form::select('brand_id[]', $brands, null, ['class' => 'form-control select2', 'multiple', 'id' => 'brand_id']); !!}
           
          		</div>
        	</div>
        	<div class="col-sm-4 @if(!session('business.enable_category')) hide @endif">
          		<div class="form-group">
            		{!! Form::label('category_id', __('product.category') . ':') !!}
              		{!! Form::select('category_id[]', $categories, null, ['class' => 'form-control select2', 'multiple', 'id' => 'category_id']); !!}
          		</div>
        	</div>
			@if(count($business_locations) == 1)
				@php 
					$default_location = current(array_keys($business_locations->toArray()));
					$search_disable = false; 
				@endphp
			@else
				@php
					$default_location = $editing ? $purchase->location_id : null;
					$search_disable = true;
				@endphp
			@endif
			<div class="col-sm-4">
				<div class="form-group">
					{!! Form::label('location_id', __('purchase.business_location').':') !!}
					{!! Form::select('location_id', $business_locations, old('location_id', $default_location), ['class' => 'form-control select2', 'placeholder' => __('messages.please_select'), 'required']); !!}
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-sm-12 text-right">
				<br>
				<button type="button" class="tw-dw-btn tw-dw-btn-warning tw-text-white tw-dw-btn-sm" id="show_pr_products"><i class="fas fa-search"></i> @lang('lang_v1.show_products')</button>
			</div>
		</div>
	@endcomponent

	@component('components.widget', ['class' => 'box-solid'])
		<div class="row">
			<div class="col-sm-4">
				<div class="form-group">
					{!! Form::label('ref_no', __('purchase.ref_no').':') !!}
					@show_tooltip(__('lang_v1.leave_empty_to_autogenerate'))
					{!! Form::text('ref_no', old('ref_no', $editing ? $purchase->ref_no : null), ['class' => 'form-control']); !!}
				</div>
			</div>
			<div class="col-sm-4">
				<div class="form-group">
					{!! Form::label('delivery_date', __('lang_v1.required_by_date') . ':') !!}
					<div class="input-group">
						<span class="input-group-addon">
							<i class="fa fa-calendar"></i>
						</span>
					{!! Form::text('delivery_date', old('delivery_date', $editing ? $deliveryDate : null), ['class' => 'form-control', 'readonly', 'required']); !!}
					</div>
				</div>
			</div>
			<div class="col-sm-4">
				<div class="form-group">
					{!! Form::label('priority', 'Priority:*') !!}
					{!! Form::select('priority', ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'], old('priority', $editing ? $purchase->procurementDocument->priority : 'normal'), ['class' => 'form-control select2', 'required']) !!}
				</div>
			</div>
			@if($departments->isNotEmpty())
				<div class="col-sm-4">
					<div class="form-group">
						{!! Form::label('department_id', 'Department:') !!}
						{!! Form::select('department_id', $departments, old('department_id', $defaultDepartmentId), ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]) !!}
					</div>
				</div>
			@endif
			@if($projects->isNotEmpty())
				<div class="col-sm-4">
					<div class="form-group">
						{!! Form::label('project_id', 'Project:') !!}
						{!! Form::select('project_id', $projects, old('project_id', $editing ? $purchase->procurementDocument->project_id : null), ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]) !!}
					</div>
				</div>
			@endif
			<div class="col-sm-4">
				<div class="form-group">
					{!! Form::label('budget_amount', 'Estimated budget:') !!}
					{!! Form::text('budget_amount', old('budget_amount', $editing ? $purchase->procurementDocument->budget_amount : null), ['class' => 'form-control input_number', 'inputmode' => 'decimal', 'placeholder' => 'Optional']) !!}
					<span class="help-block">Used for Finance review; supplier quotations determine the final PO value.</span>
				</div>
			</div>
			<div class="col-sm-12">
				<div class="form-group">
					{!! Form::label('purpose', 'Business purpose / justification:*') !!}
					{!! Form::textarea('purpose', old('purpose', $editing ? $purchase->procurementDocument->purpose : null), ['class' => 'form-control', 'rows' => 3, 'required', 'minlength' => 10, 'maxlength' => 3000, 'placeholder' => 'Explain what is needed, why it is needed, and the expected outcome.']) !!}
				</div>
			</div>
		</div>	
	@endcomponent

	@component('components.widget', ['class' => 'box-solid'])
		<div class="row">
			<div class="col-md-12">
				<table class="table" id="products_list">
					<thead>
						<tr>
							<th width="40%">@lang('sale.product')</th>
							<th width="20%">@lang('product.alert_quantity')</th>
							<th width="35%">@lang('lang_v1.required_quantity')</th>
							<th width="5%"><i class="text-danger fas fa-trash"></i></th>
						</tr>
					</thead>
					<tbody>
					@if($editing)
						@foreach($purchase->purchase_lines as $purchaseLine)
							@php
								$variationId = $purchaseLine->variation_id;
								$product = $purchaseLine->product;
								$unit = optional($product->unit);
								$secondUnit = $product->second_unit;
							@endphp
							<tr data-variation_id="{{ $variationId }}">
								<td>
									{{ $product->name }}
									@if($product->type === 'single')
										({{ $product->sku }})
									@else
										- {{ optional(optional($purchaseLine->variations)->product_variation)->name }} - {{ optional($purchaseLine->variations)->name }} ({{ optional($purchaseLine->variations)->sub_sku }})
									@endif
								</td>
								<td>@format_quantity($product->alert_quantity) {{ $unit->short_name }}</td>
								<td>
									<input type="hidden" name="purchases[{{ $variationId }}][product_id]" value="{{ $product->id }}">
									<input type="hidden" name="purchases[{{ $variationId }}][variation_id]" value="{{ $variationId }}">
									<div class="input-group">
										<input type="text" name="purchases[{{ $variationId }}][quantity]" value="{{ old('purchases.'.$variationId.'.quantity', $purchaseLine->quantity) }}" class="form-control input-sm input_number mousetrap" required data-decimal="{{ $unit->allow_decimal ? 1 : 0 }}" @if(!$unit->allow_decimal) data-rule-abs_digit="true" data-msg-abs_digit="{{ __('lang_v1.decimal_value_not_allowed') }}" @endif>
										<div class="input-group-addon">{{ $unit->short_name }}</div>
									</div>
									@if($secondUnit)
										<br><label>@lang('lang_v1.second_quantity')</label>
										<div class="input-group">
											<input type="text" name="purchases[{{ $variationId }}][secondary_unit_quantity]" value="{{ old('purchases.'.$variationId.'.secondary_unit_quantity', $purchaseLine->secondary_unit_quantity) }}" class="form-control input-sm input_number mousetrap" required data-decimal="{{ $secondUnit->allow_decimal ? 1 : 0 }}" @if(!$secondUnit->allow_decimal) data-rule-abs_digit="true" data-msg-abs_digit="{{ __('lang_v1.decimal_value_not_allowed') }}" @endif>
											<div class="input-group-addon">{{ $secondUnit->short_name }}</div>
										</div>
									@endif
								</td>
								<td><button type="button" class="btn btn-danger btn-xs remove_product_line"><i class="fas fa-times" aria-hidden="true"></i></button></td>
							</tr>
						@endforeach
					@endif
					</tbody>
				</table>
			</div>
		</div>
	@endcomponent

	<div class="row">
		<div class="col-sm-12 text-center">
			@if($editing)<p class="help-block">Supplier quotations will be cleared because the request details may have changed.</p>@endif
			<button type="button" class="tw-dw-btn tw-dw-btn-primary tw-dw-btn-lg tw-text-white" id="submit_pr_form"><i class="fas {{ $editing ? 'fa-save' : 'fa-paper-plane' }}" aria-hidden="true"></i> {{ $editing ? 'Save corrections' : 'Submit for approval' }}</button>
		</div>
	</div>

{!! Form::close() !!}
</section>
@endsection

@section('javascript')
	<script type="text/javascript">
		$(document).ready( function(){
      		__page_leave_confirmation('#add_purchase_requisition_form');
      		$('#delivery_date').datetimepicker({
                format: moment_date_format + ' ' + moment_time_format,
                ignoreReadonly: true,
            });

            var data = {
            	location_id: $('#location_id').val(),
            	brand_id: $('#brand_id').val(),
            	category_id: $('#category_id').val()
            }

            $('#show_pr_products').click( function(){
            	if ($('#location_id').val() == '') {
            		alert('{{__("lang_v1.select_location")}}');
            		return false;
            	}
            	var data = {
	            	location_id: $('#location_id').val(),
	            	brand_id: $('#brand_id').val(),
	            	category_id: $('#category_id').val()
	            }

            	$.ajax({
                    method: 'post',
                    url: "{{route('get-requisition-products')}}",
                    dataType: 'html',
                    data: data,
                    success: function(result) {
                    	var rows = $(result);
                    	rows.find('tr').each(function(){
                    		var row_variation_id = $(this).attr('data-variation_id');
                    		if ($('tr[data-variation_id="' + row_variation_id + '"]').length == 0) {
                    			$('#products_list tbody').append($(this));
                    		}
                    	})
                        
                    },
                });
            });
    	});

		var prev_location;

		$('#location_id').on('select2:selecting', function(){
		    prev_location = $(this).val();
		})

		$('#location_id').on('select2:select', function(){
			if ($('#products_list tbody').find('tr').length > 0){
        		swal({
		            title: LANG.sure,
		            text: '{{__("lang_v1.all_added_products_will_be_removed")}}',
		            icon: 'warning',
		            buttons: true,
		            dangerMode: true,
		        }).then(willDelete => {
		            if (willDelete) {
		                $('#products_list tbody').html('');
		            } else {
		        		$('#location_id').val(prev_location);
		        		$('#location_id').change();
		        		return false;
		        	}
		        });
        	}
		});

    	$(document).on('click', 'button.remove_product_line', function(){
    		$(this).closest('tr').remove();
    	})

    	$(document).on('click', 'button#submit_pr_form', function(e){
    		e.preventDefault();
    		if ($('#products_list tbody').find('tr').length == 0){
    			toastr.warning(LANG.no_products_added);
    			return false;
    		}
			if ($('form#add_purchase_requisition_form').valid()) {
				$(this).prop('disabled', true).attr('aria-busy', 'true');
				$('form#add_purchase_requisition_form').submit();
    		}
    		
    	})
	</script>
@endsection
