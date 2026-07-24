(() => {
  'use strict';

  const shell = document.querySelector('[data-admin-shell]');
  if (!shell) return;

  const storage = {
    get(key, fallback = '') {
      try { return localStorage.getItem(key) ?? fallback; } catch (_) { return fallback; }
    },
    set(key, value) {
      try { localStorage.setItem(key, value); } catch (_) {}
    }
  };

  const compactKey = 'artdon.admin.v7.sidebar.compact';
  const compact = storage.get(compactKey) === '1';
  if (compact && window.matchMedia('(min-width: 1041px)').matches) {
    shell.classList.add('is-sidebar-collapsed');
  }

  document.querySelector('[data-sidebar-collapse]')?.addEventListener('click', () => {
    shell.classList.toggle('is-sidebar-collapsed');
    storage.set(compactKey, shell.classList.contains('is-sidebar-collapsed') ? '1' : '0');
  });

  const openMobile = () => {
    shell.classList.add('is-mobile-open');
    document.body.style.overflow = 'hidden';
  };
  const closeMobile = () => {
    shell.classList.remove('is-mobile-open');
    document.body.style.overflow = '';
  };
  document.querySelector('[data-sidebar-open]')?.addEventListener('click', openMobile);
  document.querySelector('[data-sidebar-close]')?.addEventListener('click', closeMobile);
  document.querySelector('[data-sidebar-backdrop]')?.addEventListener('click', closeMobile);
  window.addEventListener('resize', () => {
    if (window.innerWidth > 1040) closeMobile();
  });

  document.querySelectorAll('[data-nav-group]').forEach(group => {
    const key = `artdon.admin.v7.nav.${group.dataset.navGroup}`;
    const toggle = group.querySelector('[data-nav-group-toggle]');
    const saved = storage.get(key);
    if (saved === '0' && !group.classList.contains('is-current')) {
      group.classList.add('is-collapsed');
      toggle?.setAttribute('aria-expanded', 'false');
    }
    toggle?.addEventListener('click', () => {
      const collapsed = group.classList.toggle('is-collapsed');
      toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      storage.set(key, collapsed ? '0' : '1');
    });
  });

  document.querySelectorAll('.admin-nav-link').forEach(link => {
    link.addEventListener('click', () => {
      if (window.innerWidth <= 1040) closeMobile();
    });
  });

  const userTrigger = document.querySelector('[data-user-menu-toggle]');
  const userMenu = document.querySelector('[data-user-menu]');
  const closeUserMenu = () => {
    if (!userMenu || !userTrigger) return;
    userMenu.hidden = true;
    userTrigger.setAttribute('aria-expanded', 'false');
  };
  userTrigger?.addEventListener('click', event => {
    event.stopPropagation();
    if (!userMenu) return;
    userMenu.hidden = !userMenu.hidden;
    userTrigger.setAttribute('aria-expanded', userMenu.hidden ? 'false' : 'true');
  });
  document.addEventListener('click', event => {
    if (!event.target.closest('.admin-user-menu-wrap')) closeUserMenu();
  });

  const command = document.querySelector('[data-command]');
  const commandInput = document.querySelector('[data-command-input]');
  const commandItems = [...document.querySelectorAll('[data-command-item]')];
  const commandEmpty = document.querySelector('[data-command-empty]');
  let selectedIndex = -1;

  const visibleItems = () => commandItems.filter(item => !item.hidden);
  const updateSelection = () => {
    commandItems.forEach(item => item.classList.remove('is-selected'));
    const items = visibleItems();
    if (!items.length) { selectedIndex = -1; return; }
    selectedIndex = Math.max(0, Math.min(selectedIndex, items.length - 1));
    items[selectedIndex].classList.add('is-selected');
    items[selectedIndex].scrollIntoView({ block: 'nearest' });
  };
  const filterCommand = () => {
    const term = (commandInput?.value || '').trim().toLowerCase();
    let count = 0;
    commandItems.forEach(item => {
      const show = !term || (item.dataset.commandText || '').includes(term);
      item.hidden = !show;
      if (show) count++;
    });
    if (commandEmpty) commandEmpty.hidden = count !== 0;
    selectedIndex = count ? 0 : -1;
    updateSelection();
  };
  const openCommand = () => {
    if (!command) return;
    closeUserMenu();
    command.hidden = false;
    command.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    if (commandInput) {
      commandInput.value = '';
      filterCommand();
      requestAnimationFrame(() => commandInput.focus());
    }
  };
  const closeCommand = () => {
    if (!command) return;
    command.hidden = true;
    command.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    selectedIndex = -1;
  };

  document.querySelector('[data-command-open]')?.addEventListener('click', openCommand);
  document.querySelectorAll('[data-command-close]').forEach(button => button.addEventListener('click', closeCommand));
  commandInput?.addEventListener('input', filterCommand);
  commandItems.forEach(item => item.addEventListener('mouseenter', () => {
    selectedIndex = visibleItems().indexOf(item);
    updateSelection();
  }));

  document.addEventListener('keydown', event => {
    const isShortcut = (event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k';
    if (isShortcut) {
      event.preventDefault();
      command?.hidden ? openCommand() : closeCommand();
      return;
    }
    if (!command || command.hidden) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeCommand();
    } else if (event.key === 'ArrowDown') {
      event.preventDefault();
      selectedIndex++;
      updateSelection();
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      selectedIndex--;
      updateSelection();
    } else if (event.key === 'Enter') {
      const item = visibleItems()[selectedIndex];
      if (item) {
        event.preventDefault();
        window.location.href = item.href;
      }
    }
  });

  // Keep active editing position when the browser itself reloads a backend page.
  const scrollKey = `artdon.admin.v7.scroll.${location.pathname}${location.search}`;
  let restoreTimer = null;
  const saveScroll = () => {
    if (restoreTimer) cancelAnimationFrame(restoreTimer);
    restoreTimer = requestAnimationFrame(() => {
      try { sessionStorage.setItem(scrollKey, String(window.scrollY)); } catch (_) {}
    });
  };
  window.addEventListener('scroll', saveScroll, { passive: true });
  window.addEventListener('beforeunload', saveScroll);
  const navigation = performance.getEntriesByType?.('navigation')?.[0];
  if (navigation?.type === 'reload') {
    let y = 0;
    try { y = Number(sessionStorage.getItem(scrollKey) || 0); } catch (_) {}
    if (y > 0) {
      const restore = () => window.scrollTo({ top: y, left: 0, behavior: 'auto' });
      requestAnimationFrame(restore);
      setTimeout(restore, 120);
      setTimeout(restore, 420);
    }
  }
})();
