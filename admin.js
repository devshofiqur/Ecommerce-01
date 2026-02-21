// ============================================================
// Dunrovin Group — Admin JavaScript
// ============================================================

(function () {
  'use strict';

  // ----------------------------------------------------------
  // Rich text editor (contenteditable + execCommand)
  // ----------------------------------------------------------
  const editor     = document.getElementById('body-editor');
  const bodyInput  = document.getElementById('body-input');
  const toolbar    = document.getElementById('editor-toolbar');

  if (editor && bodyInput && toolbar) {
    // Sync hidden input before form submit
    const form = editor.closest('form');
    if (form) {
      form.addEventListener('submit', () => {
        bodyInput.value = editor.innerHTML;
      });
    }

    // Toolbar button actions
    toolbar.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-cmd]');
      if (!btn) return;
      e.preventDefault();

      const cmd = btn.dataset.cmd;
      const val = btn.dataset.val || null;

      if (cmd === 'createLink') {
        const url = prompt('Enter URL:');
        if (url) document.execCommand('createLink', false, url);
      } else {
        document.execCommand(cmd, false, val);
      }
      editor.focus();
    });

    // Paste as plain text to avoid style bleed
    editor.addEventListener('paste', (e) => {
      e.preventDefault();
      const text = (e.clipboardData || window.clipboardData).getData('text/plain');
      document.execCommand('insertText', false, text);
    });
  }

  // ----------------------------------------------------------
  // Auto-generate slug from title
  // ----------------------------------------------------------
  const titleInput = document.getElementById('title');
  const slugInput  = document.getElementById('slug');

  if (titleInput && slugInput) {
    let slugManuallyEdited = slugInput.value.length > 0;

    titleInput.addEventListener('input', () => {
      if (slugManuallyEdited) return;
      slugInput.value = slugify(titleInput.value);
    });

    slugInput.addEventListener('input', () => {
      slugManuallyEdited = slugInput.value.length > 0;
    });

    function slugify(str) {
      return str
        .toLowerCase()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
    }
  }

  // ----------------------------------------------------------
  // Status field: toggle published_at / scheduled_at
  // ----------------------------------------------------------
  const statusSelect    = document.getElementById('status');
  const publishedGroup  = document.getElementById('published-at-group');
  const scheduledGroup  = document.getElementById('scheduled-at-group');

  if (statusSelect) {
    const toggleDateFields = () => {
      const s = statusSelect.value;
      if (publishedGroup)  publishedGroup.hidden  = s === 'scheduled';
      if (scheduledGroup) scheduledGroup.hidden = s !== 'scheduled';
    };
    statusSelect.addEventListener('change', toggleDateFields);
    toggleDateFields();
  }

  // ----------------------------------------------------------
  // Image preview
  // ----------------------------------------------------------
  const fileInput   = document.getElementById('featured_image');
  const previewWrap = document.getElementById('imagePreview');
  const previewImg  = document.getElementById('previewImg');

  if (fileInput && previewWrap && previewImg) {
    fileInput.addEventListener('change', () => {
      const file = fileInput.files[0];
      if (!file) { previewWrap.hidden = true; return; }
      const reader = new FileReader();
      reader.onload = (e) => {
        previewImg.src  = e.target.result;
        previewWrap.removeAttribute('hidden');
      };
      reader.readAsDataURL(file);
    });
  }

  // ----------------------------------------------------------
  // Character counters
  // ----------------------------------------------------------
  document.querySelectorAll('[maxlength]').forEach((input) => {
    const hint = input.nextElementSibling;
    if (!hint || !hint.classList.contains('char-count')) return;
    const max  = input.getAttribute('maxlength');
    const update = () => { hint.textContent = `${input.value.length} / ${max}`; };
    input.addEventListener('input', update);
    update();
  });

  // ----------------------------------------------------------
  // Auto-dismiss alerts after 4s
  // ----------------------------------------------------------
  document.querySelectorAll('.alert').forEach((el) => {
    setTimeout(() => {
      el.style.transition = 'opacity .5s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 500);
    }, 4000);
  });

})();
