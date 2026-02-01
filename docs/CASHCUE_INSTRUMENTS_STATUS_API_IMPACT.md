# CashCue Instruments – Status Handling & API Impact

## 1. Status Definitions

| Status      | Meaning / Usage |
|------------|----------------|
| ACTIVE     | Fully operational. Usable for trading, portfolio calculations, and all dashboards. |
| INACTIVE   | Visible but not selectable for new transactions. Can be used for historical reporting. |
| SUSPENDED  | Trading suspended. Only historical data available. No new operations allowed. |
| DELISTED   | Permanently removed from market. Historical data accessible. No new transactions. |
| ARCHIVED   | Fully historical. Hidden from standard UI. Only for historical reports or exports. |

---

## 2. API Impact & Filtering Recommendations

| API | Instrument Usage | Status Filter / Recommendation |
|-----|-----------------|-------------------------------|
| `getInstruments.php` | List instruments for UI selection | Exclude `ARCHIVED` by default. Optionally include `INACTIVE`/`DELISTED` for admin or reporting contexts. |
| `getInstrumentDetails.php` | Fetch instrument details | Accessible for all status. UI can indicate non-tradable instruments. |
| `getInstrumentHistory.php` | Historical data & charts | Accessible for all status, including `ARCHIVED`. |
| `postOrder.php` / `addOrder.php` | Place new orders | Only `ACTIVE` allowed. Return clear error if instrument is `INACTIVE`, `SUSPENDED`, `DELISTED`, or `ARCHIVED`. |
| `getHoldings.php` / `getPortfolioSnapshot.php` / `getPortfolioHistory.php` | Portfolio calculations | Include `ACTIVE`, `INACTIVE`, `SUSPENDED`, `DELISTED`. Exclude `ARCHIVED` in standard view; include for full historical reports. |
| `getDividends.php` / `addDividend.php` | Dividend calculations & records | Include all except `ARCHIVED` for current operations. |
| `getRealtimeData.php` / `getRealtimeDashboard.php` | Dashboard, market data | Exclude `DELISTED` and `ARCHIVED`. Mark `SUSPENDED` instruments with status notice. |
| `updateInstrument.php` / `addInstrument.php` | Edit or create instrument | Already includes validation rules by status. Restrict modifications on `ARCHIVED` instruments according to business rules. |
| Other CRUD endpoints for brokers, cash, or transactions | No direct impact | Apply similar instrument status filters when instruments are joined in queries. |

---

## 3. Implementation Guidelines

1. **Centralized Status Filtering**  
   - For APIs returning lists of instruments, include a `status` filter parameter where applicable.  
   - Default behavior should exclude `ARCHIVED` from user-facing selections.  

2. **UI Messaging**  
   - Show clear indicators for instruments that are `INACTIVE`, `SUSPENDED`, or `DELISTED` to prevent accidental usage.  

3. **Backend Validation**  
   - Reject any operation (orders, cash adjustments) involving non-`ACTIVE` instruments.  
   - Return standardized error messages for invalid operations.  

4. **Admin & Reporting Views**  
   - Provide option to view instruments in all statuses, including `ARCHIVED`, for audit and historical analysis.  

5. **Logging / Auditing**  
   - Track attempts to operate on non-tradable instruments for monitoring and compliance.  

---

## 4. Status Transition Rules (Reference)

| From      | To                | Allowed? | Notes |
|-----------|-------------------|----------|-------|
| ACTIVE    | INACTIVE          | ✅       | No new transactions. |
| ACTIVE    | SUSPENDED         | ✅       | Trading paused. |
| ACTIVE    | DELISTED          | ✅       | No future price updates. |
| INACTIVE  | ACTIVE            | ✅       | Fully operational again. |
| SUSPENDED | ACTIVE            | ✅       | Resume trading and updates. |
| DELISTED  | ARCHIVED          | ✅       | Historical only, hidden from UI. |
| ARCHIVED  | Any               | ❌       | No modifications allowed. |
