// ============================================================
// Dividends Management
// Aligned with Orders lifecycle (ACTIVE / CANCELLED)
// ============================================================
document.addEventListener('DOMContentLoaded', () => {

    // --------------------------------------------------------
    // DOM references
    // --------------------------------------------------------

    const tableBody = document.querySelector('#dividendsTable tbody');
    const modalEl = document.getElementById('dividendModal');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('dividendForm');

    const addBtn = document.getElementById('addDividendBtn');
    const saveBtn = document.getElementById('saveDividendBtn');

    // --------------------------------------------------------
    // Auto-calculate net amount = gross - taxes
    // --------------------------------------------------------
    const grossField = document.getElementById('gross_amount');
    const taxesField = document.getElementById('taxes_withheld');
    const amountField = document.getElementById('amount');

    [grossField, taxesField].forEach(field =>
        field.addEventListener('input', () => {
            const gross = parseFloat(grossField.value) || 0;
            const taxes = parseFloat(taxesField.value) || 0;
            amountField.value = (gross - taxes).toFixed(4);
        })
    );

    // --------------------------------------------------------
    // Load dropdowns (brokers & instruments)
    // --------------------------------------------------------
    async function loadDropdowns() {
        const brokerSelect = document.getElementById('broker_account_id');
        const instrumentSelect = document.getElementById('instrument_id');

        brokerSelect.innerHTML = '';
        instrumentSelect.innerHTML = '';

        const [brokersRes, instrumentsRes] = await Promise.all([
            fetch('/cashcue/api/getBrokers.php'),
            fetch('/cashcue/api/getInstruments.php')
        ]);

        let brokers = await brokersRes.json();
        let instruments = await instrumentsRes.json();

        if (brokers.data) brokers = brokers.data;
        if (instruments.data) instruments = instruments.data;

        brokers.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.id;
            opt.textContent = b.name;
            brokerSelect.appendChild(opt);
        });

        instruments.forEach(i => {
            const opt = document.createElement('option');
            opt.value = i.id;
            opt.textContent = `${i.symbol} - ${i.label}`;
            instrumentSelect.appendChild(opt);
        });
    }

    // --------------------------------------------------------
    // Load dividends from API
    // --------------------------------------------------------
    async function loadDividends() {

        const brokerAccountId = window.getActiveBrokerAccountId();

        try {
            const url = new URL('/cashcue/api/getDividends.php', window.location.origin);

            if (brokerAccountId && brokerAccountId !== "all") {
                url.searchParams.append('broker_account_id', brokerAccountId);
            }

            const res = await fetch(url);
            const json = await res.json();

            if (!json.success) throw new Error(json.error);

            renderTable(json.data);

        } catch (err) {
            console.error('Error loading dividends:', err);
            tableBody.innerHTML =
                `<tr>
                    <td colspan="9" class="text-danger text-center">
                        Error loading dividends
                    </td>
                </tr>`;
        }
    }


    // --------------------------------------------------------
    // Render dividends table
    // --------------------------------------------------------
    function renderTable(dividends) {
        tableBody.innerHTML = '';

        if (!dividends || dividends.length === 0) {
            tableBody.innerHTML =
                '<tr><td colspan="9" class="text-center">No dividends recorded</td></tr>';
            return;
        }

        dividends.forEach(d => {
            const isCancelled = d.status === 'CANCELLED';

            const statusBadge = isCancelled
                ? '<span class="badge bg-secondary">CANCELLED</span>'
                : '<span class="badge bg-success">ACTIVE</span>';

            const cancelIconClass = isCancelled
                ? 'text-muted'
                : 'text-danger cancel-dividend';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${d.payment_date}</td>
                <td>${d.symbol || ''}</td>
                <td>${d.broker_name || ''}</td>
                <td>${parseFloat(d.gross_amount || 0).toFixed(4)}</td>
                <td>${parseFloat(d.taxes_withheld || 0).toFixed(4)}</td>
                <td>${parseFloat(d.amount).toFixed(4)}</td>
                <td>${d.currency}</td>
                <td>${statusBadge}</td>
                <td>${d.cancelled_at ?? '—'}</td>
                <td class="text-center">
                    <span
                        class="cancel-action ${d.status === 'CANCELLED' ? 'is-disabled' : 'is-active'}"
                        data-id="${d.id}"
                        role="button"
                        aria-label="Cancel order"
                        title="${d.status === 'CANCELLED'
                        ? 'Dividend already cancelled'
                        : 'Cancel dividend (creates a cash reversal)'}"
                    >
                        <i class="bi bi-x-circle-fill"></i>
                    </span>
                </td>
            `;
            tableBody.appendChild(tr);
        });
    }

    // --------------------------------------------------------
    // Cancel dividend (logical cancel)
    // --------------------------------------------------------
    async function cancelDividend(id) {
        if (!confirm(
            "Cancelling this Dividend will create a cash reversal.\n" +
            "This action is irreversible.\n\n" +
            "Do you want to continue?"
        )) return;

        try {
            const res = await fetch('/cashcue/api/cancelDividend.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });

            const json = await res.json();
            if (!json.success) throw new Error(json.error);

            await loadDividends();

        } catch (err) {
            console.error('Error cancelling dividend:', err);
            alert(err.message || 'Cancel failed');
        }
    }

    // --------------------------------------------------------
    // Event delegation for CANCEL
    // --------------------------------------------------------
    //tableBody.addEventListener('click', (e) => {
    //
    //    const cancelBtn = e.target.closest('.cancel-dividend'c);
    //    if (cancelBtn) {
    //        cancelDividend(cancelBtn.dataset.id);
    //    }
    //});
    
    // ============================================================
    // Cancel order handler (event delegation)
    // ============================================================
    document.addEventListener("click", (e) => {

        const el = e.target.closest(".cancel-action");
        if (!el) return;

        // Dividend already cancelled → do nothing
        if (el.classList.contains("is-disabled")) {
            return;
        }

        const dividendId = el.dataset.id;
        if (!dividendId) return;

        cancelDividend(dividendId);
    });
    
    // --------------------------------------------------------
    // Add dividend
    // --------------------------------------------------------
    addBtn.addEventListener('click', async () => {
        form.reset();
        form.dividend_id.value = '';
        await loadDropdowns();
        document.getElementById('dividendModalLabel').textContent = 'Add Dividend';
        modal.show();
    });

    // --------------------------------------------------------
    // Save dividend (ADD ONLY — immutable model)
    // --------------------------------------------------------
    saveBtn.addEventListener('click', async (e) => {
        e.preventDefault();

        const payload = {
            broker_account_id: form.broker_account_id.value,
            instrument_id: form.instrument_id.value,
            payment_date: form.payment_date.value,
            gross_amount: parseFloat(form.gross_amount.value) || 0,
            taxes_withheld: parseFloat(form.taxes_withheld.value) || 0,
            amount: parseFloat(form.amount.value) || 0,
            currency: form.currency.value || 'EUR'
        };

        try {
            const res = await fetch('/cashcue/api/addDividend.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const json = await res.json();
            if (!json.success) throw new Error(json.error);

            modal.hide();
            await loadDividends();

        } catch (err) {
            alert('Error adding dividend: ' + err.message);
        }
    });


    // --------------------------------------------------------
    // Initial load — broker-aware
    // --------------------------------------------------------
    (async () => {

        // Wait until header.js finished initializing broker selector
        await window.waitForBrokerSelector();

        // Initial load with correct broker context
        await loadDividends();

        // Reload dividends when broker account changes
        window.onBrokerAccountChange(async () => {
            await loadDividends();
        });

    })();

});
