<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Beverages', 'description' => 'Soft drinks, juice, water, coffee, tea'],
            ['name' => 'Snacks', 'description' => 'Chips, biscuits, nuts, crackers'],
            ['name' => 'Canned Goods', 'description' => 'Sardines, corned beef, vienna sausage'],
            ['name' => 'Instant Noodles', 'description' => 'Lucky Me, Nissin, Payless'],
            ['name' => 'Rice & Grains', 'description' => 'Rice, monggo, sugar'],
            ['name' => 'Bread & Pastries', 'description' => 'Pandesal, loaf bread, ensaymada'],
            ['name' => 'Condiments', 'description' => 'Soy sauce, vinegar, ketchup, cooking oil'],
            ['name' => 'Personal Care', 'description' => 'Soap, shampoo, toothpaste'],
            ['name' => 'Household', 'description' => 'Detergent, bleach, cleaning supplies'],
            ['name' => 'Sweets & Candy', 'description' => 'Candy, chocolate, ice pops'],
            ['name' => 'Dairy & Eggs', 'description' => 'Milk, eggs, cheese, butter'],
            ['name' => 'Frozen Goods', 'description' => 'Ice cream, frozen meat, ice pops'],
        ];

        foreach ($categories as $cat) {
            Category::create($cat);
        }
    }
}
