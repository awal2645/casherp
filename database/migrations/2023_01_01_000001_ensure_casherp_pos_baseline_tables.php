<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-apply the overlay POS baseline after a partial migrate:fresh.
 * createIfMissing keeps this a no-op when the 2018 migration already
 * created the same tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->ensureUsersTable();

        $this->createIfMissing('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description')->nullable();
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->unsignedInteger('business_id')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });

        $this->createIfMissing('currencies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('country');
            $table->string('currency');
            $table->string('code');
            $table->string('symbol');
            $table->string('thousand_separator', 10)->default(',');
            $table->string('decimal_separator', 10)->default('.');
            $table->timestamps();
        });

        $this->createIfMissing('business', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->unsignedInteger('currency_id')->nullable();
            $table->date('start_date')->nullable();
            $table->string('tax_number_1')->nullable();
            $table->string('tax_label_1')->nullable();
            $table->string('tax_number_2')->nullable();
            $table->string('tax_label_2')->nullable();
            $table->unsignedInteger('default_sales_tax')->nullable();
            $table->decimal('default_profit_percent', 22, 4)->default(0);
            $table->unsignedInteger('owner_id')->nullable();
            $table->string('time_zone')->nullable();
            $table->tinyInteger('fy_start_month')->default(1);
            $table->enum('accounting_method', ['fifo', 'lifo', 'avco'])->default('fifo');
            $table->decimal('default_sales_discount', 22, 4)->nullable();
            $table->enum('sell_price_tax', ['includes', 'excludes'])->default('includes');
            $table->string('logo')->nullable();
            $table->string('sku_prefix')->nullable();
            $table->boolean('enable_product_expiry')->default(0);
            $table->enum('expiry_type', ['add_expiry', 'add_manufacturing'])->default('add_expiry');
            $table->enum('on_product_expiry', ['keep_selling', 'stop_selling', 'auto_delete'])->default('keep_selling');
            $table->integer('stop_selling_before')->default(0);
            $table->boolean('enable_tooltip')->default(1);
            $table->boolean('purchase_in_diff_currency')->default(0);
            $table->unsignedInteger('purchase_currency_id')->nullable();
            $table->decimal('p_exchange_rate', 20, 3)->default(1);
            $table->unsignedInteger('transaction_edit_days')->default(30);
            $table->unsignedInteger('stock_expiry_alert_days')->default(30);
            $table->text('keyboard_shortcuts')->nullable();
            $table->text('pos_settings')->nullable();
            $table->boolean('enable_brand')->default(1);
            $table->boolean('enable_category')->default(1);
            $table->boolean('enable_sub_category')->default(1);
            $table->boolean('enable_price_tax')->default(1);
            $table->boolean('enable_purchase_status')->default(1);
            $table->boolean('enable_lot_number')->default(0);
            $table->unsignedInteger('default_unit')->nullable();
            $table->boolean('enable_racks')->default(0);
            $table->boolean('enable_row')->default(0);
            $table->boolean('enable_position')->default(0);
            $table->boolean('enable_editing_product_from_purchase')->default(1);
            $table->enum('sales_cmsn_agnt', ['logged_in_user', 'user', 'cmsn_agnt'])->nullable();
            $table->boolean('item_addition_method')->default(1);
            $table->boolean('enable_inline_tax')->default(1);
            $table->enum('currency_symbol_placement', ['before', 'after'])->default('before');
            $table->text('enabled_modules')->nullable();
            $table->string('date_format')->default('m/d/Y');
            $table->enum('time_format', ['12', '24'])->default('24');
            $table->text('repair_settings')->nullable();
            $table->text('ref_no_prefixes')->nullable();
            $table->text('common_settings')->nullable();
            $table->text('productcatalogue_settings')->nullable();
            $table->text('email_settings')->nullable();
            $table->text('sms_settings')->nullable();
            $table->text('weighing_scale_setting')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamps();
        });

        $this->ensureBooleanColumn('business', 'is_active', 1);
        $this->ensureBooleanColumn('business_locations', 'is_active', 1);
        $this->ensureNullableTextColumn('business', 'enabled_modules');
        $this->ensureNullableTextColumn('business', 'common_settings');
        $this->ensureNullableTextColumn('business', 'pos_settings');
        $this->ensureNullableTextColumn('business', 'email_settings');

        $this->createIfMissing('business_locations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->string('location_id')->nullable();
            $table->string('name');
            $table->text('landmark')->nullable();
            $table->string('country');
            $table->string('state');
            $table->string('city');
            $table->char('zip_code', 10);
            $table->unsignedInteger('invoice_scheme_id')->nullable();
            $table->unsignedInteger('invoice_layout_id')->nullable();
            $table->unsignedInteger('sale_invoice_layout_id')->nullable();
            $table->unsignedInteger('selling_price_group_id')->nullable();
            $table->boolean('print_receipt_on_invoice')->default(1);
            $table->enum('receipt_printer_type', ['browser', 'printer'])->default('browser');
            $table->unsignedInteger('printer_id')->nullable();
            $table->string('mobile')->nullable();
            $table->string('alternate_number')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_active')->default(1);
            $table->text('default_payment_accounts')->nullable();
            $table->string('custom_field1')->nullable();
            $table->string('custom_field2')->nullable();
            $table->string('custom_field3')->nullable();
            $table->string('custom_field4')->nullable();
            $table->longText('zatca_details')->nullable();
            $table->longText('zatca_response')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        $this->createIfMissing('contacts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->enum('type', ['customer', 'supplier', 'both']);
            $table->string('supplier_business_name')->nullable();
            $table->string('name');
            $table->string('prefix')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_id')->nullable();
            $table->string('contact_status')->default('active');
            $table->string('tax_number')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('zip_code')->nullable();
            $table->date('dob')->nullable();
            $table->string('mobile')->nullable();
            $table->string('landline')->nullable();
            $table->string('alternate_number')->nullable();
            $table->unsignedInteger('pay_term_number')->nullable();
            $table->enum('pay_term_type', ['days', 'months'])->nullable();
            $table->decimal('credit_limit', 22, 4)->nullable();
            $table->unsignedInteger('created_by');
            $table->text('custom_field1')->nullable();
            $table->text('custom_field2')->nullable();
            $table->text('custom_field3')->nullable();
            $table->text('custom_field4')->nullable();
            $table->timestamps();
        });

        $this->createIfMissing('cash_registers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->enum('status', ['close', 'open'])->default('open');
            $table->dateTime('closed_at')->nullable();
            $table->text('closing_note')->nullable();
            $table->text('denominations')->nullable();
            $table->timestamps();
        });

        $this->createIfMissing('cash_register_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cash_register_id');
            $table->decimal('amount', 22, 4)->default(0);
            $table->string('pay_method')->nullable();
            $table->enum('type', ['debit', 'credit']);
            $table->string('transaction_type')->nullable();
            $table->unsignedInteger('transaction_id')->nullable();
            $table->timestamps();
        });

        $this->createIfMissing('transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedInteger('res_table_id')->nullable();
            $table->unsignedInteger('res_waiter_id')->nullable();
            $table->string('res_order_status')->nullable();
            $table->string('type')->nullable();
            $table->string('sub_type')->nullable();
            $table->string('status')->nullable();
            $table->boolean('is_quotation')->default(0);
            $table->string('payment_status')->nullable();
            $table->string('adjustment_type')->nullable();
            $table->unsignedInteger('contact_id')->nullable();
            $table->unsignedInteger('customer_group_id')->nullable();
            $table->string('invoice_no')->nullable();
            $table->string('ref_no')->nullable();
            $table->string('source')->nullable();
            $table->string('subscription_no')->nullable();
            $table->dateTime('transaction_date');
            $table->decimal('total_before_tax', 22, 4)->default(0);
            $table->unsignedInteger('tax_id')->nullable();
            $table->decimal('tax_amount', 22, 4)->default(0);
            $table->enum('discount_type', ['fixed', 'percentage'])->nullable();
            $table->decimal('discount_amount', 22, 4)->default(0);
            $table->string('shipping_details')->nullable();
            $table->decimal('shipping_charges', 22, 4)->default(0);
            $table->text('additional_notes')->nullable();
            $table->text('staff_note')->nullable();
            $table->decimal('final_total', 22, 4)->default(0);
            $table->unsignedInteger('expense_category_id')->nullable();
            $table->unsignedInteger('expense_sub_category_id')->nullable();
            $table->unsignedInteger('expense_for')->nullable();
            $table->unsignedInteger('commission_agent')->nullable();
            $table->string('document')->nullable();
            $table->boolean('is_direct_sale')->default(0);
            $table->decimal('exchange_rate', 20, 3)->default(1);
            $table->decimal('total_amount_recovered', 22, 4)->nullable();
            $table->unsignedInteger('transfer_parent_id')->nullable();
            $table->unsignedInteger('opening_stock_product_id')->nullable();
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('hms_coupon_id')->nullable();
            $table->dateTime('hms_booking_arrival_date_time')->nullable();
            $table->dateTime('hms_booking_departure_date_time')->nullable();
            $table->string('hms_reason_for_trip')->nullable();
            $table->string('hms_means_of_transport')->nullable();
            $table->string('hms_vehicle_registration_number')->nullable();
            $table->string('hms_place_of_origin')->nullable();
            $table->string('hms_final_destination')->nullable();
            $table->dateTime('check_in')->nullable();
            $table->dateTime('check_out')->nullable();
            $table->unsignedInteger('mfg_parent_production_purchase_id')->nullable();
            $table->decimal('mfg_wasted_units', 22, 4)->nullable();
            $table->decimal('mfg_production_cost', 22, 4)->default(0);
            $table->boolean('mfg_is_final')->default(0);
            $table->timestamps();
        });

        $this->createIfMissing('transaction_payments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('transaction_id')->nullable();
            $table->unsignedInteger('business_id')->nullable();
            $table->boolean('is_return')->default(0);
            $table->decimal('amount', 22, 4)->default(0);
            $table->string('method')->nullable();
            $table->string('transaction_no')->nullable();
            $table->string('card_transaction_number')->nullable();
            $table->string('card_number')->nullable();
            $table->string('card_type')->nullable();
            $table->string('card_holder_name')->nullable();
            $table->string('card_month')->nullable();
            $table->string('card_year')->nullable();
            $table->string('card_security')->nullable();
            $table->string('cheque_number')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->dateTime('paid_on')->nullable();
            $table->unsignedInteger('created_by');
            $table->boolean('is_advance')->default(0);
            $table->unsignedInteger('payment_for')->nullable();
            $table->unsignedInteger('parent_id')->nullable();
            $table->text('note')->nullable();
            $table->string('document')->nullable();
            $table->unsignedInteger('account_id')->nullable();
            $table->timestamps();
        });

        $this->createIfMissing('packages', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('location_count')->default(0);
            $table->integer('user_count')->default(0);
            $table->integer('product_count')->default(0);
            $table->integer('invoice_count')->default(0);
            $table->boolean('bookings')->default(0);
            $table->boolean('kitchen')->default(0);
            $table->boolean('order_screen')->default(0);
            $table->boolean('tables')->default(0);
            $table->enum('interval', ['days', 'months', 'years'])->default('months');
            $table->integer('interval_count')->default(1);
            $table->integer('trial_days')->default(0);
            $table->decimal('price', 22, 4)->default(0);
            $table->unsignedInteger('created_by')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(1);
            $table->text('custom_permissions')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        $this->createIfMissing('subscriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('package_id')->nullable();
            $table->date('start_date')->nullable();
            $table->date('trial_end_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('package_price', 22, 4)->nullable();
            $table->text('package_details')->nullable();
            $table->unsignedInteger('created_id')->nullable();
            $table->string('paid_via')->nullable();
            $table->string('payment_transaction_id')->nullable();
            $table->enum('status', ['approved', 'waiting', 'declined'])->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        $this->createIfMissing('system', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });

        $this->createIfMissing('hms_room_types', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->unsignedInteger('no_of_adult')->default(1);
            $table->unsignedInteger('no_of_child')->default(0);
            $table->unsignedInteger('max_occupancy')->default(1);
            $table->text('amenities')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->createIfMissing('hms_rooms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hms_room_type_id');
            $table->string('room_number');
            $table->timestamps();
        });

        $this->createIfMissing('hms_room_type_pricings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hms_room_type_id');
            $table->string('season_type')->nullable();
            $table->decimal('default_price_per_night', 22, 4)->default(0);
            $table->unsignedInteger('adults')->nullable();
            $table->unsignedInteger('childrens')->nullable();
            $table->decimal('price_monday', 22, 4)->nullable();
            $table->decimal('price_tuesday', 22, 4)->nullable();
            $table->decimal('price_wednesday', 22, 4)->nullable();
            $table->decimal('price_thursday', 22, 4)->nullable();
            $table->decimal('price_friday', 22, 4)->nullable();
            $table->decimal('price_saturday', 22, 4)->nullable();
            $table->decimal('price_sunday', 22, 4)->nullable();
            $table->timestamps();
        });

        $this->createIfMissing('hms_coupons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hms_room_type_id')->nullable();
            $table->unsignedInteger('business_id');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('coupon_code');
            $table->decimal('discount', 22, 4)->default(0);
            $table->string('discount_type')->nullable();
            $table->timestamps();
        });

        $this->createIfMissing('hms_booking_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('transaction_id');
            $table->unsignedBigInteger('hms_room_id')->nullable();
            $table->unsignedBigInteger('hms_room_type_id')->nullable();
            $table->unsignedInteger('adults')->default(1);
            $table->unsignedInteger('childrens')->default(0);
            $table->decimal('price', 22, 4)->default(0);
            $table->timestamps();
        });

        $this->createIfMissing('hms_extras', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->string('name');
            $table->decimal('price', 22, 4)->default(0);
            $table->string('price_per')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->createIfMissing('hms_booking_extras', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('transaction_id');
            $table->unsignedBigInteger('hms_extra_id');
            $table->decimal('price', 22, 4)->default(0);
            $table->timestamps();
        });

        $this->createIfMissing('essentials_leaves', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('user_id');
            $table->string('status')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_additional')->default(0);
            $table->timestamps();
        });

        $this->createIfMissing('essentials_attendances', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('user_id');
            $table->dateTime('clock_in_time')->nullable();
            $table->dateTime('clock_out_time')->nullable();
            $table->timestamps();
        });

        $this->createIfMissing('essentials_user_shifts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
        });

        $this->createIfMissing('essentials_payroll_group_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('transaction_id')->nullable();
            $table->timestamps();
        });

        $this->createIfMissing('permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        $this->createIfMissing('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->unsignedInteger('business_id')->nullable();
            $table->boolean('is_default')->default(0);
            $table->boolean('is_service_staff')->default(0);
            $table->timestamps();
        });

        if (Schema::hasTable('roles') && ! Schema::hasColumn('roles', 'business_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->unsignedInteger('business_id')->nullable();
            });
        }
        if (Schema::hasTable('roles') && ! Schema::hasColumn('roles', 'is_default')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->boolean('is_default')->default(0);
            });
        }
        if (Schema::hasTable('roles') && ! Schema::hasColumn('roles', 'is_service_staff')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->boolean('is_service_staff')->default(0);
            });
        }

        $this->createIfMissing('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        $this->createIfMissing('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        $this->createIfMissing('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });
    }

    public function down(): void
    {
        // Keep baseline tables on rollback; a real CashERP database may already
        // have owned this schema before the overlay was applied.
    }

    private function createIfMissing(string $table, \Closure $callback): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $callback);
        }
    }

    private function ensureBooleanColumn(string $table, string $column, int $default = 1): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($column, $default) {
            $table->boolean($column)->default($default);
        });
    }

    private function ensureNullableTextColumn(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($column) {
            $table->text($column)->nullable();
        });
    }

    private function ensureUsersTable(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $this->ultimatePosUserColumns($table);
            });

            return;
        }

        if (Schema::hasColumn('users', 'surname')) {
            if (! Schema::hasColumn('users', 'user_type')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('user_type')->default('user');
                });
            }

            return;
        }

        // Fresh Laravel users table from 2014_10_12: replace it with the
        // Ultimate POS shape so later overlay foreign keys can use INT ids.
        if (DB::table('users')->count() === 0) {
            Schema::drop('users');
            Schema::create('users', function (Blueprint $table) {
                $this->ultimatePosUserColumns($table);
            });

            return;
        }

        if (Schema::hasColumn('users', 'name')) {
            DB::statement('alter table `users` modify `name` varchar(255) null');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('surname')->nullable()->after('id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('username')->nullable()->unique();
            $table->string('language', 10)->default('en');
            $table->string('contact_no')->nullable();
            $table->text('address')->nullable();
            $table->unsignedInteger('business_id')->nullable();
            $table->boolean('is_cmmsn_agnt')->default(0);
            $table->decimal('cmmsn_percent', 22, 4)->default(0);
            $table->string('user_type')->default('user');
            $table->boolean('allow_login')->default(1);
            $table->string('status')->default('active');
            $table->softDeletes();
        });
    }

    private function ultimatePosUserColumns(Blueprint $table): void
    {
        $table->increments('id');
        $table->string('surname')->nullable();
        $table->string('first_name');
        $table->string('last_name')->nullable();
        $table->string('username')->nullable()->unique();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->string('language', 10)->default('en');
        $table->string('contact_no')->nullable();
        $table->text('address')->nullable();
        $table->rememberToken();
        $table->unsignedInteger('business_id')->nullable();
        $table->boolean('is_cmmsn_agnt')->default(0);
        $table->decimal('cmmsn_percent', 22, 4)->default(0);
        $table->string('user_type')->default('user');
        $table->boolean('allow_login')->default(1);
        $table->string('status')->default('active');
        $table->softDeletes();
        $table->timestamps();
    }
};
