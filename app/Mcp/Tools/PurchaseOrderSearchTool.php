<?php

namespace App\Mcp\Tools;

use App\Enums\OrganizationPermission;
use App\Enums\PurchaseOrderStatus;
use App\Mcp\AiMcpExecutionIdentityResolver;
use App\Models\PurchaseOrder;
use App\Support\Billing\FeatureCode;
use App\Support\Purchasing\PurchaseOrderIndexQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('procurement_purchase_orders')]
#[Description('Read purchase-order summaries for the worker-established organization. The date range is capped at 90 days and monetary totals require costs.view permission.')]
#[IsReadOnly]
final class PurchaseOrderSearchTool extends MiseLedgerReadTool
{
    protected function requiredPermission(): OrganizationPermission
    {
        return OrganizationPermission::PurchasingView;
    }

    protected function requiredFeature(): string
    {
        return FeatureCode::Purchasing;
    }

    public function handle(Request $request, AiMcpExecutionIdentityResolver $identityResolver, PurchaseOrderIndexQuery $purchaseOrderIndexQuery): ResponseFactory
    {
        $startedAt = hrtime(true);
        $identity = $this->authorize($identityResolver);
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(PurchaseOrderStatus::class)],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('organization_id', $identity->organization->id)],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('organization_id', $identity->organization->id)],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ]);
        $from = isset($validated['from']) ? (string) $validated['from'] : now()->subDays(self::MAX_DATE_RANGE_DAYS)->toDateString();
        $to = isset($validated['to']) ? (string) $validated['to'] : now()->toDateString();
        if ($from > $to || Carbon::parse($from)->diffInDays(Carbon::parse($to)) > self::MAX_DATE_RANGE_DAYS) {
            throw ValidationException::withMessages(['from' => 'The date range must be ordered and no longer than 90 days.']);
        }
        $limit = (int) ($validated['limit'] ?? self::DEFAULT_LIMIT);
        $canViewCosts = Gate::forUser($identity->user)->allows(OrganizationPermission::CostsView->value, $identity->organization);
        $rows = $purchaseOrderIndexQuery->builder($identity->organization, ['search' => isset($validated['search']) ? trim((string) $validated['search']) ?: null : null, 'status' => isset($validated['status']) ? (string) $validated['status'] : null, 'supplierId' => isset($validated['supplier_id']) ? (int) $validated['supplier_id'] : null, 'locationId' => isset($validated['location_id']) ? (int) $validated['location_id'] : null, 'from' => $from, 'to' => $to])
            ->with(['supplier:id,name', 'location:id,name'])->orderByDesc('order_date')->orderByDesc('id')->limit($limit + 1)->get()
            ->map(static fn (PurchaseOrder $po): array => ['id' => $po->id, 'number' => $po->number, 'status' => $po->status->value, 'order_date' => $po->order_date->toDateString(), 'expected_delivery_date' => $po->expected_delivery_date?->toDateString(), 'supplier' => $po->supplier->name, 'location' => $po->location->name, 'total' => $canViewCosts ? $po->total : null])->all();
        $result = $this->boundedRows($rows, $limit);
        $result['metadata']['from'] = $from;
        $result['metadata']['to'] = $to;
        $this->recordAudit($identity, 'succeeded', $startedAt, ['resource_type' => 'purchase_order', 'row_count' => $result['metadata']['returned_row_count'], 'truncated' => $result['metadata']['truncated'] ? 'true' : 'false']);

        return Response::structured($result);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return ['search' => $schema->string()->max(120)->description('Optional purchase-order number or supplier search.'), 'status' => $schema->string()->enum(PurchaseOrderStatus::class)->description('Optional purchase-order status.'), 'supplier_id' => $schema->integer()->min(1)->description('Organization supplier ID.'), 'location_id' => $schema->integer()->min(1)->description('Organization location ID.'), 'from' => $schema->string()->format('date')->description('Start date. Defaults to 90 days ago.'), 'to' => $schema->string()->format('date')->description('End date. Defaults to today.'), 'limit' => $schema->integer()->min(1)->max(self::MAX_LIMIT)->default(self::DEFAULT_LIMIT)->description('Maximum rows, capped at 1000.')];
    }
}
