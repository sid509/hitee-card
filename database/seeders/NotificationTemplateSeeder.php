<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'type' => 'fcm',
                'name' => 'Welcome Message',
                'subject_en' => 'Welcome to Hitee, {name}!',
                'subject_ne' => 'Hitee मा स्वागत छ, {name}!',
                'body_en' => 'Thank you for registering. Enjoy our transit and merchant services.',
                'body_ne' => 'तपाईंलाई दर्ता गर्नुभएकोमा धन्यवाद। हाम्रो सेवाको आनन्द लिनुहोस्।',
                'variables' => ['name'],
            ],
            [
                'type' => 'fcm',
                'name' => 'Low Balance Alert',
                'subject_en' => 'Low Balance Warning',
                'subject_ne' => 'कम ब्यालेन्स चेतावनी',
                'body_en' => 'Dear {name}, your card balance is running low. Please top up soon.',
                'body_ne' => 'प्रिय {name}, तपाईंको कार्ड ब्यालेन्स कम छ। कृपया चाँडै टपअप गर्नुहोस्।',
                'variables' => ['name'],
            ]
        ];

        foreach ($templates as $t) {
            NotificationTemplate::firstOrCreate(['name' => $t['name']], $t);
        }
    }
}
