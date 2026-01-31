// ============================================================
// CashCue – appContext.js
// ============================================================
// Responsibility:
// - Provide centralized access to the current brokerAccountId
// - Notify pages when brokerAccountId becomes available or changes
// - Avoid multiple definitions in each page JS
// ============================================================

console.log("appContext.js loaded");

(function(global) {

  // ------------------------------------------------------------
  // Internal state
  // ------------------------------------------------------------
  let brokerAccountId = null;       // Current brokerAccountId
  const readyCallbacks = [];        // Functions to call when brokerAccountId is ready
  let isReady = false;              // Flag: brokerAccountId is available

  // ------------------------------------------------------------
  // Internal: trigger ready callbacks
  // ------------------------------------------------------------
  function triggerReady() {
    isReady = true;
    readyCallbacks.forEach(cb => cb(brokerAccountId));
    readyCallbacks.length = 0; // clear callbacks
  }

  // ------------------------------------------------------------
  // Public API
  // ------------------------------------------------------------
  global.CashCueAppContext = {

    /**
     * Get brokerAccountId immediately (may be null if not ready)
     */
    getBrokerAccountId: () => brokerAccountId,

    /**
     * Register a callback to be called when brokerAccountId is available
     * If already ready, callback is called immediately
     */
    onBrokerAccountReady: (callback) => {
      if (typeof callback !== "function") return;
      if (isReady) {
        callback(brokerAccountId);
      } else {
        readyCallbacks.push(callback);
      }
    },

    /**
     * Return a Promise resolved when brokerAccountId is available
     */
    waitForBrokerAccount: () => {
      return new Promise(resolve => {
        global.CashCueAppContext.onBrokerAccountReady(resolve);
      });
    }
  };

  // ------------------------------------------------------------
  // Poll localStorage or header select until brokerAccountId is known
  // ------------------------------------------------------------
  function initBrokerAccountPolling() {
    const maxPolls = 50;  // e.g., 5s max
    let pollCount = 0;

    const poll = setInterval(() => {
      pollCount++;
      // Try localStorage first
      const stored = localStorage.getItem("selectedAccount");
      if (stored) {
        brokerAccountId = stored;
        triggerReady();
        clearInterval(poll);
        console.log("appContext.js: brokerAccountId ready →", brokerAccountId);
        return;
      }

      // Fallback: check header select (if present)
      const select = document.getElementById("activeAccountSelect");
      if (select && select.value) {
        brokerAccountId = select.value;
        triggerReady();
        clearInterval(poll);
        console.log("appContext.js: brokerAccountId ready via header select →", brokerAccountId);
        return;
      }

      if (pollCount >= maxPolls) {
        clearInterval(poll);
        console.warn("appContext.js: brokerAccountId NOT found after polling");
        triggerReady(); // still trigger callbacks with null
      }
    }, 100);
  }

  // Start polling immediately
  initBrokerAccountPolling();

  // ------------------------------------------------------------
  // Observe changes on header select to update brokerAccountId
  // ------------------------------------------------------------
  function observeHeaderSelect() {
    const select = document.getElementById("activeAccountSelect");
    if (!select) {
      setTimeout(observeHeaderSelect, 100); // retry until select exists
      return;
    }

    select.addEventListener("change", () => {
      brokerAccountId = select.value;
      localStorage.setItem("selectedAccount", brokerAccountId);
      console.log("appContext.js: brokerAccountId changed →", brokerAccountId);

      // Notify all ready callbacks
      triggerReady();

      // Optionally, emit a custom event for pages
      document.dispatchEvent(new CustomEvent("brokerAccountChanged", {
        detail: { brokerAccountId }
      }));
    });
  }

  // Start observing after DOM ready
  document.addEventListener("DOMContentLoaded", observeHeaderSelect);

})(window);
