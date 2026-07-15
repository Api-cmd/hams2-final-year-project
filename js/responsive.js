
(function() {
  'use strict';

  // ─── SIDEBAR / MOBILE NAV TOGGLE ───
  function initSidebarToggle() {
    const toggle = document.getElementById('sidebar-toggle');
    if (!toggle) return;

    // Check if we have a sidebar or a mobile-nav-dropdown
    const sidebar = document.querySelector('.sidebar');
    const dropdown = document.getElementById('mobile-nav-dropdown');

    // If neither sidebar nor dropdown exists, nothing to toggle
    if (!sidebar && !dropdown) return;

    // If dropdown exists (patient pages), use dropdown logic
    if (dropdown && !sidebar) {
      toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('open');
      });

      // Close when clicking outside
      document.addEventListener('click', function(e) {
        if (!dropdown.contains(e.target) && e.target !== toggle && !toggle.contains(e.target)) {
          dropdown.classList.remove('open');
        }
      });

      // Close on link click
      dropdown.querySelectorAll('a').forEach(function(link) {
        link.addEventListener('click', function() {
          dropdown.classList.remove('open');
        });
      });

      // Close on Escape
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          dropdown.classList.remove('open');
        }
      });

      return;
    }

    // Create overlay if not exists
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'sidebar-overlay';
      document.body.appendChild(overlay);
    }

    function openSidebar() {
      sidebar.classList.add('sidebar-open');
      overlay.classList.add('visible');
      overlay.style.display = 'block';
      document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
      sidebar.classList.remove('sidebar-open');
      overlay.classList.remove('visible');
      overlay.classList.add('closing');
      document.body.style.overflow = '';
      setTimeout(() => {
        overlay.style.display = 'none';
        overlay.classList.remove('closing');
      }, 280);
    }

    toggle.addEventListener('click', function(e) {
      e.stopPropagation();
      if (sidebar.classList.contains('sidebar-open')) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });

    // Close on overlay click
    overlay.addEventListener('click', closeSidebar);

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && sidebar.classList.contains('sidebar-open')) {
        closeSidebar();
      }
    });

    // Close when clicking a sidebar link (for single-page navigation)
    sidebar.querySelectorAll('a').forEach(function(link) {
      link.addEventListener('click', function() {
        if (window.innerWidth < 1024) {
          closeSidebar();
        }
      });
    });

    // Handle resize
    let resizeTimer;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function() {
        if (window.innerWidth >= 1024 && sidebar.classList.contains('sidebar-open')) {
          closeSidebar();
        }
      }, 200);
    });
  }

  // ─── USER DROPDOWN ON MOBILE ───
  function initUserMenu() {
    const userLink = document.querySelector('.navbar-user-link');
    if (!userLink) return;
    // On mobile, clicking user link shows a small dropdown
    // For now, just ensure it's accessible
  }

  // ─── RESPONSIVE TABLE HELPER ───
  // Adds data-label attributes to table cells based on header text
  function initResponsiveTables() {
    const tables = document.querySelectorAll('.table-wrap table');
    tables.forEach(function(table) {
      // Skip if already processed
      if (table.hasAttribute('data-rprocessed')) return;
      table.setAttribute('data-rprocessed', 'true');

      const headers = [];
      const thead = table.querySelector('thead');
      if (!thead) return;

      thead.querySelectorAll('th').forEach(function(th) {
        headers.push(th.textContent.trim());
      });

      // Add data-label to each td
      table.querySelectorAll('tbody tr').forEach(function(row) {
        row.querySelectorAll('td').forEach(function(td, index) {
          if (headers[index] && !td.hasAttribute('data-label')) {
            td.setAttribute('data-label', headers[index]);
          }
        });
      });
    });
  }

  // ─── WATCH FOR DYNAMIC TABLE CONTENT ───
  function watchForTableUpdates() {
    const observer = new MutationObserver(function() {
      initResponsiveTables();
    });
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

  // ─── FIX INPUT ZOOM ON IOS ───
  function initIOSFix() {
    // Font size 16px prevents auto-zoom on iOS
    // Already handled in CSS
  }

  // ─── ENSURE MAIN CONTENT HAS PROPER PADDING ───
  function fixMainContent() {
    // Add table-responsive-cards class to table-wraps on mobile
    function checkWidth() {
      const wraps = document.querySelectorAll('.table-wrap');
      wraps.forEach(function(wrap) {
        if (window.innerWidth <= 767) {
          wrap.classList.add('table-responsive-cards');
        } else {
          wrap.classList.remove('table-responsive-cards');
        }
      });
    }

    checkWidth();
    let resizeTimer;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(checkWidth, 150);
    });
  }

  // ─── INIT ───
  document.addEventListener('DOMContentLoaded', function() {
    initSidebarToggle();
    initUserMenu();
    initResponsiveTables();
    watchForTableUpdates();
    fixMainContent();
    initIOSFix();
  });

  // Also run after any dynamic content load (for admin pages)
  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    setTimeout(function() {
      initResponsiveTables();
      fixMainContent();
    }, 500);
  }

})();