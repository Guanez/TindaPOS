<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'store_name' => 'TindaPOS Sari-Sari Store',
            'store_address' => 'Brgy. Sample, Quezon City, Metro Manila',
            'store_phone' => '0917-123-4567',
            'receipt_footer' => 'Salamat po! Please come again.',
            'currency_symbol' => '₱',
            'low_stock_threshold' => '10',
            'tax_rate' => '0',
            'receipt_show_address' => 'true',
        ];

        foreach ($settings as $key => $value) {
            Setting::create(['key' => $key, 'value' => $value]);
        }
    }
}
