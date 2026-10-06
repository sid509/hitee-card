<?php

namespace App\Services;

use App\Models\Parking;
use App\Models\ParkingAttribute;
use App\Models\User;
use Database\Seeders\HiteeSolutionMerchantSeeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ParkingImportService
{
    /**
     * Source JSON feature keys → ParkingAttribute names.
     */
    private const FEATURE_MAP = [
        '24_hours'        => '24/7 Access',
        'cctv'            => 'CCTV Surveillance',
        'covered'         => 'Covered Parking',
        'ev_charging'     => 'EV Charging',
        'helmet'          => 'Helmet Facility',
        'monthly_parking' => 'Monthly Parking',
        'qr_enabled'      => 'QR Enabled',
        'security_guard'  => 'Security Guard',
    ];

    /**
     * The merchant imported lots are assigned to — the seeded
     * "Hitee Solution" account, created on the fly if missing.
     */
    public function defaultMerchant(): User
    {
        $merchant = User::where('email', HiteeSolutionMerchantSeeder::EMAIL)->first();

        if (!$merchant) {
            (new HiteeSolutionMerchantSeeder)->run();
            $merchant = User::where('email', HiteeSolutionMerchantSeeder::EMAIL)->firstOrFail();
        }

        return $merchant;
    }

    /**
     * Import parking lots from the decoded JSON export.
     *
     * @return array{created:int, updated:int, failed:int, images:int, errors:array}
     */
    public function import(array $lots, User $merchant, bool $withImages = true): array
    {
        $result = ['created' => 0, 'updated' => 0, 'failed' => 0, 'images' => 0, 'errors' => []];

        foreach ($lots as $index => $lot) {
            if (!is_array($lot) || blank(Arr::get($lot, 'name'))) {
                $result['failed']++;
                $result['errors'][] = "#{$index}: missing or invalid name";
                continue;
            }

            try {
                $parking = DB::transaction(fn () => $this->importLot($lot, $merchant));

                $result[$parking->wasRecentlyCreated ? 'created' : 'updated']++;

                if ($withImages) {
                    $result['images'] += $this->importImages($parking, Arr::get($lot, 'pictures', []));
                }
            } catch (\Throwable $e) {
                $result['failed']++;
                $result['errors'][] = ($lot['name'] ?? "#{$index}") . ': ' . $e->getMessage();
            }
        }

        return $result;
    }

    private function importLot(array $lot, User $merchant): Parking
    {
        // Match on the source id when present so re-imports update in place.
        $match = isset($lot['id'])
            ? ['external_id' => $lot['id']]
            : ['name' => $lot['name']];

        $parking = Parking::updateOrCreate($match, [
            'name'           => $lot['name'],
            'location'       => Arr::get($lot, 'location.name', 'Unknown'),
            'latitude'       => Arr::get($lot, 'location.latitude'),
            'longitude'      => Arr::get($lot, 'location.longitude'),
            'merchant_id'    => $merchant->id,
            'owner_name'     => Arr::get($lot, 'owner.name'),
            'owner_phone'    => Arr::get($lot, 'owner.phone'),
            'notes'          => Arr::get($lot, 'notes'),
            'status'         => 'opened',
        ]);

        if (!empty($lot['created_at'])) {
            $parking->created_at = $lot['created_at'];
            $parking->saveQuietly();
        }

        $parking->fees()->delete();
        $parking->fees()->createMany($this->mapFees(Arr::get($lot, 'charges', [])));

        $parking->attributes()->sync(
            collect(Arr::get($lot, 'features', []))->map(fn ($f) => $this->resolveAttribute($f)->id)
        );

        return $parking;
    }

    /**
     * Map the nested vehicle charges onto flat parking_fees tiers.
     */
    private function mapFees(array $charges): array
    {
        $fees = [];

        $add = function (string $title, ?string $subtitle, $price) use (&$fees) {
            if ($price === null) return;
            $fees[] = [
                'title'     => $title,
                'subtitle'  => $subtitle,
                'price_rs'  => $price,
                'price_pts' => $price,
                'order'     => count($fees),
            ];
        };

        $add('Bike — First Hour',      'Two-wheeler entry fee',          Arr::get($charges, 'bike.first_hour'));
        $add('Bike — Additional Hour', 'Per hour after the first hour',  Arr::get($charges, 'bike.after'));
        $add('Car — First Hour',       'Four-wheeler entry fee',         Arr::get($charges, 'car.first_hour'));
        $add('Car — Additional Hour',  'Per hour after the first hour',  Arr::get($charges, 'car.after'));
        $add('EV — Per Hour',          'EV charging time rate',          Arr::get($charges, 'ev.per_hour'));
        $add('EV — Per Unit',          'Per kWh consumed',               Arr::get($charges, 'ev.per_unit'));
        $add('Monthly — Bike',         'Monthly pass',                   Arr::get($charges, 'monthly.bike'));
        $add('Monthly — Car',          'Monthly pass',                   Arr::get($charges, 'monthly.car'));

        return $fees;
    }

    private function resolveAttribute(string $feature): ParkingAttribute
    {
        $name = self::FEATURE_MAP[$feature] ?? Str::headline(str_replace(['-', '_'], ' ', $feature));

        return ParkingAttribute::firstOrCreate(['name' => $name]);
    }

    /**
     * First picture becomes the featured image, the rest go to the gallery.
     * Existing media is left untouched on re-imports.
     *
     * @return int number of images stored
     */
    private function importImages(Parking $parking, array $urls): int
    {
        if ($parking->media()->exists()) {
            return 0;
        }

        $stored = 0;
        foreach (array_values(array_filter($urls)) as $i => $url) {
            $collection = $i === 0 ? 'featured' : 'gallery';

            try {
                if ($parking->addMediaFromUrl($url, $collection)) {
                    $stored++;
                }
            } catch (\Throwable) {
                // A broken image URL shouldn't sink the whole lot import.
            }
        }

        return $stored;
    }
}
