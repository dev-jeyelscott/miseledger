---
paths:
  - 'app/Actions/Inventory/**'
---

# Inventory

## Preserve product-family containment for saved option values
Inventory items with saved option-value associations may not be moved to another product family or detached from their family unless the associations are reconciled in the same transaction. Validate retained associations at the SaveInventoryItem boundary.
