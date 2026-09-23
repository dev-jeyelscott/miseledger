<?php

namespace App\Support\Mobile;

/**
 * One aggregated unit of operational work surfaced on the mobile Home and
 * Tasks screens (Spec 7). Always derived live from a source model; never
 * persisted.
 */
final readonly class MobileTask
{
    /**
     * @param  'receive'|'count'|'ship'|'receive_transfer'|'restock'  $type
     * @param  'overdue'|'in_progress'|'ready'|'attention'  $urgency
     */
    public function __construct(
        public string $type,
        public string $urgency,
        public string $title,
        public string $subtitle,
        public string $href,
        public string $createdAt,
    ) {}

    /**
     * @return array{type: string, urgency: string, title: string, subtitle: string, href: string, createdAt: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'urgency' => $this->urgency,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'href' => $this->href,
            'createdAt' => $this->createdAt,
        ];
    }
}
