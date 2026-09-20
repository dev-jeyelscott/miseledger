<?php

namespace App\Support\Backup;

interface ResolvesBackupHosts
{
    /**
     * Resolve every A/AAAA address for a hostname. A resolution failure
     * (NXDOMAIN, no records, or a DNS error) is indistinguishable from "no
     * address" to the caller, forcing callers to fail closed rather than
     * treat an unresolvable host as implicitly safe.
     *
     * @return list<string>
     */
    public function resolve(string $host): array;
}
