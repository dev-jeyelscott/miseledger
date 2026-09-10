<?php

use App\Models\ProblemReport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Verify that a failed filesystem write cannot leave a persisted problem report.
 */
it('rolls back the report when screenshot storage returns false', function () {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('GD extension is required for this test');
    }

    $user = User::factory()->create();

    Storage::shouldReceive('disk->putFileAs')
        ->once()
        ->andReturn(false);

    $response = $this->actingAs($user)->post('/problem-reports', [
        'description' => 'Screenshot storage failure test.',
        'screenshots' => [
            UploadedFile::fake()->image('screenshot.png'),
        ],
    ]);

    $response->assertServerError();

    expect(
        ProblemReport::where('user_id', $user->id)
            ->where('description', 'Screenshot storage failure test.')
            ->exists()
    )->toBeFalse();
});
