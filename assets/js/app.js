(() => {
  'use strict';
  const reducedMotion = window.matchMedia
    ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
    : false;
  const canAnimate = () => Boolean(
    window.anime
    && typeof window.anime.animate === 'function'
    && !reducedMotion
  );
  const runAnimation = (target, options) => {
    if (!window.anime || !canAnimate() || !target) return null;
    try {
      return window.anime.animate(target, options);
    } catch (error) {
      console.warn('Optional interface animation was skipped.', error);
      return null;
    }
  };
  const startOpacityPulse = (element) => {
    if (!canAnimate() || !element) return () => {};
    const animation = runAnimation(element, {
      opacity: [0.55, 1],
      duration: 500,
      ease: 'inOutSine',
      alternate: true,
      loop: true,
    });
    return () => {
      if (animation && typeof animation.pause === 'function') animation.pause();
      element.style.opacity = '';
    };
  };
  const animateCatalogCards = () => {
    if (!canAnimate() || !window.anime.stagger) return;
    const cards = document.querySelectorAll('.home-resource-card');
    if (!cards.length) return;
    runAnimation(cards, {
      opacity: [0, 1],
      y: [12, 0],
      duration: 400,
      ease: 'outCubic',
      delay: window.anime.stagger(40),
    });
  };

  if (canAnimate()) {
    const hero = document.querySelector('.dashboard-hero');
    if (hero) {
      const heroItems = [
        hero.querySelector('.eyebrow'),
        hero.querySelector('h1'),
        hero.querySelector('.dashboard-welcome > p:last-of-type'),
        hero.querySelector(':scope > .button'),
      ].filter(Boolean);
      heroItems.forEach((item, index) => {
        runAnimation(item, {
          opacity: [0, 1],
          y: [10, 0],
          duration: 450,
          delay: index * 60,
          ease: 'outCubic',
        });
      });
    }

    document.querySelectorAll('[data-count-target]').forEach((counter) => {
      const target = Number(counter.dataset.countTarget);
      if (!Number.isFinite(target)) return;
      const value = { count: 0 };
      runAnimation(value, {
        count: [0, target],
        duration: 600,
        ease: 'outCubic',
        onUpdate: () => { counter.textContent = String(Math.round(value.count)); },
      });
    });

    const dashboardCards = document.querySelectorAll(
      '.dashboard-stat, .notification-list > li, .favorite-list > li, '
      + '.student-next-steps > div, .pick-card, .review-queue-card, .admin-shortcut'
    );
    if (dashboardCards.length && window.anime.stagger) {
      runAnimation(dashboardCards, {
        opacity: [0, 1],
        y: [12, 0],
        duration: 450,
        ease: 'outCubic',
        delay: window.anime.stagger(50),
      });
    }
    animateCatalogCards();

    document.querySelectorAll('.flash').forEach((message) => {
      runAnimation(message, {
        opacity: [0, 1],
        y: [-8, 0],
        duration: 300,
        ease: 'outCubic',
      });
    });

    const preview = document.querySelector('[data-animation="preview"]');
    if (preview) {
      runAnimation(preview, {
        opacity: [0, 1],
        duration: 400,
        ease: 'outCubic',
      });
    }
  }

  const toggle = document.querySelector('.nav-toggle');
  const nav = document.querySelector('#site-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', () => {
      const open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', String(open));
    });
  }

  const dashboardMenuToggle = document.querySelector('.dashboard-menu-toggle');
  const dashboardMenu = document.querySelector('.dashboard-role-menu');
  if (dashboardMenuToggle && dashboardMenu) {
    const closeDashboardMenu = () => {
      dashboardMenu.hidden = true;
      dashboardMenuToggle.setAttribute('aria-expanded', 'false');
    };

    dashboardMenuToggle.addEventListener('click', () => {
      const open = dashboardMenu.hidden;
      dashboardMenu.hidden = !open;
      dashboardMenuToggle.setAttribute('aria-expanded', String(open));
      if (open && canAnimate()) {
        runAnimation(dashboardMenu, {
          opacity: [0, 1],
          y: [-5, 0],
          duration: 250,
          ease: 'outCubic',
        });
        const links = dashboardMenu.querySelectorAll('.dashboard-menu-link');
        if (links.length && window.anime.stagger) {
          runAnimation(links, {
            opacity: [0, 1],
            y: [5, 0],
            duration: 300,
            ease: 'outCubic',
            delay: window.anime.stagger(50),
          });
        }
      }
    });

    dashboardMenu.addEventListener('click', event => {
      if (event.target.closest('a')) closeDashboardMenu();
    });

    document.addEventListener('click', event => {
      if (!dashboardMenu.hidden
        && !dashboardMenu.contains(event.target)
        && !dashboardMenuToggle.contains(event.target)) {
        closeDashboardMenu();
      }
    });

    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && !dashboardMenu.hidden) {
        closeDashboardMenu();
        dashboardMenuToggle.focus();
      }
    });
  }

  const editForm = document.querySelector('[data-edit-form]');
  if (editForm) {
    const radios = [...editForm.querySelectorAll('[data-resource-type]')];
    const fileField = editForm.querySelector('[data-file-field]');
    const linkField = editForm.querySelector('[data-link-field]');
    const syncEdit = () => {
      const type = radios.find(radio => radio.checked)?.value || 'file';
      fileField.classList.toggle('hidden', type !== 'file');
      linkField.classList.toggle('hidden', type !== 'link');
    };
    radios.forEach(radio => radio.addEventListener('change', syncEdit));
    syncEdit();
  }

  // Public catalog: fetch the JSON search API and render results without a reload.
  // The GET form still works with JavaScript off (progressive enhancement).
  const catalogForm = document.querySelector('.archive-search');
  const catalogResults = document.querySelector('#catalog-results');
  const searchStatus = document.querySelector('#search-status');
  if (catalogForm && catalogResults) {
    const el = (tag, cls, text) => {
      const n = document.createElement(tag);
      if (cls) n.className = cls;
      if (text !== undefined) n.textContent = text;
      return n;
    };
    const renderCatalog = (items) => {
      catalogResults.replaceChildren();
      if (!items.length) {
        const empty = el('div', 'home-empty');
        empty.appendChild(el('span', 'empty-search-icon'));
        empty.appendChild(el('h3', '', 'No matching resources yet'));
        empty.appendChild(el('p', '', 'Try a different search or browse another subject area.'));
        const link = el('a', 'home-text-link', 'See the full collection →');
        link.href = 'index.php#catalog';
        empty.appendChild(link);
        catalogResults.appendChild(empty);
        return;
      }
      const list = el('div', 'home-resource-grid');
      items.forEach((item) => {
        const article = el('article', 'home-resource-card');
        if (item.thumbnail_url) {
          const thumb = el('a', 'card-thumb');
          thumb.href = item.url;
          const img = document.createElement('img');
          img.src = item.thumbnail_url;
          img.alt = item.title;
          img.loading = 'lazy';
          thumb.appendChild(img);
          article.appendChild(thumb);
        } else {
          const thumb = el('a', 'card-thumb card-tile ' + (item.tile_class || 'tile-other'));
          thumb.href = item.url;
          thumb.appendChild(el('span', '', item.tile_label || 'FILE'));
          article.appendChild(thumb);
        }
        const body = el('div', 'card-body');
        const meta = el('div', 'home-resource-meta');
        meta.appendChild(el('span', '', item.category));
        meta.appendChild(el('span', 'meta-separator', '/'));
        meta.appendChild(el('span', '', item.file_type));
        body.appendChild(meta);
        const h3 = el('h3');
        const titleLink = el('a', '', item.title);
        titleLink.href = item.url;
        h3.appendChild(titleLink);
        body.appendChild(h3);
        body.appendChild(el('p', '', item.description));
        const byline = el('div', 'home-resource-byline');
        byline.appendChild(el('span', '', item.author));
        byline.appendChild(el('span', '', item.academic_year));
        body.appendChild(byline);
        article.appendChild(body);
        list.appendChild(article);
      });
      catalogResults.appendChild(list);
      animateCatalogCards();
    };
    catalogForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const params = new URLSearchParams(new FormData(catalogForm));
      if (searchStatus) searchStatus.textContent = 'Searching the archive…';
      const stopSearchPulse = startOpacityPulse(searchStatus);
      try {
        const res = await fetch('api/search.php?' + params.toString(), { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error('Search failed (' + res.status + ')');
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Search failed.');
        renderCatalog(json.data);
        if (searchStatus) searchStatus.textContent = json.count === 1 ? '1 matching resource.' : json.count + ' matching resources.';
        history.replaceState(null, '', 'index.php?' + params.toString() + '#catalog');
      } catch (err) {
        if (searchStatus) searchStatus.textContent = 'Search could not load. Please try again, or reload the page to use the plain search form.';
        catalogResults.replaceChildren();
        const empty = el('div', 'home-empty');
        empty.appendChild(el('h3', '', 'Search could not load'));
        empty.appendChild(el('p', '', 'Please try again, or reload the page to use the plain search form.'));
        catalogResults.appendChild(empty);
      } finally {
        stopSearchPulse();
      }
    });
  }

  const form = document.querySelector('[data-submit-form]');
  if (form) {
    const radios = [...form.querySelectorAll('[data-resource-type]')];
    const fileField = form.querySelector('[data-file-field]');
    const linkField = form.querySelector('[data-link-field]');
    const fileInput = form.querySelector('input[type="file"]');
    const urlInput = form.querySelector('input[name="external_url"]');
    const sync = () => {
      const type = radios.find(radio => radio.checked)?.value || 'file';
      fileField.classList.toggle('hidden', type !== 'file');
      linkField.classList.toggle('hidden', type !== 'link');
      if (fileInput) fileInput.required = type === 'file';
      if (urlInput) urlInput.required = type === 'link';
    };
    radios.forEach(radio => radio.addEventListener('change', sync));
    sync();
    // Submit via fetch: progress, success, and error states without a reload.
    // The plain form post still works with JavaScript off.
    form.addEventListener('submit', async (event) => {
      const type = radios.find(radio => radio.checked)?.value;
      if (type === 'link' && urlInput && urlInput.value) {
        try {
          const parsed = new URL(urlInput.value);
          if (!['http:', 'https:'].includes(parsed.protocol)) throw new Error('scheme');
        } catch {
          event.preventDefault();
          urlInput.setCustomValidity('Enter a valid HTTP or HTTPS URL.');
          urlInput.reportValidity();
          return;
        }
        urlInput.setCustomValidity('');
      }
      event.preventDefault();
      const submit = form.querySelector('button[type="submit"]');
      const progress = form.querySelector('.form-progress');
      const originalLabel = submit ? submit.innerHTML : '';
      const stopSubmitPulse = startOpacityPulse(submit);
      if (submit) { submit.disabled = true; submit.textContent = 'Submitting…'; }
      if (progress) progress.textContent = 'Please wait while your resource is saved.';
      try {
        const res = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
        const json = await res.json();
        stopSubmitPulse();
        if (!res.ok || !json.success) throw new Error(json.message || 'Could not save the resource.');
        if (progress) progress.textContent = json.message + ' Opening your dashboard…';
        if (submit) submit.textContent = 'Saved ✓';
        setTimeout(() => { window.location.href = json.redirect || 'dashboard.php'; }, 1200);
      } catch (err) {
        stopSubmitPulse();
        if (progress) progress.textContent = 'Could not save: ' + err.message;
        if (submit) { submit.disabled = false; submit.innerHTML = originalLabel; }
      }
    });
  }

  document.querySelectorAll('[data-favorite-form]').forEach((favoriteForm) => {
    let submittingAfterPop = false;
    favoriteForm.addEventListener('submit', (event) => {
      if (!canAnimate() || submittingAfterPop) return;
      event.preventDefault();
      const button = favoriteForm.querySelector('[data-favorite-toggle]');
      if (!button) {
        submittingAfterPop = true;
        favoriteForm.requestSubmit();
        return;
      }
      const submitNormally = () => {
        submittingAfterPop = true;
        favoriteForm.requestSubmit();
      };
      const animation = runAnimation(button, {
        scale: [1, 1.35, 1],
        duration: 300,
        ease: 'outExpo',
        onComplete: submitNormally,
      });
      if (!animation) submitNormally();
    });
  });

  // Review actions via fetch: the status badge and feedback update in place.
  // The plain form post still works with JavaScript off.
  const reviewForm = document.querySelector('[data-review-form]');
  if (reviewForm) {
    reviewForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const decision = event.submitter ? event.submitter.value : null;
      if (!decision) return;
      const statusEl = document.querySelector('#review-status');
      const badge = document.querySelector('#review-status-badge');
      const buttons = [...reviewForm.querySelectorAll('button')];
      const stopReviewPulse = startOpacityPulse(statusEl);
      buttons.forEach(b => { b.disabled = true; });
      if (statusEl) statusEl.textContent = 'Saving your decision…';
      try {
        const data = new FormData(reviewForm);
        data.set('decision', decision);
        const res = await fetch(reviewForm.action, { method: 'POST', body: data, headers: { Accept: 'application/json' } });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'Could not save the review.');
        if (badge) {
          badge.textContent = json.status_label;
          badge.className = 'status status-' + json.status;
          badge.id = 'review-status-badge';
        }
        const fb = reviewForm.querySelector('textarea[name="feedback"]');
        if (fb && json.feedback !== undefined) fb.value = json.feedback || '';
        if (statusEl) statusEl.textContent = json.message;
        const reviewItem = reviewForm.closest('[data-review-item]');
        if (reviewItem && canAnimate()) {
          runAnimation(reviewItem, {
            opacity: [1, 0],
            x: [0, 16],
            duration: 300,
            ease: 'outCubic',
            onComplete: () => reviewItem.remove(),
          });
        } else if (reviewItem) reviewItem.remove();
      } catch (err) {
        if (statusEl) statusEl.textContent = 'Could not save: ' + err.message;
      } finally {
        stopReviewPulse();
        buttons.forEach(b => { b.disabled = false; });
      }
    });
  }
})();
