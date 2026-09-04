@extends('layouts.app')
@section('title', 'Add Property')

@section('content')
<section class="content-header">
    <h1>Add Property <small>Classify and assign the property to the correct location.</small></h1>
</section>

<section class="content">
    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Please review the highlighted information.</strong>
            <ul class="tw-mb-0 tw-mt-2">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-info" role="note">
        <i class="fa fa-map-marker"></i>
        A <strong>location</strong> is an operational branch, office, region, or portfolio base. Each location can contain multiple properties, and your package controls how many locations the company may create.
    </div>

    <div class="box box-primary">
        {!! Form::open(['route' => 'property.properties.store']) !!}
        <div class="box-body row">
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label('name', 'Property name:*') !!}
                    {!! Form::text('name', old('name'), ['class' => 'form-control', 'required', 'maxlength' => 255, 'autocomplete' => 'organization']) !!}
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label('business_location_id', 'Business location:*') !!}
                    {!! Form::select('business_location_id', $locations, old('business_location_id'), ['class' => 'form-control select2', 'required', 'placeholder' => 'Select a location']) !!}
                    <span class="help-block">Only locations this user is permitted to access are listed.</span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label('portfolio_category', 'Portfolio category:*') !!}
                    {!! Form::select('portfolio_category', $portfolioCategories, old('portfolio_category'), ['class' => 'form-control select2', 'required', 'placeholder' => 'Select the primary category']) !!}
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    {!! Form::label('property_subtype', 'Property subtype:*') !!}
                    <select id="property_subtype" name="property_subtype" class="form-control select2" required>
                        <option value="">Select the closest property type</option>
                        @foreach($propertySubtypes as $value => $subtype)
                            <option
                                value="{{ $value }}"
                                data-category="{{ $subtype['category'] }}"
                                {{ old('property_subtype') === $value ? 'selected' : '' }}
                            >
                                {{ $subtype['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <span class="help-block">For example, a shopping mall and an office park are commercial-property subtypes.</span>
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    {!! Form::label('address', 'Property address:') !!}
                    {!! Form::textarea('address', old('address'), ['class' => 'form-control', 'rows' => 3, 'maxlength' => 2000, 'autocomplete' => 'street-address']) !!}
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    {!! Form::label('description', 'Property description:') !!}
                    {!! Form::textarea('description', old('description'), ['class' => 'form-control', 'rows' => 3, 'maxlength' => 5000, 'placeholder' => 'Describe the property, access, positioning and important operational details.']) !!}
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    {!! Form::label('amenities_text', 'Amenities and facilities:') !!}
                    {!! Form::textarea('amenities_text', old('amenities_text'), ['class' => 'form-control', 'rows' => 2, 'maxlength' => 3000, 'placeholder' => 'Parking, security, lifts, backup power, loading bay (separate with commas)']) !!}
                    <span class="help-block">These structured details support listings, searches and property documents without creating duplicate modules.</span>
                </div>
            </div>
        </div>
        <div class="box-footer">
            <a class="btn btn-default" href="{{ route('property.index') }}">Cancel</a>
            <button class="btn btn-primary pull-right" type="submit">
                <i class="fa fa-save"></i> Save property
            </button>
        </div>
        {!! Form::close() !!}
    </div>
</section>
@endsection

@section('javascript')
<script>
    $(function () {
        $('.select2').select2({ width: '100%' });

        var category = document.getElementById('portfolio_category');
        var subtype = document.getElementById('property_subtype');
        var subtypeOptions = Array.prototype.slice.call(subtype.options);

        function filterSubtypes() {
            var selectedCategory = category.value;
            subtypeOptions.forEach(function (option) {
                if (!option.value) {
                    option.disabled = false;
                    return;
                }

                option.disabled = Boolean(selectedCategory)
                    && selectedCategory !== 'mixed_portfolio'
                    && option.getAttribute('data-category') !== selectedCategory;
            });

            if (subtype.selectedOptions.length && subtype.selectedOptions[0].disabled) {
                subtype.value = '';
            }
            $(subtype).trigger('change.select2');
        }

        category.addEventListener('change', filterSubtypes);
        filterSubtypes();
    });
</script>
@endsection
