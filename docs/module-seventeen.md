# Module 17 — Procurement, Assets & Inventory

**Status:** Installed locally · **Menu:** Operations & Projects → Procurement, assets & inventory

This module maintains an organization-scoped supplier register, purchase request approvals, purchase orders, goods receipts, stock balances and movements, and asset custody, maintenance and disposal records.

## Purchasing workflow

1. Register suppliers and retain due-diligence notes.
2. Submit a purchase request with its business need, department, required date, currency and estimate.
3. A different authorized user approves or rejects the request. The requester cannot approve their own request.
4. Create a purchase order from an approved request and select an active supplier.
5. Receive deliveries against outstanding order quantities. Record partial receipts and discrepancy notes. For stock items, choose the destination location; accepted quantities update the stock balance.

Purchase orders and receipts support multiple lines, partial deliveries and discrepancy notes. Supplier quotation comparison, budget enforcement, invoice generation and external supplier communications are not included.

## Inventory

Create stock items (SKU, unit and reorder level) and active storage locations. Stock is added through receipts or positive adjustments. Transfers require distinct source and destination locations. Adjustments add stock when only a destination is selected and remove stock when only a source is selected. The system rejects a movement that would make a location balance negative. Movement records include timestamp, user, reason and reference.

The overview lists balances and highlights balances at or below the item reorder level. This module does not include barcode scanning or physical stocktaking.

## Asset register

Register equipment with its tag, serial number, acquisition details, cost, location and assignee. Update custody when an employee or location changes. Schedule maintenance and mark completed work; the asset returns to assigned or available status. Submit a disposal request for independent approval. The requester cannot approve their own disposal; approved disposal marks the asset disposed and clears its assignee.

## Access

The `Procurement` role receives the module's permissions. Use the access control screen to grant only the actions each person needs:

- `procurement.view` — view the module overview and records.
- `procurement.suppliers.manage` — register suppliers and record due diligence.
- `procurement.requests.create` / `procurement.requests.approve` — submit requests / decide requests.
- `procurement.orders.manage` — issue purchase orders.
- `procurement.receipts.manage` — record deliveries.
- `procurement.inventory.manage` — maintain stock items, locations and movements.
- `procurement.assets.manage` — register assets, update custody, schedule maintenance and request disposal.
- `procurement.disposals.approve` — independently approve or reject disposal.
- `procurement.reports.view` — reserved for reporting access as reports expand.

Administrator has all module permissions. All records and reference sequences are scoped to the active organization.

## Commissioning boundaries

This is an operational register, not a general ledger or fixed-asset depreciation system. It does not compare supplier quotations, enforce budgets, post invoices to Finance, run stocktakes, scan barcodes, provide a vendor portal or send supplier communications. Set approval thresholds, review role assignments and define physical receiving, custody and stock-count procedures before production use.
