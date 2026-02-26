// ============================================================
// manage_dividends.js
// ------------------------------------------------------------
// Dividends Management — Admin Controller (CashCueTable edition)
//
// Responsibilities:
//  - Load dividends in broker context (CashCueAppContext)
//  - Render sortable dividends table via CashCueTable
//  - Handle dividend cancellation (immutable accounting model)
//  - Handle dividend creation via modal form
//
// Sorting rules:
//  - Payment Date      → date
//  - Instrument        → string
//  - Gross / Taxes / Net → number
//
// Architectural rules:
//  - Broker selected globally (header)
//  - NO broker selection inside modal
//  - Instruments list excludes ARCHIVED
// ============================================================

console.log("manage_dividends.js loaded");

// ------------------------------------------------------------
// State
// ------------------------------------------------------------
let dividendModal;
let dividendsTable;
let dividendsData = [];

// ------------------------------------------------------------
// DOM Elements
// ------------------------------------------------------------ 
const modalEl = document.getElementById('dividendModal');
const form    = document.getElementById('dividendForm');
const addBtn  = document.getElementById('addDividendBtn');
const saveBtn = document.getElementById('saveDividendBtn');

// Amount fields
const grossField  = document.getElementById('gross_amount');
const taxesField  = document.getElementById('taxes_withheld');
const amountField = document.getElementById('amount');

// ------------------------------------------------------------
// Helper: format amount to 4 decimals, with fallback to '0.0000'
// ------------------------------------------------------------
function fmtAmount(value) {
    const n = parseFloat(value);
    return isNaN(n) ? '0.0000' : n.toFixed(4);
}

// ------------------------------------------------------------
// Auto-calculate net amount (gross - taxes)
// Triggered on input in gross or taxes fields
// ------------------------------------------------------------
[grossField, taxesField].forEach(field => {
    field.addEventListener('input', () => {
        const gross = parseFloat(grossField.value) || 0;
        const taxes = parseFloat(taxesField.value) || 0;
        amountField.value = (gross - taxes).toFixed(4);
    });
});

// ------------------------------------------------------------
// Load instruments dropdown
// Rule: all instruments EXCEPT ARCHIVED
// Minimal error handling focused on API return control
// ------------------------------------------------------------
async function loadInstrumentsDropdown() {
    const instrumentSelect = document.getElementById('instrument_id');
    instrumentSelect.innerHTML = '';

    try {
        const res  = await fetch('/cashcue/api/getInstruments.php');
        const json = await res.json();

        // 🔐 API contract validation (ESSENTIAL)
        if (json.status !== "success") {
            throw new Error(json.message || "API returned error status");
        }

        if (!Array.isArray(json.data)) {
            throw new Error("Invalid API payload (data is not an array)");
        }

        json.data
            .filter(i => i.status !== 'ARCHIVED')
            .forEach(i => {
                const opt = document.createElement('option');
                opt.value = i.id;
                opt.textContent = `${i.symbol} - ${i.label}`;
                instrumentSelect.appendChild(opt);
            });

    } catch (error) {
        console.error("loadInstrumentsDropdown:", error);
        showAlert("danger", `Failed to load instruments: ${error.message}`);
    }
}


// ------------------------------------------------------------
// Load dividends for selected broker account and populate CashCueTable
// Uses CashCueAppContext to get current broker account ID
// ------------------------------------------------------------
async function loadDividends() {
    try {
        const brokerAccountId =
            await CashCueAppContext.waitForBrokerAccount();

        const url = new URL(
            '/cashcue/api/getDividends.php',
            window.location.origin
        );

        if (brokerAccountId !== 'all') {
            url.searchParams.append(
                'broker_account_id',
                brokerAccountId
            );
        }

        const res  = await fetch(url);
        const json = await res.json();

        if (!json.success) {
            throw new Error(json.error || 'API error');
        }

        dividendsData = json.data || [];
        dividendsTable.setData(dividendsData);

    } catch (err) {
        console.error(err);
        showAlert('danger', `Failed to load dividends: ${err.message}`);
        dividendsTable.setData([]);
    }
}

