<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    /**
     * Seed the fixed banner positions with dummy data.
     */
    public function run(): void
    {
        // 1. Home Top - Carousel
        Banner::updateOrCreate(
            ['position' => 'home_top'],
            [
                'type' => 'carousel',
                'title' => 'Welcome to Hitee',
                'images' => [
                    [
                        'title' => 'Fast & Secure Commute',
                        'image_path' => 'banners/dummy/home_1.jpg',
                        'link' => 'https://hitee.ai/commute'
                    ],
                    [
                        'title' => 'Hassle-free Parking',
                        'image_path' => 'banners/dummy/home_2.jpg',
                        'link' => 'https://hitee.ai/parking'
                    ],
                    [
                        'title' => 'Cashless Payments',
                        'image_path' => 'banners/dummy/home_3.jpg',
                        'link' => 'https://hitee.ai/payments'
                    ]
                ],
                'is_active' => true,
            ]
        );

        // 2. Home Middle - Single
        Banner::updateOrCreate(
            ['position' => 'home_middle'],
            [
                'type' => 'single',
                'title' => 'Limited Time Offer',
                'image_path' => 'banners/dummy/offer.jpg',
                'link' => 'https://hitee.ai/offers',
                'is_active' => true,
            ]
        );

        // 3. Home Bottom - Single
        Banner::updateOrCreate(
            ['position' => 'home_bottom'],
            [
                'type' => 'single',
                'title' => 'Refer & Earn',
                'image_path' => 'banners/dummy/refer.jpg',
                'link' => 'https://hitee.ai/referral',
                'is_active' => true,
            ]
        );

        // 4. Wallet Top - Carousel
        Banner::updateOrCreate(
            ['position' => 'wallet_top'],
            [
                'type' => 'carousel',
                'title' => 'Wallet Promotions',
                'images' => [
                    [
                        'title' => '10% Cashback on Topup',
                        'image_path' => 'banners/dummy/wallet_1.jpg',
                        'link' => 'https://hitee.ai/cashback'
                    ],
                    [
                        'title' => 'Partner Rewards',
                        'image_path' => 'banners/dummy/wallet_2.jpg',
                        'link' => 'https://hitee.ai/partners'
                    ]
                ],
                'is_active' => true,
            ]
        );

        $this->command->info('Banner positions seeded with dummy data.');
    }
}
