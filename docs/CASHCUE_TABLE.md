# CashCueTable — UI Table Component

## Overview

`CashCueTable` is a lightweight, reusable JavaScript class used to render data tables in the CashCue web interface.

It is designed to:
- Separate **data rendering** from **business logic**
- Provide **optional column sorting**
- Be fully compatible with **Bootstrap tables**
- Remain **designer-friendly** (HTML + CSS driven)

This component does **not** fetch data by itself.  
It only displays data passed to it.

---

## Typical Use Cases

- Realtime prices table
- Instruments list
- Orders history
- Dividends tables
- Admin dashboards

---

## Basic Concept

A `CashCueTable` instance is defined by:
1. A **container** (where the table is rendered)
2. A **column definition** (labels, styling, sorting)
3. A **dataset** (array of objects)

---

## Minimal Example

```js
const table = new CashCueTable({
  containerId: "realtimeTableContainer",
  columns: [...],
  data: [...]
});

table.render();
```

---

## Column Definition

Each column is described using a configuration object.

```js
{
  key: "symbol",
  label: "Symbol",
  sortable: true,
  type: "string"
}
```

### Column Properties

| Property | Type | Required | Description |
|--------|------|----------|-------------|
| `key` | string | yes | Field name in the data object |
| `label` | string | yes | Header text displayed |
| `sortable` | boolean | no | Enables sorting (default: false) |
| `type` | string | no | `"string"` or `"number"` (sorting only) |
| `className` | string | no | CSS class applied to `<td>` |
| `render` | function | no | Custom cell renderer |

---

## Optional Sorting (Important)

👉 **Sorting is entirely optional per column**

If `sortable` is not defined or set to `false`:
- No click behavior
- No sort icons
- No sorting logic applied

```js
{
  key: "updated_at",
  label: "Updated",
  sortable: false
}
```

This allows designers to control **exactly** which columns are interactive.

---

## Custom Cell Rendering

For advanced formatting (colors, icons, units), use `render()`.

```js
{
  key: "pct_change",
  label: "Change (%)",
  sortable: true,
  type: "number",
  render: row => {
    const v = parseFloat(row.pct_change);
    if (isNaN(v)) return "-";
    const cls = v >= 0 ? "text-success" : "text-danger";
    return `<span class="${cls}">${v.toFixed(2)}%</span>`;
  }
}
```

Designers can fully control:
- Colors
- Icons
- HTML structure
- Typography

---

## Row Click Handling

Rows can trigger actions (for example opening charts):

```js
onRowClick: row => {
  loadInstrumentChart(row.instrument_id, row.label);
}
```

This logic stays **outside** the table layout.

---

## Updating Data

When new data arrives:

```js
table.setData(newData);
```

The table automatically re-renders, keeping:
- Sorting state
- Column layout
- Styling

---

## CSS & Styling

`CashCueTable` relies on standard Bootstrap classes:

- `.table`
- `.table-striped`
- `.table-hover`
- `.table-dark`

Sorting icons use Bootstrap Icons:

```html
<i class="bi bi-caret-up-fill"></i>
<i class="bi bi-caret-down-fill"></i>
```

Designers are encouraged to override styles using CSS:

```css
th.sortable { cursor: pointer; }
th.sorted-asc { color: #0d6efd; }
th.sorted-desc { color: #0d6efd; }
```

---

## Responsibilities Split

### CashCueTable
- Rendering
- Sorting logic
- DOM updates
- UI consistency

### Page Scripts (e.g. `dashboard.js`)
- Fetching data
- Business rules
- API calls
- Navigation and charts

---

## Design Philosophy

- **Declarative**: layout described, not hardcoded
- **Reusable**: one table system, many screens
- **Safe**: no implicit behavior
- **Predictable**: no magic, no hidden DOM mutations

---

## Summary for Designers

- You control **what is sortable**
- You control **how cells look**
- You never touch API code
- You never manage sorting state manually

`CashCueTable` is a UI building block — not a framework.
