<?php

namespace Tests\Feature\CardManagement;

use App\Models\CardManagement\ValidatorDevice;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidatorQrTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin']
        );

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->roles()->attach(Role::where('slug', 'super-admin')->first());

        return $user;
    }

    public function test_qr_page_returns_ok_and_renders_payload(): void
    {
        $device = ValidatorDevice::create([
            'device_id' => 'VAL-E60-001',
            'vehicle_id' => 'BUS-042',
            'route_id' => 'ROUTE-12',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->superAdmin())
            ->get(route('card-management.validators.qr', $device->id));

        $response->assertOk()
            ->assertSee('VAL-E60-001', false)
            ->assertSee('BUS-042', false)
            ->assertSee('ROUTE-12', false)
            ->assertSee('deviceId', false)
            ->assertSee('apiUrl', false)
            ->assertSee('<svg', false);
    }

    public function test_qr_payload_endpoint_returns_expected_json(): void
    {
        $device = ValidatorDevice::create([
            'device_id' => 'VAL-E60-002',
            'vehicle_id' => 'BUS-099',
            'route_id' => 'ROUTE-3',
            'status' => 'REGISTERED',
        ]);

        $response = $this->actingAs($this->superAdmin())
            ->getJson(route('card-management.validators.payload', $device->id));

        $response->assertOk()
            ->assertJsonPath('deviceId', 'VAL-E60-002')
            ->assertJsonPath('vehicleId', 'BUS-099')
            ->assertJsonPath('routeId', 'ROUTE-3')
            ->assertJsonStructure(['deviceId', 'vehicleId', 'routeId', 'apiUrl']);
    }

    public function test_qr_page_404_for_unknown_device(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('card-management.validators.qr', 'non-existent-uuid'))
            ->assertNotFound();
    }

    public function test_qr_page_requires_super_admin(): void
    {
        $device = ValidatorDevice::create([
            'device_id' => 'VAL-E60-003',
            'status' => 'ACTIVE',
        ]);

        // Unauthenticated -> redirect to login
        $this->get(route('card-management.validators.qr', $device->id))
            ->assertRedirect(route('login'));

        // Authenticated non-admin -> 403
        Role::firstOrCreate(['slug' => 'customers'], ['name' => 'Customers']);
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->roles()->attach(Role::where('slug', 'customers')->first());

        $this->actingAs($user)
            ->get(route('card-management.validators.qr', $device->id))
            ->assertForbidden();
    }
}
