<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class RestaurantAndHotelOperationsV15Test extends TestCase
{
    public function test_restaurant_operations_are_industry_scoped_and_company_configurable(): void
    {
        $config = require $this->path('config/restaurant_operations.php');
        $migration = $this->read('database/migrations/2026_09_04_000001_create_restaurant_operations_v15.php');
        $provisioning = $this->read('app/Services/IndustryFeatureProvisioningService.php');

        $this->assertSame(['dine_in', 'counter', 'takeaway', 'delivery'], $config['industry_profiles']['restaurant_food_service']['default_channels']);
        $this->assertTrue($config['industry_profiles']['hotel_lodge_guesthouse']['room_posting']);
        $this->assertTrue($config['industry_profiles']['hotel_with_restaurant']['room_posting']);
        foreach (['restaurant_operation_settings', 'restaurant_kitchen_stations', 'restaurant_kitchen_tickets', 'restaurant_recipes', 'restaurant_ingredient_movements', 'restaurant_order_fulfilments', 'restaurant_waiter_requests', 'restaurant_register_reconciliations'] as $table) {
            $this->assertStringContainsString("Schema::create('$table'", $migration);
        }
        $this->assertStringContainsString("['restaurant_food_service', 'hotel_lodge_guesthouse', 'hotel_with_restaurant']", $migration);
        foreach (['booking', 'modifiers', 'types_of_service', 'restaurant_operations'] as $module) {
            $this->assertStringContainsString("\$modules[] = '$module'", $provisioning);
        }
    }

    public function test_restaurant_workflows_are_tenant_scoped_idempotent_and_use_mutating_http_verbs(): void
    {
        $kitchen = $this->read('app/Services/KitchenRoutingService.php');
        $recipes = $this->read('app/Services/RecipeConsumptionService.php');
        $registers = $this->read('app/Services/RegisterReconciliationService.php');
        $routes = $this->read('routes/web.php');
        $booking = $this->read('app/Http/Controllers/Restaurant/BookingController.php');

        $this->assertStringContainsString("where('business_id', \$businessId)", $kitchen);
        $this->assertStringContainsString('KitchenTicket::firstOrCreate', $kitchen);
        $this->assertStringContainsString("'idempotency_key' => \$key", $recipes);
        $this->assertStringContainsString('lockForUpdate()', $recipes);
        $this->assertStringContainsString('A different authorized user must review this register', $registers);
        $this->assertStringContainsString("Route::patch('/kitchen-tickets/{ticket}/status'", $routes);
        $this->assertStringContainsString("Route::post('/kitchen/mark-as-cooked/{id}'", $routes);
        $this->assertStringNotContainsString("Route::get('/kitchen/mark-as-cooked/{id}'", $routes);
        $this->assertStringContainsString('ReservationAvailabilityService', $booking);
        $this->assertStringContainsString('restaurant.reservations.manage', $booking);
    }

    public function test_hotel_reference_gaps_are_added_without_combining_hms_and_hrm(): void
    {
        $migration = $this->read('Modules/Hms/Database/Migrations/2026_09_04_000006_create_hms_stay_and_event_operations.php');
        $permissions = $this->read('Modules/Hms/Http/Controllers/DataController.php');
        $routes = $this->read('Modules/Hms/Routes/web.php');
        $hotelFiles = $migration.$permissions.$routes.$this->read('Modules/Hms/Services/RoomMoveService.php');

        foreach (['hms_booking_guests', 'hms_room_moves', 'hms_event_bookings', 'hms_event_charges', 'hms_restaurant_postings'] as $table) {
            $this->assertStringContainsString("Schema::create('$table'", $migration);
        }
        foreach (['hms.room_status_board', 'hms.manage_stay_guests', 'hms.manage_room_moves', 'hms.manage_events', 'hms.post_restaurant_charges'] as $permission) {
            $this->assertStringContainsString($permission, $permissions);
        }
        $this->assertStringContainsString("Route::get('/room-status'", $routes);
        $this->assertStringContainsString("Route::post('/events/{event}/charges'", $routes);
        $this->assertStringNotContainsString('Modules\\Essentials', $hotelFiles);
        $this->assertStringNotContainsString('Human Resource', $hotelFiles);
    }

    public function test_hotel_events_room_moves_and_restaurant_posting_fail_closed(): void
    {
        $events = $this->read('Modules/Hms/Services/EventBookingService.php');
        $moves = $this->read('Modules/Hms/Services/RoomMoveService.php');
        $posting = $this->read('Modules/Hms/Services/RestaurantFolioPostingService.php');
        $deposits = $this->read('app/Services/SecurityDepositService.php');

        $this->assertStringContainsString("where('starts_at', '<', \$input['ends_at'])", $events);
        $this->assertStringContainsString("where('ends_at', '>', \$input['starts_at'])", $events);
        $this->assertStringContainsString("assertContextCanClose(\$businessId, 'hms_event'", $events);
        $this->assertStringContainsString("\$to->housekeeping_status !== 'ready'", $moves);
        $this->assertStringContainsString('room cannot accommodate', strtolower($moves));
        $this->assertStringContainsString("where('restaurant_transaction_id', \$sale->id)", $posting);
        $this->assertStringContainsString('same operating location', $posting);
        $this->assertStringContainsString("'context_type' => 'hms_event'", $deposits);
        $this->assertStringContainsString("['hms_booking', 'hms_event']", $deposits);
    }

    public function test_new_views_compile_to_parseable_php(): void
    {
        $compiler = new BladeCompiler(new Filesystem(), sys_get_temp_dir());
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        foreach ([
            'resources/views/role/partials/restaurant_operations_permissions.blade.php',
            'resources/views/restaurant/operations/index.blade.php',
            'resources/views/restaurant/operations/stations.blade.php',
            'resources/views/restaurant/operations/kitchen_board.blade.php',
            'resources/views/restaurant/operations/fulfilments.blade.php',
            'resources/views/restaurant/operations/recipes.blade.php',
            'resources/views/restaurant/operations/inventory.blade.php',
            'resources/views/restaurant/operations/waiter_requests.blade.php',
            'resources/views/restaurant/operations/registers.blade.php',
            'resources/views/restaurant/operations/settings.blade.php',
            'Modules/Hms/Resources/views/room_status/index.blade.php',
            'Modules/Hms/Resources/views/events/index.blade.php',
            'Modules/Hms/Resources/views/folios/show.blade.php',
            'Modules/Hms/Resources/views/layouts/nav.blade.php',
        ] as $view) {
            $compiled = $compiler->compileString($this->read($view));
            try {
                $parsed = $parser->parse($compiled);
            } catch (\Throwable $exception) {
                $this->fail($view.' did not compile to parseable PHP: '.$exception->getMessage());
            }
            $this->assertNotEmpty($parsed, "$view did not compile to parseable PHP");
        }
    }

    private function read(string $relative): string
    {
        return file_get_contents($this->path($relative));
    }

    private function path(string $relative): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
