<?php

namespace Tests\Feature\Api\Merchant;

use App\Models\Bus;
use App\Models\Parking;
use App\Models\BusLocation;
use App\Models\MerchantIncome;
use App\Models\MerchantWithdrawal;
use App\Models\Role;
use App\Models\Route;
use App\Models\Stop;
use App\Models\User;
use App\Models\BalanceOut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Carbon\Carbon;

class MerchantDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected $merchant;
    protected $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::create(['name' => 'Merchant', 'slug' => 'merchant']);
        $this->merchant = User::create([
            'name' => 'Test Merchant',
            'email' => 'merchant@example.com',
            'phone_number' => '9841234567',
            'password' => Hash::make('password'),
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        $this->merchant->roles()->attach($this->role);
    }

    public function test_merchant_can_get_income_stats()
    {
        // Use a fixed date for predictability
        Carbon::setTestNow(Carbon::create(2026, 5, 5, 12, 0, 0));
        
        $now = Carbon::now();
        $lastMonth = $now->copy()->subMonthNoOverflow();

        // Current month income
        $bo1 = BalanceOut::create(['amount' => 1000, 'user_id' => $this->merchant->id, 'merchant_id' => $this->merchant->id, 'type' => 'fare_deduction']);
        $mi1 = new MerchantIncome([
            'merchant_id' => $this->merchant->id,
            'balance_out_id' => $bo1->id,
            'amount' => 1000,
            'type' => 'fare',
            'reference_id' => 1,
            'reference_type' => Bus::class,
        ]);
        $mi1->created_at = $now;
        $mi1->save();

        // Last month income
        $bo2 = BalanceOut::create(['amount' => 500, 'user_id' => $this->merchant->id, 'merchant_id' => $this->merchant->id, 'type' => 'fare_deduction']);
        $bo2->created_at = $lastMonth;
        $bo2->save();
        
        $mi2 = new MerchantIncome([
            'merchant_id' => $this->merchant->id,
            'balance_out_id' => $bo2->id,
            'amount' => 500,
            'type' => 'fare',
            'reference_id' => 1,
            'reference_type' => Bus::class,
        ]);
        $mi2->created_at = $lastMonth;
        $mi2->save();

        Sanctum::actingAs($this->merchant);
        $response = $this->getJson('/api/merchant/dashboard/income');

        $response->assertStatus(200)
            ->assertJsonPath('content.current_month_income', 1000)
            ->assertJsonPath('content.last_month_income', 500)
            ->assertJsonPath('content.percentage_change', 100);
            
        Carbon::setTestNow(); // Reset
    }

    public function test_merchant_can_get_top_routes()
    {
        $route = Route::create(['name' => 'Test Route', 'slug' => 'test-route', 'direction' => 'inbound']);
        $bus = Bus::create([
            'name' => 'Test Bus',
            'bus_number' => 'BA 1 PA 1234',
            'hwid' => 'HWID123',
            'merchant_id' => $this->merchant->id,
            'route_id' => $route->id,
            'status' => 'active'
        ]);

        $bo = BalanceOut::create(['amount' => 1000, 'user_id' => $this->merchant->id, 'merchant_id' => $this->merchant->id, 'type' => 'fare_deduction']);
        MerchantIncome::create([
            'merchant_id' => $this->merchant->id,
            'balance_out_id' => $bo->id,
            'amount' => 1000,
            'type' => 'fare',
            'reference_id' => $bus->id,
            'reference_type' => Bus::class,
        ]);

        Sanctum::actingAs($this->merchant);
        $response = $this->getJson('/api/merchant/dashboard/top-routes');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Test Route', 'total_revenue' => 1000]);
    }

    public function test_merchant_can_get_top_parkings()
    {
        $parking = Parking::create([
            'name' => 'Test Parking',
            'location' => 'Kathmandu',
            'status' => 'opened',
            'merchant_id' => $this->merchant->id,
            'latitude' => 27.7,
            'longitude' => 85.3,
            'total_capacity' => 100,
        ]);

        $bo = BalanceOut::create(['amount' => 500, 'user_id' => $this->merchant->id, 'merchant_id' => $this->merchant->id, 'type' => 'parking_fee']);
        MerchantIncome::create([
            'merchant_id' => $this->merchant->id,
            'balance_out_id' => $bo->id,
            'amount' => 500,
            'type' => 'parking',
            'reference_id' => $parking->id,
            'reference_type' => Parking::class,
        ]);

        Sanctum::actingAs($this->merchant);
        $response = $this->getJson('/api/merchant/dashboard/top-parkings');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Test Parking', 'total_revenue' => 500]);
    }

    public function test_merchant_can_get_withdrawals()
    {
        MerchantWithdrawal::create([
            'merchant_id' => $this->merchant->id,
            'amount' => 500,
            'status' => 'completed',
        ]);

        Sanctum::actingAs($this->merchant);
        $response = $this->getJson('/api/merchant/dashboard/withdrawals');

        $response->assertStatus(200)
            ->assertJsonStructure(['content', 'paginate'])
            ->assertJsonFragment(['amount' => 500]);
    }

    public function test_merchant_can_get_nearby_arrivals()
    {
        $stop = Stop::create([
            'name' => 'Test Stop',
            'latitude' => 27.7172,
            'longitude' => 85.3240,
        ]);

        $route = Route::create(['name' => 'Test Route', 'slug' => 'test-route', 'direction' => 'inbound']);
        $route->stops()->create([
            'stop_id' => $stop->id,
            'stop_name' => $stop->name,
            'latitude' => $stop->latitude,
            'longitude' => $stop->longitude,
            'order' => 1
        ]);

        $bus = Bus::create([
            'name' => 'Test Bus',
            'bus_number' => 'BA 1 PA 1234',
            'hwid' => 'HWID123',
            'merchant_id' => $this->merchant->id,
            'route_id' => $route->id,
            'status' => 'active'
        ]);

        BusLocation::create([
            'bus_id' => $bus->id,
            'latitude' => 27.7180,
            'longitude' => 85.3250,
            'speed' => 20,
            'heading' => 90,
            'recorded_at' => now()
        ]);

        Sanctum::actingAs($this->merchant);
        $response = $this->getJson('/api/merchant/dashboard/arrivals?lat=27.7172&long=85.3240');

        $response->assertStatus(200)
            ->assertJsonStructure(['content'])
            ->assertJsonFragment(['stop_name' => 'Test Stop', 'bus_name' => 'Test Bus']);
    }

    public function test_staff_can_see_aggregated_income()
    {
        $staff = User::create([
            'name' => 'Staff Member',
            'email' => 'staff@example.com',
            'phone_number' => '9841000000',
            'password' => Hash::make('password'),
            'status' => User::STATUS_ACTIVE,
        ]);
        
        // Link staff to merchant
        $this->merchant->staff()->attach($staff);

        // Income for the merchant
        $bo = BalanceOut::create(['amount' => 1000, 'user_id' => $this->merchant->id, 'merchant_id' => $this->merchant->id, 'type' => 'fare_deduction']);
        MerchantIncome::create([
            'merchant_id' => $this->merchant->id,
            'balance_out_id' => $bo->id,
            'amount' => 1000,
            'type' => 'fare',
            'reference_id' => 1,
            'reference_type' => Bus::class,
        ]);

        Sanctum::actingAs($staff);
        $response = $this->getJson('/api/merchant/dashboard/income');

        $response->assertStatus(200)
            ->assertJsonPath('content.current_month_income', 1000);
    }
}
