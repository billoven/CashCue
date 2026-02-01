# Instrument Lifecycle and Status Rules

This document defines the lifecycle of financial instruments within CashCue, including allowed status transitions, UI/UX impacts, and rules for modifying instrument attributes based on status.

---

## 1. Status Definitions

| Status     | Description                                                                                  |
|-----------|----------------------------------------------------------------------------------------------|
| ACTIVE     | Instrument is fully operational, selectable for trading and portfolio operations.          |
| INACTIVE   | Instrument is temporarily disabled; no new operations can be performed.                     |
| SUSPENDED  | Trading and price updates are suspended; valuation uses last known price.                    |
| DELISTED   | Instrument is permanently delisted; no future price updates or trades.                      |
| ARCHIVED   | Historical record; hidden from default views; read-only.                                     |

---

## 2. Allowed Status Transitions

| From      | To                                  | Notes / Impact                                                      |
|-----------|-------------------------------------|----------------------------------------------------------------------|
| ACTIVE    | INACTIVE, SUSPENDED, DELISTED       | Each change displays impact message for user confirmation.          |
| INACTIVE  | ACTIVE                              | Instrument becomes operational again.                                |
| SUSPENDED | ACTIVE                              | Trading and pricing resume.                                          |
| DELISTED  | ARCHIVED                            | Instrument becomes historical only.                                  |
| ARCHIVED  | –                                   | No transitions allowed.                                              |

---

## 3. Status Change Impact Messages

| Transition           | Message                                                                                     |
|----------------------|---------------------------------------------------------------------------------------------|
| ACTIVE → INACTIVE     | The instrument will no longer be selectable for new operations.                            |
| ACTIVE → SUSPENDED    | Trading and price updates will be suspended. Valuation will rely on the last known price. |
| ACTIVE → DELISTED     | The instrument is permanently delisted. No future price updates will occur.               |
| INACTIVE → ACTIVE     | The instrument will become fully operational again.                                        |
| SUSPENDED → ACTIVE    | Trading and price updates will resume.                                                     |
| DELISTED → ARCHIVED   | The instrument becomes historical only and will be hidden from default views.             |

---

## 4. Rules for Editing Other Fields Based on Status

| Field        | Editable Statuses                 | Notes                                                                 |
|--------------|----------------------------------|-----------------------------------------------------------------------|
| symbol       | ACTIVE, INACTIVE, SUSPENDED      | Must remain unique; cannot change for DELISTED or ARCHIVED instruments. |
| label        | ACTIVE, INACTIVE, SUSPENDED      | Can be updated for display purposes.                                   |
| isin         | ACTIVE, INACTIVE, SUSPENDED      | Optional; read-only for DELISTED or ARCHIVED.                          |
| type         | ACTIVE, INACTIVE, SUSPENDED      | Cannot be modified if DELISTED or ARCHIVED.                            |
| currency     | ACTIVE, INACTIVE, SUSPENDED      | Cannot be modified if DELISTED or ARCHIVED.                            |
| status       | ACTIVE, INACTIVE, SUSPENDED, DELISTED | Controlled by allowed transitions above; ARCHIVED is read-only.        |

> **Note:** DELISTED and ARCHIVED instruments are effectively **read-only** except for the status transition from DELISTED → ARCHIVED.

---

## 5. UI / Frontend Guidelines

1. **Add Instrument**
   - New instruments are created with `ACTIVE` status.
   - Status field hidden on creation.

2. **Edit Instrument**
   - Status field shown only in edit mode.
   - Status select shows only allowed transitions plus current status (disabled).
   - Changing status shows impact message and requires user confirmation.

3. **Delete Instrument**
   - Physical deletion is **not allowed**.
   - Lifecycle changes are handled exclusively via status.

4. **Visual Indicators**
   - Table displays current status as a badge.
   - ARCHIVED instruments may be hidden by default in table views.

---

## 6. API Considerations

- `updateInstrument.php`
  - Validates status transitions.
  - Checks editable fields based on current status.
  - Returns previous and current status in response.

- `addInstrument.php`
  - Sets initial status to `ACTIVE`.
  - Ensures required fields are provided.

- `getInstrumentDetails.php` / `getInstruments.php`
  - Include `status` field for UI rendering.

- **Frontend JS**
  - `manage_instruments.js` enforces status select and displays impact messages.
  - Prevents edits to read-only fields for DELISTED and ARCHIVED instruments.

---

## 7. Summary

- All instruments start as `ACTIVE`.
- No physical deletion; lifecycle managed via status only.
- Certain fields are read-only depending on status.
- Status transitions are strictly enforced both in the backend API and frontend UI.
