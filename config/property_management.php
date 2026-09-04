<?php

return [
    /*
     * A company chooses one primary operating model during onboarding. The
     * choice controls sensible defaults only; it never prevents the company
     * from managing another type of property later.
     */
    'operating_models' => [
        'owner_landlord' => 'Property owner / landlord',
        'third_party_manager' => 'Third-party property management company',
        'real_estate_agency' => 'Real estate agency / brokerage',
        'property_developer' => 'Property developer',
        'facilities_estate_manager' => 'Facilities / estate management company',
        'hybrid' => 'Hybrid real estate business',
    ],

    'portfolio_categories' => [
        'residential' => 'Residential property',
        'commercial' => 'Commercial property',
        'mixed_use' => 'Mixed-use property',
        'industrial_logistics' => 'Industrial and logistics property',
        'land' => 'Land and plots',
        'community_association' => 'Estate, community, strata or association management',
        'mixed_portfolio' => 'Mixed property portfolio',
    ],

    /*
     * These values are used by onboarding and property creation. Labels make
     * the hierarchy explicit so that a shopping mall is correctly understood
     * as one commercial-property subtype rather than the whole industry.
     */
    'property_subtypes' => [
        'apartments' => ['label' => 'Residential - apartments / flats', 'category' => 'residential'],
        'single_family' => ['label' => 'Residential - houses / single-family homes', 'category' => 'residential'],
        'gated_estate' => ['label' => 'Residential - gated estate / housing complex', 'category' => 'residential'],
        'student_housing' => ['label' => 'Residential - student housing / hostels', 'category' => 'residential'],
        'serviced_residences' => ['label' => 'Residential - serviced residences', 'category' => 'residential'],
        'shopping_mall' => ['label' => 'Commercial - shopping mall / retail centre', 'category' => 'commercial'],
        'office_building' => ['label' => 'Commercial - office building / office park', 'category' => 'commercial'],
        'street_retail' => ['label' => 'Commercial - shops / street retail', 'category' => 'commercial'],
        'business_centre' => ['label' => 'Commercial - business centre / co-working property', 'category' => 'commercial'],
        'medical_offices' => ['label' => 'Commercial - medical / professional offices', 'category' => 'commercial'],
        'mixed_use_development' => ['label' => 'Mixed-use - residential and commercial development', 'category' => 'mixed_use'],
        'warehouse' => ['label' => 'Industrial - warehouse / distribution centre', 'category' => 'industrial_logistics'],
        'industrial_park' => ['label' => 'Industrial - factory / industrial park', 'category' => 'industrial_logistics'],
        'logistics_hub' => ['label' => 'Industrial - logistics hub', 'category' => 'industrial_logistics'],
        'plots' => ['label' => 'Land - serviced plots / subdivisions', 'category' => 'land'],
        'development_land' => ['label' => 'Land - development or investment land', 'category' => 'land'],
        'agricultural_land' => ['label' => 'Land - agricultural property', 'category' => 'land'],
        'estate_association' => ['label' => 'Community - estate / homeowners association', 'category' => 'community_association'],
        'strata_condominium' => ['label' => 'Community - strata / condominium management', 'category' => 'community_association'],
        'other' => ['label' => 'Other real estate property type', 'category' => 'mixed_portfolio'],
    ],

    'dashboard_widgets' => [
        'portfolio_summary' => ['label' => 'Portfolio summary', 'description' => 'Properties, units, occupancy and active tenants.'],
        'rent_performance' => ['label' => 'Rent and collections', 'description' => 'Billed rent, collections, outstanding balances and collection rate.'],
        'unit_status' => ['label' => 'Unit status', 'description' => 'Occupied, vacant, reserved and maintenance units.'],
        'lease_expiries' => ['label' => 'Lease expiry schedule', 'description' => 'Active leases approaching their end date.'],
        'receivables_ageing' => ['label' => 'Receivables ageing', 'description' => 'Current and overdue tenant balances by age.'],
        'maintenance' => ['label' => 'Maintenance', 'description' => 'Open work orders, priorities and costs.'],
        'properties' => ['label' => 'Property portfolio', 'description' => 'Properties available within the selected location.'],
        'recent_leases' => ['label' => 'Recent leases', 'description' => 'Recent and active lease records.'],
        'documents' => ['label' => 'Property documents', 'description' => 'Lease, invoice, receipt, quotation and statement shortcuts.'],
    ],

    'default_widgets' => [
        'portfolio_summary',
        'rent_performance',
        'unit_status',
        'lease_expiries',
        'receivables_ageing',
        'maintenance',
        'properties',
        'recent_leases',
        'documents',
    ],
];
