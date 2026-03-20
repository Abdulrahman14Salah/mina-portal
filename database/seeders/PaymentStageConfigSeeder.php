<?php

namespace Database\Seeders;

use App\Models\PaymentStageConfig;
use App\Models\VisaType;
use Illuminate\Database\Seeder;

class PaymentStageConfigSeeder extends Seeder
{
    public function run(): void
    {
        $visaTypes = VisaType::all();

        foreach ($visaTypes as $type) {
            PaymentStageConfig::firstOrCreate(
                ['visa_type_id' => $type->id, 'stage' => 1],
                ['name' => 'Application Fee', 'amount' => 50000, 'currency' => 'usd']
            );

            PaymentStageConfig::firstOrCreate(
                ['visa_type_id' => $type->id, 'stage' => 2],
                ['name' => 'Processing Fee', 'amount' => 100000, 'currency' => 'usd']
            );

            PaymentStageConfig::firstOrCreate(
                ['visa_type_id' => $type->id, 'stage' => 3],
                ['name' => 'Visa Fee', 'amount' => 150000, 'currency' => 'usd']
            );
        }
    }
}
