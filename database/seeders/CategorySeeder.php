<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Plumbing', 'slug' => 'plumbing', 'description' => 'Leaks, taps, geysers and drains', 'price_min' => 150, 'price_max' => 600],
            ['name' => 'Electrical', 'slug' => 'electrical', 'description' => 'Sockets, lighting and fault finding', 'price_min' => 180, 'price_max' => 700],
            ['name' => 'Cleaning', 'slug' => 'cleaning', 'description' => 'House and office cleaning', 'price_min' => 200, 'price_max' => 650],
            ['name' => 'Pool cleaning', 'slug' => 'pool-cleaning', 'description' => 'Pool care and chemical balance', 'price_min' => 300, 'price_max' => 900],
            ['name' => 'Carpentry', 'slug' => 'carpentry', 'description' => 'Doors, cupboards and furniture', 'price_min' => 250, 'price_max' => 1500],
            ['name' => 'Event planning', 'slug' => 'event-planning', 'description' => 'Parties, weddings and setup', 'price_min' => 1500, 'price_max' => 8000],
            ['name' => 'Skilled student', 'slug' => 'student-help', 'description' => 'Tutoring and light assembly', 'price_min' => 80, 'price_max' => 250],
        ];

        foreach ($categories as $category) {
            Category::query()->updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
