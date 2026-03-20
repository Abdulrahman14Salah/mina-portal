<?php

namespace Database\Seeders;

use App\Models\VisaType;
use Illuminate\Database\Seeder;

class VisaTypeSeeder extends Seeder
{
    public function run(): void
    {
        VisaType::firstOrCreate(
            ['name' => 'Tourist Visa'],
            ['description' => 'Short-term tourist visit visa.', 'is_active' => true]
        );

        VisaType::firstOrCreate(
            ['name' => 'Work Permit'],
            ['description' => 'Employment authorization visa.', 'is_active' => true]
        );

        VisaType::firstOrCreate(
            ['name' => 'Family Reunification'],
            ['description' => 'Visa for joining family members abroad.', 'is_active' => true]
        );
    }
}
