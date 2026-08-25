---
paths:
  - 'app/Http/Controllers/Billing/**'
---

# Billing

## Provider-specific billing callbacks
Keep provider callbacks on explicit provider-specific paths. PayMongo verifies raw-body HMAC before payload processing and resolves ownership only through provider-neutral customer and subscription projections; never add provider guessing or organization commercial-write middleware.
