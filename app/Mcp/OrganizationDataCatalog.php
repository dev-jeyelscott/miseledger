<?php

namespace App\Mcp;

use App\Enums\OrganizationPermission;
use App\Models\AuditLog;
use App\Models\BillingSubscription;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\RecipeVersionComponent;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\SupplierItemPrice;
use App\Models\WasteRecord;
use App\Support\Billing\FeatureCode;

/**
 * The complete public query vocabulary. Keys are business-facing aliases;
 * persistence identifiers, organization IDs, and arbitrary relations never
 * leave this catalog.
 */
final class OrganizationDataCatalog
{
    /** @return array<string, array<string, mixed>> */
    public function resources(): array
    {
        return [
            'organization_profile' => $this->direct(Organization::class, ['id' => 'id', 'name' => 'name', 'slug' => 'slug', 'timezone' => 'timezone', 'currency' => 'currency'], [OrganizationPermission::ReportsView], scope: 'organization'),
            'inventory_items' => $this->direct(InventoryItem::class, ['id' => 'id', 'name' => 'name', 'sku' => 'sku', 'description' => 'description', 'type' => 'type', 'active' => 'active', 'yield_percentage' => 'yield_percentage'], [OrganizationPermission::InventoryView], relations: ['category' => ['relation' => 'inventoryCategory', 'fields' => ['id' => 'id', 'name' => 'name']], 'brand' => ['relation' => 'inventoryBrand', 'fields' => ['id' => 'id', 'name' => 'name']], 'base_unit' => ['relation' => 'baseUnitOfMeasure', 'fields' => ['id' => 'id', 'name' => 'name', 'symbol' => 'symbol']]]),
            'locations' => $this->direct(Location::class, ['id' => 'id', 'name' => 'name', 'code' => 'code', 'active' => 'active'], [OrganizationPermission::InventoryView]),
            'storage_locations' => $this->direct(StorageLocation::class, ['id' => 'id', 'name' => 'name', 'code' => 'code', 'active' => 'active'], [OrganizationPermission::InventoryView], relations: ['location' => ['relation' => 'location', 'fields' => ['id' => 'id', 'name' => 'name', 'code' => 'code']]]),
            'stock_balances' => $this->direct(StockBalance::class, ['id' => 'id', 'quantity_on_hand' => 'quantity_on_hand', 'last_movement_at' => 'last_movement_at', 'average_unit_cost' => ['column' => 'average_unit_cost', 'cost' => true], 'inventory_value' => ['column' => 'inventory_value', 'cost' => true]], [OrganizationPermission::ReportsView], relations: ['item' => ['relation' => 'inventoryItem', 'fields' => ['id' => 'id', 'name' => 'name', 'sku' => 'sku']], 'location' => ['relation' => 'location', 'fields' => ['id' => 'id', 'name' => 'name']], 'storage_location' => ['relation' => 'storageLocation', 'fields' => ['id' => 'id', 'name' => 'name']]]),
            'stock_movements' => $this->direct(StockMovement::class, ['id' => 'id', 'type' => 'type', 'quantity' => 'quantity', 'occurred_at' => 'occurred_at', 'reference_type' => 'reference_type', 'reference_id' => 'reference_id', 'notes' => 'notes', 'unit_cost' => ['column' => 'unit_cost', 'cost' => true], 'total_cost' => ['column' => 'total_cost', 'cost' => true]], [OrganizationPermission::ReportsView], relations: ['item' => ['relation' => 'inventoryItem', 'fields' => ['id' => 'id', 'name' => 'name', 'sku' => 'sku']], 'location' => ['relation' => 'location', 'fields' => ['id' => 'id', 'name' => 'name']]]),
            'suppliers' => $this->direct(Supplier::class, ['id' => 'id', 'name' => 'name', 'code' => 'code', 'contact_name' => 'contact_name', 'email' => 'email', 'phone' => 'phone', 'payment_terms' => 'payment_terms', 'lead_time_days' => 'lead_time_days', 'active' => 'active'], [OrganizationPermission::PurchasingView], FeatureCode::Purchasing),
            'supplier_items' => $this->direct(SupplierItem::class, ['id' => 'id', 'supplier_sku' => 'supplier_sku', 'active' => 'active'], [OrganizationPermission::PurchasingView], FeatureCode::Purchasing, scope: 'organization', relations: ['supplier' => ['relation' => 'supplier', 'fields' => ['id' => 'id', 'name' => 'name', 'code' => 'code']], 'item' => ['relation' => 'inventoryItem', 'fields' => ['id' => 'id', 'name' => 'name', 'sku' => 'sku']]]),
            'supplier_prices' => $this->direct(SupplierItemPrice::class, ['id' => 'id', 'effective_at' => 'effective_at', 'price' => ['column' => 'price', 'cost' => true]], [OrganizationPermission::PurchasingView], FeatureCode::Purchasing),
            'purchase_orders' => $this->direct(PurchaseOrder::class, ['id' => 'id', 'number' => 'number', 'status' => 'status', 'order_date' => 'order_date', 'expected_delivery_date' => 'expected_delivery_date', 'notes' => 'notes', 'subtotal' => ['column' => 'subtotal', 'cost' => true], 'tax_total' => ['column' => 'tax_total', 'cost' => true], 'discount_total' => ['column' => 'discount_total', 'cost' => true], 'total' => ['column' => 'total', 'cost' => true]], [OrganizationPermission::PurchasingView], FeatureCode::Purchasing, relations: ['supplier' => ['relation' => 'supplier', 'fields' => ['id' => 'id', 'name' => 'name']], 'location' => ['relation' => 'location', 'fields' => ['id' => 'id', 'name' => 'name']]]),
            'purchase_order_lines' => $this->parent(PurchaseOrderLine::class, 'purchaseOrder', ['id' => 'id', 'ordered_quantity' => 'ordered_quantity', 'received_base_quantity' => 'received_base_quantity', 'unit_price' => ['column' => 'unit_price', 'cost' => true], 'line_total' => ['column' => 'line_total', 'cost' => true]], [OrganizationPermission::PurchasingView], FeatureCode::Purchasing),
            'goods_receipts' => $this->direct(GoodsReceipt::class, ['id' => 'id', 'number' => 'number', 'status' => 'status', 'received_at' => 'received_at', 'supplier_reference' => 'supplier_reference', 'notes' => 'notes'], [OrganizationPermission::PurchasingView], FeatureCode::Purchasing),
            'goods_receipt_lines' => $this->parent(GoodsReceiptLine::class, 'goodsReceipt', ['id' => 'id', 'received_quantity' => 'received_quantity', 'base_quantity' => 'base_quantity', 'notes' => 'notes', 'unit_cost' => ['column' => 'unit_cost', 'cost' => true], 'total_cost' => ['column' => 'total_cost', 'cost' => true]], [OrganizationPermission::PurchasingView], FeatureCode::Purchasing),
            'stock_counts' => $this->direct(StockCount::class, ['id' => 'id', 'number' => 'number', 'status' => 'status', 'counted_at' => 'counted_at', 'finalized_at' => 'finalized_at'], [OrganizationPermission::CountsCreate, OrganizationPermission::ReportsView]),
            'stock_transfers' => $this->direct(StockTransfer::class, ['id' => 'id', 'number' => 'number', 'status' => 'status', 'requested_at' => 'requested_at', 'shipped_at' => 'shipped_at', 'received_at' => 'received_at', 'notes' => 'notes'], [OrganizationPermission::TransfersCreate, OrganizationPermission::ReportsView]),
            'waste_records' => $this->direct(WasteRecord::class, ['id' => 'id', 'quantity' => 'quantity', 'base_quantity' => 'base_quantity', 'occurred_at' => 'occurred_at', 'notes' => 'notes', 'unit_cost' => ['column' => 'unit_cost', 'cost' => true], 'total_cost' => ['column' => 'total_cost', 'cost' => true]], [OrganizationPermission::WasteRecord, OrganizationPermission::ReportsView]),
            'recipes' => $this->direct(Recipe::class, ['id' => 'id', 'code' => 'code', 'name' => 'name', 'type' => 'type', 'active' => 'active'], [OrganizationPermission::RecipesView], FeatureCode::Recipes),
            'recipe_versions' => $this->parent(RecipeVersion::class, 'recipe', ['id' => 'id', 'version_number' => 'version_number', 'status' => 'status', 'yield_quantity' => 'yield_quantity', 'published_at' => 'published_at', 'effective_start_date' => 'effective_start_date', 'effective_end_date' => 'effective_end_date', 'notes' => 'notes'], [OrganizationPermission::RecipesView], FeatureCode::Recipes),
            'recipe_components' => $this->parent(RecipeVersionComponent::class, 'recipeVersion.recipe', ['id' => 'id', 'quantity' => 'quantity', 'base_quantity' => 'base_quantity', 'yield_percentage' => 'yield_percentage', 'notes' => 'notes'], [OrganizationPermission::RecipesView], FeatureCode::Recipes),
            'members' => $this->direct(OrganizationMembership::class, ['id' => 'id', 'role' => 'role', 'ai_enabled' => 'ai_enabled', 'created_at' => 'created_at'], [OrganizationPermission::UsersManage], relations: ['user' => ['relation' => 'user', 'fields' => ['id' => 'id', 'name' => 'name', 'email' => 'email']]]),
            'audit_metadata' => $this->direct(AuditLog::class, ['id' => 'id', 'action' => 'action', 'entity_type' => 'entity_type', 'entity_id' => 'entity_id', 'created_at' => 'created_at'], [OrganizationPermission::OrganizationManage]),
            'billing_lifecycle' => $this->direct(BillingSubscription::class, ['id' => 'id', 'plan_code' => 'plan_code', 'interval' => 'interval', 'provider_status' => 'provider_status', 'trial_ends_at' => 'trial_ends_at', 'current_period_ends_at' => 'current_period_ends_at', 'next_billing_at' => 'next_billing_at', 'ends_at' => 'ends_at', 'cancelled_at' => 'cancelled_at'], [OrganizationPermission::BillingManage]),
        ];
    }

    /**
     * @param  array<string, string|array{column:string,cost?:bool}>  $fields
     * @param  list<OrganizationPermission>  $permissions
     * @param  array<string, array<string, mixed>>  $relations
     * @return array<string, mixed>
     */
    private function direct(string $model, array $fields, array $permissions, ?string $feature = null, string $scope = 'direct', array $relations = []): array
    {
        return compact('model', 'fields', 'permissions', 'feature', 'scope', 'relations');
    }

    /**
     * @param  array<string, string|array{column:string,cost?:bool}>  $fields
     * @param  list<OrganizationPermission>  $permissions
     * @return array<string, mixed>
     */
    private function parent(string $model, string $parent, array $fields, array $permissions, ?string $feature = null): array
    {
        return $this->direct($model, $fields, $permissions, $feature, 'parent:'.$parent);
    }
}
