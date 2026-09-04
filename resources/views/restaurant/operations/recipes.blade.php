@extends('layouts.app')
@section('title', 'Restaurant Recipes and Food Cost')
@section('content')
<section class="content-header"><h1>Recipes & food cost <small>consume ingredients only for explicitly mapped menu items</small></h1></section>
<section class="content">
    @include('restaurant.operations.partials.nav')
    <div class="row"><div class="col-md-5"><div class="box box-primary"><div class="box-header"><h3 class="box-title">Create or replace recipe</h3></div>
        <form method="post" action="{{ route('restaurant-operations.recipes.store') }}">@csrf<div class="box-body">
            <div class="form-group"><label>Recipe name *</label><input name="name" class="form-control" required></div>
            <div class="form-group"><label>Menu product *</label>{!! Form::select('menu_product_id',$products,null,['class'=>'form-control select2','placeholder'=>'Select product','required']) !!}</div>
            <div class="form-group"><label>Menu variation</label>{!! Form::select('menu_variation_id',$variations,null,['class'=>'form-control select2','placeholder'=>'All variations']) !!}</div>
            <div class="form-group"><label>Recipe yield *</label><input type="number" step="0.0001" min="0.0001" name="yield_quantity" value="1" class="form-control" required><p class="help-block">Ingredient quantities use the product's base inventory unit.</p></div>
            <hr><h4>Ingredient</h4>
            <div id="recipe-ingredients"><div class="recipe-ingredient well well-sm">
                {!! Form::select('ingredients[0][ingredient_product_id]',$products,null,['class'=>'form-control select2','placeholder'=>'Ingredient product','required']) !!}<br>
                {!! Form::select('ingredients[0][ingredient_variation_id]',$variations,null,['class'=>'form-control select2','placeholder'=>'Ingredient variation','required']) !!}<br>
                <input name="ingredients[0][quantity]" type="number" min="0.0001" step="0.0001" class="form-control" placeholder="Quantity per yield" required><br>
                <input name="ingredients[0][unit_cost_snapshot]" type="number" min="0" step="0.0001" class="form-control" placeholder="Unit cost snapshot">
            </div></div>
            <button type="button" class="btn btn-default btn-sm" id="add-recipe-ingredient"><i class="fa fa-plus"></i> Add ingredient</button>
        </div><div class="box-footer"><button class="btn btn-primary">Save recipe</button></div></form>
    </div></div><div class="col-md-7"><div class="box"><div class="box-header"><h3 class="box-title">Recipe register</h3></div><div class="box-body table-responsive">
        <table class="table table-bordered table-striped"><thead><tr><th>Name</th><th>Yield</th><th>Ingredients</th><th>Estimated cost</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($recipes as $recipe)<tr><td>{{ $recipe->name }}</td><td>{{ (float)$recipe->yield_quantity }}</td><td>{{ $recipe->lines_count }}</td><td>{{ number_format((float)$recipe->estimated_cost,2) }}</td><td>{{ $recipe->is_active?'Active':'Disabled' }}</td><td>@if($recipe->is_active)<form method="post" action="{{ route('restaurant-operations.recipes.destroy',$recipe->id) }}">@csrf @method('DELETE')<button class="btn btn-xs btn-danger">Disable</button></form>@endif</td></tr>
        @empty<tr><td colspan="6" class="text-center">No recipes configured.</td></tr>@endforelse
        </tbody></table>{{ $recipes->links() }}
    </div></div></div></div>
</section>
@endsection
@section('javascript')
<script>$(function(){var i=1;$('#add-recipe-ingredient').on('click',function(){var row=$('.recipe-ingredient:first').clone();row.find('.select2-container').remove();row.find('select,input').each(function(){this.name=this.name.replace('[0]','['+i+']');$(this).val('');});$('#recipe-ingredients').append(row);row.find('select').select2();i++;});});</script>
@endsection
