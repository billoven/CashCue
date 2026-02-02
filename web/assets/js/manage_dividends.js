// ============================================================
// manage_dividend.js
// ------------------------------------------------------------
// Dividends Management — Admin Controller
//
// Responsibilities:
//  - Load and render dividends table
//  - Handle dividend cancellation (immutable model)
//  - Handle dividend creation via modal form
//  - Enforce broker context via CashCueAppContext
//
// Architectural rules:
//  - Broker is selected globally (header)
//  - NO broker selection inside modal
//  - getInstruments.php stays generic
//  - Eligible instruments = ALL except ARCHIVED
// ============================================================

console.log("manage_dividend.js loaded");

// ------------------------------------------------------------
// State
// ------------------------------------------------------------
let dividendModal;
let currentMode = "add";

// ------------------------------------------------------------
// DOM references
// ------------------------------------------------------------
const tableBody = document.querySelector('#dividendsTable tbody');
const modalEl   = document.getElementById('dividendModal');
const form      = document.getElementById('dividendForm');
const addBtn    = document.getElementById('addDividendBtn');
const saveBtn   = document.getElementById('saveDividendBtn');

// Amount fields
const grossField  = document.getElementById('gross_amount');
const taxesField  = document.getElementById('taxes_withheld');
const amountField = document.getElementById('amount');

// ------------------------------------------------------------
// Auto-calculate net amount
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
//
// Rule:
//  - All instruments allowed
//  - EXCEPT status = ARCHIVED
// ------------------------------------------------------------
async function loadInstrumentsDropdown() {
    const instrumentSelect = document.getElementById('instrument_id');
    instrumentSelect.innerHTML = '';

    const res = await fetch('/cashcue/api/getInstruments.php');
    let json  = await res.json();
    let instruments = json.data ?? json;

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
        const brokerAccountId = await CashCueAppContext.waitForBrokerAccount();

        const url = new URL('/cashcue/api/getDividends.php', window.location.origin);
        if (brokerAccountId !== 'all') {
            url.searchParams.append('broker_account_id', brokerAccountId);
        }

        const res  = await fetch(url);
        const json = await res.json();
        if (!json.success) throw new Error(json.error);

        renderTable(json.data);

    } catch (err) {
        console.error(err);
        tableBody.innerHTML = `
            <tr>
                <td colspan="10" class="text-danger text-center">
                    Error loading dividends
                </td>
            </tr>
        `;
    }
}

// ------------------------------------------------------------
// Render dividends table
// ------------------------------------------------------------
function renderTable(dividends) {
    tableBody.innerHTML = '';

    if (!dividends || dividends.length === 0) {
        tableBody.innerHTML =
            '<tr><td colspan="10" class="text-center">No dividends recorded</td></tr>';
        return;
    }

    dividends.forEach(d => {
        const cancelled = d.status === 'CANCELLED';

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${d.payment_date}</td>
            <td>${d.symbol ?? ''}</td>
            <td>${parseFloat(d.gross_amount).toFixed(4)}</td>
            <td>${parseFloat(d.taxes_withheld).toFixed(4)}</td>
            <td>${parseFloat(d.amount).toFixed(4)}</td>
            <td>${d.currency}</td>
            <td>
                <span class="badge ${cancelled ? 'bg-secondary' : 'bg-success'}">
                    ${d.status}
                </span>
            </td>
            <td>${d.cancelled_at ?? '—'}</td>
            <td class="text-center">
                <span
                    class="cancel-action ${cancelled ? 'is-disabled' : 'is-active'}"
                    data-id="${d.id}"
                    role="button"
                >
                    <i class="bi bi-x-circle-fill"></i>
                </span>
            </td>
        `;
        tableBody.appendChild(tr);
    });
}

// ------------------------------------------------------------
// Cancel dividend
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

// Event delegation
document.addEventListener('click', e => {
    const el = e.target.closest('.cancel-action');
    if (!el || el.classList.contains('is-disabled')) return;
    cancelDividend(el.dataset.id);
});

// ------------------------------------------------------------
// Add dividend modal
// ------------------------------------------------------------
addBtn.addEventListener('click', async () => {
    currentMode = 'add';
    form.reset();

    await loadInstrumentsDropdown();

    document.getElementById('dividendModalLabel').textContent = 'Add Dividend';
    dividendModal.show();
});

// ------------------------------------------------------------
// Save dividend (broker injected from context)
// ------------------------------------------------------------
saveBtn.addEventListener('click', async e => {
    e.preventDefault();

    const brokerAccountId = CashCueAppContext.getBrokerAccountId();
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

    const res  = await fetch('/cashcue/api/addDividend.php', {
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

    await CashCueAppContext.waitForBrokerAccount();
    await loadDividends();

    document.addEventListener('brokerAccountChanged', loadDividends);
});

