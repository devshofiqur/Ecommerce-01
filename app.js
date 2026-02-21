// ============================================================
// Dunrovin Group — Public JavaScript
// Zero-dependency, minimal, performance-first
// ============================================================

(function () {
  'use strict';

  // ----------------------------------------------------------
  // Mobile menu toggle
  // ----------------------------------------------------------
  const menuBtn = document.querySelector('.btn-menu');
  const mobileMenu = document.getElementById('mobile-menu');

  if (menuBtn && mobileMenu) {
    menuBtn.addEventListener('click', () => {
      const isOpen = menuBtn.getAttribute('aria-expanded') === 'true';
      menuBtn.setAttribute('aria-expanded', String(!isOpen));
      if (isOpen) {
        mobileMenu.hidden = true;
      } else {
        mobileMenu.removeAttribute('hidden');
      }
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
      if (!menuBtn.contains(e.target) && !mobileMenu.contains(e.target)) {
        menuBtn.setAttribute('aria-expanded', 'false');
        mobileMenu.hidden = true;
      }
    });
  }

  // ----------------------------------------------------------
  // Reading progress bar
  // ----------------------------------------------------------
  const progressBar = document.getElementById('readingProgress');
  const articleBody = document.querySelector('.article-body');

  if (progressBar && articleBody) {
    const updateProgress = () => {
      const docHeight   = document.documentElement.scrollHeight - window.innerHeight;
      const scrolled    = window.scrollY;
      const pct         = docHeight > 0 ? Math.min(100, (scrolled / docHeight) * 100) : 100;
      progressBar.style.width = pct + '%';
    };

    window.addEventListener('scroll', updateProgress, { passive: true });
    updateProgress();
  }

  // ----------------------------------------------------------
  // Lazy-load font (progressive enhancement)
  // ----------------------------------------------------------
  if ('fonts' in document) {
    document.fonts.load('1em Lora').then(() => {
      document.documentElement.classList.add('fonts-loaded');
    });
  }

  // ----------------------------------------------------------
  // Search autofocus on search page
  // ----------------------------------------------------------
  const searchInput = document.getElementById('search-input');
  if (searchInput && !searchInput.value) {
    // Only autofocus if no query so cursor doesn't jump
    searchInput.focus();
  }

})();
