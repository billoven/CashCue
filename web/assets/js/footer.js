// /assets/js/footer.js

document.addEventListener('DOMContentLoaded', function () {

  // --- Sidebar toggle ---
  const sidebarToggle = document.getElementById('menuToggle');
  const wrapper = document.getElementById('wrapper');

  if (sidebarToggle && wrapper) {
    sidebarToggle.addEventListener('click', function () {
      wrapper.classList.toggle('toggled');

      try {
        localStorage.setItem('cashcue_sidebar_toggled',
          wrapper.classList.contains('toggled')
        );
      } catch (e) {}
    });

    try {
      const saved = localStorage.getItem('cashcue_sidebar_toggled');
      if (saved === 'true') wrapper.classList.add('toggled');
    } catch (e) {}
  }

  
});

