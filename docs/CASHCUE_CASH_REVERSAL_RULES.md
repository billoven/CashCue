# CashCue – Cash Reversal Rules

**Version:** Draft v1

**Purpose**
This document defines the authoritative rules for generating, reversing, and validating `cash_transaction` entries resulting from management actions on orders and dividends in CashCue.

It complements **CASHCUE_ACCOUNTING_AND_MANAGEMENT_RULES.md** and focuses exclusively on cash integrity.

---

## 1. Core Principles

### 1.1 Cash Is an Accounting Journal

The `cash_transaction` table represents an **append-only accounting journal**.

Rules:

* No UPDATE
* No DELETE
* No status field
* Corrections are performed exclusively via **reversal entries**

---

### 1.2 One Business Event → Deterministic Cash Effects

Each financial business event (order, dividend, cancellation) must generate a **deterministic and reproducible set of cash transactions**.

At any time:

> **Cash balance = initial balance + SUM(cash_transaction.amount)**

---

## 2. Cash Transaction Typology

### 2.1 Supported Types

`cash_transaction.type` ENUM:

* BUY
* SELL
* DIVIDEND
* DEPOSIT
* WITHDRAWAL
* FEES
* ADJUSTMENT

This document covers **BUY, SELL, DIVIDEND** only.

---

## 3. Order → Cash Mapping

### 3.1 BUY Order (ACTIVE)

**Trigger:** INSERT order_transaction (order_type = BUY)

| Attribute             | Value                      |
| --------------------- | -------------------------- |
| cash_transaction.type | BUY                        |
| amount                | -(quantity × price + fees) |
| broker_account_id     | order.broker_account_id    |
| date                  | order.trade_date           |
| reference_id          | order.id                   |

Result:

* Cash decreases
* Position increases

---

### 3.2 SELL Order (ACTIVE)

**Trigger:** INSERT order_transaction (order_type = SELL)

| Attribute             | Value                      |
| --------------------- | -------------------------- |
| cash_transaction.type | SELL                       |
| amount                | +(quantity × price − fees) |
| broker_account_id     | order.broker_account_id    |
| date                  | order.trade_date           |
| reference_id          | order.id                   |

Result:

* Cash increases
* Position decreases

---

## 4. Order Cancellation → Cash Reversal

### 4.1 CANCEL BUY Order

**Trigger:** UPDATE order_transaction.status = CANCELLED

Generated cash entry:

| Attribute         | Value                       |
| ----------------- | --------------------------- |
| type              | BUY                         |
| amount            | +(original BUY cash amount) |
| broker_account_id | order.broker_account_id     |
| date              | cancellation datetime       |
| reference_id      | order.id                    |
| comment           | Reversal of BUY order #id   |

Result:

* Cash restored
* Position neutralized

---

### 4.2 CANCEL SELL Order

**Trigger:** UPDATE order_transaction.status = CANCELLED

Generated cash entry:

| Attribute         | Value                        |
| ----------------- | ---------------------------- |
| type              | SELL                         |
| amount            | −(original SELL cash amount) |
| broker_account_id | order.broker_account_id      |
| date              | cancellation datetime        |
| reference_id      | order.id                     |
| comment           | Reversal of SELL order #id   |

Result:

* Cash reverted
* Position restored

---

## 5. Dividend → Cash Mapping

### 5.1 INSERT Dividend (ACTIVE)

**Trigger:** INSERT dividend

| Attribute             | Value                     |
| --------------------- | ------------------------- |
| cash_transaction.type | DIVIDEND                  |
| amount                | +dividend.amount          |
| broker_account_id     | dividend.broker_account_id|
| date                  | dividend.payment_date     |
| reference_id          | dividend.id               |

Result:

* Cash increases
* No position change

---

## 6. Dividend Cancellation → Cash Reversal

### 6.1 CANCEL Dividend

**Trigger:** UPDATE dividend.status = CANCELLED

Generated cash entry:

| Attribute         | Value                       |
| ----------------- | --------------------------- |
| type              | DIVIDEND                    |
| amount            | −(original dividend.amount) |
| broker_account_id | dividend.broker_account_id  |
| date              | cancellation datetime       |
| reference_id      | dividend.id                 |
| comment           | Reversal of dividend #id    |

Result:

* Cash reverted

---

## 7. Guards & Invariants

### 7.1 Single-Source Rule

* Each order or dividend must have **exactly one ACTIVE cash_transaction** linked via `reference_id`
* Each cancellation must generate **exactly one reversal entry**

---

### 7.2 Cash Integrity Invariant

For any broker_account:

```
computed_balance = SUM(cash_transaction.amount)
```

This value must always match the reported balance.

---

## 8. Forbidden Situations

* Multiple ACTIVE cash entries for the same order/dividend
* Cancellation without reversal
* Reversal without original entry
* Manual UPDATE of cash_transaction

---

## 9. Scope Notes

* Deposits, withdrawals, and adjustments are managed separately
* Fees are assumed to be embedded in orders for now
* FX handling is out of scope

---

**End of Draft v1**
