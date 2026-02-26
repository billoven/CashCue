// ============================================================
// Test Context JS – verify brokerAccountId propagation
// ============================================================

console.log("test_context.js loaded");

// ------------------------------------------------------------
// 1. Ensure CashCueContext exists
// ------------------------------------------------------------
if (!window.CashCueContext || typeof window.CashCueContext.waitForBrokerAccount !== "object") {
    console.error("CashCueContext or waitForBrokerAccount is missing!");
} else {

    const logArea = document.getElementById("contextLog");
    const brokerDisplay = document.getElementById("brokerAccountDisplay");

    function log(msg) {
        console.log(msg);
        if (logArea) {
            const p = document.createElement("p");
            p.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
            logArea.appendChild(p);
        }
    }

    // ------------------------------------------------------------
    // 2. Register callback for when brokerAccountId is ready
    // ------------------------------------------------------------
    log("Registering onBrokerAccountReady()");

    window.CashCueContext.waitForBrokerAccount.then(brokerAccountId => {
        log(`BrokerAccountId ready → ${brokerAccountId}`);
        if (brokerDisplay) {
            brokerDisplay.textContent = brokerAccountId;
        }
    }).catch(err => {
        log(`Error in waitForBrokerAccount: ${err}`);
    });

    // ------------------------------------------------------------
    // 3. Immediate check (may still be null if not yet ready)
    // ------------------------------------------------------------
    const immediate = localStorage.getItem("selectedAccount") || null;
    log(`Immediate getBrokerAccountId() = ${immediate}`);

    if (brokerDisplay) {
        brokerDisplay.textContent = immediate ?? "—";
    }
}
