<?php

namespace App\Actions\Ai;

use App\Enums\AiProvider;
use App\Models\AiProviderConnection;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class ActivateAiProviderConnection
{
    /**
     * Activate one provider connection for a user, deactivating any prior
     * active connection in the same transaction. OAuth credentials are never
     * accepted or persisted by this boundary.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        User $user,
        AiProvider $provider,
        ?string $externalAccountId = null,
        ?string $accountLabel = null,
        array $metadata = [],
    ): AiProviderConnection {
        return DB::transaction(function () use (
            $user,
            $provider,
            $externalAccountId,
            $accountLabel,
            $metadata,
        ): AiProviderConnection {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedUser->aiProviderConnections()
                ->active()
                ->lockForUpdate()
                ->update([
                    'is_active' => false,
                    'deactivated_at' => now(),
                ]);

            $connection = $lockedUser->aiProviderConnections()
                ->where('provider', $provider)
                ->lockForUpdate()
                ->first() ?? new AiProviderConnection([
                    'provider' => $provider,
                ]);

            $connection->fill([
                'external_account_id' => $externalAccountId,
                'account_label' => $accountLabel,
                'metadata' => $this->safeMetadata($metadata),
                'is_active' => true,
                'activated_at' => now(),
                'deactivated_at' => null,
            ]);

            $lockedUser->aiProviderConnections()->save($connection);

            return $connection;
        });
    }

    /**
     * Keep only non-credential provider-account descriptors. This action is
     * intentionally not a generic provider metadata store.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, string|array<int, string>>
     */
    private function safeMetadata(array $metadata): array
    {
        $safeMetadata = Arr::only($metadata, [
            'account_type',
            'workspace_name',
            'scopes',
        ]);

        return array_filter(
            $safeMetadata,
            static fn (mixed $value): bool => is_string($value)
                || (is_array($value)
                    && array_is_list($value)
                    && array_all(
                        $value,
                        static fn (mixed $scope): bool => is_string($scope),
                    )),
        );
    }
}
