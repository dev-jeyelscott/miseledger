<?php

namespace Database\Factories;

use App\Models\ProblemReport;
use App\Models\ProblemReportAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProblemReportAttachment>
 */
class ProblemReportAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'problem_report_id' => ProblemReport::factory(),
            'disk' => 'local',
            'path' => 'problem-reports/'.rand(1, 1000).'/test-image.jpg',
            'original_name' => 'screenshot.jpg',
            'mime_type' => 'image/jpeg',
            'size' => rand(100000, 5000000),
        ];
    }
}
