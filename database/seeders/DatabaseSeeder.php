<?php

namespace Database\Seeders;

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Product::firstOrCreate(['slug' => 'rhythm-conductor'], [
            'name' => 'Rhythm Conductor',
            'description' => 'JUCE-based VST3 audio plugin for macOS and Windows.',
            'is_active' => false,
        ]);
    }
}
