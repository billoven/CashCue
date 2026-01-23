# CashCue – Management Impact & Data Integrity Rules

**Version:** Draft v1

**Purpose**
This document defines the functional and accounting rules governing INSERT / UPDATE / CANCEL / DELETE actions across CashCue management features. The objective is to guarantee cash integrity, historical consistency, and predictable user experience, in line with standard financial and accounting practices.

---

## 1. Core Principles

### 1.1 Accounting First

CashCue is an **accounting-driven system**, not a CRUD application.

> Any data that has generated a financial impact **must never be physically deleted**.

### 1.2 Immutability of Financial History

Once an operation impacts:

* cash balance
* positions
* performance metrics
* portfolio valuation

…it becomes **historical accounting data** and must remain traceable.

### 1.3 Correction, Not Mutation

Errors are handled by:

* cancellation
* reversal
* corrective entries

Never by in-place modification of financial values.

---

## 2. Entity Classification

| Entity                       | Role                 | Financial Impact | Physical Delete |
| ---------------------------- | -------------------- | ---------------- | --------------- |
| broker_account               | Accounting container | Yes              | ❌ Never         |
| instrument                   | Market reference     | Indirect         | ❌ Never         |
| order_transaction            | Trading event        | Yes              | ❌ Never         |
| dividend                     | Cash income          | Yes              | ❌ Never         |
| cash_transaction             | Accounting journal   | Yes              | ❌ Never         |
| daily_price / realtime_price | Market data          | No direct        | ⚠️ Controlled   |
| portfolio_snapshot           | Historical valuation | Derived          | ❌ Never         |

---

## 3. Standard Lifecycle States

### 3.1 Common Status Pattern

* ACTIVE
* CANCELLED
* CLOSED
* INACTIVE / DELISTED

All financial entities must expose a **status field** and relevant timestamps:

* created_at
* cancelled_at
* closed_at

---

## 4. Operation Semantics

### 4.4 Update Policy – Field-Level Rules

UPDATE operations are intentionally **restricted** and evaluated **field by field**.

Principle:

> UPDATE is allowed only for non-financial attributes. Any UPDATE impacting accounting facts must be implemented as CANCEL + RECREATE.

---

#### 4.4.1 order_transaction (BUY / SELL)

| Field         | Direct UPDATE Allowed | Impact / Rule                                                |
| ------------- | --------------------- | ------------------------------------------------------------ |
| broker_account_id     | ❌                     | CANCEL order + recreate (cash & position impacted)           |
| instrument_id | ❌                     | CANCEL order + recreate                                      |
| order_type    | ❌                     | Immutable                                                    |
| quantity      | ❌                     | CANCEL order + recreate                                      |
| price         | ❌                     | CANCEL order + recreate                                      |
| fees          | ❌                     | CANCEL order + recreate (total_cost changes)                 |
| total_cost    | ❌                     | Generated field – never updated                              |
| trade_date    | ❌                     | CANCEL order + recreate                                      |
| settled       | ⚠️                    | Allowed only for settlement workflow (no cash recalculation) |

Note: Any cancellation of an order must generate a reversal cash_transaction.

---

#### 4.4.2 dividend

| Field          | Direct UPDATE Allowed | Impact / Rule                              |
| -------------- | --------------------- | ------------------------------------------ |
| broker_account_id      | ❌                     | CANCEL dividend + recreate                 |
| instrument_id  | ❌                     | CANCEL dividend + recreate                 |
| amount         | ❌                     | CANCEL dividend + recreate (cash impacted) |
| gross_amount   | ❌                     | CANCEL dividend + recreate                 |
| currency       | ❌                     | CANCEL dividend + recreate                 |
| payment_date   | ❌                     | CANCEL dividend + recreate                 |
| taxes_withheld | ❌                     | CANCEL dividend + recreate                 |
| created_at     | ❌                     | Immutable                                  |

No direct UPDATE is allowed on financial fields of dividends.

---

#### 4.4.3 cash_transaction

cash_transaction entries represent the accounting journal and are **fully immutable**.

| Field | UPDATE Allowed | Rule                                       |
| ----- | -------------- | ------------------------------------------ |
| any   | ❌              | Never updated, only reversed via new entry |

---

#### 4.4.4 broker_account

| Field            | Direct UPDATE Allowed | Impact / Rule                    |
| ---------------- | --------------------- | -------------------------------- |
| name             | ✅                     | Descriptive only                 |
| account_number   | ✅                     | Descriptive only                 |
| account_type     | ❌                     | Accounting container – immutable |
| currency         | ❌                     | Accounting container – immutable |
| has_cash_account | ❌                     | Structural – immutable           |
| created_at       | ❌                     | Immutable                        |

Closing a broker account must be implemented via a status flag (future extension).

---

