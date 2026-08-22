<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderService;
use App\Support\StoreContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A second store, so multi-tenancy is exercised by real use and not only by
 * tests — and so there is a cafe menu to click through.
 *
 * Coffee does not track stock (nobody counts lattes) while pastries do, which
 * is the distinction track_stock exists for.
 */
class CafeSeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::query()->firstOrCreate(
            ['slug' => 'kape-lokal'],
            [
                'name' => 'Kape Lokal',
                'type' => 'cafe',
                'address' => '12 Maginhawa St, Diliman, Quezon City',
                'phone' => '0917-555-0123',
                'receipt_footer' => 'Salamat! Balik ka ulit.',
                'currency_symbol' => 'P',
                'qr_token' => Str::random(32),
                'online_ordering_enabled' => false,
            ]
        );

        app(StoreContext::class)->runFor($store->id, function () use ($store): void {
            $this->seedStaff();
            $groups = $this->seedModifierGroups();
            $this->seedMenu($groups);
            $this->seedQueue($store);
        });
    }

    private function seedStaff(): void
    {
        User::query()->firstOrCreate(
            ['username' => 'kape_owner'],
            [
                'name' => 'Marisol Reyes',
                'email' => 'owner@kapelokal.ph',
                'password' => 'owner123',
                'role' => 'owner',
            ]
        );

        User::query()->firstOrCreate(
            ['username' => 'kape_barista'],
            [
                'name' => 'Paolo Cruz',
                'email' => 'barista@kapelokal.ph',
                'password' => 'owner123',
                'role' => 'cashier',
            ]
        );
    }

    /**
     * A few orders on the board, so the queue screen has something to show
     * before the customer-facing page exists. Delete this method once real
     * orders start arriving.
     */
    private function seedQueue(Store $store): void
    {
        if (Order::query()->count() > 0) {
            return;
        }

        $latte = Product::query()->where('sku', 'LAT')->first();
        $pastry = Product::query()->where('sku', 'ENS')->first();
        $barista = User::query()->where('username', 'kape_barista')->first();

        if ($latte === null || $pastry === null || $barista === null) {
            return;
        }

        $large = ProductVariant::query()->where('product_id', $latte->id)->where('name', '16oz')->first();
        $hot = Modifier::query()->where('name', 'Hot')->first();
        $oat = Modifier::query()->where('name', 'Oat milk')->first();

        $orders = app(OrderService::class);

        $latteLine = fn (array $modifierIds) => [
            'product_id' => $latte->id,
            'quantity' => 1,
            'variant_id' => $large?->id,
            'modifier_ids' => array_values(array_filter($modifierIds)),
        ];

        // Waiting at the till.
        $orders->place($store, [
            'items' => [$latteLine([$hot?->id, $oat?->id])],
            'customer_name' => 'Ana',
            'note' => 'Less ice please',
        ]);

        // Paid, being made.
        $second = $orders->place($store, [
            'items' => [$latteLine([$hot?->id]), ['product_id' => $pastry->id, 'quantity' => 2]],
            'customer_name' => 'Miguel',
        ]);
        $orders->settle($second, $barista, ['payment_method' => 'gcash']);

        // Ready for pickup.
        $third = $orders->place($store, [
            'items' => [$latteLine([$hot?->id])],
            'customer_name' => 'Joy',
        ]);
        $orders->markReady(
            $orders->settle($third, $barista, ['payment_method' => 'cash', 'cash_received' => 500])
        );
    }

    /**
     * @return array<string, ModifierGroup>
     */
    private function seedModifierGroups(): array
    {
        $definitions = [
            'Temperature' => [
                'min_select' => 1,
                'max_select' => 1,
                'options' => [['Hot', 0], ['Iced', 10]],
            ],
            'Milk' => [
                'min_select' => 0,
                'max_select' => 1,
                'options' => [['Fresh milk', 0], ['Oat milk', 30], ['Soy milk', 25], ['Almond milk', 35]],
            ],
            'Extras' => [
                'min_select' => 0,
                'max_select' => 3,
                'options' => [['Extra shot', 25], ['Vanilla syrup', 20], ['Caramel syrup', 20], ['Whipped cream', 15]],
            ],
        ];

        $groups = [];

        foreach ($definitions as $name => $definition) {
            $group = ModifierGroup::query()->firstOrCreate(
                ['name' => $name],
                ['min_select' => $definition['min_select'], 'max_select' => $definition['max_select']]
            );

            foreach ($definition['options'] as $index => $option) {
                $group->modifiers()->firstOrCreate(
                    ['name' => $option[0]],
                    ['price_delta' => $option[1], 'sort_order' => $index]
                );
            }

            $groups[$name] = $group;
        }

        return $groups;
    }

    /**
     * @param  array<string, ModifierGroup>  $groups
     */
    private function seedMenu(array $groups): void
    {
        $espresso = Category::query()->firstOrCreate(['name' => 'Espresso'], ['description' => 'Coffee drinks']);
        $nonCoffee = Category::query()->firstOrCreate(['name' => 'Non-Coffee'], ['description' => 'Everything else to drink']);
        $pastries = Category::query()->firstOrCreate(['name' => 'Pastries'], ['description' => 'Baked daily']);

        // Drinks: sized, add-on friendly, and not stock-counted.
        $drinks = [
            [$espresso, 'Americano', 'AME', 110, 150, 28, true],
            [$espresso, 'Cafe Latte', 'LAT', 130, 170, 34, true],
            [$espresso, 'Cappuccino', 'CAP', 130, 170, 34, true],
            [$espresso, 'Spanish Latte', 'SPL', 145, 185, 40, true],
            [$espresso, 'Caramel Macchiato', 'CML', 155, 195, 44, true],
            [$nonCoffee, 'Matcha Latte', 'MAT', 150, 190, 46, true],
            [$nonCoffee, 'Hot Chocolate', 'HCH', 120, 160, 32, false],
        ];

        foreach ($drinks as $drink) {
            [$category, $name, $sku, $small, $large, $cost, $takesMilk] = $drink;

            $product = Product::query()->firstOrCreate(
                ['sku' => $sku],
                [
                    'category_id' => $category->id,
                    'name' => $name,
                    'cost_price' => $cost,
                    'selling_price' => $small,
                    'stock_quantity' => 0,
                    'track_stock' => false,
                    'is_favorite' => in_array($name, ['Cafe Latte', 'Americano'], true),
                ]
            );

            if ($product->variants()->count() === 0) {
                $product->variants()->createMany([
                    ['name' => '12oz', 'selling_price' => $small, 'cost_price' => $cost, 'is_default' => true, 'sort_order' => 0],
                    ['name' => '16oz', 'selling_price' => $large, 'cost_price' => $cost + 8, 'sort_order' => 1],
                ]);
            }

            $attach = [$groups['Temperature']->id, $groups['Extras']->id];

            if ($takesMilk) {
                $attach[] = $groups['Milk']->id;
            }

            $product->modifierGroups()->sync($attach);
        }

        // Pastries are counted, priced once, and have no add-ons.
        $baked = [
            ['Pandesal with Butter', 'PDB', 18, 35, 40],
            ['Banana Bread Slice', 'BNB', 42, 85, 18],
            ['Ensaymada', 'ENS', 38, 80, 24],
            ['Blueberry Cheesecake', 'BCC', 95, 180, 12],
        ];

        foreach ($baked as $item) {
            [$name, $sku, $cost, $price, $stock] = $item;

            Product::query()->firstOrCreate(
                ['sku' => $sku],
                [
                    'category_id' => $pastries->id,
                    'name' => $name,
                    'cost_price' => $cost,
                    'selling_price' => $price,
                    'stock_quantity' => $stock,
                    'low_stock_threshold' => 5,
                    'track_stock' => true,
                ]
            );
        }
    }
}
