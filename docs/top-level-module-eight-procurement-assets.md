# Top-level Module 8 — Procurement & Assets

**Status:** Local implementation in progress. The module uses organization-scoped records and permission-gated navigation. It does not affect the production server.

## Navigation

Procurement Dashboard, Suppliers, Requisitions, Approvals, RFQs, Comparisons, Purchase Orders, Goods Receipt, Supplier Invoices, Inventory, Asset Register, Assignments, Maintenance, Transfers & Disposal, and Reports.

## Implemented workflows

- Supplier register with contact information, tax number and due-diligence notes.
- Purchase requisitions with justification, needed-by date, estimated amount and an independent approve/reject decision. A requester cannot approve their own requisition.
- Purchase orders generated only from approved requisitions, supporting multiple lines and suppliers.
- Partial and complete goods receipt with discrepancy notes. Receipts for stock items update location balances.
- Inventory item and location setup, controlled adjustments and transfers, and reorder-level visibility.
- Asset registration, current employee/location custody, maintenance scheduling/completion, and independent disposal decisions.
- Organization-scoped purchasing commitment report by currency.

## Workflow boundaries requiring business decisions

The supplied Decision Register marks each feature catalogue as proposed and says to confirm business ownership, required fields, permissions, approval rules, exceptions, reporting/export needs and integrations before build. RFQs, supplier quotation comparisons and supplier invoice matching therefore have navigable pages but remain clearly marked as not enabled until their rules and finance hand-off are agreed. Asset assignments currently show current custody; signed employee acknowledgement and dated assignment history are not implemented. Transfers are currently handled by updating asset location/custody, without a separate approval record. The Reports page currently summarizes committed purchase spend only.

## Access

The Procurement role receives the module permissions. The access control screen can grant least-privilege access using `procurement.view`, `procurement.suppliers.manage`, `procurement.requests.create`, `procurement.requests.approve`, `procurement.orders.manage`, `procurement.receipts.manage`, `procurement.inventory.manage`, `procurement.assets.manage`, `procurement.disposals.approve`, and `procurement.reports.view`.

All lists are scoped to the signed-in user's active organization. Validate schema changes locally before deployment; do not copy local `.env` secrets to production.
