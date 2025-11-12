document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.querySelector('#dividendsTable tbody');
    const modal = new bootstrap.Modal(document.getElementById('dividendModal'));
    const form = document.getElementById('dividendForm');
    const addBtn = document.getElementById('addDividendBtn');
    const saveBtn = document.getElementById('saveDividendBtn');

    // Auto-calc net amount
    const grossField = document.getElementById('gross_amount');
    const taxesField = document.getElementById('taxes_withheld');
    const amountField = document.getElementById('amount');
    [grossField, taxesField].forEach(f => f.addEventListener('input', () => {
        const gross = parseFloat(grossField.value) || 0;
        const taxes = parseFloat(taxesField.value) || 0;
        amountField.value = (gross - taxes).toFixed(4);
    }));

    // ---------------------------
    // Generic function to populate a <select> dropdown
    async function populateSelect(selectId, apiUrl, textFormatter = x => x.name) {
        const select = document.getElementById(selectId);
        if (!select) return;

        select.innerHTML = '<option value="">Select...</option>';

        try {
            const res = await fetch(apiUrl);
            const items = await res.json(); // use array directly
            items.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.id;
                opt.textContent = textFormatter(item);
                select.appendChild(opt);
            });
        } catch (err) {
            console.error(`Error loading ${selectId}:`, err);
        }
    }

    // Load both dropdowns
    async function loadDropdowns() {
        const brokerSelect = document.getElementById('broker_id');
        const instrumentSelect = document.getElementById('instrument_id');
        brokerSelect.innerHTML = '';
        instrumentSelect.innerHTML = '';

        const brokersRes = await fetch('../api/getBrokers.php');
        const instrumentsRes = await fetch('../api/getInstruments.php');

        let brokers = await brokersRes.json();
        let instruments = await instrumentsRes.json();

        // If the API returns { data: [...] }, unwrap it
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


    // ---------------------------
    async function loadDividends() {
        try {
            const res = await fetch('../api/getDividends.php');
            const json = await res.json();
            if (!json.success) throw new Error(json.error);
            renderTable(json.data);
        } catch (err) {
            console.error('Error loading dividends:', err);
            tableBody.innerHTML = `<tr><td colspan="8" class="text-danger">Error loading dividends</td></tr>`;
        }
    }

    function renderTable(dividends) {
        tableBody.innerHTML = '';
        if (!dividends || dividends.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="8" class="text-center">No dividends recorded</td></tr>';
            return;
        }

        dividends.forEach(d => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${d.payment_date}</td>
                <td>${d.symbol || ''}</td>
                <td>${d.broker_name || ''}</td>
                <td>${parseFloat(d.gross_amount || 0).toFixed(4)}</td>
                <td>${parseFloat(d.taxes_withheld || 0).toFixed(4)}</td>
                <td>${parseFloat(d.amount).toFixed(4)}</td>
                <td>${d.currency}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary me-1 edit-btn" data-id="${d.id}"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${d.id}"><i class="bi bi-trash"></i></button>
                </td>
            `;
            tableBody.appendChild(tr);
        });

        document.querySelectorAll('.edit-btn').forEach(btn =>
            btn.addEventListener('click', e => openEditModal(e.target.closest('button').dataset.id))
        );
        document.querySelectorAll('.delete-btn').forEach(btn =>
            btn.addEventListener('click', e => deleteDividend(e.target.closest('button').dataset.id))
        );
    }

    // ---------------------------
    async function openEditModal(id) {
        await loadDropdowns();
        const res = await fetch(`../api/getDividends.php?id=${id}`);
        const json = await res.json();
        if (!json.success || !json.data.length) return;
        const d = json.data[0];

        document.getElementById('dividend_id').value = d.id;
        document.getElementById('broker_id').value = d.broker_id;
        document.getElementById('instrument_id').value = d.instrument_id;
        document.getElementById('payment_date').value = d.payment_date;
        document.getElementById('gross_amount').value = d.gross_amount;
        document.getElementById('taxes_withheld').value = d.taxes_withheld;
        document.getElementById('amount').value = d.amount;
        document.getElementById('currency').value = d.currency;

        document.getElementById('dividendModalLabel').textContent = 'Edit Dividend';
        modal.show();
    }

    // ---------------------------
    addBtn.addEventListener('click', async () => {
        form.reset();
        document.getElementById('dividend_id').value = '';
        await loadDropdowns();
        document.getElementById('dividendModalLabel').textContent = 'Add Dividend';
        modal.show();
    });

    saveBtn.addEventListener('click', async () => {
        const id = document.getElementById('dividend_id').value;
        const payload = {
            id: id || undefined,
            broker_id: form.broker_id.value,
            instrument_id: form.instrument_id.value,
            payment_date: form.payment_date.value,
            gross_amount: parseFloat(form.gross_amount.value) || 0,
            taxes_withheld: parseFloat(form.taxes_withheld.value) || 0,
            amount: parseFloat(form.amount.value) || 0,
            currency: form.currency.value || 'EUR'
        };

        const endpoint = id ? '../api/updateDividend.php' : '../api/addDividend.php';
        try {
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.error);
            modal.hide();
            await loadDividends();
        } catch (err) {
            alert('Error saving dividend: ' + err.message);
        }
    });

    // ---------------------------
    async function deleteDividend(id) {
        if (!confirm('Delete this dividend?')) return;
        try {
            const res = await fetch('../api/deleteDividend.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.error);
            await loadDividends();
        } catch (err) {
            alert('Error deleting dividend: ' + err.message);
        }
    }

    loadDividends();
});

