(() => {
  const lists = [...document.querySelectorAll('[data-sort-list]')];
  lists.forEach(list => {
    let dragging = null;
    const inputSelector = list.dataset.sortInput || '';
    const input = inputSelector ? document.querySelector(inputSelector) : null;
    const update = () => {
      if (!input) return;
      input.value = [...list.querySelectorAll('[data-sort-id]')]
        .map(row => row.dataset.sortId || '')
        .filter(Boolean)
        .join(',');
    };
    list.querySelectorAll('[data-sort-id]').forEach(row => {
      row.addEventListener('dragstart', event => {
        dragging = row;
        row.classList.add('is-dragging');
        if (event.dataTransfer) {
          event.dataTransfer.effectAllowed = 'move';
          event.dataTransfer.setData('text/plain', row.dataset.sortId || '');
        }
      });
      row.addEventListener('dragend', () => {
        row.classList.remove('is-dragging');
        dragging = null;
        update();
      });
    });
    list.addEventListener('dragover', event => {
      if (!dragging) return;
      event.preventDefault();
      const rows = [...list.querySelectorAll('[data-sort-id]:not(.is-dragging)')];
      const next = rows.find(row => {
        const box = row.getBoundingClientRect();
        return event.clientY < box.top + box.height / 2;
      });
      if (next) list.insertBefore(dragging, next);
      else list.appendChild(dragging);
      update();
    });
    update();
  });
})();
