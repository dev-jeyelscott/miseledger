<?php

namespace Database\Seeders;

use App\Actions\Organizations\EnsureDefaultWasteReasons;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class WasteReasonSeeder extends Seeder
{
    /**
     * Seed missing approved waste reasons for every existing organization.
     */
    public function run(
        EnsureDefaultWasteReasons $ensureDefaultWasteReasons,
    ): void {
        Organization::query()
            ->select('id')
            ->eachById(
                static function (
                    Organization $organization,
                ) use ($ensureDefaultWasteReasons): void {
                    $ensureDefaultWasteReasons->handle($organization);
                },
            );
    }
}
