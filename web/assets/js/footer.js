// /assets/js/footer.js

// ------------------------------------------------
// Footer JS
// ------------------------------------------------
// This file is included at the end of the page and can be used for any JavaScript that needs to run after the main content has loaded.
// It is a good place to put any global event listeners, utility functions, or initialization code that should run on every page.
// Since it is loaded after all other scripts, it can also be used to safely call functions defined in other JS files without worrying about load order.
// Example usage:
// document.addEventListener('DOMContentLoaded', function() {
//     console.log("Footer JS loaded and DOM is ready");
//     // You can add more code here that needs to run after the page has fully loaded
// });
// ------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {

  // --- Sidebar toggle ---
  const sidebarToggle = document.getElementById('menuToggle');
  const wrapper = document.getElementById('wrapper');

  // Load saved sidebar state from localStorage and apply it
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

