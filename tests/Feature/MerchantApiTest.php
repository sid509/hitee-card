<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\MerchantIncome;
use App\Models\MerchantWithdrawal;
use App\Models\Role;
use App\Models\Route;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MerchantApiTest extends TestCase
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

    public function test_merchant_can_login()
    {
        $response = $this->postJson('/api/merchant/login', [
            'phone_number' => '9841234567',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'content' => [
                    'access_token',
                    'user' => ['id', 'name', 'merchant_balance']
                ]
            ]);
    }

    public function test_non_merchant_cannot_login_to_merchant_app()
    {
        $user = User::create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone_number' => '9800000000',
            'password' => Hash::make('password'),
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        
        $response = $this->postJson('/api/merchant/login', [
            'phone_number' => '9800000000',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
    }

    public function test_merchant_can_get_income_stats()
    {
        $now = \Carbon\Carbon::now();
        $lastMonth = $now->copy()->subMonthNoOverflow();

        // Create current month income
        $balanceOut1 = \App\Models\BalanceOut::create([
            'amount' => 1000,
            'user_id' => $this->merchant->id,
            'merchant_id' => $this->merchant->id,
            'type' => 'fare_deduction',
        ]);

        $mi1 = new MerchantIncome([
            'merchant_id' => $this->merchant->id,
            'balance_out_id' => $balanceOut1->id,
            'amount' => 1000,
            'type' => 'fare',
            'reference_id' => 1,
            'reference_type' => 'App\Models\Bus',
        ]);
        $mi1->created_at = $now;
        $mi1->save();

        // Create last month income
        $balanceOut2 = \App\Models\BalanceOut::create([
            'amount' => 500,
            'user_id' => $this->merchant->id,
            'merchant_id' => $this->merchant->id,
            'type' => 'fare_deduction',
        ]);
        $balanceOut2->created_at = $lastMonth;
        $balanceOut2->save();

        $mi2 = new MerchantIncome([
            'merchant_id' => $this->merchant->id,
            'balance_out_id' => $balanceOut2->id,
            'amount' => 500,
            'type' => 'fare',
            'reference_id' => 1,
            'reference_type' => 'App\Models\Bus',
        ]);
        $mi2->created_at = $lastMonth;
        $mi2->save();

        $response = $this->actingAs($this->merchant)->getJson('/api/merchant/dashboard/income');

        $response->assertStatus(200)
            ->assertJsonPath('content.current_month_income', 1000)
            ->assertJsonPath('content.last_month_income', 500)
            ->assertJsonPath('content.percentage_change', 100); 
    }

    public function test_merchant_can_list_buses()
    {
        Bus::create([
            'name' => 'Test Bus',
            'bus_number' => 'BA 1 PA 1234',
            'hwid' => 'HWID123',
            'merchant_id' => $this->merchant->id,
            'status' => 'active'
        ]);

        $response = $this->actingAs($this->merchant)->getJson('/api/merchant/buses');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'content');
    }

    public function test_merchant_can_update_profile()
    {
        $response = $this->actingAs($this->merchant)->putJson('/api/merchant/profile', [
            'name' => 'Updated Merchant Name',
            'email' => 'updated@merchant.com',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated Merchant Name', $this->merchant->fresh()->name);
    }
}
