// ================================
//   Global Broker Account Loader
// ================================

// Called once the page is ready
document.addEventListener("DOMContentLoaded", () => {
    console.log("header.js loaded");

    const select = document.getElementById("activeAccountSelect");
    if (!select) {
        console.warn("header.js: No activeAccountSelect found on this page.");
        return;
    }

    select.innerHTML = '<option>Loading accounts...</option>';

    fetch('/cashcue/api/getBrokerAccounts.php')
        .then(response => response.json())
        .then(accounts => {

            console.log("header.js: accounts loaded =", accounts);

            // Reset dropdown
            select.innerHTML = '<option value="all">All Accounts</option>';

            // Add real broker accounts
            accounts.forEach(acc => {
                const opt = document.createElement("option");
                opt.value = acc.id;
                opt.textContent = acc.label;
                select.appendChild(opt);
            });

            // Restore previously selected account
            const saved = localStorage.getItem("selectedAccount");
            if (saved && select.querySelector(`option[value="${saved}"]`)) {
                select.value = saved;
            }

            // When user selects an account → save + refresh page
            select.addEventListener("change", () => {
                localStorage.setItem("selectedAccount", select.value);

                console.log("header.js: account changed →", select.value);

                if (window.refreshPageData && typeof window.refreshPageData === "function") {
                    window.refreshPageData();
                } else {
                    // Fallback: reload whole page
                    location.reload();
                }
            });

            // ⚡ First-load automatic refresh
            if (window.refreshPageData && typeof window.refreshPageData === "function") {
                console.log("header.js: triggering first refreshPageData()");
                window.refreshPageData();
            }
        })
        .catch(err => {
            console.error("header.js: Error loading accounts:", err);
            select.innerHTML = '<option>Error</option>';
        });
});
