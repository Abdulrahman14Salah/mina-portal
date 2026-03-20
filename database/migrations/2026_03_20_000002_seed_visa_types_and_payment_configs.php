<?php

use App\Models\PaymentStageConfig;
use App\Models\VisaType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $visaTypes = [
            [
                'name'        => 'Tourist Visa',
                'description' => 'Short-term tourist visit visa.',
            ],
            [
                'name'        => 'Work Permit',
                'description' => 'Employment authorization visa.',
            ],
            [
                'name'        => 'Family Reunification',
                'description' => 'Visa for joining family members abroad.',
            ],
        ];

        foreach ($visaTypes as $data) {
            $type = VisaType::firstOrCreate(
                ['name' => $data['name']],
                ['description' => $data['description'], 'is_active' => true]
            );

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

    public function down(): void
    {
        //
    }
};