#### 4.4.5 instrument

| Field      | Direct UPDATE Allowed | Impact / Rule                     |
| ---------- | --------------------- | --------------------------------- |
| symbol     | ❌                     | Market reference – immutable      |
| label      | ✅                     | Descriptive only                  |
| isin       | ❌                     | Market identifier – immutable     |
| type       | ❌                     | Market classification – immutable |
| currency   | ❌                     | Pricing reference – immutable     |
| created_at | ❌                     | Immutable                         |

Instrument lifecycle changes must be handled via status (ACTIVE / INACTIVE / DELISTED).

---

### 4.1 INSERT

INSERT operations are allowed when:

* the target entity is ACTIVE
* referential integrity is preserved

INSERT always means **creating new accounting facts**.

Examples:

* BUY → order_transaction + cash_transaction (negative)
* SELL → order_transaction + cash_transaction (positive)
* DIVIDEND (manual only) → dividend + cash_transaction

UI requirement:

> The user must be informed of the cash and portfolio impact before confirmation.

---

### 4.2 UPDATE

| Update Type                                      | Allowed | Rule                  |
| ------------------------------------------------ | ------- | --------------------- |
| Descriptive fields                               | Yes     | Direct update         |
| Financial fields (amount, quantity, price, date) | No      | Cancel + recreate     |
| Instrument / Broker reassignment                 | No      | Accounting correction |

Rule:

> A financial operation cannot be edited; it must be cancelled and replaced.

---

### 4.3 CANCEL (Functional Delete)

CANCEL is the **standard equivalent of DELETE** for financial entities.

Effects:

* status → CANCELLED
* reversal cash entry generated
* position impact neutralized

Physical DELETE is forbidden.

---

## 5. Detailed Impact Matrix

### 5.1 Orders (BUY / SELL)

| Action      | Order Status | Cash Impact        | Position Impact |
| ----------- | ------------ | ------------------ | --------------- |
| INSERT BUY  | ACTIVE       | -amount            | +quantity       |
| INSERT SELL | ACTIVE       | +amount            | -quantity       |
| CANCEL BUY  | CANCELLED    | +amount (reversal) | -quantity       |
| CANCEL SELL | CANCELLED    | -amount (reversal) | +quantity       |

Note:

* SELL cancellation is symmetrical to BUY cancellation
* reversal entries must reference the cancelled order

---

### 5.2 Dividends (Manual Only)

Dividends are **never automatic** in CashCue.
They are always created via Dividend Management.

| Action          | Dividend Status | Cash Impact        |
| --------------- | --------------- | ------------------ |
| INSERT DIVIDEND | ACTIVE          | +amount            |
| CANCEL DIVIDEND | CANCELLED       | -amount (reversal) |

---

### 5.3 Broker Account Management

| Action | Broker Status | Cash    | Orders    | Reporting |
| ------ | ------------- | ------- | --------- | --------- |
| CREATE | ACTIVE        | Enabled | Enabled   | Yes       |
| CLOSE  | CLOSED        | Frozen  | Read-only | Yes       |

Rules:

* Closing a broker account forbids new operations
* Historical data remains visible and auditable

---

### 5.4 Instrument Management

| Action     | Instrument Status   | Orders    | Dividends | History |
| ---------- | ------------------- | --------- | --------- | ------- |
| CREATE     | ACTIVE              | Allowed   | Allowed   | Yes     |
| DEACTIVATE | INACTIVE / DELISTED | Forbidden | Forbidden | Yes     |

Rules:

* Instruments used in historical operations must never be deleted
* Delisted instruments remain referenceable

---

## 6. User Confirmation Requirements

Strong confirmation is mandatory for:

* cancelling BUY or SELL orders
* cancelling dividends
* closing broker accounts
* deactivating instruments

Standard confirmation message:

> This action will impact cash balance and historical performance. Do you want to continue?

---

## 7. Audit & Traceability (Future Phase)

Recommended additions:

* action_log table
* performed_by
* reason / comment

This enables:

* regulatory-grade audit
* user accountability

---

## 8. Implementation Roadmap

### Phase 1 – Structural Safety

* status fields everywhere
* forbid physical deletes
* enforce cancellation logic

### Phase 2 – Cash Integrity

* systematic reversal entries
* invariant tests on cash balance

### Phase 3 – UX Clarity

* impact previews
* explicit confirmation modals

### Phase 4 – Audit & Compliance

* action logs
* historical reconstruction

---

## 9. Scope Notes

* Dividend automation is **explicitly out of scope**
* All dividends are manually entered
* UPDATE operations are field-restricted and entity-specific
* This document defines behavior, not UI design

---

* Dividend automation is **explicitly out of scope**
* All dividends are manually entered
* This document defines behavior, not UI design

---

**End of Draft v1**
