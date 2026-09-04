<div class="form-group">
    <label for="property_filter_location">Location:</label>
    <select
        id="property_filter_location"
        name="location_id"
        class="form-control property-location-filter"
    >
        <option value="">All permitted locations</option>
        @foreach($filterLocations as $location)
            <option value="{{ $location->id }}" {{ (int) $selectedLocationId === (int) $location->id ? 'selected' : '' }}>
                {{ $location->name }}
            </option>
        @endforeach
    </select>
</div>
<div class="form-group">
    <label for="property_filter_property">Property:</label>
    <select
        id="property_filter_property"
        name="property_id"
        class="form-control property-portfolio-filter"
    >
        <option value="">All properties</option>
        @foreach($filterProperties as $property)
            <option
                value="{{ $property->id }}"
                data-location-id="{{ $property->business_location_id }}"
                {{ (int) $selectedPropertyId === (int) $property->id ? 'selected' : '' }}
            >
                {{ $property->name }}
                @if($property->businessLocation)
                    — {{ $property->businessLocation->name }}
                @endif
            </option>
        @endforeach
    </select>
</div>

@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var locationField = document.querySelector('.property-location-filter');
            var propertyField = document.querySelector('.property-portfolio-filter');
            if (!locationField || !propertyField) {
                return;
            }

            var options = Array.prototype.slice.call(propertyField.options);
            locationField.addEventListener('change', function () {
                var locationId = locationField.value;
                options.forEach(function (option) {
                    if (!option.value) {
                        option.hidden = false;
                        return;
                    }
                    option.hidden = Boolean(locationId)
                        && option.getAttribute('data-location-id') !== locationId;
                });
                if (propertyField.selectedOptions.length
                    && propertyField.selectedOptions[0].hidden) {
                    propertyField.value = '';
                }
            });
        });
    </script>
@endonce
