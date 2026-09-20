<?php

namespace App\Support\Backup;

final class DnsBackupHostResolver implements ResolvesBackupHosts
{
    public function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if ($records === false) {
            return [];
        }

        $addresses = array_map(
            static fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        );

        return array_values(array_unique(array_filter($addresses)));
    }
}
