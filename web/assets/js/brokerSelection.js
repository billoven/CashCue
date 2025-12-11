// ============================================================
//   BROKER ACCOUNT SELECTION MANAGER (Global for all pages)
// ============================================================

/**
 * Returns the currently selected broker_account_id.
 * If the selector is not present on this page → returns "all".
 * If not yet loaded in the DOM → returns null (meaning “not ready”).
 */
export function getActiveBrokerAccountId() {
    const selector = document.getElementById("activeAccountSelect");

    if (!selector) {
        // Page without broker filtering: default = ALL
        return "all";
    }

    const raw = selector.value;
    return raw && raw !== "" ? raw : "all";
}


/**
 * Waits until the broker selector is available in the DOM.
 * Timeout safeguard prevents infinite waiting.
 * Usage: await waitForBrokerSelector();
 */
export function waitForBrokerSelector(timeoutMs = 3000) {
    return new Promise((resolve, reject) => {
        const start = Date.now();

        function check() {
            if (document.getElementById("activeAccountSelect")) {
                return resolve();
            }

            if (Date.now() - start > timeoutMs) {
                console.warn("WARNING: activeAccountSelect not found within timeout — assuming no broker filtering on this page.");
                return resolve(); // Not reject → fallback to pages with no selector
            }

            requestAnimationFrame(check);
        }

        check();
    });
}


/**
 * Installs a listener to react when the user changes the broker selection.
 * Common use-case: refreshPageData();
 */
export function onBrokerAccountChange(callback) {
    document.addEventListener("change", (event) => {
        if (event.target && event.target.id === "activeAccountSelect") {
            console.log("DEBUG: Broker selection changed →", event.target.value);
            callback(event.target.value);
        }
    });
}
