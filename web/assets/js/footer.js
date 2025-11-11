// /assets/js/footer.js

document.addEventListener('DOMContentLoaded', function () {
  // --- Sidebar toggle ---
  const sidebarToggle = document.getElementById('menuToggle'); // navbar button id
  const wrapper = document.getElementById('wrapper');

  if (sidebarToggle && wrapper) {
    sidebarToggle.addEventListener('click', function () {
      wrapper.classList.toggle('toggled');
      // optional: persist state between pages
      try {
        localStorage.setItem('cashcue_sidebar_toggled', wrapper.classList.contains('toggled'));
      } catch (e) { /* ignore */ }
    });

    // restore previous state on load
    try {
      const saved = localStorage.getItem('cashcue_sidebar_toggled');
      if (saved === 'true') wrapper.classList.add('toggled');
    } catch (e) { /* ignore */ }
  }

  // --- Broker account selection logic ---
  const select = document.getElementById('brokerSelect');
  if (select) {
    select.innerHTML = '<option>Loading accounts...</option>';

    fetch('/cashcue/api/getBrokerAccounts.php')
      .then(response => response.json())
      .then(accounts => {
        select.innerHTML = '<option value="all">All Accounts</option>';

        accounts.forEach(acc => {
          const opt = document.createElement('option');
          opt.value = acc.id;
          opt.textContent = acc.label;
          select.appendChild(opt);
        });

        // Restore previous selection
        const saved = localStorage.getItem('selectedAccount');
        if (saved && select.querySelector(`option[value="${saved}"]`)) {
          select.value = saved;
        }

        // Save selection + reload
        select.addEventListener('change', () => {
          localStorage.setItem('selectedAccount', select.value);
          location.reload(); // Optional: can trigger data refresh instead
        });
      })
      .catch(error => {
        console.error('Error loading broker accounts:', error);
        select.innerHTML = '<option>Error loading accounts</option>';
      });
  }
});
