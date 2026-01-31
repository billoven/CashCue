<?php
// ============================================================
// CashCue – Test page for appContext.js
// ============================================================

// Include standard header (bootstrap, header.js, etc.)
include __DIR__ . '/header.php';
?>

<div class="container mt-4">
    <h2>CashCue – appContext Test</h2>

    <div class="mb-3">
        <strong>Current brokerAccountId:</strong>
        <span id="currentBroker">—</span>
    </div>

    <div class="mb-3">
        <strong>LocalStorage stored brokerAccountId:</strong>
        <span id="storedBroker">—</span>
    </div>

    <div class="mb-3">
        <strong>Event log:</strong>
        <ul id="eventLog"></ul>
    </div>

    <div class="mb-3">
        <strong>Manual check buttons:</strong><br>
        <button id="btnGetImmediate" class="btn btn-primary btn-sm mt-1">Get brokerAccountId immediately</button>
        <button id="btnWaitPromise" class="btn btn-success btn-sm mt-1">Wait for brokerAccountId (Promise)</button>
    </div>
</div>

<script src="/cashcue/assets/js/appContext.js"></script>
<script>
console.log("test_context.js loaded");

const elCurrent = document.getElementById("currentBroker");
const elStored  = document.getElementById("storedBroker");
const elLog     = document.getElementById("eventLog");

// Helper: log messages
function logEvent(msg) {
  const li = document.createElement("li");
  li.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
  elLog.appendChild(li);
  console.log(msg);
}

// ------------------------------------------------------------
// 1. Immediate check (may be null if not ready)
// ------------------------------------------------------------
logEvent("Registering onBrokerAccountReady()");
const immediate = CashCueAppContext.getBrokerAccountId();
logEvent(`Immediate getBrokerAccountId() = ${immediate}`);
elCurrent.textContent = immediate ?? '—';
elStored.textContent  = localStorage.getItem("selectedAccount") ?? '—';

// ------------------------------------------------------------
// 2. Register callback to wait for brokerAccountId
// ------------------------------------------------------------
CashCueAppContext.onBrokerAccountReady((brokerId) => {
    logEvent(`Callback: brokerAccountId ready → ${brokerId}`);
    elCurrent.textContent = brokerId ?? '—';
    elStored.textContent  = localStorage.getItem("selectedAccount") ?? '—';
});

// ------------------------------------------------------------
// 3. Use Promise API to wait asynchronously
// ------------------------------------------------------------
CashCueAppContext.waitForBrokerAccount().then((brokerId) => {
    logEvent(`Promise resolved: brokerAccountId → ${brokerId}`);
    elCurrent.textContent = brokerId ?? '—';
    elStored.textContent  = localStorage.getItem("selectedAccount") ?? '—';
});

// ------------------------------------------------------------
// 4. Listen for broker changes from header select
// ------------------------------------------------------------
document.addEventListener("brokerAccountChanged", (e) => {
    const id = e.detail.brokerAccountId;
    logEvent(`Event brokerAccountChanged → ${id}`);
    elCurrent.textContent = id ?? '—';
    elStored.textContent  = localStorage.getItem("selectedAccount") ?? '—';
});

// ------------------------------------------------------------
// 5. Manual buttons for testing
// ------------------------------------------------------------
document.getElementById("btnGetImmediate")?.addEventListener("click", () => {
    const id = CashCueAppContext.getBrokerAccountId();
    logEvent(`Manual immediate check → ${id}`);
    elCurrent.textContent = id ?? '—';
});

document.getElementById("btnWaitPromise")?.addEventListener("click", () => {
    CashCueAppContext.waitForBrokerAccount().then((id) => {
        logEvent(`Manual promise resolved → ${id}`);
        elCurrent.textContent = id ?? '—';
    });
});
</script>
