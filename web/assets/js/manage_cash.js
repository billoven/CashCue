// ============================================================
// Cash Movements Management — Admin Controller
// ============================================================

console.log("manage_cash.js loaded");

let cashModal;
let currentMode = "add"; // add | edit

// ------------------------------------------------------------
// Business rules
// ------------------------------------------------------------

// ONLY these types are computed from orders and must be locked
const LOCKED_TYPES = ["BUY", "SELL", "DIVIDEND"];
    
// ------------------------------------------------------------
// Helpers
// ------------------------------------------------------------

function fmtAmount(v) {
    return Number(v).toFixed(2);
}

function getActiveBrokerId() {
    if (typeof window.getActiveBrokerAccountId === "function") {
        return window.getActiveBrokerAccountId();
    }
    return null;
}

// ------------------------------------------------------------
// Core reload logic
// ------------------------------------------------------------

async function reloadPageData() {
    const brokerId = getActiveBrokerId();
    const tbody = document.getElementById("cashAdminBody");

    tbody.innerHTML = "";

    if (!brokerId) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-muted">
                    Select a broker account
                </td>
            </tr>
        `;
        return;
    }

    try {
        const res = await fetch(
            `/cashcue/api/getCashTransactions.php?broker_account_id=${brokerId}`
        );

        const json = await res.json();
        const rows = json.data ?? [];

        if (!rows.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-muted">
                        No cash movements
                    </td>
                </tr>
            `;
            return;
        }

        rows.forEach(r => {
            const type = String(r.type).toUpperCase();
            const isLocked = LOCKED_TYPES.includes(type);

            const actions = isLocked
                ? `<span class="text-muted fst-italic">Computed</span>`
                : `
                    <button class="btn btn-sm btn-outline-primary me-1"
                        onclick="editCash(${r.id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger"
                        onclick="deleteCash(${r.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                `;

            tbody.insertAdjacentHTML("beforeend", `
                <tr>
                    <td>${r.date}</td>
                    <td>${r.type}</td>
                    <td class="text-end">${fmtAmount(r.amount)}</td>
                    <td>${r.reference_id ?? "—"}</td>
                    <td>${r.comment ?? ""}</td>
                    <td class="text-center">
                        ${actions}
                    </td>
                </tr>
            `);
        });

    } catch (err) {
        console.error("Failed to load cash movements", err);
        alert("Error loading cash movements.");
    }
}

// ------------------------------------------------------------
// Modal handling
// ------------------------------------------------------------

function openAddCashModal() {
    currentMode = "add";
    document.getElementById("cashModalTitle").innerText = "Add Cash Movement";
    document.getElementById("cashForm").reset();
    document.getElementById("cash_id").value = "";

    // Type always editable when adding
    document.getElementById("cash_type").disabled = false;

    cashModal.show();
}

async function editCash(id) {
    currentMode = "edit";
    const brokerId = getActiveBrokerId();

    try {
        const res = await fetch(
            `/cashcue/api/getCashTransactions.php?broker_account_id=${brokerId}`
        );

        const json = await res.json();
        const rows = json.data ?? [];
        const row = rows.find(r => r.id == id);

        if (!row) {
            alert("Cash movement not found.");
            return;
        }

        const type = String(row.type).toUpperCase();

        document.getElementById("cashModalTitle").innerText = "Edit Cash Movement";
        document.getElementById("cash_id").value = row.id;
        document.getElementById("cash_date").value = row.date.replace(" ", "T");

        const typeSelect = document.getElementById("cash_type");
        typeSelect.value = type;

        // 🔒 ONLY BUY / SELL are locked
        typeSelect.disabled = LOCKED_TYPES.includes(type);

        document.getElementById("cash_amount").value = row.amount;
        document.getElementById("cash_comment").value = row.comment ?? "";

        cashModal.show();

    } catch (err) {
        console.error("Failed to load cash movement", err);
        alert("Error loading cash movement.");
    }
}

// ------------------------------------------------------------
// Save (add / update)
// ------------------------------------------------------------

async function saveCash() {
    const brokerId = getActiveBrokerId();

    const payload = {
        broker_account_id: brokerId,
        date: document.getElementById("cash_date").value,
        type: document.getElementById("cash_type").value,
        amount: document.getElementById("cash_amount").value,
        comment: document.getElementById("cash_comment").value
    };

    let url;

    if (currentMode === "add") {
        url = "/cashcue/api/addCashTransaction.php";
    } else {
        payload.id = document.getElementById("cash_id").value;
        url = "/cashcue/api/updateCashTransaction.php";
    }

    try {
        const res = await fetch(url, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        const json = await res.json();

        if (!json.success) {
            throw new Error(json.error || "Unknown API error");
        }

        cashModal.hide();
        reloadPageData();

    } catch (err) {
        console.error("Save cash failed", err);
        alert(`Cash save failed: ${err.message}`);
    }
}

// ------------------------------------------------------------
// Delete
// ------------------------------------------------------------

async function deleteCash(id) {
    if (!confirm("Delete this cash movement?")) return;

    try {
        const res = await fetch("/cashcue/api/deleteCashTransaction.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id })
        });

        const json = await res.json();
        if (!json.success) {
            throw new Error(json.error || "Delete failed");
        }

        reloadPageData();

    } catch (err) {
        console.error("Delete cash failed", err);
        alert(`Delete failed: ${err.message}`);
    }
}

// ------------------------------------------------------------
// Broker change listener
// ------------------------------------------------------------

document.addEventListener("brokerChanged", () => {
    reloadPageData();
});

// ------------------------------------------------------------
// Init
// ------------------------------------------------------------

document.addEventListener("DOMContentLoaded", () => {

    cashModal = new bootstrap.Modal(
        document.getElementById("cashModal")
    );

    document
        .getElementById("btnAddCash")
        .addEventListener("click", openAddCashModal);

    document
        .getElementById("cashSaveBtn")
        .addEventListener("click", saveCash);

    // window.waitForBrokerSelector?.().then(reloadPageData);
});
