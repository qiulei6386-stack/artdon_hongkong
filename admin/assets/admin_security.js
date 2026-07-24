(() => {
  'use strict';

  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
      const message = form.dataset.confirm || '确定继续？';
      if (!window.confirm(message)) event.preventDefault();
    });
  });

  document.querySelector('[data-reset-overrides]')?.addEventListener('click', () => {
    document.querySelectorAll('select[name^="permission_override"]').forEach(select => { select.value = 'inherit'; });
  });

  document.querySelectorAll('[data-permission-group-toggle]').forEach(button => {
    button.addEventListener('click', () => button.closest('.permission-group')?.classList.toggle('is-collapsed'));
  });

  document.querySelectorAll('[data-role-group-check]').forEach(groupCheck => {
    const section = groupCheck.closest('section');
    const children = [...section.querySelectorAll('input[name="permissions[]"]')];
    const sync = () => {
      const checked = children.filter(input => input.checked).length;
      groupCheck.checked = checked > 0 && checked === children.length;
      groupCheck.indeterminate = checked > 0 && checked < children.length;
    };
    groupCheck.addEventListener('change', () => children.forEach(input => { if (!input.disabled) input.checked = groupCheck.checked; }));
    children.forEach(input => input.addEventListener('change', sync));
    sync();
  });

  document.querySelector('[data-check-all-permissions]')?.addEventListener('click', () => {
    document.querySelectorAll('input[name="permissions[]"]:not(:disabled)').forEach(input => { input.checked = true; input.dispatchEvent(new Event('change')); });
  });
  document.querySelector('[data-uncheck-all-permissions]')?.addEventListener('click', () => {
    document.querySelectorAll('input[name="permissions[]"]:not(:disabled)').forEach(input => { input.checked = false; input.dispatchEvent(new Event('change')); });
  });

  const cleanup = document.querySelector('[data-log-cleanup]');
  const openCleanup = () => { if (cleanup) { cleanup.hidden = false; document.body.style.overflow = 'hidden'; } };
  const closeCleanup = () => { if (cleanup) { cleanup.hidden = true; document.body.style.overflow = ''; } };
  document.querySelector('[data-log-cleanup-open]')?.addEventListener('click', openCleanup);
  document.querySelectorAll('[data-log-cleanup-close]').forEach(button => button.addEventListener('click', closeCleanup));
  document.addEventListener('keydown', event => { if (event.key === 'Escape' && cleanup && !cleanup.hidden) closeCleanup(); });
})();
