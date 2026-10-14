(function () {
  var region = document.getElementById('newsRegion');
  if (!region) return;

  var selectedIds = new Set();

  function updateBulkBar() {
    var bulkBar = region.querySelector('#bulkBar');
    var bulkCount = region.querySelector('#bulkCount');
    if (bulkCount) bulkCount.textContent = selectedIds.size + ' selected';
    if (bulkBar) bulkBar.classList.toggle('show', selectedIds.size > 0);
  }

  function applySelectionToDOM() {
    var form = region.querySelector('#bulkForm');
    if (!form) return;
    form.querySelectorAll('.row-check').forEach(function (c) {
      var id = parseInt(c.getAttribute('data-id'), 10);
      c.checked = !isNaN(id) && selectedIds.has(id);
    });
    var all = form.querySelectorAll('.row-check');
    var checked = 0;
    all.forEach(function (x) { if (x.checked) checked++; });
    var selectAll = form.querySelector('#selectAll');
    if (selectAll) {
      selectAll.checked = all.length > 0 && checked === all.length;
      selectAll.indeterminate = checked > 0 && checked < all.length;
    }
    updateBulkBar();
  }

  function loadPage(params, pushState) {
    var url = new URL(window.location.origin + '/admin');
    if (params.search) url.searchParams.set('search', params.search);
    if (params.page && params.page > 1) url.searchParams.set('page', params.page);

    var fetchUrl = new URL(url.toString());
    fetchUrl.searchParams.set('ajax', '1');

    region.classList.add('is-loading');

    fetch(fetchUrl.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.text();
      })
      .then(function (html) {
        region.innerHTML = html;
        region.classList.remove('is-loading');
        bindBulk();
        if (pushState !== false) {
          history.pushState(
            { search: params.search || '', page: params.page || 1 },
            '',
            url.toString()
          );
        }
      })
      .catch(function () {
        region.classList.remove('is-loading');
        window.location.href = url.toString();
      });
  }

  region.addEventListener('click', function (e) {
    var link = e.target.closest('.pagination-links a[data-page]');
    if (!link) return;
    e.preventDefault();
    var p = parseInt(link.getAttribute('data-page'), 10) || 1;
    var searchInput = region.querySelector('#searchInput');
    var s = searchInput ? searchInput.value.trim() : '';
    loadPage({ search: s, page: p });
  });

  region.addEventListener('input', function (e) {
    if (!e.target.matches('#searchInput')) return;
    var searchInput = e.target;
    clearTimeout(searchInput._t);
    searchInput._t = setTimeout(function () {
      loadPage({ search: searchInput.value.trim(), page: 1 });
    }, 1000);
  });

  function bindBulk() {
    var form = region.querySelector('#bulkForm');
    if (!form) return;

    var selectAll = form.querySelector('#selectAll');
    var bulkClear = form.querySelector('#bulkClear');
    var rowChecks = form.querySelectorAll('.row-check');

    rowChecks.forEach(function (c) {
      if (c._bound) return;
      c._bound = true;
      c.addEventListener('change', function () {
        var id = parseInt(c.getAttribute('data-id'), 10);
        if (isNaN(id)) return;
        if (c.checked) selectedIds.add(id);
        else selectedIds.delete(id);
        var all = form.querySelectorAll('.row-check');
        var checked = 0;
        all.forEach(function (x) { if (x.checked) checked++; });
        if (selectAll) {
          selectAll.checked = all.length > 0 && checked === all.length;
          selectAll.indeterminate = checked > 0 && checked < all.length;
        }
        updateBulkBar();
      });
    });

    if (selectAll && !selectAll._bound) {
      selectAll._bound = true;
      selectAll.addEventListener('change', function () {
        var all = form.querySelectorAll('.row-check');
        all.forEach(function (c) {
          c.checked = selectAll.checked;
          var id = parseInt(c.getAttribute('data-id'), 10);
          if (isNaN(id)) return;
          if (selectAll.checked) selectedIds.add(id);
          else selectedIds.delete(id);
        });
        updateBulkBar();
      });
    }

    if (bulkClear && !bulkClear._bound) {
      bulkClear._bound = true;
      bulkClear.addEventListener('click', function () {
        selectedIds.clear();
        applySelectionToDOM();
      });
    }

    applySelectionToDOM();
  }

  document.addEventListener('submit', function (e) {
    if (!e.target.matches('#bulkForm')) return;
    var hidden = document.getElementById('hiddenIds');
    if (!hidden) return;
    hidden.innerHTML = '';

    var visibleIds = new Set();
    e.target.querySelectorAll('.row-check').forEach(function (c) {
      var id = parseInt(c.getAttribute('data-id'), 10);
      if (!isNaN(id)) visibleIds.add(id);
    });

    selectedIds.forEach(function (id) {
      if (!visibleIds.has(id)) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = String(id);
        hidden.appendChild(input);
      }
    });
  });

  window.addEventListener('popstate', function (e) {
    var state = e.state || {};
    loadPage({ search: state.search || '', page: state.page || 1 }, false);
  });

  bindBulk();
})();

function confirmBulkDelete(e) {
  var form = document.getElementById('bulkForm');
  if (!form) return true;
  var visible = form.querySelectorAll('.row-check:checked').length;
  var hidden = form.querySelectorAll('#hiddenIds input').length;
  var total = visible + hidden;
  if (total === 0) {
    e.preventDefault();
    return false;
  }
  return confirm('Delete ' + total + ' selected news item' + (total === 1 ? '' : 's') + '? This cannot be undone.');
}