<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\VisaApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'application_id' => VisaApplication::factory(),
            'stage' => 1,
            'name' => $this->faker->randomElement(['Application Fee', 'Processing Fee', 'Visa Fee']),
            'amount' => $this->faker->numberBetween(10000, 200000),
            'currency' => 'usd',
            'status' => 'pending',
        ];
    }
}
