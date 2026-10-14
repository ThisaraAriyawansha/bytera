<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\MainCategory;
use App\Models\Product;
use App\Models\SubCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Main categories of a computer and mobile shop, each with its sub categories.
     *
     * @var array<string, list<string>>
     */
    private const CATEGORIES = [
        'Mobile Phones' => ['Smartphones', 'Feature Phones'],
        'Tablets' => ['Android Tablets', 'iPads'],
        'Laptops' => ['Everyday Laptops', 'Business Laptops', 'Gaming Laptops'],
        'Desktops & Monitors' => ['Monitors', 'Desktop PCs'],
        'Computer Components' => ['SSDs & Hard Drives', 'RAM', 'Power Supplies'],
        'Peripherals' => ['Keyboards & Mice', 'Headsets & Speakers', 'Webcams'],
        'Networking' => ['Routers', 'Switches & Adapters'],
        'Printers & Ink' => ['Printers', 'Ink & Toner'],
        'Mobile Accessories' => ['Chargers & Cables', 'Power Banks', 'Cases & Screen Protectors', 'Earphones', 'Memory Cards & Pen Drives'],
        'Spare Parts' => ['Phone Displays', 'Batteries', 'Laptop Parts'],
    ];

    /**
     * Brands stocked by the shop.
     *
     * @var list<string>
     */
    private const BRANDS = [
        'Samsung', 'Apple', 'Xiaomi', 'Oppo', 'Vivo', 'Nokia', 'HP', 'Dell', 'Lenovo', 'ASUS', 'Acer', 'MSI',
        'Kingston', 'Western Digital', 'Seagate', 'SanDisk', 'Corsair', 'Logitech', 'A4Tech', 'JBL', 'TP-Link',
        'Tenda', 'Canon', 'Epson', 'Anker', 'Baseus', 'Generic',
    ];

    /**
     * Products as [name, brand, main category, sub category, SKU, selling price, warranty months, track serial, low stock alert].
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: int, 6: int, 7: bool, 8: int}>
     */
    private const PRODUCTS = [
        // Serial / IMEI tracked
        ['Samsung Galaxy A55 5G 8GB/256GB', 'Samsung', 'Mobile Phones', 'Smartphones', 'PH-SAM-A55-256', 139900, 12, true, 2],
        ['Samsung Galaxy A15 6GB/128GB', 'Samsung', 'Mobile Phones', 'Smartphones', 'PH-SAM-A15-128', 54900, 12, true, 3],
        ['Samsung Galaxy S24 8GB/256GB', 'Samsung', 'Mobile Phones', 'Smartphones', 'PH-SAM-S24-256', 289900, 12, true, 1],
        ['Apple iPhone 15 128GB', 'Apple', 'Mobile Phones', 'Smartphones', 'PH-APL-IP15-128', 289000, 12, true, 1],
        ['Apple iPhone 13 128GB', 'Apple', 'Mobile Phones', 'Smartphones', 'PH-APL-IP13-128', 199000, 12, true, 1],
        ['Xiaomi Redmi Note 13 8GB/256GB', 'Xiaomi', 'Mobile Phones', 'Smartphones', 'PH-XIA-RN13-256', 72900, 12, true, 3],
        ['Oppo A79 5G 8GB/256GB', 'Oppo', 'Mobile Phones', 'Smartphones', 'PH-OPP-A79-256', 79900, 12, true, 2],
        ['Vivo Y28 8GB/128GB', 'Vivo', 'Mobile Phones', 'Smartphones', 'PH-VIV-Y28-128', 56900, 12, true, 2],
        ['Nokia 105 (2023) Dual SIM', 'Nokia', 'Mobile Phones', 'Feature Phones', 'PH-NOK-105', 6900, 6, true, 5],
        ['Samsung Galaxy Tab A9+ Wi-Fi 64GB', 'Samsung', 'Tablets', 'Android Tablets', 'TB-SAM-A9P-64', 84900, 12, true, 1],
        ['Apple iPad 10th Gen Wi-Fi 64GB', 'Apple', 'Tablets', 'iPads', 'TB-APL-IPAD10-64', 159000, 12, true, 1],
        ['HP 15 Core i5 13th Gen 8GB/512GB', 'HP', 'Laptops', 'Everyday Laptops', 'LP-HP-15-I5', 219900, 24, true, 2],
        ['Lenovo IdeaPad Slim 3 Ryzen 5 8GB/512GB', 'Lenovo', 'Laptops', 'Everyday Laptops', 'LP-LEN-IPS3-R5', 199900, 24, true, 2],
        ['ASUS Vivobook 15 Core i3 8GB/512GB', 'ASUS', 'Laptops', 'Everyday Laptops', 'LP-ASU-VB15-I3', 159900, 24, true, 2],
        ['Dell Latitude 3540 Core i5 16GB/512GB', 'Dell', 'Laptops', 'Business Laptops', 'LP-DEL-LAT3540-I5', 289900, 36, true, 1],
        ['Acer Aspire 7 Core i5 RTX 2050 16GB/512GB', 'Acer', 'Laptops', 'Gaming Laptops', 'LP-ACE-ASP7-I5', 309900, 24, true, 1],
        ['MSI Thin GF63 Core i7 RTX 4050 16GB/512GB', 'MSI', 'Laptops', 'Gaming Laptops', 'LP-MSI-GF63-I7', 429900, 24, true, 1],
        ['Samsung 24" FHD IPS Monitor', 'Samsung', 'Desktops & Monitors', 'Monitors', 'MN-SAM-24FHD', 42900, 36, true, 2],
        ['Dell 27" P2723 QHD Monitor', 'Dell', 'Desktops & Monitors', 'Monitors', 'MN-DEL-P2723', 119900, 36, true, 1],
        ['HP Pro Tower 290 G9 Core i5 8GB/512GB', 'HP', 'Desktops & Monitors', 'Desktop PCs', 'PC-HP-290G9-I5', 199900, 36, true, 1],
        ['Samsung 870 EVO 500GB SATA SSD', 'Samsung', 'Computer Components', 'SSDs & Hard Drives', 'CP-SAM-870EVO-500', 22900, 60, true, 3],
        ['WD Blue SN580 1TB NVMe SSD', 'Western Digital', 'Computer Components', 'SSDs & Hard Drives', 'CP-WD-SN580-1TB', 28900, 60, true, 3],
        ['Seagate Barracuda 1TB HDD', 'Seagate', 'Computer Components', 'SSDs & Hard Drives', 'CP-SEA-BAR-1TB', 16900, 24, true, 3],
        ['Corsair CV550 550W Power Supply', 'Corsair', 'Computer Components', 'Power Supplies', 'CP-COR-CV550', 21900, 36, true, 2],
        ['TP-Link Archer C6 AC1200 Router', 'TP-Link', 'Networking', 'Routers', 'NW-TPL-C6', 13900, 36, true, 3],
        ['Canon PIXMA G3010 Ink Tank Printer', 'Canon', 'Printers & Ink', 'Printers', 'PR-CAN-G3010', 56900, 12, true, 2],
        ['Epson EcoTank L3250 Wi-Fi Printer', 'Epson', 'Printers & Ink', 'Printers', 'PR-EPS-L3250', 59900, 12, true, 2],

        // Quantity only (no serial tracking)
        ['Kingston 8GB DDR4 3200MHz Laptop RAM', 'Kingston', 'Computer Components', 'RAM', 'CP-KIN-8GB-D4-SO', 7900, 36, false, 5],
        ['Kingston 16GB DDR4 3200MHz Desktop RAM', 'Kingston', 'Computer Components', 'RAM', 'CP-KIN-16GB-D4-DT', 13900, 36, false, 5],
        ['Logitech MK270 Wireless Keyboard & Mouse', 'Logitech', 'Peripherals', 'Keyboards & Mice', 'PE-LOG-MK270', 8900, 12, false, 5],
        ['Logitech M185 Wireless Mouse', 'Logitech', 'Peripherals', 'Keyboards & Mice', 'PE-LOG-M185', 3900, 12, false, 10],
        ['A4Tech KR-85 USB Keyboard', 'A4Tech', 'Peripherals', 'Keyboards & Mice', 'PE-A4T-KR85', 2400, 6, false, 10],
        ['A4Tech OP-720 USB Mouse', 'A4Tech', 'Peripherals', 'Keyboards & Mice', 'PE-A4T-OP720', 950, 6, false, 15],
        ['Logitech H390 USB Headset', 'Logitech', 'Peripherals', 'Headsets & Speakers', 'PE-LOG-H390', 9900, 12, false, 3],
        ['JBL Go 3 Bluetooth Speaker', 'JBL', 'Peripherals', 'Headsets & Speakers', 'PE-JBL-GO3', 12900, 12, false, 3],
        ['Logitech C270 HD Webcam', 'Logitech', 'Peripherals', 'Webcams', 'PE-LOG-C270', 8500, 12, false, 3],
        ['TP-Link TL-SF1008D 8-Port Switch', 'TP-Link', 'Networking', 'Switches & Adapters', 'NW-TPL-SF1008D', 4900, 24, false, 3],
        ['TP-Link TL-WN725N USB Wi-Fi Adapter', 'TP-Link', 'Networking', 'Switches & Adapters', 'NW-TPL-WN725N', 2900, 12, false, 5],
        ['Tenda Cat6 Network Cable 3m', 'Tenda', 'Networking', 'Switches & Adapters', 'NW-TEN-CAT6-3M', 650, 0, false, 20],
        ['Canon GI-790 Black Ink Bottle', 'Canon', 'Printers & Ink', 'Ink & Toner', 'PR-CAN-GI790-BK', 2600, 0, false, 10],
        ['Epson 003 Black Ink Bottle', 'Epson', 'Printers & Ink', 'Ink & Toner', 'PR-EPS-003-BK', 2700, 0, false, 10],
        ['Samsung 25W USB-C Fast Charger', 'Samsung', 'Mobile Accessories', 'Chargers & Cables', 'MA-SAM-25W', 5900, 6, false, 10],
        ['Apple 20W USB-C Power Adapter', 'Apple', 'Mobile Accessories', 'Chargers & Cables', 'MA-APL-20W', 7900, 6, false, 5],
        ['Anker USB-C to Lightning Cable 1m', 'Anker', 'Mobile Accessories', 'Chargers & Cables', 'MA-ANK-C2L-1M', 3900, 6, false, 10],
        ['Baseus USB-C to USB-C 60W Cable 1m', 'Baseus', 'Mobile Accessories', 'Chargers & Cables', 'MA-BAS-C2C-1M', 1900, 3, false, 15],
        ['Anker PowerCore 10000mAh Power Bank', 'Anker', 'Mobile Accessories', 'Power Banks', 'MA-ANK-PC10K', 8900, 12, false, 5],
        ['Baseus 20000mAh 22.5W Power Bank', 'Baseus', 'Mobile Accessories', 'Power Banks', 'MA-BAS-PB20K', 9900, 12, false, 5],
        ['Tempered Glass Screen Protector', 'Generic', 'Mobile Accessories', 'Cases & Screen Protectors', 'MA-GEN-TGLASS', 800, 0, false, 30],
        ['Silicone Back Cover', 'Generic', 'Mobile Accessories', 'Cases & Screen Protectors', 'MA-GEN-BCOVER', 1200, 0, false, 30],
        ['Xiaomi Redmi Buds 4 Lite', 'Xiaomi', 'Mobile Accessories', 'Earphones', 'MA-XIA-BUDS4L', 6900, 6, false, 5],
        ['Samsung Wired Earphones Type-C', 'Samsung', 'Mobile Accessories', 'Earphones', 'MA-SAM-EAR-TC', 2900, 3, false, 10],
        ['SanDisk Ultra 64GB microSD Card', 'SanDisk', 'Mobile Accessories', 'Memory Cards & Pen Drives', 'MA-SAN-MSD64', 2900, 12, false, 10],
        ['SanDisk Ultra Flair 64GB USB 3.0 Pen Drive', 'SanDisk', 'Mobile Accessories', 'Memory Cards & Pen Drives', 'MA-SAN-FLAIR64', 2700, 12, false, 10],
        ['Kingston DataTraveler 32GB Pen Drive', 'Kingston', 'Mobile Accessories', 'Memory Cards & Pen Drives', 'MA-KIN-DT32', 1600, 12, false, 10],
        ['Samsung Galaxy A15 Display Assembly', 'Generic', 'Spare Parts', 'Phone Displays', 'SP-DSP-SAM-A15', 9500, 1, false, 2],
        ['Apple iPhone 11 Display (Incell)', 'Generic', 'Spare Parts', 'Phone Displays', 'SP-DSP-IP11', 12500, 1, false, 2],
        ['Redmi Note 12 Display Assembly', 'Generic', 'Spare Parts', 'Phone Displays', 'SP-DSP-RN12', 10500, 1, false, 2],
        ['Apple iPhone 11 Battery', 'Generic', 'Spare Parts', 'Batteries', 'SP-BAT-IP11', 5500, 3, false, 3],
        ['Samsung Galaxy A-Series Battery', 'Generic', 'Spare Parts', 'Batteries', 'SP-BAT-SAM-A', 4500, 3, false, 3],
        ['HP 15 Laptop Battery (HT03XL)', 'Generic', 'Spare Parts', 'Batteries', 'SP-BAT-HP-HT03XL', 14500, 6, false, 2],
        ['15.6" FHD Laptop Screen 30-Pin', 'Generic', 'Spare Parts', 'Laptop Parts', 'SP-LAP-SCR156', 21500, 3, false, 2],
        ['Laptop Keyboard (HP 15 Series)', 'Generic', 'Spare Parts', 'Laptop Parts', 'SP-LAP-KB-HP15', 6500, 3, false, 2],
        ['65W Universal Laptop Charger', 'Generic', 'Spare Parts', 'Laptop Parts', 'SP-LAP-CHG65', 5500, 6, false, 5],
        ['Arctic MX-4 Thermal Paste 4g', 'Generic', 'Spare Parts', 'Laptop Parts', 'SP-LAP-MX4', 2500, 0, false, 5],
    ];

    /**
     * Seed the shop's categories, brands and products. Products start with no stock; receive stock through a GRN.
     */
    public function run(): void
    {
        $subCategories = [];

        foreach (self::CATEGORIES as $mainCategoryName => $subCategoryNames) {
            $mainCategory = MainCategory::query()->firstOrCreate(['name' => $mainCategoryName]);

            foreach ($subCategoryNames as $subCategoryName) {
                $subCategories[$mainCategoryName][$subCategoryName] = SubCategory::query()->firstOrCreate([
                    'name' => $subCategoryName,
                    'main_category_id' => $mainCategory->id,
                ]);
            }
        }

        $brands = collect(self::BRANDS)
            ->mapWithKeys(fn (string $name): array => [$name => Brand::query()->firstOrCreate(['name' => $name])]);

        foreach (self::PRODUCTS as [$name, $brand, $mainCategory, $subCategory, $sku, $sellingPrice, $warrantyMonths, $tracksSerial, $lowStockAlert]) {
            $productSubCategory = $subCategories[$mainCategory][$subCategory];

            Product::query()->firstOrCreate(['sku' => $sku], [
                'name' => $name,
                'brand_id' => $brands[$brand]->id,
                'main_category_id' => $productSubCategory->main_category_id,
                'sub_category_id' => $productSubCategory->id,
                'selling_price' => $sellingPrice,
                'total_stock' => 0,
                'stores_stock' => 0,
                'showroom_stock' => 0,
                'low_stock_alert' => $lowStockAlert,
                'warranty_months' => $warrantyMonths,
                'track_serial' => $tracksSerial,
                'active' => true,
            ]);
        }
    }
}
