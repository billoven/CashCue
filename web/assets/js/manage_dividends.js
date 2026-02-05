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
// DOM references
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
// Utilities
// ------------------------------------------------------------
function fmtAmount(value) {
    const n = parseFloat(value);
    return isNaN(n) ? '0.0000' : n.toFixed(4);
}

// ------------------------------------------------------------
// Auto-calculate net amount (gross - taxes)
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
// ------------------------------------------------------------
async function loadInstrumentsDropdown() {
    const instrumentSelect = document.getElementById('instrument_id');
    instrumentSelect.innerHTML = '';

    const res  = await fetch('/cashcue/api/getInstruments.php');
    const json = await res.json();
    const instruments = json.data ?? json;

    instruments
        .filter(i => i.status !== 'ARCHIVED')
        .forEach(i => {
            const opt = document.createElement('option');
            opt.value = i.id;
            opt.textContent = `${i.symbol} - ${i.label}`;
            instrumentSelect.appendChild(opt);
        });
}

// ------------------------------------------------------------
// Load dividends (broker-context aware)
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
        dividendsTable.setData([]);
    }
}

// ------------------------------------------------------------
// Cancel dividend (immutable reversal model)
// ------------------------------------------------------------
async function cancelDividend(id) {
    if (!confirm(
        "Cancelling this dividend will create a cash reversal.\n\nContinue?"
    )) return;

    const res = await fetch('/cashcue/api/cancelDividend.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    });

    const json = await res.json();
    if (!json.success) {
        alert(json.error);
        return;
    }

    await loadDividends();
}

// ------------------------------------------------------------
// CashCueTable actions renderer
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
// ------------------------------------------------------------
function initDividendsTable() {
    dividendsTable = new CashCueTable({
        containerId: 'dividendsTableContainer',
        emptyMessage: 'No dividends recorded',
        pagination: { enabled: true, pageSize: 5 },
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
// ------------------------------------------------------------
document.addEventListener('click', e => {
    const el = e.target.closest('.cancel-action');
    if (!el || el.classList.contains('is-disabled')) return;
    cancelDividend(el.dataset.id);
});

// ------------------------------------------------------------
// Add dividend modal
// ------------------------------------------------------------
addBtn.addEventListener('click', async () => {
    form.reset();
    await loadInstrumentsDropdown();

    document.getElementById('dividendModalLabel')
        .textContent = 'Add Dividend';

    dividendModal.show();
});

// ------------------------------------------------------------
// Save dividend
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
        alert(json.error);
        return;
    }

    dividendModal.hide();
    await loadDividends();
});

// ------------------------------------------------------------
// Initialization
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
