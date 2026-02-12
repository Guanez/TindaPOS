<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Map category names to IDs
        $cat = Category::pluck('id', 'name');

        $products = [
            // ─── Beverages ───────────────────────────
            ['category' => 'Beverages', 'name' => 'Coca-Cola Mismo (295ml)', 'sku' => 'BEV-COKE-295', 'cost_price' => 12, 'selling_price' => 15, 'stock' => 48],
            ['category' => 'Beverages', 'name' => 'Royal Tru-Orange (500ml)', 'sku' => 'BEV-ROYAL-500', 'cost_price' => 18, 'selling_price' => 25, 'stock' => 36],
            ['category' => 'Beverages', 'name' => 'C2 Green Tea Apple (500ml)', 'sku' => 'BEV-C2-APPLE', 'cost_price' => 16, 'selling_price' => 20, 'stock' => 48],
            ['category' => 'Beverages', 'name' => 'Nescafe 3-in-1 Original', 'sku' => 'BEV-NESCAFE-3N1', 'cost_price' => 7, 'selling_price' => 10, 'stock' => 100],
            ['category' => 'Beverages', 'name' => 'Absolute Drinking Water (500ml)', 'sku' => 'BEV-ABS-500', 'cost_price' => 8, 'selling_price' => 12, 'stock' => 60],

            // ─── Snacks ──────────────────────────────
            ['category' => 'Snacks', 'name' => 'Piattos Cheese (40g)', 'sku' => 'SNK-PIATTOS-40', 'cost_price' => 12, 'selling_price' => 15, 'stock' => 36],
            ['category' => 'Snacks', 'name' => 'Boy Bawang Cornick Garlic', 'sku' => 'SNK-BOYBWNG-G', 'cost_price' => 8, 'selling_price' => 12, 'stock' => 48],
            ['category' => 'Snacks', 'name' => 'SkyFlakes Crackers Pack', 'sku' => 'SNK-SKYFLK-PK', 'cost_price' => 9, 'selling_price' => 12, 'stock' => 36],
            ['category' => 'Snacks', 'name' => 'Chippy BBQ (110g)', 'sku' => 'SNK-CHIPPY-110', 'cost_price' => 18, 'selling_price' => 24, 'stock' => 24],
            ['category' => 'Snacks', 'name' => 'Oishi Prawn Crackers (60g)', 'sku' => 'SNK-PRAWN-60', 'cost_price' => 14, 'selling_price' => 18, 'stock' => 30],

            // ─── Canned Goods ────────────────────────
            ['category' => 'Canned Goods', 'name' => 'Mega Sardines (155g)', 'sku' => 'CAN-MEGA-155', 'cost_price' => 18, 'selling_price' => 22, 'stock' => 48],
            ['category' => 'Canned Goods', 'name' => 'Century Tuna Flakes (180g)', 'sku' => 'CAN-TUNA-180', 'cost_price' => 32, 'selling_price' => 40, 'stock' => 36],
            ['category' => 'Canned Goods', 'name' => 'Argentina Corned Beef (175g)', 'sku' => 'CAN-ARGENTINA-175', 'cost_price' => 38, 'selling_price' => 48, 'stock' => 24],
            ['category' => 'Canned Goods', 'name' => 'Purefoods Vienna Sausage', 'sku' => 'CAN-VIENNA-PF', 'cost_price' => 22, 'selling_price' => 28, 'stock' => 30],

            // ─── Instant Noodles ─────────────────────
            ['category' => 'Instant Noodles', 'name' => 'Lucky Me Pancit Canton Original', 'sku' => 'NDL-LMPCO', 'cost_price' => 8, 'selling_price' => 12, 'stock' => 72],
            ['category' => 'Instant Noodles', 'name' => 'Lucky Me Pancit Canton Chilimansi', 'sku' => 'NDL-LMPCC', 'cost_price' => 8, 'selling_price' => 12, 'stock' => 72],
            ['category' => 'Instant Noodles', 'name' => 'Nissin Cup Noodles Seafood', 'sku' => 'NDL-NISSIN-SEA', 'cost_price' => 22, 'selling_price' => 28, 'stock' => 24],
            ['category' => 'Instant Noodles', 'name' => 'Payless Noodles Chicken', 'sku' => 'NDL-PAYLESS-C', 'cost_price' => 5, 'selling_price' => 7, 'stock' => 100],

            // ─── Rice & Grains ───────────────────────
            ['category' => 'Rice & Grains', 'name' => 'NFA Rice (1kg)', 'sku' => 'RICE-NFA-1K', 'cost_price' => 38, 'selling_price' => 44, 'stock' => 30],
            ['category' => 'Rice & Grains', 'name' => 'White Sugar (1kg)', 'sku' => 'GRAIN-SUGAR-1K', 'cost_price' => 55, 'selling_price' => 65, 'stock' => 20],

            // ─── Bread ───────────────────────────────
            ['category' => 'Bread & Pastries', 'name' => 'Pandesal (10pcs)', 'sku' => 'BRD-PANDESAL-10', 'cost_price' => 30, 'selling_price' => 40, 'stock' => 15],
            ['category' => 'Bread & Pastries', 'name' => 'Gardenia Classic White (Regular)', 'sku' => 'BRD-GARDENIA-R', 'cost_price' => 70, 'selling_price' => 85, 'stock' => 10],

            // ─── Condiments ──────────────────────────
            ['category' => 'Condiments', 'name' => 'Silver Swan Soy Sauce (200ml)', 'sku' => 'CON-TOYO-200', 'cost_price' => 12, 'selling_price' => 16, 'stock' => 30],
            ['category' => 'Condiments', 'name' => 'Datu Puti Vinegar (200ml)', 'sku' => 'CON-SUKA-200', 'cost_price' => 10, 'selling_price' => 14, 'stock' => 30],
            ['category' => 'Condiments', 'name' => 'UFC Banana Ketchup (320g)', 'sku' => 'CON-KETCHUP-UFC', 'cost_price' => 28, 'selling_price' => 35, 'stock' => 24],
            ['category' => 'Condiments', 'name' => 'Baguio Oil (250ml)', 'sku' => 'CON-OIL-250', 'cost_price' => 32, 'selling_price' => 40, 'stock' => 18],

            // ─── Personal Care ───────────────────────
            ['category' => 'Personal Care', 'name' => 'Safeguard Soap (130g)', 'sku' => 'PC-SAFEGUARD', 'cost_price' => 32, 'selling_price' => 42, 'stock' => 24],
            ['category' => 'Personal Care', 'name' => 'Palmolive Shampoo Sachet', 'sku' => 'PC-PALMOLIVE-S', 'cost_price' => 5, 'selling_price' => 8, 'stock' => 100],
            ['category' => 'Personal Care', 'name' => 'Colgate Toothpaste (50ml)', 'sku' => 'PC-COLGATE-50', 'cost_price' => 30, 'selling_price' => 38, 'stock' => 20],

            // ─── Household ───────────────────────────
            ['category' => 'Household', 'name' => 'Surf Powder Detergent (Tingi)', 'sku' => 'HH-SURF-TINGI', 'cost_price' => 6, 'selling_price' => 9, 'stock' => 60],
            ['category' => 'Household', 'name' => 'Domex Bleach (250ml)', 'sku' => 'HH-DOMEX-250', 'cost_price' => 25, 'selling_price' => 32, 'stock' => 15],

            // ─── Sweets ──────────────────────────────
            ['category' => 'Sweets & Candy', 'name' => 'Choc Nut Peanut Chocolate', 'sku' => 'SWT-CHOCNUT', 'cost_price' => 1.50, 'selling_price' => 2.50, 'stock' => 200],
            ['category' => 'Sweets & Candy', 'name' => 'Hany Caramel Candy', 'sku' => 'SWT-HANY', 'cost_price' => 0.75, 'selling_price' => 1.00, 'stock' => 200],

            // ─── Dairy ───────────────────────────────
            ['category' => 'Dairy & Eggs', 'name' => 'Bear Brand Powdered Milk (33g)', 'sku' => 'DRY-BEARBRAND-33', 'cost_price' => 12, 'selling_price' => 15, 'stock' => 48],
            ['category' => 'Dairy & Eggs', 'name' => 'Eggs (1pc)', 'sku' => 'DRY-EGG-1', 'cost_price' => 7, 'selling_price' => 9, 'stock' => 120],
        ];

        foreach ($products as $p) {
            Product::create([
                'category_id' => $cat[$p['category']],
                'name' => $p['name'],
                'sku' => $p['sku'],
                'cost_price' => $p['cost_price'],
                'selling_price' => $p['selling_price'],
                'stock_quantity' => $p['stock'],
                'low_stock_threshold' => 10,
            ]);
        }
    }
}