// ------------------------------------------------------------
// Cancel dividend (immutable reversal model)
// Prompts user for confirmation, then calls API to cancel dividend by ID   
// ------------------------------------------------------------
async function cancelDividend(id) {
    if (!confirm(
        "Cancelling this dividend will create a cash reversal.\n\nContinue?"
    )) return;

    try {
        const res = await fetch('/cashcue/api/cancelDividend.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });

        if (!res.ok) {
            throw new Error(`HTTP error ${res.status}`);
        }

        const json = await res.json();

        if (!json.success) {
            showAlert("danger", json.error || "Failed to cancel dividend.");
            return;
        }

        await loadDividends();
        showAlert("success", "Dividend cancelled successfully.");
    } catch (error) {
        console.error("cancelDividend error:", error);
        showAlert("danger", `Error cancelling dividend: ${error.message}`);
    }
}


// ------------------------------------------------------------
// CashCueTable actions renderer
// Renders action buttons for each row, with conditional disabling based on dividend status
// ------------------------------------------------------------
function renderActions(row) {
    const disabled = row.status === 'CANCELLED';

    return `
        <span
            class="cancel-action ${disabled ? 'is-disabled' : 'is-active'}"
            data-id="${row.id}"
            role="button"
            title="Cancel dividend"
        >
            <i class="bi bi-x-circle-fill"></i>
        </span>
    `;
}

// ------------------------------------------------------------
// Table initialization
// Defines the columns and settings for the CashCueTable, including custom renderers for certain fields and enabling pagination and sorting where appropriate
// ------------------------------------------------------------
function initDividendsTable() {
    dividendsTable = new CashCueTable({
        containerId: 'dividendsTableContainer',
        emptyMessage: 'No dividends recorded',
        pagination: { enabled: true, pageSize: 20 },
        columns: [
            {
                key: 'payment_date',
                label: 'Payment Date',
                sortable: true,
                sortType: 'date'
            },
            {
                key: 'symbol',
                label: 'Instrument',
                sortable: true,
                sortType: 'string',
                render: row => row.symbol ?? ''
            },
            {
                key: 'gross_amount',
                label: 'Gross',
                sortable: true,
                sortType: 'number',
                align: 'end',
                render: row => fmtAmount(row.gross_amount)
            },
            {
                key: 'taxes_withheld',
                label: 'Taxes',
                sortable: true,
                sortType: 'number',
                align: 'end',
                render: row => fmtAmount(row.taxes_withheld)
            },
            {
                key: 'amount',
                label: 'Net Received',
                sortable: true,
                sortType: 'number',
                align: 'end',
                render: row => fmtAmount(row.amount)
            },
            {
                key: 'currency',
                label: 'Currency',
                sortable: false
            },
            {
                key: 'status',
                label: 'Status',
                sortable: false,
                type: 'string',
                render: row => `
                    <span class="badge ${
                        row.status === 'CANCELLED'
                            ? 'bg-secondary'
                            : 'bg-success'
                    }">
                        ${row.status}
                    </span>
                `
            },
            {
                key: 'cancelled_at',
                label: 'Cancelled at',
                sortable: false,
                type: 'date',
                render: row => row.cancelled_at ?? '—'
            },
            {
                key: 'actions',
                label: 'Actions',
                sortable: false,
                align: 'center',
                html: true,
                render: renderActions
            }
        ]
    });
}

// ------------------------------------------------------------
// Event delegation (actions column)
// Listens for clicks on the cancel action buttons within the table and triggers the cancelDividend function with the appropriate ID, while preventing interaction with already cancelled dividends
// ------------------------------------------------------------
document.addEventListener('click', e => {
    const el = e.target.closest('.cancel-action');
    if (!el || el.classList.contains('is-disabled')) return;
    cancelDividend(el.dataset.id);
});

// ------------------------------------------------------------
// Add dividend modal
// Resets form, loads instruments dropdown, sets modal title, and shows the modal when the add button is clicked
// ------------------------------------------------------------
addBtn.addEventListener('click', async () => {
    form.reset();
    await loadInstrumentsDropdown();

    document.getElementById('dividendModalLabel')
        .textContent = 'Add Dividend';

    dividendModal.show();
});

// ------------------------------------------------------------
// Save dividend (form submission)
// Validates broker account selection, constructs payload from form data, sends it to the API, and handles the response by either showing an error or reloading the dividends table and hiding the modal
// ------------------------------------------------------------
saveBtn.addEventListener('click', async e => {
    e.preventDefault();

    const brokerAccountId =
        CashCueAppContext.getBrokerAccountId();

    if (!brokerAccountId || brokerAccountId === 'all') {
        alert('Please select a broker account first.');
        return;
    }

    const payload = {
        broker_account_id: brokerAccountId,
        instrument_id: form.instrument_id.value,
        payment_date: form.payment_date.value,
        gross_amount: parseFloat(form.gross_amount.value) || 0,
        taxes_withheld: parseFloat(form.taxes_withheld.value) || 0,
        amount: parseFloat(form.amount.value) || 0,
        currency: form.currency.value || 'EUR'
    };

    const res = await fetch('/cashcue/api/addDividend.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    const json = await res.json();
    if (!json.success) {
        showAlert("danger", json.error || "Failed to add dividend.");
        return;
    }

    dividendModal.hide();
    await loadDividends();
    showAlert("success", "Dividend added successfully.");
});

// ------------------------------------------------------------
// Initial setup: attach event listeners and initialize the table
// Sets up the click listener for the add button, change listener for the cash account checkbox, initializes the CashCueTable, and loads the initial data.
// Also listens for broker account changes to reload dividends accordingly.
// ------------------------------------------------------------
document.addEventListener('DOMContentLoaded', async () => {
    dividendModal = new bootstrap.Modal(modalEl);

    initDividendsTable();

    await CashCueAppContext.waitForBrokerAccount();
    await loadDividends();

    document.addEventListener(
        'brokerAccountChanged',
        loadDividends
    );
});