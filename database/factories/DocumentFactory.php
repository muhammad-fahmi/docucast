<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'file_path' => fake()->filePath(),
            'file_name' => fake()->word() . '.pdf',
            'uploader_id' => 1, // Default, can be overridden with ->for()
            'status' => 'pending',
        ];
    }
}
