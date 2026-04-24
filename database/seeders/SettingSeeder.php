<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'support_email', 'value' => 'support@hitee.ai'],
            ['key' => 'support_phone', 'value' => '+977-1-1234567'],
            ['key' => 'terms_url', 'value' => 'https://hitee.ai/terms'],
            ['key' => 'policy_url', 'value' => 'https://hitee.ai/privacy'],
            ['key' => 'negative_allowed_point', 'value' => '50'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], ['value' => $setting['value']]);
        }

        $this->command->info('Global settings seeded successfully.');
    }
}
