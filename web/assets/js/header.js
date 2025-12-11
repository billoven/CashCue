// ============================================================
//  Global Broker Account Controller — Refactored Version
//  Centralized broker selection logic with page-level policies
// ============================================================

console.log("header.js loaded");

// ------------------------------------------------------------
// 1. Page Mode Detection
// ------------------------------------------------------------
const BROKER_SCOPE = window.__BROKER_SCOPE__ || "single-or-all";
console.log("header.js: Broker scope =", BROKER_SCOPE);

// ------------------------------------------------------------
// 2. Internal State and Callbacks
// ------------------------------------------------------------
let brokerSelectorReady = false;
let brokerSelectorCallbacks = [];
let brokerChangeCallbacks = [];

// Public API
window.waitForBrokerSelector = function () {
    return new Promise(resolve => {
        if (brokerSelectorReady) resolve();
        else brokerSelectorCallbacks.push(resolve);
    });
};

window.getActiveBrokerAccountId = function () {
    const select = document.getElementById("activeAccountSelect");
    if (!select) return "all";
    return select.value;
};

window.onBrokerAccountChange = function (callback) {
    brokerChangeCallbacks.push(callback);
};

// ------------------------------------------------------------
// 3. DOM Content Loaded
// ------------------------------------------------------------
document.addEventListener("DOMContentLoaded", () => {

    const area = document.getElementById("brokerAccountArea");
    const select = document.getElementById("activeAccountSelect");

    // --------------------------------------------------------
    // CASE 1 : mode BROKER_SCOPE = "disabled"
    //         => remove selector, replace with a clean message
    // --------------------------------------------------------
    if (BROKER_SCOPE === "disabled") {

        if (area) {
            area.innerHTML = `
                <div class="text-muted small fst-italic">
                    No broker account selection on this page
                </div>
            `;
        }

        brokerSelectorReady = true;
        brokerSelectorCallbacks.forEach(cb => cb());
        console.log("header.js: selector disabled (clean replacement)");
        return;   // safe because we are inside DOMContentLoaded callback
    }

    // --------------------------------------------------------
    // CASE 2 : selector is expected, but missing from DOM
    // --------------------------------------------------------
    if (!select) {
        console.warn("header.js: No #activeAccountSelect found, but scope =", BROKER_SCOPE);
        brokerSelectorReady = true;
        brokerSelectorCallbacks.forEach(cb => cb());
        return;
    }

    // Temporary placeholder
    select.innerHTML = '<option>Loading accounts...</option>';

    // --------------------------------------------------------
    // 4. Fetch accounts list (normal mode)
    // --------------------------------------------------------
    fetch('/cashcue/api/getBrokerAccounts.php')
        .then(resp => resp.json())
        .then(accounts => {

            select.innerHTML = ""; // clear

            // MODE: single-or-all  (full functionality)
            if (BROKER_SCOPE === "single-or-all" || BROKER_SCOPE === "portfolio") {
                const optAll = document.createElement("option");
                optAll.value = "all";
                optAll.textContent = "All Accounts";
                select.appendChild(optAll);
            }

            // Append accounts
            accounts.forEach(acc => {
                const opt = document.createElement("option");
                opt.value = acc.id;
                opt.textContent = acc.label;
                select.appendChild(opt);
            });

            // Restore previous selection
            const saved = localStorage.getItem("selectedAccount");

            if (
                saved && 
                select.querySelector(`option[value="${saved}"]`)
            ) {
                select.value = saved;
                console.log("header.js: restored saved account →", saved);

            } else {
                if (BROKER_SCOPE === "single") {
                    const first = accounts.length > 0 ? accounts[0].id : "all";
                    select.value = first;
                    console.log("header.js: using default (single) →", first);

                } else {
                    select.value = "all";
                    console.log("header.js: using default (all)");
                }
            }

            // Mark selector ready
            brokerSelectorReady = true;
            brokerSelectorCallbacks.forEach(cb => cb());
            brokerChangeCallbacks.forEach(cb => cb(select.value));

            // Event Listener
            select.addEventListener("change", () => {
                const newVal = select.value;
                localStorage.setItem("selectedAccount", newVal);
                console.log("header.js: active account changed →", newVal);

                brokerChangeCallbacks.forEach(cb => cb(newVal));

                if (BROKER_SCOPE === "portfolio") {
                    document.dispatchEvent(new CustomEvent("portfolioBrokerChanged", {
                        detail: { accountId: newVal }
                    }));
                }
            });

        })
        .catch(err => {
            console.error("header.js: failed to load accounts", err);
            select.innerHTML = '<option>Error</option>';
            brokerSelectorReady = true;
            brokerSelectorCallbacks.forEach(cb => cb());
        });

});

