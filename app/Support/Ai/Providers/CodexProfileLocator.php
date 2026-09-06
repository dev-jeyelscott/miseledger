<?php

namespace App\Support\Ai\Providers;

use App\Models\User;
use Illuminate\Filesystem\Filesystem;

final class CodexProfileLocator
{
    public function __construct(private readonly Filesystem $files) {}

    public function reference(User $user): string
    {
        return hash_hmac('sha256', 'codex-profile:'.$user->getKey(), (string) config('app.key'));
    }

    public function path(User $user): string
    {
        $path = rtrim((string) config('ai.codex.profile_root'), '/').'/'.$this->reference($user);

        $this->files->ensureDirectoryExists($path, 0700, true);

        return $path;
    }
}
