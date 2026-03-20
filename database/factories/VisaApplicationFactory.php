<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaType;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisaApplicationFactory extends Factory
{
    protected $model = VisaApplication::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'visa_type_id' => VisaType::factory(),
            'status' => 'in_progress',
            'full_name' => $this->faker->name(),
            'email' => $this->faker->email(),
            'phone' => $this->faker->phoneNumber(),
            'nationality' => $this->faker->country(),
            'country_of_residence' => $this->faker->country(),
            'job_title' => $this->faker->jobTitle(),
            'employment_type' => $this->faker->randomElement(['employed', 'self-employed', 'unemployed']),
            'monthly_income' => $this->faker->numberBetween(1000, 10000),
            'adults_count' => $this->faker->numberBetween(0, 3),
            'children_count' => $this->faker->numberBetween(0, 3),
            'application_start_date' => $this->faker->date(),
            'notes' => $this->faker->optional()->text(),
            'agreed_to_terms' => true,
        ];
    }
}
