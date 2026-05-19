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
            ['key' => 'support_email', 'value' => 'info@hitee.ai'],
            ['key' => 'support_phone', 'value' => '+977-1-1234567'],
            ['key' => 'terms_url', 'value' => 'https://hitee.ai/terms'],
            ['key' => 'policy_url', 'value' => 'https://hitee.ai/privacy'],
            ['key' => 'negative_allowed_point', 'value' => '50'],
            ['key' => 'stripe_secret_key', 'value' => config('services.stripe.secret', '')],
            ['key' => 'stripe_publishable_key', 'value' => config('services.stripe.key', '')],
            ['key' => 'stripe_currency', 'value' => config('services.stripe.currency', 'usd')],
            ['key' => 'khalti_secret_key', 'value' => config('services.khalti.secret_key', '')],
            ['key' => 'khalti_public_key', 'value' => config('services.khalti.public_key', '')],
            ['key' => 'khalti_mode', 'value' => config('services.khalti.mode', 'test')],
            ['key' => 'NOTIFICATION_TOKEN', 'value' => env('NOTIFICATION_TOKEN', '')],
            ['key' => 'FIREBASE_SERVICE_ACCOUNT', 'value' => env('FIREBASE_SERVICE_ACCOUNT', '')],
            ['key' => 'facebook_client_id', 'value' => config('services.facebook.client_id', '')],
            ['key' => 'facebook_client_secret', 'value' => config('services.facebook.client_secret', '')],
            ['key' => 'facebook_redirect_url', 'value' => config('services.facebook.redirect', '')],
            ['key' => 'google_client_id', 'value' => config('services.google.client_id', '')],
            ['key' => 'google_client_secret', 'value' => config('services.google.client_secret', '')],
            ['key' => 'google_redirect_url', 'value' => config('services.google.redirect', '')],
            ['key' => 'mail_host', 'value' => config('mail.mailers.smtp.host', 'smtp.zoho.in')],
            ['key' => 'mail_port', 'value' => config('mail.mailers.smtp.port', '465')],
            ['key' => 'mail_username', 'value' => config('mail.mailers.smtp.username', 'info@hitee.ai')],
            ['key' => 'mail_password', 'value' => config('mail.mailers.smtp.password', 'Created@2026')],
            ['key' => 'mail_encryption', 'value' => config('mail.mailers.smtp.encryption', 'ssl')],
            ['key' => 'mail_from_address', 'value' => config('mail.from.address', 'info@hitee.ai')],
            ['key' => 'mail_from_name', 'value' => config('mail.from.name', 'Hitee')],
        ];

        foreach ($settings as $setting) {
            if (!empty(trim((string)$setting['value'])) || in_array($setting['key'], ['support_email', 'support_phone', 'terms_url', 'policy_url', 'negative_allowed_point', 'khalti_mode', 'mail_encryption'])) {
                Setting::updateOrCreate(['key' => $setting['key']], ['value' => trim((string)$setting['value'])]);
            }
        }

        $this->command->info('Global settings seeded successfully.');
    }
}
