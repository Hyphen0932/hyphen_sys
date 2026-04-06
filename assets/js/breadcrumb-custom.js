/* ========================= Custom Breadcrumb Steps ========================= */

(function () {
  var breadcrumb = document.getElementById('breadcrumb-steps');
  var resetButton = document.getElementById('breadcrumb-reset-button');
  var storageKey = 'hyphen_breadcrumb_history';
  var maxItems = 10;
  var mobileBreakpoint = 768;
  var appBasePath = resolveAppBasePath();
  var registeredEntries = getRegisteredBreadcrumbEntries();
  var homeEntry = {
    url: appBasePath + '/pages/home/user_dashboard',
    label: 'Home'
  };

  if (!breadcrumb) {
    return;
  }

  if (breadcrumb.dataset.maxItems) {
    maxItems = parseInt(breadcrumb.dataset.maxItems, 10) || maxItems;
  }

  migrateHistory();

  var currentEntry = buildCurrentEntry();
  var history = readHistory();

  if (currentEntry) {
    history = appendHistory(history, currentEntry);
    history = history.slice(0, maxItems);
    writeHistory(history);
  }

  renderHistory(history, currentEntry);

  window.addEventListener('resize', debounce(function () {
    renderHistory(readHistory(), buildCurrentEntry());
  }, 120));

  breadcrumb.addEventListener('click', function (event) {
    var item = event.target.closest('.breadcrumb-steps__item[data-url]');
    if (!item) {
      return;
    }

    var targetUrl = item.getAttribute('data-url');
    if (!targetUrl) {
      return;
    }

    window.location.href = targetUrl;
  });

  breadcrumb.addEventListener('keydown', function (event) {
    var item = event.target.closest('.breadcrumb-steps__item[data-url]');
    if (!item) {
      return;
    }

    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      item.click();
    }
  });

  if (resetButton) {
    resetButton.addEventListener('click', function () {
      writeHistory([]);
      renderHistory([], null);
    });
  }

  function buildCurrentEntry() {
    var currentUrl = window.location.pathname + window.location.search;
    var normalizedCurrentUrl = normalizePath(currentUrl);
    var registeredEntry = registeredEntries[normalizedCurrentUrl] || null;

    if (!registeredEntry) {
      return null;
    }

    var entry = {
      url: currentUrl,
      label: registeredEntry.label
    };

    if (normalizePath(entry.url) === normalizePath(homeEntry.url)) {
      return null;
    }

    return entry;
  }

  function resolveCurrentLabel() {
    var currentPath = normalizePath(window.location.pathname || '');
    var sidebarLinks = document.querySelectorAll('.app-sidebar .side-menu__item[data-page-url]');

    for (var index = 0; index < sidebarLinks.length; index += 1) {
      var sidebarLink = sidebarLinks[index];
      var sidebarPath = normalizePath(sidebarLink.getAttribute('data-page-url') || '');

      if (sidebarPath === currentPath && sidebarLink.textContent) {
        return sidebarLink.textContent.trim();
      }
    }

    var activeSidebarLink = document.querySelector('.app-sidebar .side-menu__item.active[data-page-url]');
    if (activeSidebarLink && activeSidebarLink.textContent) {
      return activeSidebarLink.textContent.trim();
    }

    return document.title ? document.title.trim() : '';
  }

  function getRegisteredBreadcrumbEntries() {
    var registryScript = document.getElementById('hyphen-page-registry');
    if (registryScript && registryScript.textContent) {
      try {
        var parsedRegistry = JSON.parse(registryScript.textContent);
        if (parsedRegistry && typeof parsedRegistry === 'object') {
          return parsedRegistry;
        }
      } catch (error) {
        // Ignore invalid registry JSON and fall back to sidebar links.
      }
    }

    var entries = {};
    var sidebarLinks = document.querySelectorAll('.app-sidebar .side-menu__item[data-page-url]');

    for (var index = 0; index < sidebarLinks.length; index += 1) {
      var sidebarLink = sidebarLinks[index];
      var normalizedUrl = normalizePath(sidebarLink.getAttribute('data-page-url') || '');
      var label = sidebarLink.textContent ? sidebarLink.textContent.trim() : '';
      var breadcrumbVisible = (sidebarLink.getAttribute('data-breadcrumb-visible') || '1') !== '0';

      if (!normalizedUrl || !label || !breadcrumbVisible) {
        continue;
      }

      entries[normalizedUrl] = {
        url: buildSidebarTargetUrl(normalizedUrl),
        label: label
      };
    }

    return entries;
  }

  function buildSidebarTargetUrl(normalizedUrl) {
    var safePath = String(normalizedUrl || '').replace(/^\/+/, '');
    return appBasePath + '/pages/' + safePath;
  }

  function normalizePath(value) {
    var normalized = String(value || '').replace(/\\/g, '/').trim();
    normalized = normalized.replace(/^https?:\/\/[^/]+/i, '');
    normalized = normalized.replace(/^.*\/pages\//i, '');
    normalized = normalized.replace(/^\/+/, '');
    normalized = normalized.replace(/\.php$/i, '');
    normalized = normalized.replace(/\?.*$/, '');
    return normalized;
  }

  function resolveAppBasePath() {
    var pathname = String(window.location.pathname || '').replace(/\\/g, '/');
    var marker = '/pages/';
    var markerIndex = pathname.indexOf(marker);

    if (markerIndex === -1) {
      return '';
    }

    return pathname.substring(0, markerIndex);
  }

  function readHistory() {
    try {
      var raw = window.localStorage.getItem(storageKey);
      var parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed.filter(isValidEntry).filter(isRegisteredEntry) : [];
    } catch (error) {
      return [];
    }
  }

  function writeHistory(historyItems) {
    try {
      window.localStorage.setItem(storageKey, JSON.stringify(historyItems));
    } catch (error) {
      // Ignore storage write failures.
    }
  }

  function appendHistory(historyItems, entry) {
    var exists = historyItems.some(function (item) {
      return normalizePath(item.url) === normalizePath(entry.url);
    });

    if (exists) {
      return historyItems;
    }

    return historyItems.concat(entry);
  }

  function isValidEntry(entry) {
    return entry && typeof entry.url === 'string' && typeof entry.label === 'string' && entry.url !== '' && entry.label !== '';
  }

  function isRegisteredEntry(entry) {
    return !!registeredEntries[normalizePath(entry.url)];
  }

  function renderHistory(historyItems, currentItem) {
    var items = [homeEntry].concat(historyItems);

    if (isMobileViewport()) {
      items = buildMobileItems(items, currentItem);
    }

    breadcrumb.innerHTML = items.map(function (item, index) {
      var isCurrent = currentItem ? normalizePath(item.url) === normalizePath(currentItem.url) : index === 0;
      var classes = 'breadcrumb-steps__item' + (isCurrent ? ' breadcrumb-steps__item--active' : '');
      var attributes = 'class="' + classes + '" data-url="' + escapeHtml(item.url) + '" tabindex="0" role="link" aria-label="Go to ' + escapeHtml(item.label) + '"';

      if (isCurrent) {
        attributes += ' aria-current="page"';
      }

      return '<li ' + attributes + '>' + escapeHtml(item.label) + '</li>';
    }).join('');
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function isMobileViewport() {
    return window.innerWidth < mobileBreakpoint;
  }

  function buildMobileItems(items, currentItem) {
    if (!currentItem) {
      return [homeEntry];
    }

    var currentNormalizedUrl = normalizePath(currentItem.url);
    var currentMatch = items.find(function (item) {
      return normalizePath(item.url) === currentNormalizedUrl;
    });

    if (!currentMatch || currentNormalizedUrl === normalizePath(homeEntry.url)) {
      return [homeEntry];
    }

    return [homeEntry, currentMatch];
  }

  function debounce(callback, delay) {
    var timeoutId;

    return function () {
      window.clearTimeout(timeoutId);
      timeoutId = window.setTimeout(callback, delay);
    };
  }

  function migrateHistory() {
    var migratedHistory = readHistory().filter(function (item) {
      return normalizePath(item.url) !== normalizePath(homeEntry.url) && item.label !== 'Theme Styles';
    });

    writeHistory(migratedHistory.slice(0, maxItems));
  }
})();
