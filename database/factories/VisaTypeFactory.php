<?php

namespace Database\Factories;

use App\Models\VisaType;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisaTypeFactory extends Factory
{
    protected $model = VisaType::class;

    public function definition(): array
    {
        return [
            'name' => 'Test Visa ' . $this->faker->unique()->numerify('#####'),
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }
}
