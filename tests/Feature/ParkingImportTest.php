<?php

namespace Tests\Feature;

use App\Models\Parking;
use App\Models\ParkingAttribute;
use App\Models\Role;
use App\Models\User;
use App\Services\ParkingImportService;
use Database\Seeders\HiteeSolutionMerchantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParkingImportTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function lot(array $overrides = []): array
    {
        return array_merge([
            'id' => 13,
            'name' => 'Tripureswor Eye Hospital',
            'location' => ['latitude' => 27.6933448, 'longitude' => 85.3140369, 'name' => 'Tripureswor'],
            'owner' => ['name' => 'Sarkari parking', 'phone' => null],
            'charges' => [
                'bike' => ['first_hour' => 10, 'after' => 5],
                'car' => ['first_hour' => 20, 'after' => 15],
                'ev' => ['per_hour' => null, 'per_unit' => null],
                'monthly' => ['bike' => 1500, 'car' => null],
            ],
            'features' => ['security_guard', 'ev_charging', 'brand_new_feature'],
            'pictures' => [
                'https://example.com/a.jpg',
                'https://example.com/b.jpg',
            ],
            'notes' => 'Near the main gate',
            'created_at' => '2026-09-02 06:43:39',
        ], $overrides);
    }

    private function merchant(): User
    {
        return app(ParkingImportService::class)->defaultMerchant();
    }

    public function test_import_creates_parking_with_fees_attributes_and_owner(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response(base64_decode(self::PNG), 200, ['Content-Type' => 'image/jpeg'])]);

        $result = app(ParkingImportService::class)->import([$this->lot()], $this->merchant());

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['failed']);

        $parking = Parking::where('external_id', 13)->sole();

        $this->assertSame('Tripureswor Eye Hospital', $parking->name);
        $this->assertSame('Tripureswor', $parking->location);
        $this->assertSame(27.6933448, $parking->latitude);
        $this->assertSame('Sarkari parking', $parking->owner_name);
        $this->assertSame('Near the main gate', $parking->notes);
        $this->assertSame('opened', $parking->status);
        $this->assertEquals('2026-09-02 06:43:39', $parking->created_at->format('Y-m-d H:i:s'));
        $this->assertSame(HiteeSolutionMerchantSeeder::EMAIL, $parking->merchant->email);

        // Fees: bike/car first-hour + after, monthly bike — null values skipped
        $this->assertEqualsCanonicalizing(
            ['Bike — First Hour', 'Bike — Additional Hour', 'Car — First Hour', 'Car — Additional Hour', 'Monthly — Bike'],
            $parking->fees->pluck('title')->all()
        );
        $this->assertSame(10.0, (float) $parking->fees->firstWhere('title', 'Bike — First Hour')->price_rs);

        // Feature keys map onto attributes; unknown keys are humanized
        $this->assertEqualsCanonicalizing(
            ['Security Guard', 'EV Charging', 'Brand New Feature'],
            $parking->attributes->pluck('name')->all()
        );
    }

    public function test_first_picture_becomes_featured_and_rest_go_to_gallery(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response(base64_decode(self::PNG), 200, ['Content-Type' => 'image/jpeg'])]);

        $result = app(ParkingImportService::class)->import([$this->lot()], $this->merchant());

        $this->assertSame(2, $result['images']);

        $parking = Parking::sole();
        $this->assertSame(1, $parking->media()->where('collection_name', 'featured')->count());
        $this->assertSame(1, $parking->media()->where('collection_name', 'gallery')->count());
        $this->assertStringNotContainsString('no_image.png', $parking->featured_image_url);
    }

    public function test_reimport_updates_in_place_without_duplicating(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response(base64_decode(self::PNG), 200, ['Content-Type' => 'image/jpeg'])]);

        $service = app(ParkingImportService::class);
        $service->import([$this->lot()], $this->merchant());
        $result = $service->import([$this->lot(['name' => 'Renamed Lot'])], $this->merchant());

        $this->assertSame(1, Parking::count());
        $this->assertSame(1, $result['updated']);
        $this->assertSame('Renamed Lot', Parking::sole()->name);
        $this->assertSame(2, Parking::sole()->media()->count()); // images not re-downloaded
    }

    public function test_import_without_images_skips_downloads(): void
    {
        Storage::fake('public');
        Http::fake();

        $result = app(ParkingImportService::class)->import([$this->lot()], $this->merchant(), withImages: false);

        $this->assertSame(0, $result['images']);
        Http::assertNothingSent();
    }

    public function test_super_admin_can_upload_import_file(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response(base64_decode(self::PNG), 200, ['Content-Type' => 'image/jpeg'])]);

        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get(route('parkings.import'))->assertOk();

        $file = UploadedFile::fake()->createWithContent('lots.json', json_encode([$this->lot()]));

        $this->actingAs($admin)
            ->post(route('parkings.import.store'), ['file' => $file])
            ->assertRedirect(route('parkings.index'));

        $this->assertSame(1, Parking::count());
        $this->assertSame('Tripureswor Eye Hospital', Parking::sole()->name);
    }

    public function test_non_admin_cannot_access_import(): void
    {
        $staff = User::factory()->create();
        Role::firstOrCreate(['slug' => 'staff'], ['name' => 'Staff'])->users()->attach($staff);

        $this->actingAs($staff)->get(route('parkings.import'))->assertForbidden();

        $file = UploadedFile::fake()->createWithContent('lots.json', json_encode([$this->lot()]));
        $this->actingAs($staff)
            ->post(route('parkings.import.store'), ['file' => $file])
            ->assertForbidden();
    }

    public function test_invalid_json_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $file = UploadedFile::fake()->createWithContent('bad.json', '{"not": "a list"}');

        $this->actingAs($admin)
            ->post(route('parkings.import.store'), ['file' => $file])
            ->assertSessionHas('error');

        $this->assertSame(0, Parking::count());
    }

    private function makeAdmin(): User
    {
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        $admin = User::factory()->create();
        $admin->roles()->attach($role);

        return $admin;
    }
}
