/**
 * DEJOIY Seller OS - Enhanced Premium JavaScript
 * @version 3.0.0
 * @description Premium SaaS interactions: page transitions, fuzzy search,
 *   command palette, bulk ops, drag-and-drop, toasts, skeleton loading,
 *   auto-save, infinite scroll, CSV export, print, and more.
 */
(function(window, document) {
    'use strict';

    var DSO = window.DSO = {
        state: {
            sidebarOpen: false,
            searchOpen: false,
            commandOpen: false,
            settingsOpen: false,
            helpOpen: false,
            favoritesOpen: false,
            charts: {},
            searchTimeout: null,
            refreshTimer: null,
            recentSearches: [],
            recentCommands: [],
            selectedProducts: {},
            selectedCount: 0,
            searchIndex: -1,
            commandIndex: -1,
            lastClickedRow: null,
            theme: 'auto',
            infiniteScrollActive: false,
            infiniteScrollPage: 1,
            infiniteScrollLoading: false,
            infiniteScrollDone: false,
            touchStartX: 0,
            touchStartY: 0,
            swipeTarget: null,
            autoSaveTimers: {},
            toastQueue: [],
            maxToasts: 5
        },

        config: {
            apiBase: '',
            ajaxUrl: '',
            nonce: '',
            vendorId: 0,
            userId: 0,
            baseUrl: '/',
            currency: 'USD',
            searchDebounce: 300,
            refreshInterval: 60000,
            maxRecent: 10,
            autoSaveDelay: 2000,
            infiniteScrollThreshold: 200,
            pageSize: 20
        },

        init: function() {
            this.loadConfig();
            this.loadTheme();
            this.loadRecentSearches();
            this.loadRecentCommands();
            this.initSidebar();
            this.initNavChildren();
            this.initSearch();
            this.initCommandPalette();
            this.initSettings();
            this.initHelp();
            this.initFavorites();
            this.initKeyboardShortcuts();
            this.initAutoRefresh();
            this.initResponsive();
            this.initSmoothScroll();
            this.initTableSort();
            this.initPageTransitions();
            this.initSkeletonDefaults();
            this.initAutoSave();
            this.initBulkSelection();
            this.initInfiniteScroll();
            this.initPullToRefresh();
            this.initSwipeActions();
            this.initLazyLoading();
            this.initScrollToTop();
            this.initConfirmDialogs();
            this.initResponsiveTableToggle();
            this.initQuickActions();
            this.initPrintView();
            this.initThemeToggle();
            this.initKeyboardShortcutsOverlay();
            this.initFuzzySearch();
            this.initCommandCategories();
            this.initInlineEditing();
            this.initDragDropImages();
            this.initExportCSV();
            this.initPrintOrder();
            this.initStockAdjustModal();
            this.initAnalyticsDateRange();
            this.initFormValidation();
            this.initRelativeDates();
            this.initOptimisticUpdates();
            this.initAccessibility();
        },

        // ═══════════════════════════════════════════════════
        // CONFIG & HELPERS
        // ═══════════════════════════════════════════════════

        loadConfig: function() {
            if (typeof dsoData === 'undefined') return;
            var c = this.config;
            c.apiBase = dsoData.restUrl || '/wp-json/dejoiy-seller-os/v1';
            c.ajaxUrl = dsoData.ajaxUrl || '';
            c.nonce = dsoData.nonce || '';
            c.vendorId = dsoData.vendorId || 0;
            c.userId = dsoData.userId || 0;
            c.baseUrl = dsoData.baseUrl || '/';
            c.currency = dsoData.currency || 'USD';
        },

        api: function(endpoint, opts) {
            var url = this.config.apiBase + endpoint;
            var defaults = {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                }
            };
            var options = Object.assign({}, defaults, opts || {});
            return fetch(url, options).then(function(res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            });
        },

        $: function(sel, ctx) {
            return (ctx || document).querySelector(sel);
        },

        $$: function(sel, ctx) {
            return Array.from((ctx || document).querySelectorAll(sel));
        },

        debounce: function(fn, delay) {
            var timer;
            return function() {
                var args = arguments;
                var ctx = this;
                clearTimeout(timer);
                timer = setTimeout(function() { fn.apply(ctx, args); }, delay);
            };
        },

        throttle: function(fn, limit) {
            var inThrottle = false;
            return function() {
                var args = arguments;
                var ctx = this;
                if (!inThrottle) {
                    fn.apply(ctx, args);
                    inThrottle = true;
                    setTimeout(function() { inThrottle = false; }, limit);
                }
            };
        },

        escapeHTML: function(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        },

        generateId: function() {
            return 'dso_' + Math.random().toString(36).substr(2, 9);
        },

        // ═══════════════════════════════════════════════════
        // 1. SIDEBAR TOGGLE (preserved)
        // ═══════════════════════════════════════════════════

        initSidebar: function() {
            var self = this;
            document.querySelectorAll('[data-dso-toggle="sidebar"]').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.toggleSidebar();
                });
            });
            var overlay = document.getElementById('dso-sidebar-overlay');
            if (overlay) {
                overlay.addEventListener('click', function() { self.closeSidebar(); });
            }
            var sidebar = document.getElementById('dso-sidebar');
            if (sidebar) {
                sidebar.querySelectorAll('.nav-item > a').forEach(function(link) {
                    link.addEventListener('click', function() {
                        if (window.innerWidth < 1024) self.closeSidebar();
                    });
                });
            }
        },

        toggleSidebar: function() {
            this.state.sidebarOpen ? this.closeSidebar() : this.openSidebar();
        },

        openSidebar: function() {
            var sidebar = document.getElementById('dso-sidebar');
            var overlay = document.getElementById('dso-sidebar-overlay');
            if (sidebar) sidebar.classList.add('open');
            if (overlay) overlay.classList.add('active');
            document.body.classList.add('dso-sidebar-open');
            this.state.sidebarOpen = true;
        },

        closeSidebar: function() {
            var sidebar = document.getElementById('dso-sidebar');
            var overlay = document.getElementById('dso-sidebar-overlay');
            if (sidebar) sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('active');
            document.body.classList.remove('dso-sidebar-open');
            this.state.sidebarOpen = false;
        },

        // ═══════════════════════════════════════════════════
        // 2. NAV CHILDREN EXPAND (preserved)
        // ═══════════════════════════════════════════════════

        initNavChildren: function() {
            var self = this;
            document.querySelectorAll('.nav-item.has-children').forEach(function(item) {
                var link = item.querySelector(':scope > a');
                if (link) {
                    link.addEventListener('click', function(e) {
                        if (window.innerWidth < 1024) {
                            e.preventDefault();
                            self.toggleNavItem(item);
                        }
                    });
                }
                item.addEventListener('mouseenter', function() {
                    if (window.innerWidth >= 1024) item.classList.add('expanded');
                });
                item.addEventListener('mouseleave', function() {
                    if (window.innerWidth >= 1024) item.classList.remove('expanded');
                });
            });
        },

        toggleNavItem: function(item) {
            var wasExpanded = item.classList.contains('expanded');
            var siblings = item.parentElement.querySelectorAll('.nav-item.has-children.expanded');
            for (var i = 0; i < siblings.length; i++) siblings[i].classList.remove('expanded');
            if (!wasExpanded) item.classList.add('expanded');
        },

        // ═══════════════════════════════════════════════════
        // 3. UNIVERSAL SEARCH (enhanced with fuzzy)
        // ═══════════════════════════════════════════════════

        initSearch: function() {
            var self = this;
            document.querySelectorAll('[data-dso-toggle="search"]').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.openSearch();
                });
            });
            var overlay = document.getElementById('dso-search-overlay');
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) self.closeSearch();
                });
            }
            var closeBtn = document.getElementById('dso-search-close');
            if (closeBtn) closeBtn.addEventListener('click', function() { self.closeSearch(); });
            var input = document.getElementById('dso-search-input');
            if (input) {
                input.addEventListener('input', function() { self.onSearchInput(this.value); });
                input.addEventListener('keydown', function(e) { self.onSearchKeydown(e); });
            }
            this.renderRecentSearches();
        },

        openSearch: function() {
            var overlay = document.getElementById('dso-search-overlay');
            var input = document.getElementById('dso-search-input');
            if (overlay) overlay.classList.add('active');
            if (input) { input.value = ''; input.focus(); }
            this.state.searchOpen = true;
            this.state.searchIndex = -1;
            this.renderRecentSearches();
            document.body.classList.add('dso-overlay-open');
        },

        closeSearch: function() {
            var overlay = document.getElementById('dso-search-overlay');
            if (overlay) overlay.classList.remove('active');
            this.state.searchOpen = false;
            this.state.searchIndex = -1;
            document.body.classList.remove('dso-overlay-open');
        },

        onSearchInput: function(query) {
            var self = this;
            clearTimeout(this.state.searchTimeout);
            if (!query) { this.renderRecentSearches(); return; }
            if (query.length < 2) return;
            this.showSearchLoading();
            this.state.searchTimeout = setTimeout(function() {
                self.fetchSearch(query);
            }, this.config.searchDebounce);
        },

        fetchSearch: function(query) {
            var self = this;
            this.api('/search?q=' + encodeURIComponent(query))
                .then(function(data) {
                    self.addRecentSearch(query);
                    self.renderSearchResults(data, query);
                })
                .catch(function(err) {
                    self.renderSearchEmpty('Search failed. Please try again.');
                    self.toast('Search request failed', 'error');
                });
        },

        renderSearchResults: function(data, query) {
            var container = document.getElementById('dso-search-results');
            if (!container) return;
            var html = '';
            var types = ['products', 'orders'];
            var self = this;
            types.forEach(function(type) {
                var items = data[type];
                if (!items || !items.length) return;
                html += '<div class="search-group">';
                html += '<div class="search-group-title">' + type.charAt(0).toUpperCase() + type.slice(1) + '</div>';
                items.forEach(function(item) {
                    var title = item.title || item.name || ('#' + item.id);
                    var subtitle = item.subtitle || item.status || '';
                    var url = item.url || '#';
                    html += '<a href="' + self.escapeHTML(url) + '" class="search-result-item">';
                    html += '<span class="search-result-title">' + self.highlightMatch(self.escapeHTML(title), query) + '</span>';
                    if (subtitle) html += '<span class="search-result-subtitle">' + self.escapeHTML(subtitle) + '</span>';
                    html += '</a>';
                });
                html += '</div>';
            });
            if (!html) { this.renderSearchEmpty('No results found for "' + self.escapeHTML(query) + '"'); return; }
            container.innerHTML = html;
            container.classList.add('has-results');
            this.state.searchIndex = -1;
            this.bindSearchResultClicks();
        },

        renderSearchEmpty: function(msg) {
            var c = document.getElementById('dso-search-results');
            if (!c) return;
            c.innerHTML = '<div class="search-empty">' + msg + '</div>';
            c.classList.remove('has-results');
        },

        showSearchLoading: function() {
            var c = document.getElementById('dso-search-results');
            if (!c) return;
            c.innerHTML = '<div class="search-loading"><span class="dso-spinner"></span></div>';
            c.classList.add('has-results');
        },

        highlightMatch: function(text, query) {
            if (!query) return text;
            var escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return text.replace(new RegExp('(' + escaped + ')', 'gi'), '<mark>$1</mark>');
        },

        addRecentSearch: function(query) {
            var searches = this.state.recentSearches.filter(function(s) { return s !== query; });
            searches.unshift(query);
            if (searches.length > this.config.maxRecent) searches.pop();
            this.state.recentSearches = searches;
            localStorage.setItem('dso_recent_searches', JSON.stringify(searches));
        },

        loadRecentSearches: function() {
            try {
                this.state.recentSearches = JSON.parse(localStorage.getItem('dso_recent_searches') || '[]');
            } catch(e) {
                this.state.recentSearches = [];
            }
        },

        renderRecentSearches: function() {
            var container = document.getElementById('dso-search-recent');
            var results = document.getElementById('dso-search-results');
            if (!container) return;
            if (!this.state.recentSearches.length) {
                container.innerHTML = '';
                container.style.display = 'none';
                return;
            }
            var self = this;
            var html = '<div class="search-recent-title">Recent Searches</div>';
            this.state.recentSearches.forEach(function(q) {
                html += '<button class="search-recent-item" data-query="' + self.escapeHTML(q) + '">';
                html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
                html += '<span>' + self.escapeHTML(q) + '</span></button>';
            });
            container.innerHTML = html;
            container.style.display = 'block';
            if (results) results.classList.remove('has-results');
            container.querySelectorAll('.search-recent-item').forEach(function(item) {
                item.addEventListener('click', function() {
                    var q = item.getAttribute('data-query');
                    var input = document.getElementById('dso-search-input');
                    if (input) { input.value = q; input.focus(); }
                    self.onSearchInput(q);
                });
            });
        },

        onSearchKeydown: function(e) {
            var container = document.getElementById('dso-search-results');
            if (!container) return;
            var items = container.querySelectorAll('.search-result-item');
            if (!items.length) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.state.searchIndex = Math.min(this.state.searchIndex + 1, items.length - 1);
                this.highlightResult(items, this.state.searchIndex);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.state.searchIndex = Math.max(this.state.searchIndex - 1, 0);
                this.highlightResult(items, this.state.searchIndex);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (this.state.searchIndex >= 0 && items[this.state.searchIndex]) {
                    var url = items[this.state.searchIndex].getAttribute('href');
                    if (url && url !== '#') window.location.href = url;
                }
            }
        },

        highlightResult: function(items, idx) {
            for (var i = 0; i < items.length; i++) items[i].classList.remove('active');
            if (idx >= 0 && items[idx]) {
                items[idx].classList.add('active');
                items[idx].scrollIntoView({ block: 'nearest' });
            }
        },

        bindSearchResultClicks: function() {
            var self = this;
            document.querySelectorAll('#dso-search-results .search-result-item').forEach(function(item) {
                item.addEventListener('click', function(e) {
                    var url = item.getAttribute('href');
                    if (!url || url === '#') e.preventDefault();
                    else self.closeSearch();
                });
            });
        },

        // ═══════════════════════════════════════════════════
        // 4. COMMAND PALETTE (enhanced with categories)
        // ═══════════════════════════════════════════════════

        commands: [
            { id: 'add-product', label: 'Add Product', icon: '📦', action: 'addProduct', category: 'products', shortcut: '' },
            { id: 'find-order', label: 'Find Order', icon: '🔍', action: 'findOrder', category: 'orders', shortcut: '' },
            { id: 'analytics', label: 'View Analytics', icon: '📊', action: 'viewAnalytics', category: 'insights', shortcut: '' },
            { id: 'inventory', label: 'Manage Inventory', icon: '📋', action: 'manageInventory', category: 'products', shortcut: '' },
            { id: 'reports', label: 'View Reports', icon: '📈', action: 'viewReports', category: 'insights', shortcut: '' },
            { id: 'performance', label: 'Check Performance', icon: '⚡', action: 'checkPerformance', category: 'insights', shortcut: '' },
            { id: 'settings', label: 'Settings', icon: '⚙️', action: 'openSettings', category: 'system', shortcut: '' },
            { id: 'help', label: 'Help & Support', icon: '❓', action: 'openHelp', category: 'system', shortcut: '' },
            { id: 'dashboard', label: 'Go to Dashboard', icon: '🏠', action: 'goDashboard', category: 'navigation', shortcut: '' },
            { id: 'finance', label: 'View Finance', icon: '💰', action: 'goFinance', category: 'insights', shortcut: '' },
            { id: 'export-csv', label: 'Export current table to CSV', icon: '📥', action: 'exportCSV', category: 'actions', shortcut: '' },
            { id: 'toggle-theme', label: 'Toggle dark/light mode', icon: '🌓', action: 'toggleTheme', category: 'system', shortcut: '' },
            { id: 'toggle-table-view', label: 'Switch table/card view', icon: '🗂️', action: 'toggleTableView', category: 'system', shortcut: '' },
            { id: 'keyboard-shortcuts', label: 'View keyboard shortcuts', icon: '⌨️', action: 'showShortcuts', category: 'system', shortcut: '' },
            { id: 'print-view', label: 'Toggle print-friendly view', icon: '🖨️', action: 'togglePrintView', category: 'actions', shortcut: '' }
        ],

        initCommandPalette: function() {
            var self = this;
            var overlay = document.getElementById('dso-command-overlay');
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) self.closeCommand();
                });
            }
            var closeBtn = document.getElementById('dso-command-close');
            if (closeBtn) closeBtn.addEventListener('click', function() { self.closeCommand(); });
            var input = document.getElementById('dso-command-input');
            if (input) {
                input.addEventListener('input', function() { self.filterCommands(this.value); });
                input.addEventListener('keydown', function(e) { self.onCommandKeydown(e); });
            }
        },

        openCommand: function() {
            var overlay = document.getElementById('dso-command-overlay');
            var input = document.getElementById('dso-command-input');
            if (overlay) overlay.classList.add('active');
            if (input) { input.value = ''; input.focus(); }
            this.state.commandOpen = true;
            this.state.commandIndex = -1;
            this.renderCommands(this.commands);
            document.body.classList.add('dso-overlay-open');
        },

        closeCommand: function() {
            var overlay = document.getElementById('dso-command-overlay');
            if (overlay) overlay.classList.remove('active');
            this.state.commandOpen = false;
            this.state.commandIndex = -1;
            document.body.classList.remove('dso-overlay-open');
        },

        renderCommands: function(cmds) {
            var container = document.getElementById('dso-command-list');
            if (!container) return;
            if (!cmds.length) {
                container.innerHTML = '<div class="command-empty">No commands found</div>';
                return;
            }
            var self = this;
            var html = '';

            // Group by category, show recent first
            var categories = {};
            var recents = this.state.recentCommands || [];
            cmds.forEach(function(cmd) {
                var cat = cmd.category || 'other';
                if (!categories[cat]) categories[cat] = [];
                categories[cat].push(cmd);
            });

            // Render recent first
            if (recents.length) {
                html += '<div class="command-category">Recent</div>';
                recents.slice(0, 3).forEach(function(cmdId) {
                    var cmd = cmds.find(function(c) { return c.id === cmdId; });
                    if (cmd) html += self._renderCommandItem(cmd);
                });
            }

            var catLabels = {
                navigation: 'Navigation',
                products: 'Products',
                orders: 'Orders',
                insights: 'Insights',
                actions: 'Actions',
                system: 'System',
                other: 'Other'
            };
            var catOrder = ['navigation', 'products', 'orders', 'insights', 'actions', 'system', 'other'];

            catOrder.forEach(function(cat) {
                if (!categories[cat] || !categories[cat].length) return;
                html += '<div class="command-category">' + (catLabels[cat] || cat) + '</div>';
                categories[cat].forEach(function(cmd) {
                    html += self._renderCommandItem(cmd);
                });
            });

            container.innerHTML = html;
            this.state.commandIndex = -1;
            container.querySelectorAll('.command-item').forEach(function(item) {
                item.addEventListener('click', function() {
                    self.executeCommand(item.getAttribute('data-action'));
                    self.closeCommand();
                });
            });
        },

        _renderCommandItem: function(cmd) {
            var shortcut = cmd.shortcut ? '<span class="command-shortcut">' + cmd.shortcut + '</span>' : '';
            return '<button class="command-item" data-action="' + cmd.action + '" data-id="' + cmd.id + '">' +
                '<span class="command-icon">' + cmd.icon + '</span>' +
                '<span class="command-label">' + cmd.label + '</span>' +
                shortcut + '</button>';
        },

        filterCommands: function(query) {
            if (!query) { this.renderCommands(this.commands); return; }
            var lower = query.toLowerCase();
            var self = this;
            var filtered = this.commands.filter(function(cmd) {
                return self.fuzzyMatch(cmd.label, query) ||
                       cmd.category.toLowerCase().indexOf(lower) !== -1 ||
                       cmd.id.toLowerCase().indexOf(lower) !== -1;
            });
            this.renderCommands(filtered);
        },

        onCommandKeydown: function(e) {
            var items = document.querySelectorAll('#dso-command-list .command-item');
            if (!items.length) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.state.commandIndex = Math.min(this.state.commandIndex + 1, items.length - 1);
                this.highlightResult(items, this.state.commandIndex);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.state.commandIndex = Math.max(this.state.commandIndex - 1, 0);
                this.highlightResult(items, this.state.commandIndex);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (this.state.commandIndex >= 0 && items[this.state.commandIndex]) {
                    items[this.state.commandIndex].click();
                }
            }
        },

        addRecentCommand: function(cmdId) {
            var recents = this.state.recentCommands.filter(function(c) { return c !== cmdId; });
            recents.unshift(cmdId);
            if (recents.length > 5) recents.pop();
            this.state.recentCommands = recents;
            localStorage.setItem('dso_recent_commands', JSON.stringify(recents));
        },

        loadRecentCommands: function() {
            try {
                this.state.recentCommands = JSON.parse(localStorage.getItem('dso_recent_commands') || '[]');
            } catch(e) {
                this.state.recentCommands = [];
            }
        },

        executeCommand: function(action) {
            var cmd = this.commands.find(function(c) { return c.action === action; });
            if (cmd) this.addRecentCommand(cmd.id);

            var base = this.config.baseUrl;
            var routes = {
                addProduct: base + 'seller/products/add/',
                findOrder: base + 'seller/orders/',
                viewAnalytics: base + 'seller/analytics/',
                manageInventory: base + 'seller/inventory/',
                viewReports: base + 'seller/reports/',
                checkPerformance: base + 'seller/performance/',
                openSettings: base + 'seller/settings/',
                openHelp: base + 'seller/help/',
                goDashboard: base + 'seller/dashboard/',
                goFinance: base + 'seller/finance/'
            };
            if (routes[action]) {
                this.navigateTo(routes[action]);
                return;
            }
            // Non-navigational commands
            switch(action) {
                case 'exportCSV':
                    this.exportCSV();
                    break;
                case 'toggleTheme':
                    this.toggleTheme();
                    break;
                case 'toggleTableView':
                    this.toggleResponsiveView();
                    break;
                case 'showShortcuts':
                    this.showShortcutsOverlay();
                    break;
                case 'togglePrintView':
                    this.togglePrintView();
                    break;
            }
        },

        // ═══════════════════════════════════════════════════
        // 5. DASHBOARD CHARTS (preserved)
        // ═══════════════════════════════════════════════════

        _chartDefaults: function() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1a1d2e',
                        titleFont: { size: 13, weight: '600' },
                        bodyFont: { size: 13 },
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 11, weight: '500' }, color: '#9ca3af' } },
                    y: { grid: { color: '#f3f4f6' }, ticks: { font: { size: 11, weight: '500' }, color: '#9ca3af' } }
                }
            };
        },

        _makeGradient: function(ctx, color1, color2) {
            var g = ctx.createLinearGradient(0, 0, 0, 280);
            g.addColorStop(0, color1);
            g.addColorStop(1, color2);
            return g;
        },

        _buildChart: function(canvasId, type, data, opts) {
            var canvas = document.getElementById(canvasId);
            if (!canvas || typeof Chart === 'undefined') return null;
            if (this.state.charts[canvasId]) this.state.charts[canvasId].destroy();
            this.state.charts[canvasId] = new Chart(canvas.getContext('2d'), {
                type: type,
                data: data,
                options: opts || this._chartDefaults()
            });
            return this.state.charts[canvasId];
        },

        initDashboard: function(chartData) {
            if (!chartData || typeof Chart === 'undefined') return;
            var canvas = document.getElementById('dso-sales-chart');
            if (!canvas) return;
            var gradient = this._makeGradient(canvas.getContext('2d'), 'rgba(79,70,229,0.15)', 'rgba(79,70,229,0)');
            var opts = this._chartDefaults();
            opts.plugins.legend.display = false;
            opts.plugins.tooltip.callbacks = {
                label: function(ctx) { return 'Sales: ' + this.config.currency + ctx.parsed.y.toLocaleString(); }.bind(this)
            };
            opts.scales.y.ticks.callback = function(v) { return this.config.currency + v.toLocaleString(); }.bind(this);
            this._buildChart('dso-sales-chart', 'line', {
                labels: chartData.labels,
                datasets: [{
                    label: 'Sales', data: chartData.sales, borderColor: '#4f46e5', backgroundColor: gradient,
                    borderWidth: 2.5, fill: true, tension: 0.4, pointRadius: 0, pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#4f46e5', pointHoverBorderColor: '#fff', pointHoverBorderWidth: 3
                }]
            }, opts);
            this.bindPeriodFilter('dso-dashboard-period', 'dashboard');
        },

        initFinance: function(chartData) {
            if (!chartData || typeof Chart === 'undefined') return;
            var canvas = document.getElementById('dso-earnings-chart');
            if (!canvas) return;
            var gradient = this._makeGradient(canvas.getContext('2d'), 'rgba(20,184,166,0.15)', 'rgba(20,184,166,0)');
            var opts = this._chartDefaults();
            opts.scales.y.ticks.callback = function(v) { return this.config.currency + v.toLocaleString(); }.bind(this);
            this._buildChart('dso-earnings-chart', 'line', {
                labels: chartData.labels,
                datasets: [{
                    label: 'Earnings', data: chartData.earnings, borderColor: '#14b8a6', backgroundColor: gradient,
                    borderWidth: 2.5, fill: true, tension: 0.4, pointRadius: 0, pointHoverRadius: 5
                }]
            }, opts);
            this.bindPeriodFilter('dso-finance-period', 'finance');
        },

        initAnalytics: function(chartData) {
            if (!chartData || typeof Chart === 'undefined') return;
            var opts = this._chartDefaults();
            var revCanvas = document.getElementById('dso-revenue-chart');
            if (revCanvas) {
                var gradient = this._makeGradient(revCanvas.getContext('2d'), 'rgba(16,185,129,0.15)', 'rgba(16,185,129,0)');
                this._buildChart('dso-revenue-chart', 'line', {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Revenue', data: chartData.revenue, borderColor: '#10b981', backgroundColor: gradient,
                        borderWidth: 2.5, fill: true, tension: 0.4, pointRadius: 0, pointHoverRadius: 5
                    }]
                }, opts);
            }
            var ordCanvas = document.getElementById('dso-orders-chart');
            if (ordCanvas) {
                var barOpts = this._chartDefaults();
                this._buildChart('dso-orders-chart', 'bar', {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Orders', data: chartData.orders, backgroundColor: 'rgba(79,70,229,0.8)',
                        borderRadius: 4, borderSkipped: false, barThickness: 'flex', maxBarThickness: 12
                    }]
                }, barOpts);
            }
            this.bindPeriodFilter('dso-analytics-period', 'analytics');
        },

        initReports: function(chartData) {
            if (!chartData || typeof Chart === 'undefined') return;
            var canvas = document.getElementById('dso-reports-chart');
            if (!canvas) return;
            var gradient = this._makeGradient(canvas.getContext('2d'), 'rgba(139,92,246,0.15)', 'rgba(139,92,246,0)');
            var opts = this._chartDefaults();
            opts.scales.y.ticks.callback = function(v) { return this.config.currency + v.toLocaleString(); }.bind(this);
            this._buildChart('dso-reports-chart', 'line', {
                labels: chartData.labels,
                datasets: [{
                    label: 'Revenue', data: chartData.values || chartData.revenue, borderColor: '#8b5cf6',
                    backgroundColor: gradient, borderWidth: 2.5, fill: true, tension: 0.4, pointRadius: 0, pointHoverRadius: 5
                }]
            }, opts);
            this.bindPeriodFilter('dso-reports-period', 'reports');
        },

        initPerformance: function(chartData) {
            if (!chartData || typeof Chart === 'undefined') return;
            var canvas = document.getElementById('dso-performance-chart');
            if (!canvas) return;
            var gradient = this._makeGradient(canvas.getContext('2d'), 'rgba(6,182,212,0.15)', 'rgba(6,182,212,0)');
            var opts = this._chartDefaults();
            opts.scales.y.ticks.callback = function(v) { return this.config.currency + v.toLocaleString(); }.bind(this);
            opts.scales.y1 = { position: 'right', grid: { drawOnChartArea: false }, ticks: { font: { size: 11 }, color: '#9ca3af' } };
            opts.plugins.legend.display = true;
            opts.plugins.legend.position = 'top';
            this._buildChart('dso-performance-chart', 'line', {
                labels: chartData.labels,
                datasets: [{
                    label: 'Revenue', data: chartData.revenue || chartData.values, borderColor: '#06b6d4',
                    backgroundColor: gradient, borderWidth: 2.5, fill: true, tension: 0.4, pointRadius: 0, pointHoverRadius: 5,
                    yAxisID: 'y'
                }, {
                    label: 'Orders', data: chartData.orders, borderColor: '#f97316',
                    backgroundColor: 'transparent', borderWidth: 2, borderDash: [5, 5], tension: 0.4,
                    pointRadius: 0, pointHoverRadius: 5, yAxisID: 'y1'
                }]
            }, opts);
            this.bindPeriodFilter('dso-performance-period', 'performance');
        },

        bindPeriodFilter: function(containerId, section) {
            var self = this;
            var container = document.getElementById(containerId);
            if (!container) return;
            container.querySelectorAll('button').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    container.querySelectorAll('button').forEach(function(b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                    self.fetchChartData(section, btn.getAttribute('data-period'));
                });
            });
        },

        fetchChartData: function(section, period) {
            var self = this;
            var canvasId = 'dso-' + (section === 'dashboard' ? 'sales' : section === 'finance' ? 'earnings' : section) + '-chart';
            var container = document.getElementById(canvasId);
            if (container) this.showSkeleton(container.parentElement);
            this.api('/charts/' + section + '?period=' + period + '&vendor_id=' + this.config.vendorId)
                .then(function(data) {
                    if (container) self.hideSkeleton(container.parentElement);
                    var method = 'init' + section.charAt(0).toUpperCase() + section.slice(1);
                    if (self[method]) self[method](data);
                })
                .catch(function() {
                    if (container) self.hideSkeleton(container.parentElement);
                    self.toast('Failed to load chart data', 'error');
                });
        },

        // ═══════════════════════════════════════════════════
        // 6. PRODUCTS (enhanced with inline editing & bulk)
        // ═══════════════════════════════════════════════════

        initProducts: function() {
            var self = this;
            var search = document.getElementById('dso-product-search');
            var statusFilter = document.getElementById('dso-product-status');
            var stockFilter = document.getElementById('dso-product-stock');
            var selectAll = document.getElementById('dso-select-all');
            var bulkBtn = document.getElementById('dso-bulk-apply');

            if (search) search.addEventListener('input', this.debounce(function() { self.filterProducts(); }, this.config.searchDebounce));
            if (statusFilter) statusFilter.addEventListener('change', function() { self.filterProducts(); });
            if (stockFilter) stockFilter.addEventListener('change', function() { self.filterProducts(); });

            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    var checked = selectAll.checked;
                    document.querySelectorAll('.dso-product-row input[type="checkbox"]').forEach(function(cb) {
                        cb.checked = checked;
                        var id = cb.getAttribute('data-id');
                        if (checked) self.state.selectedProducts[id] = true;
                        else delete self.state.selectedProducts[id];
                    });
                    self.state.selectedCount = Object.keys(self.state.selectedProducts).length;
                    self.updateBulkBar();
                });
            }

            document.querySelectorAll('.dso-product-row input[type="checkbox"]').forEach(function(cb) {
                cb.addEventListener('change', function() {
                    var id = cb.getAttribute('data-id');
                    if (cb.checked) self.state.selectedProducts[id] = true;
                    else delete self.state.selectedProducts[id];
                    self.state.selectedCount = Object.keys(self.state.selectedProducts).length;
                    self.updateBulkBar();
                    var all = document.querySelectorAll('.dso-product-row input[type="checkbox"]');
                    var sa = document.getElementById('dso-select-all');
                    if (sa) sa.checked = self.state.selectedCount === all.length;
                });
            });

            if (bulkBtn) {
                bulkBtn.addEventListener('click', function() {
                    var action = (document.getElementById('dso-bulk-action') || {}).value || '';
                    if (!action || self.state.selectedCount === 0) {
                        self.toast('Select products and an action', 'warning');
                        return;
                    }
                    self.executeBulkAction(action);
                });
            }
        },

        filterProducts: function() {
            var search = (document.getElementById('dso-product-search') || {}).value || '';
            var status = (document.getElementById('dso-product-status') || {}).value || '';
            var stock = (document.getElementById('dso-product-stock') || {}).value || '';
            var lower = search.toLowerCase();
            document.querySelectorAll('.dso-product-row').forEach(function(row) {
                var name = (row.getAttribute('data-name') || '').toLowerCase();
                var rStatus = row.getAttribute('data-status') || '';
                var rStock = row.getAttribute('data-stock') || '';
                var show = true;
                if (lower && name.indexOf(lower) === -1) show = false;
                if (status && rStatus !== status) show = false;
                if (stock && rStock !== stock) show = false;
                row.style.display = show ? '' : 'none';
            });
        },

        updateBulkBar: function() {
            var bar = document.getElementById('dso-bulk-bar');
            var count = document.getElementById('dso-bulk-count');
            if (!bar) return;
            if (this.state.selectedCount > 0) {
                bar.classList.add('active');
                if (count) count.textContent = this.state.selectedCount;
            } else {
                bar.classList.remove('active');
            }
        },

        executeBulkAction: function(action) {
            var self = this;
            var ids = Object.keys(this.state.selectedProducts);
            this.api('/products/bulk', {
                method: 'POST',
                body: JSON.stringify({ action: action, ids: ids })
            }).then(function() {
                self.toast('Bulk action completed', 'success');
                setTimeout(function() { window.location.reload(); }, 1200);
            }).catch(function() {
                self.toast('Bulk action failed', 'error');
            });
        },

        // ═══════════════════════════════════════════════════
        // 7. ADD / EDIT PRODUCT (preserved)
        // ═══════════════════════════════════════════════════

        initAddProduct: function() { this._initProductForm(); },
        initEditProduct: function() { this._initProductForm(); },

        _initProductForm: function() {
            var self = this;
            var manageStock = document.getElementById('manage_stock');
            var stockFields = document.getElementById('stock-fields');
            var statusField = document.getElementById('stock-status-field');
            if (manageStock) {
                var toggle = function() {
                    if (manageStock.checked) {
                        if (stockFields) stockFields.style.display = '';
                        if (statusField) statusField.style.display = 'none';
                    } else {
                        if (stockFields) stockFields.style.display = 'none';
                        if (statusField) statusField.style.display = '';
                    }
                };
                manageStock.addEventListener('change', toggle);
                toggle();
            }
            document.querySelectorAll('[data-dso-upload]').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.openMediaLibrary(btn.getAttribute('data-dso-upload'));
                });
            });
        },

        openMediaLibrary: function(targetId) {
            if (typeof wp === 'undefined' || !wp.media) {
                this.toast('Media library not available', 'error');
                return;
            }
            var frame = wp.media({
                title: 'Select Image',
                button: { text: 'Use this image' },
                multiple: false,
                library: { type: 'image' }
            });
            frame.on('select', function() {
                var att = frame.state().get('selection').first().toJSON();
                var input = document.getElementById(targetId);
                var preview = document.getElementById(targetId + '-preview');
                if (input) input.value = att.id;
                if (preview) {
                    preview.src = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
                    preview.style.display = 'block';
                }
            });
            frame.open();
        },

        // ═══════════════════════════════════════════════════
        // 8. INVENTORY (enhanced)
        // ═══════════════════════════════════════════════════

        initInventory: function() {
            var self = this;
            var filter = document.getElementById('dso-inv-filter');
            if (filter) {
                filter.addEventListener('change', function() {
                    var val = this.value;
                    document.querySelectorAll('.dso-table tbody tr[data-stock]').forEach(function(row) {
                        row.style.display = (!val || row.getAttribute('data-stock') === val) ? '' : 'none';
                    });
                });
            }
            document.querySelectorAll('.dso-stock-edit-btn').forEach(function(btn) {
                btn.addEventListener('click', function() { self.inlineStockEdit(btn); });
            });
            document.querySelectorAll('.dso-update-stock').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var id = btn.getAttribute('data-id');
                    var input = document.querySelector('.dso-stock-input[data-id="' + id + '"]');
                    if (!input) return;
                    var stock = parseInt(input.value, 10);
                    if (isNaN(stock) || stock < 0) return;
                    btn.disabled = true;
                    btn.textContent = 'Updating...';
                    self.api('/products/' + id + '/stock', {
                        method: 'POST',
                        body: JSON.stringify({ stock: stock })
                    }).then(function(data) {
                        btn.disabled = false;
                        if (data.success) {
                            btn.style.background = '#10b981';
                            btn.textContent = '\u2713 Saved';
                            setTimeout(function() { btn.style.background = ''; btn.textContent = 'Update'; }, 1500);
                        } else {
                            btn.textContent = 'Update';
                        }
                    }).catch(function() {
                        btn.disabled = false;
                        btn.textContent = 'Update';
                    });
                });
            });
        },

        inlineStockEdit: function(btn) {
            var self = this;
            var row = btn.closest('tr') || btn.closest('.dso-inventory-row');
            if (!row) return;
            var stockEl = row.querySelector('.dso-stock-value');
            var inputEl = row.querySelector('.dso-stock-input');
            var saveEl = row.querySelector('.dso-stock-save');
            var cancelEl = row.querySelector('.dso-stock-cancel');
            var id = row.getAttribute('data-product-id') || btn.getAttribute('data-id');
            if (stockEl) stockEl.style.display = 'none';
            btn.style.display = 'none';
            if (inputEl) { inputEl.style.display = ''; inputEl.focus(); inputEl.select(); }
            if (saveEl) saveEl.style.display = '';
            if (cancelEl) cancelEl.style.display = '';

            var save = function() {
                var qty = parseInt(inputEl.value, 10);
                if (isNaN(qty) || qty < 0) { self.toast('Enter a valid quantity', 'warning'); return; }
                self.api('/products/' + id + '/stock', {
                    method: 'POST',
                    body: JSON.stringify({ stock: qty })
                }).then(function(data) {
                    if (data.success) {
                        if (stockEl) { stockEl.textContent = qty; stockEl.style.display = ''; }
                        if (inputEl) inputEl.style.display = 'none';
                        if (saveEl) saveEl.style.display = 'none';
                        if (cancelEl) cancelEl.style.display = 'none';
                        btn.style.display = '';
                        self.toast('Stock updated', 'success');
                    }
                }).catch(function() { self.toast('Update failed', 'error'); });
            };
            var cancel = function() {
                if (stockEl) stockEl.style.display = '';
                btn.style.display = '';
                if (inputEl) inputEl.style.display = 'none';
                if (saveEl) saveEl.style.display = 'none';
                if (cancelEl) cancelEl.style.display = 'none';
                saveEl.removeEventListener('click', save);
                cancelEl.removeEventListener('click', cancel);
            };
            if (saveEl) saveEl.addEventListener('click', save);
            if (cancelEl) cancelEl.addEventListener('click', cancel);
        },

        // ═══════════════════════════════════════════════════
        // 9. SETTINGS / HELP / FAVORITES (preserved)
        // ═══════════════════════════════════════════════════

        initSettings: function() {
            var self = this;
            document.querySelectorAll('[data-dso-toggle="settings"]').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.toggleDropdown('settings');
                });
            });
        },

        initHelp: function() {
            var self = this;
            document.querySelectorAll('[data-dso-toggle="help"]').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.toggleDropdown('help');
                });
            });
        },

        initFavorites: function() {
            var self = this;
            document.querySelectorAll('[data-dso-toggle="favorites"]').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.toggleDropdown('favorites');
                });
            });
        },

        toggleDropdown: function(name) {
            var key = name + 'Open';
            var el = document.getElementById('dso-' + name + '-dropdown');
            var stateKey = name + 'Open';
            ['settings', 'help', 'favorites'].forEach(function(n) {
                if (n !== name) {
                    var d = document.getElementById('dso-' + n + '-dropdown');
                    if (d) d.classList.remove('active');
                    this.state[n + 'Open'] = false;
                }
            }.bind(this));
            if (el) el.classList.toggle('active');
            this.state[stateKey] = !this.state[stateKey];
        },

        closeAllDropdowns: function() {
            var self = this;
            ['settings', 'help', 'favorites'].forEach(function(n) {
                var el = document.getElementById('dso-' + n + '-dropdown');
                if (el) el.classList.remove('active');
                self.state[n + 'Open'] = false;
            });
        },

        // ═══════════════════════════════════════════════════
        // 10. TOAST NOTIFICATIONS (enhanced)
        // ═══════════════════════════════════════════════════

        toast: function(message, type, opts) {
            type = type || 'info';
            opts = opts || {};
            var self = this;
            var container = document.getElementById('dso-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'dso-toast-container';
                container.setAttribute('role', 'status');
                container.setAttribute('aria-live', 'polite');
                container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;max-width:380px;';
                document.body.appendChild(container);
            }

            // Enforce max toasts
            var existing = container.querySelectorAll('.dso-toast');
            if (existing.length >= this.state.maxToasts) {
                var oldest = existing[0];
                oldest.classList.remove('show');
                setTimeout(function() { oldest.remove(); }, 300);
            }

            var toast = document.createElement('div');
            toast.className = 'dso-toast dso-toast-' + type;
            if (opts.className) toast.classList.add(opts.className);
            var icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };

            var html = '<span class="dso-toast-icon" aria-hidden="true">' + (icons[type] || icons.info) + '</span>';
            html += '<span class="dso-toast-msg">' + this.escapeHTML(message) + '</span>';
            if (opts.action) {
                html += '<button class="dso-toast-action" aria-label="' + this.escapeHTML(opts.action.label || 'Action') + '">' +
                    this.escapeHTML(opts.action.label) + '</button>';
            }
            if (opts.dismissible !== false) {
                html += '<button class="dso-toast-close" aria-label="Dismiss">&times;</button>';
            }
            toast.innerHTML = html;

            if (opts.action && opts.action.handler) {
                var actionBtn = toast.querySelector('.dso-toast-action');
                if (actionBtn) actionBtn.addEventListener('click', function() {
                    opts.action.handler();
                    self._dismissToast(toast);
                });
            }

            var closeBtn = toast.querySelector('.dso-toast-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() { self._dismissToast(toast); });
            }

            container.appendChild(toast);
            requestAnimationFrame(function() { toast.classList.add('show'); });

            var duration = opts.duration || 4000;
            if (opts.persistent) return;
            setTimeout(function() { self._dismissToast(toast); }, duration);
        },

        _dismissToast: function(toast) {
            if (!toast || !toast.parentElement) return;
            toast.classList.remove('show');
            setTimeout(function() { toast.remove(); }, 300);
        },

        // ═══════════════════════════════════════════════════
        // 11. KEYBOARD SHORTCUTS (enhanced)
        // ═══════════════════════════════════════════════════

        initKeyboardShortcuts: function() {
            var self = this;
            document.addEventListener('keydown', function(e) {
                // Ignore when typing in inputs
                var tag = (e.target.tagName || '').toLowerCase();
                var isInput = tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable;
                var isMeta = e.metaKey || e.ctrlKey;

                // Cmd/Ctrl+K: open search
                if (isMeta && e.key === 'k') {
                    e.preventDefault();
                    if (self.state.commandOpen) { self.closeCommand(); return; }
                    if (self.state.searchOpen) { self.closeSearch(); return; }
                    self.openSearch();
                    return;
                }

                // Cmd/Ctrl+Shift+P: command palette
                if (isMeta && e.shiftKey && (e.key === 'p' || e.key === 'P')) {
                    e.preventDefault();
                    if (self.state.commandOpen) { self.closeCommand(); return; }
                    self.openCommand();
                    return;
                }

                // Escape: close overlays
                if (e.key === 'Escape') {
                    if (self.state.searchOpen) { self.closeSearch(); return; }
                    if (self.state.commandOpen) { self.closeCommand(); return; }
                    if (self.state.sidebarOpen) { self.closeSidebar(); return; }
                    self.closeAllDropdowns();
                    self.closeShortcutsOverlay();
                    return;
                }

                // Skip the rest when in input fields
                if (isInput) return;

                // ? : show shortcuts
                if (e.key === '?') {
                    e.preventDefault();
                    self.showShortcutsOverlay();
                    return;
                }

                // / : focus search
                if (e.key === '/') {
                    e.preventDefault();
                    self.openSearch();
                    return;
                }

                // j/k : navigate lists
                if (e.key === 'j' || e.key === 'k') {
                    self.navigateList(e.key === 'j' ? 'down' : 'up');
                    return;
                }

                // Enter : open focused item
                if (e.key === 'Enter') {
                    self.openFocusedItem();
                    return;
                }

                // x : toggle selection on focused row
                if (e.key === 'x') {
                    self.toggleFocusedSelection();
                    return;
                }

                // g then h : go to dashboard
                if (e.key === 'g') {
                    self._pendingGKey = true;
                    setTimeout(function() { self._pendingGKey = false; }, 800);
                    return;
                }
                if (self._pendingGKey) {
                    self._pendingGKey = false;
                    var base = self.config.baseUrl;
                    if (e.key === 'h') { window.location.href = base + 'seller/dashboard/'; }
                    else if (e.key === 'o') { window.location.href = base + 'seller/orders/'; }
                    else if (e.key === 'p') { window.location.href = base + 'seller/products/'; }
                }
            });
        },

        navigateList: function(direction) {
            var rows = this.$$('.dso-product-row:not([style*="display: none"]), .dso-order-row:not([style*="display: none"])');
            if (!rows.length) return;
            var currentIdx = -1;
            var focused = document.querySelector('.dso-row-focused');
            if (focused) {
                currentIdx = rows.indexOf(focused);
                focused.classList.remove('dso-row-focused');
            }
            var nextIdx = direction === 'down'
                ? Math.min(currentIdx + 1, rows.length - 1)
                : Math.max(currentIdx - 1, 0);
            if (nextIdx < 0) nextIdx = 0;
            rows[nextIdx].classList.add('dso-row-focused');
            rows[nextIdx].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        },

        openFocusedItem: function() {
            var focused = document.querySelector('.dso-row-focused');
            if (!focused) return;
            var link = focused.querySelector('a');
            if (link) link.click();
        },

        toggleFocusedSelection: function() {
            var focused = document.querySelector('.dso-row-focused');
            if (!focused) return;
            var cb = focused.querySelector('input[type="checkbox"]');
            if (cb) {
                cb.checked = !cb.checked;
                cb.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },

        // ═══════════════════════════════════════════════════
        // 12. AUTO-REFRESH NOTIFICATIONS (preserved)
        // ═══════════════════════════════════════════════════

        initAutoRefresh: function() {
            var self = this;
            this.pollNotifications();
            this.state.refreshTimer = setInterval(function() {
                self.pollNotifications();
            }, this.config.refreshInterval);
        },

        pollNotifications: function() {
            var badge = document.getElementById('dso-notif-badge');
            if (!badge) return;
            this.api('/notifications/count?vendor_id=' + this.config.vendorId)
                .then(function(data) {
                    if (data && typeof data.count !== 'undefined') {
                        if (data.count > 0) {
                            badge.textContent = data.count > 99 ? '99+' : data.count;
                            badge.style.display = '';
                        } else {
                            badge.style.display = 'none';
                        }
                    }
                })
                .catch(function() {});
        },

        // ═══════════════════════════════════════════════════
        // 13. RESPONSIVE HANDLING (preserved)
        // ═══════════════════════════════════════════════════

        initResponsive: function() {
            var self = this;
            var resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    if (window.innerWidth >= 1024 && self.state.sidebarOpen) {
                        self.closeSidebar();
                    }
                    if (window.innerWidth >= 1024) self.closeAllDropdowns();
                }, 150);
            });
        },

        // ═══════════════════════════════════════════════════
        // 14. SMOOTH SCROLL (preserved)
        // ═══════════════════════════════════════════════════

        initSmoothScroll: function() {
            document.querySelectorAll('a[href^="#"]').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    var hash = this.getAttribute('href');
                    if (!hash || hash === '#' || hash === '#0') return;
                    var target = document.querySelector(hash);
                    if (target) {
                        e.preventDefault();
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            });
        },

        // ═══════════════════════════════════════════════════
        // 15. TABLE SORTING (preserved)
        // ═══════════════════════════════════════════════════

        initTableSort: function() {
            document.querySelectorAll('.dso-table th[data-sort]').forEach(function(th) {
                th.style.cursor = 'pointer';
                th.addEventListener('click', function() {
                    var table = th.closest('table');
                    if (!table) return;
                    var tbody = table.querySelector('tbody');
                    if (!tbody) return;
                    var key = th.getAttribute('data-sort');
                    var dir = th.getAttribute('data-sort-dir') === 'asc' ? 'desc' : 'asc';
                    th.setAttribute('data-sort-dir', dir);
                    table.querySelectorAll('th[data-sort]').forEach(function(h) {
                        if (h !== th) h.removeAttribute('data-sort-dir');
                    });
                    var rows = Array.from(tbody.querySelectorAll('tr'));
                    var idx = Array.from(th.parentElement.children).indexOf(th);
                    rows.sort(function(a, b) {
                        var aVal = (a.children[idx] || {}).textContent || '';
                        var bVal = (b.children[idx] || {}).textContent || '';
                        var aNum = parseFloat(aVal.replace(/[^0-9.\-]/g, ''));
                        var bNum = parseFloat(bVal.replace(/[^0-9.\-]/g, ''));
                        if (!isNaN(aNum) && !isNaN(bNum)) {
                            return dir === 'asc' ? aNum - bNum : bNum - aNum;
                        }
                        return dir === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                    });
                    rows.forEach(function(row) { tbody.appendChild(row); });
                    table.querySelectorAll('th[data-sort]').forEach(function(h) {
                        h.classList.remove('sort-asc', 'sort-desc');
                    });
                    th.classList.add(dir === 'asc' ? 'sort-asc' : 'sort-desc');
                });
            });
        },

        // ═══════════════════════════════════════════════════
        // 16. SKELETON LOADING (preserved)
        // ═══════════════════════════════════════════════════

        showSkeleton: function(container) {
            if (!container) return;
            container.classList.add('dso-skeleton-active');
            var overlay = container.querySelector('.dso-skeleton-overlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'dso-skeleton-overlay';
                overlay.innerHTML = '<div class="dso-skeleton-pulse"></div>';
                container.style.position = 'relative';
                container.appendChild(overlay);
            }
        },

        hideSkeleton: function(container) {
            if (!container) return;
            container.classList.remove('dso-skeleton-active');
            var overlay = container.querySelector('.dso-skeleton-overlay');
            if (overlay) overlay.remove();
        },

        initSkeletonDefaults: function() {
            // Add base skeleton CSS if not present
            if (!document.getElementById('dso-skeleton-styles')) {
                var style = document.createElement('style');
                style.id = 'dso-skeleton-styles';
                style.textContent =
                    '.dso-skeleton-active{position:relative!important;pointer-events:none}' +
                    '.dso-skeleton-overlay{position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.6);display:flex;align-items:center;justify-content:center;z-index:5}' +
                    '.dso-skeleton-pulse{width:40px;height:40px;border:3px solid #e5e7eb;border-top-color:#4f46e5;border-radius:50%;animation:dso-spin .6s linear infinite}' +
                    '@keyframes dso-spin{to{transform:rotate(360deg)}}' +
                    '.dso-row-focused{outline:2px solid #4f46e5;outline-offset:-2px;border-radius:4px}' +
                    '.dso-page-transition{transition:opacity .2s ease,transform .2s ease}' +
                    '.dso-page-transition.fade-in{opacity:1;transform:translateY(0)}' +
                    '.dso-page-transition.fade-out{opacity:0;transform:translateY(8px)}' +
                    '.dso-card-view .dso-product-row,.dso-card-view .dso-order-row{display:flex;flex-wrap:wrap;gap:12px;padding:16px;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:12px;background:#fff}' +
                    '.dso-card-view table,.dso-card-view thead,.dso-card-view tbody,.dso-card-view tr,.dso-card-view td{display:block;width:100%!important}' +
                    '.dso-card-view thead{display:none}' +
                    '.dso-card-view td{padding:4px 0;border:none!important}' +
                    '.dso-card-view td::before{content:attr(data-label);font-weight:600;color:#6b7280;margin-right:8px;font-size:12px;text-transform:uppercase}' +
                    '@media print{.no-print{display:none!important}body{background:#fff!important}.dso-sidebar,#dso-toast-container,.dso-floating-actions,.dso-scroll-top-btn{display:none!important}' +
                    '.dso-card-view .dso-product-row,.dso-card-view .dso-order-row{display:table-row!important}' +
                    '.dso-card-view table,.dso-card-view thead,.dso-card-view tbody,.dso-card-view tr,.dso-card-view td{display:revert!important}' +
                    '.dso-card-view thead{display:revert!important}' +
                    '.dso-card-view td::before{content:none!important}}';
                document.head.appendChild(style);
            }
        },

        // ═══════════════════════════════════════════════════
        // 17. ORDERS / CUSTOMERS (preserved)
        // ═══════════════════════════════════════════════════

        initOrders: function() {
            var self = this;
            var searchInput = document.getElementById('dso-order-search');
            var statusFilter = document.getElementById('dso-order-status');
            function filter() {
                var query = (searchInput ? searchInput.value : '').toLowerCase();
                var status = statusFilter ? statusFilter.value : '';
                document.querySelectorAll('.dso-order-row').forEach(function(row) {
                    var text = row.textContent.toLowerCase();
                    var rowStatus = row.getAttribute('data-status') || '';
                    var show = true;
                    if (query && text.indexOf(query) === -1) show = false;
                    if (status && rowStatus !== status) show = false;
                    row.style.display = show ? '' : 'none';
                });
            }
            if (searchInput) searchInput.addEventListener('input', this.debounce(filter, this.config.searchDebounce));
            if (statusFilter) statusFilter.addEventListener('change', filter);
        },

        initCustomers: function() {
            var self = this;
            var searchInput = document.getElementById('dso-customer-search');
            if (searchInput) {
                searchInput.addEventListener('input', this.debounce(function() {
                    var query = searchInput.value.toLowerCase();
                    document.querySelectorAll('.dso-table tbody tr').forEach(function(row) {
                        row.style.display = row.textContent.toLowerCase().indexOf(query) > -1 ? '' : 'none';
                    });
                }, this.config.searchDebounce));
            }
        },

        // ═══════════════════════════════════════════════════
        // NEW: 18. FUZZY SEARCH MATCHING
        // ═══════════════════════════════════════════════════

        initFuzzySearch: function() {
            // Enhance existing search with fuzzy matching for local filtering
        },

        fuzzyMatch: function(text, query) {
            if (!query) return true;
            var lower = text.toLowerCase();
            var qLower = query.toLowerCase();

            // Exact substring match
            if (lower.indexOf(qLower) !== -1) return true;

            // Fuzzy: all query chars appear in order
            var qi = 0;
            for (var ti = 0; ti < lower.length && qi < qLower.length; ti++) {
                if (lower[ti] === qLower[qi]) qi++;
            }
            return qi === qLower.length;
        },

        fuzzyScore: function(text, query) {
            if (!query) return 0;
            var lower = text.toLowerCase();
            var qLower = query.toLowerCase();

            // Exact match = highest score
            if (lower === qLower) return 100;

            // Starts with = high score
            if (lower.indexOf(qLower) === 0) return 80;

            // Contains
            if (lower.indexOf(qLower) !== -1) return 60;

            // Fuzzy
            var qi = 0;
            var score = 0;
            var consecutive = 0;
            for (var ti = 0; ti < lower.length && qi < qLower.length; ti++) {
                if (lower[ti] === qLower[qi]) {
                    qi++;
                    consecutive++;
                    score += consecutive * 10;
                } else {
                    consecutive = 0;
                }
            }
            return qi === qLower.length ? score : 0;
        },

        // ═══════════════════════════════════════════════════
        // NEW: 19. COMMAND PALETTE CATEGORIES
        // ═══════════════════════════════════════════════════

        initCommandCategories: function() {
            // Already handled in renderCommands
        },

        // ═══════════════════════════════════════════════════
        // NEW: 20. INLINE EDITING FOR STOCK/PRICES
        // ═══════════════════════════════════════════════════

        initInlineEditing: function() {
            var self = this;
            document.querySelectorAll('[data-dso-inline-edit]').forEach(function(el) {
                el.addEventListener('dblclick', function() {
                    self.startInlineEdit(el);
                });
                el.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        el.blur();
                    }
                    if (e.key === 'Escape') {
                        el.setAttribute('contenteditable', 'false');
                        el.textContent = el.getAttribute('data-original-value') || el.textContent;
                    }
                });
            });

            document.querySelectorAll('.dso-inline-editable').forEach(function(el) {
                el.addEventListener('dblclick', function() {
                    var field = el.querySelector('.dso-inline-field');
                    if (field) {
                        el.classList.add('editing');
                        field.focus();
                        field.select();
                    }
                });
            });
        },

        startInlineEdit: function(el) {
            var self = this;
            var field = el.querySelector('input, [contenteditable]');
            if (!field) {
                var currentValue = el.textContent.trim();
                el.setAttribute('data-original-value', currentValue);
                el.setAttribute('contenteditable', 'true');
                el.focus();
                var range = document.createRange();
                range.selectNodeContents(el);
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);

                var save = function() {
                    var newValue = el.textContent.trim();
                    el.setAttribute('contenteditable', 'false');
                    if (newValue !== currentValue) {
                        var id = el.getAttribute('data-id');
                        var field_name = el.getAttribute('data-field');
                        if (id && field_name) {
                            self.api('/products/' + id, {
                                method: 'PUT',
                                body: JSON.stringify({ [field_name]: newValue })
                            }).then(function() {
                                self.toast('Updated successfully', 'success');
                            }).catch(function() {
                                el.textContent = currentValue;
                                self.toast('Update failed', 'error');
                            });
                        }
                    }
                    el.removeEventListener('blur', save);
                };
                el.addEventListener('blur', save);
            }
        },

        // ═══════════════════════════════════════════════════
        // NEW: 21. BULK SELECTION WITH SHIFT-CLICK
        // ═══════════════════════════════════════════════════

        initBulkSelection: function() {
            var self = this;
            var lastChecked = null;

            document.querySelectorAll('.dso-product-row input[type="checkbox"], .dso-order-row input[type="checkbox"]').forEach(function(cb) {
                cb.addEventListener('click', function(e) {
                    if (e.shiftKey && lastChecked) {
                        var rows = Array.from(cb.closest('tbody').querySelectorAll('tr'));
                        var startIdx = rows.indexOf(lastChecked.closest('tr'));
                        var endIdx = rows.indexOf(cb.closest('tr'));
                        if (startIdx === -1 || endIdx === -1) return;
                        var min = Math.min(startIdx, endIdx);
                        var max = Math.max(startIdx, endIdx);
                        for (var i = min; i <= max; i++) {
                            var rowCb = rows[i].querySelector('input[type="checkbox"]');
                            if (rowCb) {
                                rowCb.checked = cb.checked;
                                rowCb.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        }
                    }
                    lastChecked = cb;
                });
            });
        },

        // ═══════════════════════════════════════════════════
        // NEW: 22. DRAG & DROP FOR PRODUCT IMAGES
        // ═══════════════════════════════════════════════════

        initDragDropImages: function() {
            var self = this;
            var containers = document.querySelectorAll('[data-dso-sortable]');
            containers.forEach(function(container) {
                var items = container.querySelectorAll('.dso-sortable-item');
                items.forEach(function(item) {
                    item.setAttribute('draggable', 'true');

                    item.addEventListener('dragstart', function(e) {
                        item.classList.add('dso-dragging');
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/plain', item.getAttribute('data-id') || '');
                        self._dragSource = item;
                    });

                    item.addEventListener('dragend', function() {
                        item.classList.remove('dso-dragging');
                        container.querySelectorAll('.dso-sortable-item').forEach(function(i) {
                            i.classList.remove('dso-drag-over');
                        });
                        self._dragSource = null;
                    });

                    item.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        e.dataTransfer.dropEffect = 'move';
                        item.classList.add('dso-drag-over');
                    });

                    item.addEventListener('dragleave', function() {
                        item.classList.remove('dso-drag-over');
                    });

                    item.addEventListener('drop', function(e) {
                        e.preventDefault();
                        item.classList.remove('dso-drag-over');
                        if (self._dragSource && self._dragSource !== item) {
                            var parent = item.parentNode;
                            var sourceIndex = Array.from(parent.children).indexOf(self._dragSource);
                            var targetIndex = Array.from(parent.children).indexOf(item);
                            if (sourceIndex < targetIndex) {
                                parent.insertBefore(self._dragSource, item.nextSibling);
                            } else {
                                parent.insertBefore(self._dragSource, item);
                            }
                            self.saveImageOrder(container);
                        }
                    });
                });
            });
        },

        saveImageOrder: function(container) {
            var self = this;
            var items = container.querySelectorAll('.dso-sortable-item');
            var order = Array.from(items).map(function(item) {
                return item.getAttribute('data-id') || item.getAttribute('data-order') || '';
            }).filter(Boolean);
            var productId = container.getAttribute('data-product-id');
            if (productId && order.length) {
                this.api('/products/' + productId + '/images', {
                    method: 'POST',
                    body: JSON.stringify({ order: order })
                }).then(function() {
                    self.toast('Image order saved', 'success');
                }).catch(function() {
                    self.toast('Failed to save image order', 'error');
                });
            }
        },

        // ═══════════════════════════════════════════════════
        // NEW: 23. PAGE TRANSITIONS
        // ═══════════════════════════════════════════════════

        initPageTransitions: function() {
            var self = this;
            // Animate in on page load
            var main = document.querySelector('.dso-main-content, main, [role="main"]');
            if (main) {
                main.classList.add('dso-page-transition', 'fade-in');
            }

            // Intercept navigation links
            document.querySelectorAll('a[data-dso-navigate]').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    var href = link.getAttribute('href');
                    if (href) self.navigateTo(href);
                });
            });
        },

        navigateTo: function(url) {
            var main = document.querySelector('.dso-main-content, main, [role="main"]');
            if (main) {
                main.classList.remove('fade-in');
                main.classList.add('fade-out');
                setTimeout(function() {
                    window.location.href = url;
                }, 200);
            } else {
                window.location.href = url;
            }
        },

        // ═══════════════════════════════════════════════════
        // NEW: 24. AUTO-SAVE FOR PRODUCT FORMS
        // ═══════════════════════════════════════════════════

        initAutoSave: function() {
            var self = this;
            var forms = document.querySelectorAll('form[data-dso-autosave]');
            forms.forEach(function(form) {
                var formId = form.getAttribute('data-autosave-id') || form.id || self.generateId();
                var inputs = form.querySelectorAll('input, textarea, select');
                inputs.forEach(function(input) {
                    input.addEventListener('input', function() {
                        self.scheduleAutoSave(form, formId);
                    });
                    input.addEventListener('change', function() {
                        self.scheduleAutoSave(form, formId);
                    });
                });
            });
        },

        scheduleAutoSave: function(form, formId) {
            var self = this;
            if (this.state.autoSaveTimers[formId]) {
                clearTimeout(this.state.autoSaveTimers[formId]);
            }
            this.state.autoSaveTimers[formId] = setTimeout(function() {
                self.performAutoSave(form, formId);
            }, this.config.autoSaveDelay);
        },

        performAutoSave: function(form, formId) {
            var self = this;
            var formData = new FormData(form);
            var data = {};
            formData.forEach(function(value, key) {
                data[key] = value;
            });
            var saveIndicator = form.querySelector('.dso-autosave-indicator');
            if (saveIndicator) {
                saveIndicator.textContent = 'Saving...';
                saveIndicator.classList.add('active');
            }
            this.api('/autosave', {
                method: 'POST',
                body: JSON.stringify({ form_id: formId, data: data, vendor_id: this.config.vendorId })
            }).then(function() {
                if (saveIndicator) {
                    saveIndicator.textContent = 'Saved';
                    saveIndicator.classList.add('saved');
                    setTimeout(function() {
                        saveIndicator.classList.remove('active', 'saved');
                    }, 2000);
                }
                localStorage.setItem('dso_autosave_' + formId, JSON.stringify(data));
            }).catch(function() {
                if (saveIndicator) {
                    saveIndicator.textContent = 'Save failed';
                    saveIndicator.classList.add('error');
                }
            });
        },

        // ═══════════════════════════════════════════════════
        // NEW: 25. INFINITE SCROLL
        // ═══════════════════════════════════════════════════

        initInfiniteScroll: function() {
            var self = this;
            var trigger = document.getElementById('dso-infinite-scroll-trigger');
            if (!trigger) return;

            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting && !self.state.infiniteScrollLoading && !self.state.infiniteScrollDone) {
                        self.loadMoreItems();
                    }
                });
            }, { rootMargin: '200px' });

            observer.observe(trigger);
        },

        loadMoreItems: function() {
            var self = this;
            this.state.infiniteScrollLoading = true;
            this.state.infiniteScrollPage++;
            var loader = document.getElementById('dso-infinite-scroll-loader');
            if (loader) loader.style.display = '';

            this.api('/products?page=' + this.state.infiniteScrollPage + '&per_page=' + this.config.pageSize + '&vendor_id=' + this.config.vendorId)
                .then(function(data) {
                    if (loader) loader.style.display = 'none';
                    self.state.infiniteScrollLoading = false;
                    if (!data || !data.items || !data.items.length) {
                        self.state.infiniteScrollDone = true;
                        var trigger = document.getElementById('dso-infinite-scroll-trigger');
                        if (trigger) trigger.textContent = 'No more items';
                        return;
                    }
                    var tbody = document.querySelector('.dso-product-list tbody, .dso-order-list tbody');
                    if (tbody) {
                        data.items.forEach(function(item) {
                            var row = self._createProductRow(item);
                            tbody.appendChild(row);
                        });
                    }
                })
                .catch(function() {
                    if (loader) loader.style.display = 'none';
                    self.state.infiniteScrollLoading = false;
                    self.toast('Failed to load more items', 'error');
                });
        },

        _createProductRow: function(item) {
            var tr = document.createElement('tr');
            tr.className = 'dso-product-row';
            tr.setAttribute('data-id', item.id || '');
            tr.setAttribute('data-name', (item.name || '').toLowerCase());
            tr.setAttribute('data-status', item.status || '');
            tr.setAttribute('data-stock', item.stock_status || '');
            tr.innerHTML = '<td><input type="checkbox" data-id="' + (item.id || '') + '"></td>' +
                '<td><a href="' + (item.url || '#') + '">' + this.escapeHTML(item.name || 'Untitled') + '</a></td>' +
                '<td>' + this.escapeHTML(item.sku || '') + '</td>' +
                '<td>' + (item.price || '0.00') + '</td>' +
                '<td>' + (item.stock || '0') + '</td>' +
                '<td><span class="dso-badge dso-badge-' + (item.status || 'draft') + '">' + (item.status || 'Draft') + '</span></td>';
            return tr;
        },

        // ═══════════════════════════════════════════════════
        // NEW: 26. PULL TO REFRESH (mobile)
        // ═══════════════════════════════════════════════════

        initPullToRefresh: function() {
            if (window.innerWidth > 768) return;
            var self = this;
            var startY = 0;
            var pullDist = 0;
            var threshold = 80;
            var refreshing = false;
            var indicator = null;

            document.addEventListener('touchstart', function(e) {
                if (window.scrollY > 0) return;
                startY = e.touches[0].clientY;
            }, { passive: true });

            document.addEventListener('touchmove', function(e) {
                if (window.scrollY > 0 || refreshing) return;
                var currentY = e.touches[0].clientY;
                pullDist = currentY - startY;
                if (pullDist > 0 && pullDist < 150) {
                    if (!indicator) {
                        indicator = document.createElement('div');
                        indicator.className = 'dso-pull-indicator';
                        indicator.innerHTML = '<span class="dso-pull-icon">&#8595;</span><span class="dso-pull-text">Pull to refresh</span>';
                        indicator.style.cssText = 'position:fixed;top:0;left:50%;transform:translateX(-50%) translateY(-60px);z-index:10000;background:#fff;padding:12px 24px;border-radius:0 0 12px 12px;box-shadow:0 4px 20px rgba(0,0,0,.15);display:flex;align-items:center;gap:8px;transition:transform .2s ease;font-size:13px;font-weight:500;color:#374151;';
                        document.body.appendChild(indicator);
                    }
                    var progress = Math.min(pullDist / threshold, 1);
                    indicator.style.transform = 'translateX(-50%) translateY(' + (-60 + (pullDist * 0.5)) + 'px)';
                    var icon = indicator.querySelector('.dso-pull-icon');
                    if (icon) icon.style.transform = 'rotate(' + (progress * 180) + 'deg)';
                    if (progress >= 1) {
                        var text = indicator.querySelector('.dso-pull-text');
                        if (text) text.textContent = 'Release to refresh';
                    } else {
                        var text2 = indicator.querySelector('.dso-pull-text');
                        if (text2) text2.textContent = 'Pull to refresh';
                    }
                }
            }, { passive: true });

            document.addEventListener('touchend', function() {
                if (refreshing || pullDist < threshold) {
                    if (indicator) {
                        indicator.style.transform = 'translateX(-50%) translateY(-60px)';
                        setTimeout(function() { indicator.remove(); indicator = null; }, 300);
                    }
                    pullDist = 0;
                    return;
                }
                refreshing = true;
                if (indicator) {
                    var text = indicator.querySelector('.dso-pull-text');
                    if (text) text.textContent = 'Refreshing...';
                    indicator.style.transform = 'translateX(-50%) translateY(0)';
                }
                self.api('/products/refresh?vendor_id=' + self.config.vendorId)
                    .then(function() {
                        refreshing = false;
                        if (indicator) {
                            indicator.style.transform = 'translateX(-50%) translateY(-60px)';
                            setTimeout(function() { indicator.remove(); indicator = null; }, 300);
                        }
                        window.location.reload();
                    })
                    .catch(function() {
                        refreshing = false;
                        if (indicator) {
                            indicator.style.transform = 'translateX(-50%) translateY(-60px)';
                            setTimeout(function() { indicator.remove(); indicator = null; }, 300);
                        }
                        self.toast('Refresh failed', 'error');
                    });
                pullDist = 0;
            });
        },

        // ═══════════════════════════════════════════════════
        // NEW: 27. SWIPE ACTIONS ON MOBILE (orders)
        // ═══════════════════════════════════════════════════

        initSwipeActions: function() {
            if (window.innerWidth > 768) return;
            var self = this;
            document.querySelectorAll('.dso-order-row[data-swipe-actions]').forEach(function(row) {
                var startX = 0;
                var currentX = 0;
                var swiping = false;

                row.addEventListener('touchstart', function(e) {
                    startX = e.touches[0].clientX;
                    swiping = true;
                    row.style.transition = 'none';
                }, { passive: true });

                row.addEventListener('touchmove', function(e) {
                    if (!swiping) return;
                    currentX = e.touches[0].clientX;
                    var diff = currentX - startX;
                    if (diff < 0 && Math.abs(diff) > 10) {
                        row.style.transform = 'translateX(' + Math.max(diff, -120) + 'px)';
                    }
                }, { passive: true });

                row.addEventListener('touchend', function() {
                    swiping = false;
                    row.style.transition = 'transform .3s ease';
                    var diff = currentX - startX;
                    if (diff < -80) {
                        row.style.transform = 'translateX(-120px)';
                        self._showSwipeActions(row);
                    } else {
                        row.style.transform = '';
                        self._hideSwipeActions(row);
                    }
                });
            });
        },

        _showSwipeActions: function(row) {
            var actions = row.querySelector('.dso-swipe-actions');
            if (actions) actions.style.display = 'flex';
        },

        _hideSwipeActions: function(row) {
            var actions = row.querySelector('.dso-swipe-actions');
            if (actions) actions.style.display = 'none';
        },

        // ═══════════════════════════════════════════════════
        // NEW: 28. LAZY LOADING FOR IMAGES
        // ═══════════════════════════════════════════════════

        initLazyLoading: function() {
            var lazyImages = document.querySelectorAll('img[data-src]');
            if (!lazyImages.length) return;

            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var img = entry.target;
                            img.src = img.getAttribute('data-src');
                            img.removeAttribute('data-src');
                            img.classList.add('dso-lazy-loaded');
                            observer.unobserve(img);
                        }
                    });
                }, { rootMargin: '100px' });

                lazyImages.forEach(function(img) {
                    img.classList.add('dso-lazy');
                    observer.observe(img);
                });
            } else {
                // Fallback
                lazyImages.forEach(function(img) {
                    img.src = img.getAttribute('data-src');
                    img.removeAttribute('data-src');
                });
            }
        },

        // ═══════════════════════════════════════════════════
        // NEW: 29. SCROLL TO TOP
        // ═══════════════════════════════════════════════════

        initScrollToTop: function() {
            var self = this;
            var btn = document.getElementById('dso-scroll-top');
            if (!btn) {
                btn = document.createElement('button');
                btn.id = 'dso-scroll-top';
                btn.className = 'dso-scroll-top-btn';
                btn.setAttribute('aria-label', 'Scroll to top');
                btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 15l-6-6-6 6"/></svg>';
                btn.style.cssText = 'position:fixed;bottom:24px;right:24px;width:44px;height:44px;border-radius:50%;border:none;background:#4f46e5;color:#fff;cursor:pointer;display:none;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(79,70,229,.35);z-index:9990;transition:all .3s ease;opacity:0;transform:translateY(10px)';
                document.body.appendChild(btn);
            }

            window.addEventListener('scroll', this.throttle(function() {
                if (window.scrollY > 400) {
                    btn.style.display = 'flex';
                    requestAnimationFrame(function() {
                        btn.style.opacity = '1';
                        btn.style.transform = 'translateY(0)';
                    });
                } else {
                    btn.style.opacity = '0';
                    btn.style.transform = 'translateY(10px)';
                    setTimeout(function() { btn.style.display = 'none'; }, 300);
                }
            }, 100));

            btn.addEventListener('click', function() {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        },

        // ═══════════════════════════════════════════════════
        // NEW: 30. CONFIRMATION DIALOGS
        // ═══════════════════════════════════════════════════

        initConfirmDialogs: function() {
            var self = this;
            document.querySelectorAll('[data-dso-confirm]').forEach(function(el) {
                el.addEventListener('click', function(e) {
                    e.preventDefault();
                    var message = el.getAttribute('data-dso-confirm') || 'Are you sure?';
                    var title = el.getAttribute('data-confirm-title') || 'Confirm Action';
                    self.confirm(message, title).then(function(confirmed) {
                        if (confirmed) {
                            if (el.getAttribute('data-confirm-action') === 'submit') {
                                var form = el.closest('form');
                                if (form) form.submit();
                            } else if (el.hasAttribute('href')) {
                                window.location.href = el.getAttribute('href');
                            } else {
                                el.click();
                            }
                        }
                    });
                });
            });
        },

        confirm: function(message, title, opts) {
            var self = this;
            opts = opts || {};
            return new Promise(function(resolve) {
                var overlay = document.createElement('div');
                overlay.className = 'dso-confirm-overlay';
                overlay.setAttribute('role', 'dialog');
                overlay.setAttribute('aria-modal', 'true');
                overlay.setAttribute('aria-labelledby', 'dso-confirm-title');

                var confirmText = opts.confirmText || 'Confirm';
                var cancelText = opts.cancelText || 'Cancel';
                var danger = opts.danger ? ' dso-confirm-danger' : '';

                overlay.innerHTML =
                    '<div class="dso-confirm-dialog">' +
                    '<h3 id="dso-confirm-title" class="dso-confirm-title">' + self.escapeHTML(title || 'Confirm') + '</h3>' +
                    '<p class="dso-confirm-message">' + self.escapeHTML(message) + '</p>' +
                    '<div class="dso-confirm-actions">' +
                    '<button class="dso-btn dso-btn-secondary dso-confirm-cancel">' + self.escapeHTML(cancelText) + '</button>' +
                    '<button class="dso-btn dso-btn-primary' + danger + ' dso-confirm-ok">' + self.escapeHTML(confirmText) + '</button>' +
                    '</div></div>';

                overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:100000;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s ease';
                document.body.appendChild(overlay);
                requestAnimationFrame(function() { overlay.style.opacity = '1'; });

                var close = function(result) {
                    overlay.style.opacity = '0';
                    setTimeout(function() { overlay.remove(); }, 200);
                    resolve(result);
                };

                overlay.querySelector('.dso-confirm-ok').addEventListener('click', function() { close(true); });
                overlay.querySelector('.dso-confirm-cancel').addEventListener('click', function() { close(false); });
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) close(false);
                });
                overlay.querySelector('.dso-confirm-ok').focus();

                overlay.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') close(false);
                    if (e.key === 'Enter') close(true);
                });
            });
        },

        // ═══════════════════════════════════════════════════
        // NEW: 31. PRINT ORDER / INVOICE
        // ═══════════════════════════════════════════════════

        initPrintOrder: function() {
            var self = this;
            document.querySelectorAll('[data-dso-print]').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var orderId = btn.getAttribute('data-dso-print');
                    self.printOrder(orderId);
                });
            });
        },

        printOrder: function(orderId) {
            var self = this;
            this.api('/orders/' + orderId + '/print')
                .then(function(data) {
                    if (data && data.html) {
                        self._openPrintWindow(data.html, 'Order #' + orderId);
                    } else {
                        self.toast('Could not load order for printing', 'error');
                    }
                })
                .catch(function() {
                    self.toast('Failed to load order', 'error');
                });
        },

        printInvoice: function(orderId) {
            var self = this;
            this.api('/orders/' + orderId + '/invoice')
                .then(function(data) {
                    if (data && data.html) {
                        self._openPrintWindow(data.html, 'Invoice #' + orderId);
                    } else {
                        self.toast('Could not load invoice', 'error');
                    }
                })
                .catch(function() {
                    self.toast('Failed to load invoice', 'error');
                });
        },

        _openPrintWindow: function(html, title) {
            var printWindow = window.open('', '_blank', 'width=800,height=600');
            if (!printWindow) {
                this.toast('Pop-up blocked. Please allow pop-ups to print.', 'warning');
                return;
            }
            printWindow.document.write(
                '<!DOCTYPE html><html><head><title>' + this.escapeHTML(title) + '</title>' +
                '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;padding:40px;color:#1a1a1a}' +
                'table{width:100%;border-collapse:collapse;margin:20px 0}' +
                'th,td{padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:left}' +
                'th{background:#f9fafb;font-weight:600}' +
                '.header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:30px;border-bottom:2px solid #1a1a1a;padding-bottom:20px}' +
                '.total{font-size:18px;font-weight:700;text-align:right;margin-top:20px}' +
                '@media print{body{padding:20px}}</style></head><body>' +
                html +
                '<script>window.onload=function(){window.print();window.close()}<\/script></body></html>'
            );
            printWindow.document.close();
        },

        // ═══════════════════════════════════════════════════
        // NEW: 32. EXPORT TO CSV
        // ═══════════════════════════════════════════════════

        initExportCSV: function() {
            var self = this;
            document.querySelectorAll('[data-dso-export]').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var target = btn.getAttribute('data-dso-export') || '';
                    self.exportCSV(target);
                });
            });
        },

        exportCSV: function(targetSelector) {
            var table;
            if (targetSelector) {
                table = document.querySelector(targetSelector);
            } else {
                table = document.querySelector('.dso-table:not([style*="display: none"])');
            }
            if (!table) {
                this.toast('No table found to export', 'warning');
                return;
            }
            var rows = [];
            // Header
            var headers = [];
            table.querySelectorAll('thead th').forEach(function(th) {
                if (th.querySelector('input[type="checkbox"]')) return;
                headers.push('"' + (th.textContent || '').trim().replace(/"/g, '""') + '"');
            });
            rows.push(headers.join(','));
            // Body
            table.querySelectorAll('tbody tr').forEach(function(tr) {
                if (tr.style.display === 'none') return;
                var cells = [];
                tr.querySelectorAll('td').forEach(function(td) {
                    if (td.querySelector('input[type="checkbox"]')) return;
                    var text = (td.textContent || '').trim().replace(/"/g, '""');
                    cells.push('"' + text + '"');
                });
                rows.push(cells.join(','));
            });
            var csv = rows.join('\n');
            var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'export-' + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            this.toast('CSV exported successfully', 'success');
        },

        // ═══════════════════════════════════════════════════
        // NEW: 33. PRINT-FRIENDLY VIEW TOGGLE
        // ═══════════════════════════════════════════════════

        initPrintView: function() {
            var self = this;
            document.querySelectorAll('[data-dso-toggle-print]').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.togglePrintView();
                });
            });
        },

        togglePrintView: function() {
            document.body.classList.toggle('dso-print-view');
            var isPrint = document.body.classList.contains('dso-print-view');
            this.toast(isPrint ? 'Print view enabled' : 'Print view disabled', 'info');
        },

        // ═══════════════════════════════════════════════════
        // NEW: 34. RESPONSIVE TABLE / CARD VIEW TOGGLE
        // ═══════════════════════════════════════════════════

        initResponsiveTableToggle: function() {
            var self = this;
            document.querySelectorAll('[data-dso-toggle-view]').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.toggleResponsiveView();
                });
            });
            // Auto-toggle on small screens
            if (window.innerWidth < 768) {
                var saved = localStorage.getItem('dso_view_mode');
                if (saved === 'card' || !saved) {
                    document.body.classList.add('dso-card-view');
                }
            }
        },

        toggleResponsiveView: function() {
            document.body.classList.toggle('dso-card-view');
            var isCard = document.body.classList.contains('dso-card-view');
            localStorage.setItem('dso_view_mode', isCard ? 'card' : 'table');
            this.toast(isCard ? 'Card view' : 'Table view', 'info');
        },

        // ═══════════════════════════════════════════════════
        // NEW: 35. QUICK ACTIONS FLOATING BUTTON (mobile)
        // ═══════════════════════════════════════════════════

        initQuickActions: function() {
            var self = this;
            var container = document.getElementById('dso-quick-actions');
            if (!container) {
                container = document.createElement('div');
                container.id = 'dso-quick-actions';
                container.className = 'dso-floating-actions';
                container.innerHTML =
                    '<button class="dso-fab dso-fab-main" aria-label="Quick actions" aria-expanded="false">' +
                    '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>' +
                    '</button>' +
                    '<div class="dso-fab-menu" style="display:none">' +
                    '<button class="dso-fab-item" data-action="addProduct" aria-label="Add product"><span class="dso-fab-icon">📦</span><span class="dso-fab-label">Product</span></button>' +
                    '<button class="dso-fab-item" data-action="search" aria-label="Search"><span class="dso-fab-icon">🔍</span><span class="dso-fab-label">Search</span></button>' +
                    '<button class="dso-fab-item" data-action="export" aria-label="Export CSV"><span class="dso-fab-icon">📥</span><span class="dso-fab-label">Export</span></button>' +
                    '</div>';
                container.style.cssText = 'position:fixed;bottom:80px;right:20px;z-index:9995;display:none;flex-direction:column;align-items:flex-end;gap:8px';
                document.body.appendChild(container);
            }

            // Only show on mobile
            var checkMobile = function() {
                container.style.display = window.innerWidth < 768 ? 'flex' : 'none';
            };
            checkMobile();
            window.addEventListener('resize', checkMobile);

            var mainBtn = container.querySelector('.dso-fab-main');
            var menu = container.querySelector('.dso-fab-menu');
            var menuOpen = false;

            if (mainBtn) {
                mainBtn.addEventListener('click', function() {
                    menuOpen = !menuOpen;
                    menu.style.display = menuOpen ? 'flex' : 'none';
                    mainBtn.setAttribute('aria-expanded', menuOpen);
                    if (menuOpen) {
                        mainBtn.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
                    } else {
                        mainBtn.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
                    }
                });
            }

            container.querySelectorAll('.dso-fab-item').forEach(function(item) {
                item.addEventListener('click', function() {
                    var action = item.getAttribute('data-action');
                    menuOpen = false;
                    menu.style.display = 'none';
                    mainBtn.setAttribute('aria-expanded', 'false');
                    mainBtn.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';

                    switch(action) {
                        case 'addProduct':
                            window.location.href = self.config.baseUrl + 'seller/products/add/';
                            break;
                        case 'search':
                            self.openSearch();
                            break;
                        case 'export':
                            self.exportCSV();
                            break;
                    }
                });
            });
        },

        // ═══════════════════════════════════════════════════
        // NEW: 36. THEME PREFERENCE (light/dark)
        // ═══════════════════════════════════════════════════

        initThemeToggle: function() {
            var self = this;
            document.querySelectorAll('[data-dso-toggle-theme]').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.toggleTheme();
                });
            });
        },

        loadTheme: function() {
            var saved = localStorage.getItem('dso_theme') || 'auto';
            this.state.theme = saved;
            this.applyTheme(saved);
        },

        toggleTheme: function() {
            var current = this.state.theme;
            var next;
            if (current === 'dark') next = 'light';
            else if (current === 'light') next = 'auto';
            else next = 'dark';
            this.state.theme = next;
            localStorage.setItem('dso_theme', next);
            this.applyTheme(next);
            this.toast('Theme: ' + next.charAt(0).toUpperCase() + next.slice(1), 'info');
        },

        applyTheme: function(theme) {
            var root = document.documentElement;
            if (theme === 'dark') {
                root.setAttribute('data-theme', 'dark');
                root.classList.add('dso-dark');
            } else if (theme === 'light') {
                root.setAttribute('data-theme', 'light');
                root.classList.remove('dso-dark');
            } else {
                root.removeAttribute('data-theme');
                root.classList.remove('dso-dark');
                if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    root.classList.add('dso-dark');
                }
            }
        },

        // ═══════════════════════════════════════════════════
        // NEW: 37. KEYBOARD SHORTCUTS OVERLAY
        // ═══════════════════════════════════════════════════

        initKeyboardShortcutsOverlay: function() {
            // Overlay is created on demand
        },

        showShortcutsOverlay: function() {
            var self = this;
            var existing = document.getElementById('dso-shortcuts-overlay');
            if (existing) { existing.remove(); return; }

            var shortcuts = [
                { keys: ['Ctrl/⌘', 'K'], desc: 'Open search' },
                { keys: ['Ctrl/⌘', 'Shift', 'P'], desc: 'Command palette' },
                { keys: ['/'], desc: 'Focus search' },
                { keys: ['?'], desc: 'Show keyboard shortcuts' },
                { keys: ['Esc'], desc: 'Close overlay / menu' },
                { keys: ['j', '/'], desc: 'Navigate down' },
                { keys: ['k'], desc: 'Navigate up' },
                { keys: ['Enter'], desc: 'Open focused item' },
                { keys: ['x'], desc: 'Toggle selection' },
                { keys: ['g', 'h'], desc: 'Go to Dashboard' },
                { keys: ['g', 'o'], desc: 'Go to Orders' },
                { keys: ['g', 'p'], desc: 'Go to Products' }
            ];

            var html = '<div id="dso-shortcuts-overlay" class="dso-confirm-overlay" role="dialog" aria-modal="true" aria-labelledby="shortcuts-title">';
            html += '<div class="dso-confirm-dialog" style="max-width:520px">';
            html += '<h3 id="shortcuts-title" class="dso-confirm-title">Keyboard Shortcuts</h3>';
            html += '<div class="dso-shortcuts-list">';
            shortcuts.forEach(function(s) {
                html += '<div class="dso-shortcut-row">';
                html += '<span class="dso-shortcut-keys">';
                s.keys.forEach(function(k, i) {
                    html += '<kbd>' + self.escapeHTML(k) + '</kbd>';
                    if (i < s.keys.length - 1) html += '<span class="dso-shortcut-plus">+</span>';
                });
                html += '</span>';
                html += '<span class="dso-shortcut-desc">' + self.escapeHTML(s.desc) + '</span>';
                html += '</div>';
            });
            html += '</div>';
            html += '<div class="dso-confirm-actions">';
            html += '<button class="dso-btn dso-btn-secondary dso-shortcuts-close">Close</button>';
            html += '</div></div></div>';

            var overlay = document.createElement('div');
            overlay.innerHTML = html;
            var overlayEl = overlay.firstElementChild;
            overlayEl.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:100000;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s ease';
            document.body.appendChild(overlayEl);
            requestAnimationFrame(function() { overlayEl.style.opacity = '1'; });

            var close = function() {
                overlayEl.style.opacity = '0';
                setTimeout(function() { overlayEl.remove(); }, 200);
            };

            overlayEl.querySelector('.dso-shortcuts-close').addEventListener('click', close);
            overlayEl.addEventListener('click', function(e) {
                if (e.target === overlayEl) close();
            });
            overlayEl.querySelector('.dso-shortcuts-close').focus();
        },

        closeShortcutsOverlay: function() {
            var overlay = document.getElementById('dso-shortcuts-overlay');
            if (overlay) {
                overlay.style.opacity = '0';
                setTimeout(function() { overlay.remove(); }, 200);
            }
        },

        // ═══════════════════════════════════════════════════
        // NEW: 38. STOCK ADJUSTMENT QUICK MODAL
        // ═══════════════════════════════════════════════════

        initStockAdjustModal: function() {
            var self = this;
            document.querySelectorAll('[data-dso-stock-adjust]').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    var productId = btn.getAttribute('data-dso-stock-adjust');
                    var productName = btn.getAttribute('data-product-name') || '';
                    var currentStock = btn.getAttribute('data-current-stock') || '0';
                    self.openStockAdjustModal(productId, productName, currentStock);
                });
            });
        },

        openStockAdjustModal: function(productId, productName, currentStock) {
            var self = this;
            var overlay = document.createElement('div');
            overlay.className = 'dso-confirm-overlay';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');

            overlay.innerHTML =
                '<div class="dso-confirm-dialog" style="max-width:420px">' +
                '<h3 class="dso-confirm-title">Adjust Stock</h3>' +
                '<p class="dso-confirm-message" style="margin-bottom:16px">' +
                '<strong>' + self.escapeHTML(productName || 'Product') + '</strong><br>' +
                'Current stock: <strong>' + self.escapeHTML(currentStock) + '</strong></p>' +
                '<div style="margin-bottom:16px">' +
                '<label for="dso-adjust-type" style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;color:#6b7280">Adjustment Type</label>' +
                '<select id="dso-adjust-type" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px">' +
                '<option value="set">Set to</option>' +
                '<option value="add">Add</option>' +
                '<option value="subtract">Subtract</option>' +
                '</select></div>' +
                '<div style="margin-bottom:8px">' +
                '<label for="dso-adjust-qty" style="display:block;font-size:13px;font-weight:500;margin-bottom:4px;color:#6b7280">Quantity</label>' +
                '<input type="number" id="dso-adjust-qty" min="0" value="0" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:14px">' +
                '</div>' +
                '<div class="dso-confirm-actions">' +
                '<button class="dso-btn dso-btn-secondary dso-stock-cancel">Cancel</button>' +
                '<button class="dso-btn dso-btn-primary dso-stock-confirm">Apply</button>' +
                '</div></div>';

            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:100000;display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s ease';
            document.body.appendChild(overlay);
            requestAnimationFrame(function() { overlay.style.opacity = '1'; });

            var close = function() {
                overlay.style.opacity = '0';
                setTimeout(function() { overlay.remove(); }, 200);
            };

            overlay.querySelector('.dso-stock-cancel').addEventListener('click', close);
            overlay.addEventListener('click', function(e) { if (e.target === overlay) close(); });

            var qtyInput = overlay.querySelector('#dso-adjust-qty');
            if (qtyInput) qtyInput.focus();

            overlay.querySelector('.dso-stock-confirm').addEventListener('click', function() {
                var type = overlay.querySelector('#dso-adjust-type').value;
                var qty = parseInt(overlay.querySelector('#dso-adjust-qty').value, 10);
                if (isNaN(qty) || qty < 0) {
                    self.toast('Enter a valid quantity', 'warning');
                    return;
                }
                close();
                self.api('/products/' + productId + '/stock', {
                    method: 'POST',
                    body: JSON.stringify({ adjustment_type: type, quantity: qty })
                }).then(function(data) {
                    if (data.success) {
                        self.toast('Stock updated', 'success');
                        setTimeout(function() { window.location.reload(); }, 800);
                    } else {
                        self.toast('Failed to update stock', 'error');
                    }
                }).catch(function() {
                    self.toast('Failed to update stock', 'error');
                });
            });
        },

        // ═══════════════════════════════════════════════════
        // NEW: 39. ANALYTICS DATE RANGE PICKER
        // ═══════════════════════════════════════════════════

        initAnalyticsDateRange: function() {
            var self = this;
            document.querySelectorAll('[data-dso-date-range]').forEach(function(container) {
                var startInput = container.querySelector('input[type="date"]');
                var endInput = container.querySelectorAll('input[type="date"]')[1];
                var applyBtn = container.querySelector('.dso-date-range-apply');
                var presets = container.querySelectorAll('.dso-date-preset');

                presets.forEach(function(preset) {
                    preset.addEventListener('click', function() {
                        var range = preset.getAttribute('data-range');
                        var now = new Date();
                        var start = new Date();
                        switch(range) {
                            case 'today': break;
                            case '7d': start.setDate(now.getDate() - 7); break;
                            case '30d': start.setDate(now.getDate() - 30); break;
                            case '90d': start.setDate(now.getDate() - 90); break;
                            case 'ytd': start = new Date(now.getFullYear(), 0, 1); break;
                        }
                        if (startInput) startInput.value = start.toISOString().slice(0, 10);
                        if (endInput) endInput.value = now.toISOString().slice(0, 10);
                        presets.forEach(function(p) { p.classList.remove('active'); });
                        preset.classList.add('active');
                        self.applyDateRange(startInput ? startInput.value : '', endInput ? endInput.value : '');
                    });
                });

                if (applyBtn) {
                    applyBtn.addEventListener('click', function() {
                        presets.forEach(function(p) { p.classList.remove('active'); });
                        self.applyDateRange(startInput ? startInput.value : '', endInput ? endInput.value : '');
                    });
                }
            });
        },

        applyDateRange: function(start, end) {
            var self = this;
            if (!start || !end) {
                this.toast('Select a date range', 'warning');
                return;
            }
            this.toast('Loading data for ' + start + ' to ' + end, 'info');
            // Update URL params and reload
            var url = new URL(window.location.href);
            url.searchParams.set('date_from', start);
            url.searchParams.set('date_to', end);
            window.location.href = url.toString();
        },

        // ═══════════════════════════════════════════════════
        // NEW: 40. FORM VALIDATION UX
        // ═══════════════════════════════════════════════════

        initFormValidation: function() {
            var self = this;
            document.querySelectorAll('form[data-dso-validate]').forEach(function(form) {
                var fields = form.querySelectorAll('[required], [data-validate]');
                fields.forEach(function(field) {
                    field.addEventListener('blur', function() {
                        self.validateField(field);
                    });
                    field.addEventListener('input', function() {
                        if (field.classList.contains('dso-field-error')) {
                            self.validateField(field);
                        }
                    });
                });

                form.addEventListener('submit', function(e) {
                    var valid = true;
                    fields.forEach(function(field) {
                        if (!self.validateField(field)) valid = false;
                    });
                    if (!valid) {
                        e.preventDefault();
                        var firstError = form.querySelector('.dso-field-error');
                        if (firstError) firstError.focus();
                        self.toast('Please fix the errors below', 'error');
                    }
                });
            });
        },

        validateField: function(field) {
            var value = field.value.trim();
            var valid = true;
            var message = '';

            // Required check
            if (field.hasAttribute('required') && !value) {
                valid = false;
                message = 'This field is required';
            }
            // Email
            else if (field.type === 'email' && value) {
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    valid = false;
                    message = 'Enter a valid email address';
                }
            }
            // Min length
            else if (field.getAttribute('minlength') && value.length < parseInt(field.getAttribute('minlength'), 10)) {
                valid = false;
                message = 'Minimum ' + field.getAttribute('minlength') + ' characters';
            }
            // Pattern
            else if (field.getAttribute('pattern') && value) {
                var regex = new RegExp('^' + field.getAttribute('pattern') + '$');
                if (!regex.test(value)) {
                    valid = false;
                    message = field.getAttribute('data-pattern-message') || 'Invalid format';
                }
            }
            // Number min/max
            else if (field.type === 'number' && value) {
                var num = parseFloat(value);
                if (field.getAttribute('min') && num < parseFloat(field.getAttribute('min'))) {
                    valid = false;
                    message = 'Minimum value is ' + field.getAttribute('min');
                }
                if (field.getAttribute('max') && num > parseFloat(field.getAttribute('max'))) {
                    valid = false;
                    message = 'Maximum value is ' + field.getAttribute('max');
                }
            }

            var errorEl = field.parentElement ? field.parentElement.querySelector('.dso-field-error-msg') : null;

            if (!valid) {
                field.classList.add('dso-field-error');
                if (!errorEl) {
                    errorEl = document.createElement('span');
                    errorEl.className = 'dso-field-error-msg';
                    errorEl.setAttribute('role', 'alert');
                    errorEl.style.cssText = 'display:block;font-size:12px;color:#ef4444;margin-top:4px';
                    field.parentElement.appendChild(errorEl);
                }
                errorEl.textContent = message;
            } else {
                field.classList.remove('dso-field-error');
                if (errorEl) errorEl.remove();
            }
            return valid;
        },

        // ═══════════════════════════════════════════════════
        // NEW: 41. RELATIVE DATE FORMATTING
        // ═══════════════════════════════════════════════════

        initRelativeDates: function() {
            var self = this;
            var elements = document.querySelectorAll('[data-dso-relative-date]');
            elements.forEach(function(el) {
                var dateStr = el.getAttribute('data-dso-relative-date') || el.textContent;
                if (dateStr) {
                    el.textContent = self.relativeTime(dateStr);
                    el.title = new Date(dateStr).toLocaleString();
                }
            });
        },

        relativeTime: function(dateInput) {
            var date = typeof dateInput === 'string' ? new Date(dateInput) : dateInput;
            if (isNaN(date.getTime())) return String(dateInput);

            var now = new Date();
            var diff = now - date;
            var absDiff = Math.abs(diff);
            var seconds = Math.floor(absDiff / 1000);
            var minutes = Math.floor(seconds / 60);
            var hours = Math.floor(minutes / 60);
            var days = Math.floor(hours / 24);
            var weeks = Math.floor(days / 7);
            var months = Math.floor(days / 30);

            if (diff < 0) {
                // Future
                if (seconds < 60) return 'in a few seconds';
                if (minutes < 60) return 'in ' + minutes + ' minute' + (minutes !== 1 ? 's' : '');
                if (hours < 24) return 'in ' + hours + ' hour' + (hours !== 1 ? 's' : '');
                if (days < 7) return 'in ' + days + ' day' + (days !== 1 ? 's' : '');
                if (weeks < 4) return 'in ' + weeks + ' week' + (weeks !== 1 ? 's' : '');
                return 'in ' + months + ' month' + (months !== 1 ? 's' : '');
            }

            if (seconds < 10) return 'just now';
            if (seconds < 60) return seconds + ' seconds ago';
            if (minutes < 60) return minutes + ' minute' + (minutes !== 1 ? 's' : '') + ' ago';
            if (hours < 24) return hours + ' hour' + (hours !== 1 ? 's' : '') + ' ago';
            if (days === 1) return 'yesterday';
            if (days < 7) return days + ' days ago';
            if (weeks === 1) return '1 week ago';
            if (weeks < 4) return weeks + ' weeks ago';
            if (months === 1) return '1 month ago';
            return months + ' months ago';
        },

        // ═══════════════════════════════════════════════════
        // NEW: 42. OPTIMISTIC UI UPDATES
        // ═══════════════════════════════════════════════════

        initOptimisticUpdates: function() {
            var self = this;
            // Watch for toggle switches (status changes)
            document.querySelectorAll('[data-dso-toggle-status]').forEach(function(toggle) {
                toggle.addEventListener('change', function() {
                    var id = toggle.getAttribute('data-id');
                    var field = toggle.getAttribute('data-field');
                    var newValue = toggle.checked ? 'publish' : 'draft';
                    var originalValue = toggle.getAttribute('data-original');
                    var row = toggle.closest('tr') || toggle.closest('.dso-product-row');

                    // Optimistic UI: update immediately
                    if (row) {
                        var badge = row.querySelector('.dso-badge');
                        if (badge) {
                            badge.textContent = newValue.charAt(0).toUpperCase() + newValue.slice(1);
                            badge.className = 'dso-badge dso-badge-' + newValue;
                        }
                    }

                    this.api('/products/' + id, {
                        method: 'POST',
                        body: JSON.stringify({ [field]: newValue })
                    }).catch(function() {
                        // Rollback on failure
                        toggle.checked = !toggle.checked;
                        if (row) {
                            var badge = row.querySelector('.dso-badge');
                            if (badge) {
                                badge.textContent = originalValue || 'Unknown';
                                badge.className = 'dso-badge dso-badge-' + (originalValue || 'draft');
                            }
                        }
                        self.toast('Failed to update. Reverted.', 'error');
                    });
                }.bind(this));
            });
        },

        // ═══════════════════════════════════════════════════
        // NEW: 43. ACCESSIBILITY IMPROVEMENTS
        // ═══════════════════════════════════════════════════

        initAccessibility: function() {
            // Add ARIA landmarks
            var sidebar = document.getElementById('dso-sidebar');
            if (sidebar && !sidebar.getAttribute('role')) {
                sidebar.setAttribute('role', 'navigation');
                sidebar.setAttribute('aria-label', 'Main navigation');
            }

            // Add skip link
            if (!document.getElementById('dso-skip-link')) {
                var skipLink = document.createElement('a');
                skipLink.id = 'dso-skip-link';
                skipLink.href = '#dso-main-content';
                skipLink.textContent = 'Skip to main content';
                skipLink.className = 'dso-skip-link';
                skipLink.style.cssText = 'position:absolute;top:-100px;left:0;background:#4f46e5;color:#fff;padding:8px 16px;z-index:100000;font-size:14px;font-weight:500;transition:top .2s ease';
                document.body.insertBefore(skipLink, document.body.firstChild);
                skipLink.addEventListener('focus', function() {
                    skipLink.style.top = '0';
                });
                skipLink.addEventListener('blur', function() {
                    skipLink.style.top = '-100px';
                });
            }

            // Announce page changes for screen readers
            var announcer = document.createElement('div');
            announcer.id = 'dso-announcer';
            announcer.setAttribute('aria-live', 'polite');
            announcer.setAttribute('aria-atomic', 'true');
            announcer.className = 'sr-only';
            announcer.style.cssText = 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0';
            document.body.appendChild(announcer);
        },

        announce: function(message) {
            var announcer = document.getElementById('dso-announcer');
            if (announcer) {
                announcer.textContent = message;
                setTimeout(function() { announcer.textContent = ''; }, 1000);
            }
        }
    };

    // ─── Auto-init on DOMContentLoaded ────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { DSO.init(); });
    } else {
        DSO.init();
    }

})(window, document);
