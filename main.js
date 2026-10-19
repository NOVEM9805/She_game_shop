document.addEventListener('DOMContentLoaded', () => {
  // Native dialogs provide an overlay, focus trapping and Escape handling.
  document.querySelectorAll('dialog.edit-dialog').forEach((dialog) => {
    let trigger;
    const openDialog = (opener) => {
      if (dialog.open) return;
      trigger = opener || document.activeElement;
      dialog.showModal();
      document.body.classList.add('dialog-open');
      dialog.querySelector('.form-errors')?.setAttribute('tabindex', '-1');
      (dialog.querySelector('.form-errors') || dialog.querySelector('input:not([type="hidden"]):not([disabled])'))?.focus();
    };
    document.querySelectorAll('[data-dialog-open]').forEach((opener) => {
      if (opener.dataset.dialogOpen === dialog.id) opener.addEventListener('click', () => openDialog(opener));
    });
    dialog.querySelectorAll('[data-dialog-close]').forEach((closer) => {
      closer.addEventListener('click', () => dialog.close());
    });
    let backdropPress = false;
    const outsideDialog = (event) => {
      const rect = dialog.getBoundingClientRect();
      return event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom;
    };
    dialog.addEventListener('pointerdown', (event) => { backdropPress = event.target === dialog && outsideDialog(event); });
    dialog.addEventListener('click', (event) => {
      if (backdropPress && event.target === dialog && outsideDialog(event)) dialog.close();
      backdropPress = false;
    });
    dialog.addEventListener('close', () => {
      if (!document.querySelector('dialog.edit-dialog[open]')) document.body.classList.remove('dialog-open');
      dialog.querySelectorAll('form').forEach((form) => form.reset());
      dialog.querySelectorAll('[data-preview-input]').forEach((input) => {
        const preview = document.querySelector(input.dataset.previewInput);
        if (preview?.dataset.originalSrc) preview.src = preview.dataset.originalSrc;
      });
      trigger?.focus();
    });
    dialog.querySelectorAll('[data-preview-input]').forEach((input) => {
      const preview = document.querySelector(input.dataset.previewInput);
      if (preview) preview.dataset.originalSrc = preview.getAttribute('src');
    });
    // Only a failed form submission reopens its dialog. Normal page visits stay in view mode.
    if (dialog.hasAttribute('data-dialog-reopen')) openDialog();
  });

  const button = document.querySelector('[data-menu-toggle]');
  const menu = document.querySelector('[data-menu]');
  button?.addEventListener('click', () => menu?.classList.toggle('open'));

  const searchPanel = document.querySelector('[data-search-panel]');
  const liveSearchForm = document.querySelector('[data-live-search-form]');
  const liveSearchInput = document.querySelector('[data-live-search-input]');
  const liveSearchResults = document.querySelector('[data-live-search-results]');
  const openSearch = () => {
    if (!searchPanel) return;
    searchPanel.hidden = false;
    searchPanel.querySelector('input')?.focus();
    menu?.classList.remove('open');
  };
  document.querySelectorAll('[data-search-toggle]').forEach((trigger) => trigger.addEventListener('click', openSearch));
  const closeSearch = () => {
    if (searchPanel) searchPanel.hidden = true;
    if (liveSearchResults) { liveSearchResults.hidden = true; liveSearchResults.replaceChildren(); }
    liveSearchInput?.setAttribute('aria-expanded', 'false');
  };
  document.querySelector('[data-search-close]')?.addEventListener('click', closeSearch);
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeSearch();
  });

  let searchTimer;
  let searchController;
  const showSearchMessage = (message) => {
    if (!liveSearchResults) return;
    const item = document.createElement('div');
    item.className = 'live-search-empty';
    item.textContent = message;
    liveSearchResults.replaceChildren(item);
    liveSearchResults.hidden = false;
    liveSearchInput?.setAttribute('aria-expanded', 'true');
  };
  liveSearchInput?.addEventListener('input', () => {
    window.clearTimeout(searchTimer);
    searchController?.abort();
    const query = liveSearchInput.value.trim();
    if (!query) {
      liveSearchResults?.replaceChildren();
      if (liveSearchResults) liveSearchResults.hidden = true;
      liveSearchInput.setAttribute('aria-expanded', 'false');
      return;
    }
    showSearchMessage('Searching…');
    searchTimer = window.setTimeout(async () => {
      try {
        searchController = new AbortController();
        const endpoint = `${liveSearchForm.dataset.searchUrl}?q=${encodeURIComponent(query)}`;
        const response = await fetch(endpoint, { headers: { Accept: 'application/json' }, signal: searchController.signal });
        if (!response.ok) throw new Error('Search failed');
        const data = await response.json();
        if (liveSearchInput.value.trim() !== query) return;
        if (!data.games?.length) { showSearchMessage('No games found'); return; }
        const fragment = document.createDocumentFragment();
        data.games.forEach((game) => {
          const link = document.createElement('a');
          link.className = 'live-search-item';
          link.href = game.url;
          const image = document.createElement('img');
          image.src = game.image;
          image.alt = '';
          const text = document.createElement('span');
          const name = document.createElement('strong');
          name.textContent = game.name;
          const description = document.createElement('small');
          description.textContent = game.description;
          text.append(name, description);
          link.append(image, text);
          fragment.append(link);
        });
        liveSearchResults.replaceChildren(fragment);
        liveSearchResults.hidden = false;
        liveSearchInput.setAttribute('aria-expanded', 'true');
      } catch (error) {
        if (error.name !== 'AbortError') showSearchMessage('Search is temporarily unavailable');
      }
    }, 160);
  });
  document.addEventListener('click', (event) => {
    if (searchPanel && !searchPanel.hidden && !searchPanel.contains(event.target) && !event.target.closest('[data-search-toggle]')) closeSearch();
  });

  document.querySelectorAll('[data-confirm]').forEach((element) => {
    element.addEventListener('click', (event) => {
      if (!window.confirm(element.dataset.confirm || 'Are you sure?')) event.preventDefault();
    });
  });

  const paymentCards = document.querySelectorAll('[data-payment-card]');
  const paymentInput = document.querySelector('[name="payment_method"]');
  paymentCards.forEach((card) => card.addEventListener('click', () => {
    paymentCards.forEach((item) => item.classList.remove('selected'));
    card.classList.add('selected');
    if (paymentInput) paymentInput.value = card.dataset.paymentCard;
    document.querySelectorAll('[data-payment-fields]').forEach((group) => {
      group.hidden = group.dataset.paymentFields !== card.dataset.paymentCard;
    });
  }));

  document.querySelectorAll('[data-preview-input]').forEach((input) => {
    input.addEventListener('change', () => {
      const preview = document.querySelector(input.dataset.previewInput);
      if (preview && input.files?.[0]) preview.src = URL.createObjectURL(input.files[0]);
    });
  });

  const slider = document.querySelector('[data-slider]');
  if (slider) {
    const slides = [...slider.querySelectorAll('[data-slide]')];
    const dots = [...slider.querySelectorAll('[data-slide-dot]')];
    let current = 0;
    let timer;
    let touchStart = 0;
    const show = (index) => {
      if (!slides.length) return;
      current = (index + slides.length) % slides.length;
      slides.forEach((slide, i) => slide.classList.toggle('active', i === current));
      dots.forEach((dot, i) => dot.classList.toggle('active', i === current));
    };
    const restart = () => {
      window.clearInterval(timer);
      if (slides.length > 1) timer = window.setInterval(() => show(current + 1), 5500);
    };
    slider.querySelector('[data-slide-prev]')?.addEventListener('click', () => { show(current - 1); restart(); });
    slider.querySelector('[data-slide-next]')?.addEventListener('click', () => { show(current + 1); restart(); });
    dots.forEach((dot) => dot.addEventListener('click', () => { show(Number(dot.dataset.slideDot)); restart(); }));
    slider.addEventListener('touchstart', (event) => { touchStart = event.changedTouches[0].clientX; }, { passive: true });
    slider.addEventListener('touchend', (event) => {
      const distance = event.changedTouches[0].clientX - touchStart;
      if (Math.abs(distance) > 45) { show(current + (distance < 0 ? 1 : -1)); restart(); }
    }, { passive: true });
    slider.addEventListener('mouseenter', () => window.clearInterval(timer));
    slider.addEventListener('mouseleave', restart);
    restart();
  }
});
