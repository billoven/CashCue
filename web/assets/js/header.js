/// ============================================================
// CashCue – Global Broker Account Selector Controller
// ============================================================
// Responsibility:
// - Populate the broker account selector in the header
// - Restore last selected broker from localStorage
// - Persist broker selection
// - Notify global context when broker is ready
// - Reload page on broker change
//
// Design principle:
// - This file does NOT expose any global getter for brokerAccountId
// - Pages should use CashCueContext.waitForBrokerAccount promise
// ============================================================

console.log("header.js loaded");

// ------------------------------------------------------------
// 1. Read configuration injected by header.php
// ------------------------------------------------------------
const BROKER_SCOPE = window.__BROKER_SCOPE__ || "single-or-all";
console.log("CashCue: broker scope =", BROKER_SCOPE);

// ------------------------------------------------------------
// 2. DOM Ready
// ------------------------------------------------------------
document.addEventListener("DOMContentLoaded", () => {

    const area   = document.getElementById("brokerAccountArea");
    const select = document.getElementById("activeAccountSelect");

    // --------------------------------------------------------
    // CASE 1: Broker selection disabled
    // --------------------------------------------------------
    if (BROKER_SCOPE === "disabled") {
        if (area) {
            area.innerHTML = `
                <div class="text-muted small fst-italic">
                    Broker selection disabled on this page
                </div>
            `;
        }
        console.log("CashCue: broker selector disabled");
        return;
    }

    // --------------------------------------------------------
    // CASE 2: Selector missing (defensive)
    // --------------------------------------------------------
    if (!select) {
        console.warn("CashCue: #activeAccountSelect not found");
        return;
    }

    // Temporary placeholder while loading
    select.innerHTML = `<option>Loading accounts…</option>`;

    // --------------------------------------------------------
    // 3. Load broker accounts from API
    // --------------------------------------------------------
    fetch("/cashcue/api/getBrokerAccounts.php")
        .then(r => r.json())
        .then(accounts => {

            select.innerHTML = "";

            // Add "All Accounts" option if allowed
            if (BROKER_SCOPE === "single-or-all" || BROKER_SCOPE === "portfolio") {
                select.insertAdjacentHTML(
                    "beforeend",
                    `<option value="all">All Accounts</option>`
                );
            }

            // Add individual broker accounts
            accounts.forEach(acc => {
                select.insertAdjacentHTML(
                    "beforeend",
                    `<option value="${acc.id}">${acc.label}</option>`
                );
            });

            // --------------------------------------------------------
            // 4. Restore persisted broker selection
            // --------------------------------------------------------
            let saved = localStorage.getItem("selectedAccount");
            if (!saved || !select.querySelector(`option[value="${saved}"]`)) {
                saved = (BROKER_SCOPE === "single" && accounts.length)
                    ? accounts[0].id
                    : "all";
                localStorage.setItem("selectedAccount", saved);
            }
            select.value = saved;
            console.log("CashCue: broker restored →", saved);

            // --------------------------------------------------------
            // 5. Notify global context that broker is ready
            // --------------------------------------------------------
            if (window.CashCueContext && typeof window.CashCueContext.setBrokerAccountId === "function") {
                window.CashCueContext.setBrokerAccountId(saved);
                console.log("CashCueContext updated with brokerAccountId →", saved);
            }

            // --------------------------------------------------------
            // 6. Handle broker change (STANDARD RULE)
            // --------------------------------------------------------
            select.addEventListener("change", () => {
                const value = select.value;
                console.log("CashCue: broker changed →", value);

                // Persist globally
                localStorage.setItem("selectedAccount", value);

                // Update global context
                if (window.CashCueContext && typeof window.CashCueContext.setBrokerAccountId === "function") {
                    window.CashCueContext.setBrokerAccountId(value);
                }

                // GLOBAL RULE: broker change = context change = reload page
                window.location.reload();
            });

        })
        .catch(err => {
            console.error("CashCue: failed to load broker accounts", err);
            select.innerHTML = `<option>Error loading accounts</option>`;
        });

});
