/**
 * DEJOIY Seller Hub - Production JavaScript
 * @version 2.0.0
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
            selectedProducts: {},
            selectedCount: 0,
            searchIndex: -1,
            commandIndex: -1
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
            maxRecent: 10
        },

        init: function() {
            this.loadConfig();
            this.loadRecentSearches();
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
            this.initMediaDropzones();
        },

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

        // ─── API Helper ────────────────────────────────────
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

        // ─── 1. Sidebar Toggle ─────────────────────────────
        initSidebar: function() {
            var self = this;
            var toggleElements = document.querySelectorAll('[data-dso-toggle="sidebar"], #dso-menu-toggle');
            toggleElements.forEach(function(btn) {
                // Avoid double-binding if inline handler exists
                if (btn.getAttribute('data-dso-bound') === 'true') return;
                btn.setAttribute('data-dso-bound', 'true');
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.toggleSidebar();
                });
            });
            var overlay = document.getElementById('dso-sidebar-overlay');
            if (overlay) {
                overlay.addEventListener('click', function() { self.closeSidebar(); });
            }
            var closeBtn = document.getElementById('dso-sidebar-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() { self.closeSidebar(); });
            }
            var sidebar = document.getElementById('dso-sidebar');
            if (sidebar) {
                sidebar.querySelectorAll('.nav-item > a, .dso-nav-link, .dso-nav-child').forEach(function(link) {
                    link.addEventListener('click', function() {
                        if (window.innerWidth < 1024) self.closeSidebar();
                    });
                });
            }
        },

        toggleSidebar: function() {
            var sidebar = document.getElementById('dso-sidebar');
            var app = document.getElementById('dso-app');
            if (window.innerWidth >= 1024) {
                if (app) app.classList.toggle('dso-sidebar-collapsed');
                if (sidebar) sidebar.classList.toggle('dso-sidebar-collapsed');
            } else {
                var isOpen = sidebar ? (sidebar.classList.contains('open') || sidebar.classList.contains('dso-sidebar-open')) : false;
                isOpen ? this.closeSidebar() : this.openSidebar();
            }
        },

        openSidebar: function() {
            var sidebar = document.getElementById('dso-sidebar');
            var overlay = document.getElementById('dso-sidebar-overlay');
            if (sidebar) sidebar.classList.add('open', 'dso-sidebar-open');
            if (overlay) overlay.classList.add('visible', 'dso-visible', 'active');
            document.body.style.overflow = 'hidden';
            this.state.sidebarOpen = true;
        },

        closeSidebar: function() {
            var sidebar = document.getElementById('dso-sidebar');
            var overlay = document.getElementById('dso-sidebar-overlay');
            if (sidebar) sidebar.classList.remove('open', 'dso-sidebar-open');
            if (overlay) overlay.classList.remove('visible', 'dso-visible', 'active');
            document.body.style.overflow = '';
            this.state.sidebarOpen = false;
        },

        // ─── 2. Nav Children Expand ────────────────────────
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

        // ─── 3. Universal Search ───────────────────────────
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
                .catch(function() {
                    self.renderSearchEmpty('Search failed. Please try again.');
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
                    html += '<a href="' + url + '" class="search-result-item">';
                    html += '<span class="search-result-title">' + self.highlightMatch(title, query) + '</span>';
                    if (subtitle) html += '<span class="search-result-subtitle">' + subtitle + '</span>';
                    html += '</a>';
                });
                html += '</div>';
            });
            if (!html) { this.renderSearchEmpty('No results found for "' + query + '"'); return; }
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
                html += '<button class="search-recent-item" data-query="' + q + '">';
                html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
                html += '<span>' + q + '</span></button>';
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

        // ─── 4. Command Palette ────────────────────────────
        commands: [
            { id: 'add-product', label: 'Add Product', icon: '📦', action: 'addProduct' },
            { id: 'find-order', label: 'Find Order', icon: '🔍', action: 'findOrder' },
            { id: 'analytics', label: 'View Analytics', icon: '📊', action: 'viewAnalytics' },
            { id: 'inventory', label: 'Manage Inventory', icon: '📋', action: 'manageInventory' },
            { id: 'reports', label: 'View Reports', icon: '📈', action: 'viewReports' },
            { id: 'performance', label: 'Check Performance', icon: '⚡', action: 'checkPerformance' },
            { id: 'settings', label: 'Settings', icon: '⚙️', action: 'openSettings' },
            { id: 'help', label: 'Help & Support', icon: '❓', action: 'openHelp' },
            { id: 'dashboard', label: 'Go to Dashboard', icon: '🏠', action: 'goDashboard' },
            { id: 'finance', label: 'View Finance', icon: '💰', action: 'goFinance' }
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
            cmds.forEach(function(cmd) {
                html += '<button class="command-item" data-action="' + cmd.action + '">';
                html += '<span class="command-icon">' + cmd.icon + '</span>';
                html += '<span class="command-label">' + cmd.label + '</span></button>';
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

        filterCommands: function(query) {
            if (!query) { this.renderCommands(this.commands); return; }
            var lower = query.toLowerCase();
            var filtered = this.commands.filter(function(cmd) {
                return cmd.label.toLowerCase().indexOf(lower) !== -1;
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

        executeCommand: function(action) {
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
            if (routes[action]) window.location.href = routes[action];
        },

        // ─── 5. Dashboard Charts ───────────────────────────
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
                    label: 'Sales', data: chartData.sales, borderColor: '#0066ff', backgroundColor: gradient,
                    borderWidth: 2.5, fill: true, tension: 0.4, pointRadius: 0, pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#0066ff', pointHoverBorderColor: '#fff', pointHoverBorderWidth: 3
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
            // Revenue line
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
            // Orders bar
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
            var gradient = this._makeGradient(canvas.getContext('2d'), 'rgba(0,102,255,0.15)', 'rgba(0,102,255,0)');
            var opts = this._chartDefaults();
            opts.scales.y.ticks.callback = function(v) { return this.config.currency + v.toLocaleString(); }.bind(this);
            this._buildChart('dso-reports-chart', 'line', {
                labels: chartData.labels,
                datasets: [{
                    label: 'Revenue', data: chartData.values || chartData.revenue, borderColor: '#0066ff',
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

        // ─── 6. Products ───────────────────────────────────
        initProducts: function() {
            var self = this;
            var search = document.getElementById('dso-product-search');
            var statusFilter = document.getElementById('dso-product-status');
            var stockFilter = document.getElementById('dso-product-stock');
            var selectAll = document.getElementById('dso-select-all');
            var bulkBtn = document.getElementById('dso-bulk-apply');

            if (search) search.addEventListener('input', function() { self.filterProducts(); });
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

        // ─── 7. Add / Edit Product ─────────────────────────
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

        // ─── 8. Inventory ──────────────────────────────────
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
            if (inputEl) { inputEl.style.display = ''; inputEl.focus(); }
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

        // ─── 9. Settings / Help / Favorites ────────────────
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
            // Close others
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

        // ─── 10. Toast Notifications ───────────────────────
        toast: function(message, type) {
            type = type || 'info';
            var container = document.getElementById('dso-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'dso-toast-container';
                container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;';
                document.body.appendChild(container);
            }
            var toast = document.createElement('div');
            toast.className = 'dso-toast dso-toast-' + type;
            var icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
            toast.innerHTML = '<span class="dso-toast-icon">' + (icons[type] || icons.info) + '</span><span class="dso-toast-msg">' + message + '</span>';
            container.appendChild(toast);
            requestAnimationFrame(function() { toast.classList.add('show'); });
            setTimeout(function() {
                toast.classList.remove('show');
                setTimeout(function() { toast.remove(); }, 300);
            }, 4000);
        },

        // ─── 11. Keyboard Shortcuts ────────────────────────
        initKeyboardShortcuts: function() {
            var self = this;
            document.addEventListener('keydown', function(e) {
                var isMeta = e.metaKey || e.ctrlKey;
                // Cmd/Ctrl+K: open search
                if (isMeta && e.key === 'k') {
                    e.preventDefault();
                    if (self.state.commandOpen) { self.closeCommand(); return; }
                    if (self.state.searchOpen) { self.closeSearch(); return; }
                    self.openSearch();
                    return;
                }
                // Escape: close overlays
                if (e.key === 'Escape') {
                    if (self.state.searchOpen) { self.closeSearch(); return; }
                    if (self.state.commandOpen) { self.closeCommand(); return; }
                    if (self.state.sidebarOpen) { self.closeSidebar(); return; }
                    self.closeAllDropdowns();
                }
            });
        },

        // ─── 12. Auto-refresh Notifications ─────────────────
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

        // ─── 13. Responsive Handling ───────────────────────
        initResponsive: function() {
            var self = this;
            var resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    if (window.innerWidth >= 1024 && self.state.sidebarOpen) {
                        self.closeSidebar();
                    }
                    // Close dropdowns on resize to desktop
                    if (window.innerWidth >= 1024) self.closeAllDropdowns();
                }, 150);
            });
        },

        // ─── 14. Smooth Scroll ─────────────────────────────
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

        // ─── 15. Table Sorting ─────────────────────────────
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
                    // Clear other sort indicators
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
                    // Update visual indicator
                    table.querySelectorAll('th[data-sort]').forEach(function(h) {
                        h.classList.remove('sort-asc', 'sort-desc');
                    });
                    th.classList.add(dir === 'asc' ? 'sort-asc' : 'sort-desc');
                });
            });
        },

        // ─── 16. Skeleton Loading ──────────────────────────
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

        // ─── 17. Orders / Customers (generic init) ─────────
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
            if (searchInput) searchInput.addEventListener('input', filter);
            if (statusFilter) statusFilter.addEventListener('change', filter);
        },

        initCustomers: function() {
            var searchInput = document.getElementById('dso-customer-search');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    var query = this.value.toLowerCase();
                    document.querySelectorAll('.dso-table tbody tr').forEach(function(row) {
                        row.style.display = row.textContent.toLowerCase().indexOf(query) > -1 ? '' : 'none';
                    });
                });
            }
        },

        // ─── 18. Category Smart Fields Switcher ─────────────
        toggleSmartFields: function(type) {
            var groups = ['fashion', 'electronics', 'beauty', 'grocery', 'books'];
            groups.forEach(function(g) {
                var el = document.getElementById('smart-group-' + g);
                if (el) {
                    el.style.display = (g === type) ? '' : 'none';
                }
            });
            var badge = document.getElementById('dso-smart-badge');
            if (badge) {
                badge.textContent = type ? type.toUpperCase() : 'GENERAL';
            }
        },

        // ─── 19. Real-Time Discount Calculator ──────────────
        recalculateDiscount: function() {
            var mrp = parseFloat((document.getElementById('mrp') || {}).value) || 0;
            var regular = parseFloat((document.getElementById('regular_price') || {}).value) || 0;
            var sale = parseFloat((document.getElementById('sale_price') || {}).value) || 0;
            var effPrice = sale > 0 ? sale : regular;

            var badge = document.getElementById('dso-discount-badge');
            var text = document.getElementById('dso-discount-text');
            if (!badge || !text) return;

            if (mrp > 0 && effPrice > 0 && mrp > effPrice) {
                var pct = Math.round(((mrp - effPrice) / mrp) * 100);
                var saving = (mrp - effPrice).toFixed(2);
                badge.textContent = pct + '% OFF';
                badge.className = 'dso-badge dso-badge-green';
                text.textContent = 'Buyers save ₹' + saving + ' (' + pct + '% discount against MRP ₹' + mrp.toFixed(2) + ')';
            } else if (mrp > 0 && effPrice > mrp) {
                badge.textContent = 'PRICING ALERT';
                badge.className = 'dso-badge dso-badge-red';
                text.textContent = 'Selling price cannot exceed MRP by Indian Consumer Protection rules.';
            } else {
                badge.textContent = '0% OFF';
                badge.className = 'dso-badge dso-badge-gray';
                text.textContent = 'Enter MRP and Selling Price to see customer discount banner';
            }
        },

        // ─── 20. Media Uploaders & Removers ────────────────
        removeFeaturedImage: function() {
            var input = document.getElementById('featured_image_id');
            if (input) input.value = '';
            var preview = document.getElementById('dso-thumb-preview');
            if (preview) {
                preview.innerHTML = '<div class="dso-thumb-empty" id="dso-thumb-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg><span>Drop featured image here or click upload</span></div>';
            }
            if (this.initProductEditor) this.initProductEditor();
        },

        openMediaUploader: function(targetId, previewId) {
            var self = this;
            if (typeof wp !== 'undefined' && wp.media) {
                var frame = wp.media({
                    title: 'Select Featured Image',
                    button: { text: 'Use this image' },
                    multiple: false,
                    library: { type: 'image' }
                });
                frame.on('select', function() {
                    var att = frame.state().get('selection').first().toJSON();
                    var input = document.getElementById(targetId);
                    if (input) input.value = att.id;
                    var preview = document.getElementById(previewId);
                    if (preview) {
                        var url = (att.sizes && att.sizes.medium) ? att.sizes.medium.url : att.url;
                        preview.innerHTML = '<img src="' + url + '" alt="" id="dso-thumb-img" /><button type="button" class="dso-remove-thumb-btn" onclick="DSO.removeFeaturedImage();">×</button>';
                    }
                    if (self.initProductEditor) self.initProductEditor();
                });
                frame.open();
            } else {
                var fileInp = document.getElementById('featured_file');
                if (fileInp) fileInp.click();
            }
        },

        handleDirectUpload: function(input, previewId) {
            var self = this;
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var preview = document.getElementById(previewId);
                    if (preview) {
                        preview.innerHTML = '<img src="' + e.target.result + '" alt="" id="dso-thumb-img" /><button type="button" class="dso-remove-thumb-btn" onclick="DSO.removeFeaturedImage();">×</button>';
                    }
                    if (self.initProductEditor) self.initProductEditor();
                };
                reader.readAsDataURL(input.files[0]);
            }
        },

        openGalleryUploader: function() {
            var self = this;
            if (typeof wp !== 'undefined' && wp.media) {
                var frame = wp.media({
                    title: 'Select Gallery Images',
                    button: { text: 'Add to Gallery' },
                    multiple: true,
                    library: { type: 'image' }
                });
                frame.on('select', function() {
                    var selection = frame.state().get('selection');
                    var grid = document.getElementById('dso-gallery-grid');
                    selection.map(function(attachment) {
                        attachment = attachment.toJSON();
                        var url = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url;
                        if (grid) {
                            var item = document.createElement('div');
                            item.className = 'dso-gallery-item';
                            item.setAttribute('data-id', attachment.id);
                            item.innerHTML = '<img src="' + url + '" alt="" /><button type="button" class="dso-gallery-remove" onclick="this.parentElement.remove(); DSO.syncGalleryIds();">×</button>';
                            grid.appendChild(item);
                        }
                    });
                    self.syncGalleryIds();
                });
                frame.open();
            } else {
                var galInp = document.getElementById('gallery_files');
                if (galInp) { galInp.click(); }
                else if (self.toast) { self.toast('Media library is accessible inside active WordPress session', 'info'); }
            }
        },

        syncGalleryIds: function() {
            var ids = [];
            document.querySelectorAll('#dso-gallery-grid .dso-gallery-item').forEach(function(el) {
                var id = el.getAttribute('data-id');
                if (id) ids.push(id);
            });
            var input = document.getElementById('gallery_image_ids');
            if (input) input.value = ids.join(',');
            if (this.initProductEditor) this.initProductEditor();
        },

        handleGalleryFiles: function(input) {
            var self = this;
            var grid = document.getElementById('dso-gallery-grid');
            if (!grid || !input.files) return;
            grid.querySelectorAll('.dso-gallery-temp').forEach(function(el) { el.remove(); });
            var existing = grid.querySelectorAll('.dso-gallery-item[data-id]').length;
            var files = Array.prototype.slice.call(input.files).filter(function(f) {
                return f.type && f.type.indexOf('image/') === 0;
            });
            var slots = Math.max(0, 5 - existing);
            if (files.length > slots) {
                files = files.slice(0, slots);
                if (self.toast) self.toast('Gallery holds up to 5 images', 'info');
            }
            var dt = new DataTransfer();
            files.forEach(function(f) { dt.items.add(f); });
            input.files = dt.files;
            files.forEach(function(file) {
                var item = document.createElement('div');
                item.className = 'dso-gallery-item dso-gallery-temp';
                var img = document.createElement('img');
                img.alt = '';
                item.appendChild(img);
                var badge = document.createElement('span');
                badge.className = 'dso-gallery-new';
                badge.textContent = 'New';
                item.appendChild(badge);
                var rm = document.createElement('button');
                rm.type = 'button';
                rm.className = 'dso-gallery-remove';
                rm.textContent = '×';
                rm.setAttribute('onclick', 'DSO.removeGalleryTemp(this);');
                item.appendChild(rm);
                grid.appendChild(item);
                var reader = new FileReader();
                reader.onload = (function(im) {
                    return function(e) { im.src = e.target.result; };
                })(img);
                reader.readAsDataURL(file);
            });
            if (self.initProductEditor) self.initProductEditor();
        },

        removeGalleryTemp: function(btn) {
            var item = btn.parentElement;
            var grid = document.getElementById('dso-gallery-grid');
            var input = document.getElementById('gallery_files');
            if (grid && input && input.files) {
                var temps = Array.prototype.slice.call(grid.querySelectorAll('.dso-gallery-temp'));
                var at = temps.indexOf(item);
                if (at > -1) {
                    var dt = new DataTransfer();
                    Array.prototype.slice.call(input.files).forEach(function(f, i) {
                        if (i !== at) dt.items.add(f);
                    });
                    input.files = dt.files;
                }
            }
            if (item) item.remove();
            if (this.initProductEditor) this.initProductEditor();
        },

        initMediaDropzones: function() {
            var self = this;
            function wireDrop(zone, input, handler) {
                if (!zone || !input) return;
                ['dragenter', 'dragover'].forEach(function(ev) {
                    zone.addEventListener(ev, function(e) { e.preventDefault(); zone.classList.add('dso-drop-active'); });
                });
                ['dragleave', 'drop'].forEach(function(ev) {
                    zone.addEventListener(ev, function(e) { e.preventDefault(); zone.classList.remove('dso-drop-active'); });
                });
                zone.addEventListener('drop', function(e) {
                    var files = e.dataTransfer && e.dataTransfer.files;
                    if (!files || !files.length) return;
                    var dt = new DataTransfer();
                    if (input.multiple) {
                        Array.prototype.slice.call(input.files).forEach(function(f) { dt.items.add(f); });
                    }
                    Array.prototype.slice.call(files).forEach(function(f) { dt.items.add(f); });
                    input.files = dt.files;
                    handler(input);
                });
            }
            wireDrop(document.getElementById('dso-thumb-preview'), document.getElementById('featured_file'), function(inp) {
                self.handleDirectUpload(inp, 'dso-thumb-preview');
            });
            wireDrop(document.getElementById('dso-gallery-grid'), document.getElementById('gallery_files'), function(inp) {
                self.handleGalleryFiles(inp);
            });
            var thumb = document.getElementById('dso-thumb-preview');
            if (thumb) {
                thumb.addEventListener('click', function(e) {
                    if (e.target.closest('button')) return;
                    if (!document.getElementById('dso-thumb-img')) {
                        var fi = document.getElementById('featured_file');
                        if (fi) fi.click();
                    }
                });
            }
        },

        // ─── 21. Real-Time Listing Quality Score (LQS) ─────
        initProductEditor: function() {
            var self = this;
            function calculateLQS() {
                var score = 0;
                var title = (document.getElementById('product_title') || {}).value || '';
                var desc = (document.getElementById('description') || {}).value || '';
                var shortDesc = (document.getElementById('short_description') || {}).value || '';
                var mrp = parseFloat((document.getElementById('mrp') || {}).value) || 0;
                var regPrice = parseFloat((document.getElementById('regular_price') || {}).value) || 0;
                var featImg = (document.getElementById('featured_image_id') || {}).value || '';
                var hasThumbImg = !!document.getElementById('dso-thumb-img');
                var galleryInput = (document.getElementById('gallery_image_ids') || {}).value || '';
                var hsn = (document.getElementById('hsn_code') || {}).value || '';
                var mfr = (document.getElementById('manufacturer') || {}).value || '';

                // 1. Title Check (≥25 chars)
                var titleCheck = document.getElementById('lqs-check-title');
                if (title.trim().length >= 25) {
                    score += 20;
                    if (titleCheck) titleCheck.className = 'dso-lqs-check dso-lqs-pass';
                } else {
                    if (titleCheck) titleCheck.className = 'dso-lqs-check dso-lqs-fail';
                }

                // 2. Main Image
                var imgCheck = document.getElementById('lqs-check-img');
                if (featImg || hasThumbImg) {
                    score += 20;
                    if (imgCheck) imgCheck.className = 'dso-lqs-check dso-lqs-pass';
                } else {
                    if (imgCheck) imgCheck.className = 'dso-lqs-check dso-lqs-fail';
                }

                // 3. Gallery
                var galCheck = document.getElementById('lqs-check-gallery');
                var galCount = document.querySelectorAll('#dso-gallery-grid .dso-gallery-item').length;
                if (galleryInput || galCount > 0) {
                    score += 15;
                    if (galCheck) galCheck.className = 'dso-lqs-check dso-lqs-pass';
                } else {
                    if (galCheck) galCheck.className = 'dso-lqs-check dso-lqs-fail';
                }

                // 4. MRP & Pricing
                var mrpCheck = document.getElementById('lqs-check-mrp');
                if (mrp > 0 && regPrice > 0) {
                    score += 15;
                    if (mrpCheck) mrpCheck.className = 'dso-lqs-check dso-lqs-pass';
                } else {
                    if (mrpCheck) mrpCheck.className = 'dso-lqs-check dso-lqs-fail';
                }

                // 5. Description & Bullets
                var descCheck = document.getElementById('lqs-check-desc');
                if (desc.trim().length >= 50 || shortDesc.trim().length >= 20) {
                    score += 15;
                    if (descCheck) descCheck.className = 'dso-lqs-check dso-lqs-pass';
                } else {
                    if (descCheck) descCheck.className = 'dso-lqs-check dso-lqs-fail';
                }

                // 6. Compliance
                var compCheck = document.getElementById('lqs-check-compliance');
                if (hsn.trim().length >= 4 && mfr.trim().length >= 5) {
                    score += 15;
                    if (compCheck) compCheck.className = 'dso-lqs-check dso-lqs-pass';
                } else {
                    if (compCheck) compCheck.className = 'dso-lqs-check dso-lqs-fail';
                }

                score = Math.min(100, score);

                // Update score display
                var valEl = document.getElementById('dso-lqs-val');
                var badgeEl = document.getElementById('dso-lqs-badge');
                if (valEl) valEl.textContent = score;
                if (badgeEl) {
                    if (score >= 80) {
                        badgeEl.textContent = 'OPTIMAL';
                        badgeEl.className = 'dso-badge dso-badge-green';
                    } else if (score >= 50) {
                        badgeEl.textContent = 'GOOD';
                        badgeEl.className = 'dso-badge dso-badge-orange';
                    } else {
                        badgeEl.textContent = 'NEEDS ATTENTION';
                        badgeEl.className = 'dso-badge dso-badge-red';
                    }
                }

                // Live SERP preview update
                var serpTitle = document.getElementById('dso-serp-title-preview');
                if (serpTitle) serpTitle.textContent = title ? (title + ' — DEJOIY Marketplace') : 'Your Product Name — DEJOIY Marketplace';
                var serpDesc = document.getElementById('dso-serp-desc-preview');
                if (serpDesc) {
                    var rawDesc = shortDesc || desc;
                    serpDesc.textContent = rawDesc ? (rawDesc.slice(0, 150) + '...') : 'Buy genuine products on DEJOIY with fast nationwide shipping and secure payments.';
                }
            }

            ['product_title', 'description', 'short_description', 'mrp', 'regular_price', 'sale_price', 'hsn_code', 'manufacturer'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) {
                    el.addEventListener('input', calculateLQS);
                }
            });

            self.recalculateDiscount();
            calculateLQS();
        },

        // ─── 22. In-Line Quick Stock AJAX ───────────────────
        saveStockQuick: function(id) {
            var input = document.getElementById('stock-input-' + id);
            if (!input) return;
            var stock = parseInt(input.value) || 0;
            var self = this;
            
            var url = (typeof dsoData !== 'undefined' && dsoData.baseUrl) ? (dsoData.baseUrl + '?action=stock_update') : (self.config.baseUrl + '?action=stock_update');
            var fd = new FormData();
            fd.append('product_id', id);
            fd.append('stock', stock);

            fetch(url, {
                method: 'POST',
                body: fd
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.success) {
                    if (self.toast) self.toast('Stock updated to ' + stock + ' units', 'success');
                    var row = input.closest('tr');
                    if (row) {
                        var tag = row.querySelector('.dso-stock-status-tag');
                        if (tag) {
                            if (stock > 0) {
                                tag.className = 'dso-stock-status-tag tag-instock';
                                tag.textContent = 'In Stock (' + stock + ')';
                            } else {
                                tag.className = 'dso-stock-status-tag tag-outofstock';
                                tag.textContent = 'Out of Stock';
                            }
                        }
                    }
                } else {
                    if (self.toast) self.toast('Error saving stock: ' + (data.error || 'Failed'), 'error');
                }
            })
            .catch(function(err) {
                if (self.toast) self.toast('Failed to update stock quantity', 'error');
            });
        },

        // ─── 22b. In-Line Quick Price AJAX (Amazon Manage Inventory style) ───
        savePriceQuick: function(id) {
            var input = document.getElementById('price-input-' + id);
            if (!input) return;
            var price = parseFloat(input.value) || 0;
            var self = this;

            var url = (typeof dsoData !== 'undefined' && dsoData.baseUrl) ? (dsoData.baseUrl + '?action=price_update') : (self.config.baseUrl + '?action=price_update');
            var fd = new FormData();
            fd.append('product_id', id);
            fd.append('price', price);

            fetch(url, { method: 'POST', body: fd })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.success) {
                    if (self.toast) self.toast('Price updated to ₹' + price.toFixed(2), 'success');
                    input.classList.add('dso-save-flash');
                    setTimeout(function() { input.classList.remove('dso-save-flash'); }, 1200);

                    // Update fee and net proceeds calculation dynamically
                    var feeElem = document.getElementById('fee-preview-' + id);
                    if (feeElem) {
                        var referralFee = (price * 0.12).toFixed(2);
                        var netPayout = (price - referralFee).toFixed(2);
                        feeElem.innerHTML = 'Fee: ₹' + referralFee + ' (12%) • <span class="dso-net">Net: ₹' + netPayout + '</span>';
                    }
                } else {
                    if (self.toast) self.toast('Error saving price: ' + (data.error || 'Failed'), 'error');
                }
            })
            .catch(function() {
                if (self.toast) self.toast('Failed to update listing price', 'error');
            });
        },

        saveInlineInventory: function(id) {
            this.savePriceQuick(id);
            this.saveStockQuick(id);
        },

        // ─── 22c. Competitive Repricer Toggle ────────────────
        toggleRepricer: function(id, isChecked) {
            var self = this;
            var minInput = document.getElementById('repricer-min-' + id);
            var maxInput = document.getElementById('repricer-max-' + id);
            var minVal = minInput ? parseFloat(minInput.value) || 0 : 0;
            var maxVal = maxInput ? parseFloat(maxInput.value) || 0 : 0;

            var url = (typeof dsoData !== 'undefined' && dsoData.baseUrl) ? (dsoData.baseUrl + '?action=toggle_repricer') : (self.config.baseUrl + '?action=toggle_repricer');
            var fd = new FormData();
            fd.append('product_id', id);
            fd.append('active', isChecked ? 1 : 0);
            fd.append('min_price', minVal);
            fd.append('max_price', maxVal);

            fetch(url, { method: 'POST', body: fd })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.success) {
                    if (self.toast) self.toast(isChecked ? 'Automated repricing ACTIVATED for SKU' : 'Repricer paused for SKU', 'success');
                }
            })
            .catch(function() {
                if (self.toast) self.toast('Could not update repricer status', 'error');
            });
        },

        // ─── 22d. Coupon Code Generator ─────────────────────
        generateCouponCode: function() {
            var prefixes = ['DEJOIY', 'MEGA', 'FESTIVE', 'SUPER', 'DEAL', 'SAVE'];
            var p = prefixes[Math.floor(Math.random() * prefixes.length)];
            var rand = Math.floor(1000 + Math.random() * 9000);
            var code = p + rand;
            var codeInput = document.getElementById('coupon_code');
            if (codeInput) codeInput.value = code;
        },

        // ─── 22e. Client-side CSV Catalog Parser & Validator ──
        handleCatalogCsvUpload: function(input) {
            var file = input.files ? input.files[0] : null;
            if (!file) return;

            var reader = new FileReader();
            reader.onload = function(e) {
                var text = e.target.result;
                var lines = text.split(/\r?\n/).filter(function(l) { return l.trim().length > 0; });
                if (lines.length <= 1) {
                    alert('CSV file appears to be empty or only contains headers.');
                    return;
                }

                var headers = lines[0].split(',').map(function(h) { return h.trim().replace(/^"|"$/g, ''); });
                var rows = [];
                for (var i = 1; i < lines.length; i++) {
                    var cols = lines[i].split(',').map(function(c) { return c.trim().replace(/^"|"$/g, ''); });
                    if (cols.length >= 2) rows.push(cols);
                }

                var previewWrap = document.getElementById('dso-csv-preview-container');
                var countSpan = document.getElementById('dso-csv-count');
                var submitBtn = document.getElementById('dso-csv-commit-btn');
                var rawInput = document.getElementById('dso-csv-raw-data');

                if (countSpan) countSpan.textContent = rows.length + ' Listings Detected';
                if (rawInput) rawInput.value = text;
                if (submitBtn) submitBtn.style.display = 'inline-flex';

                var tbody = document.getElementById('dso-csv-preview-tbody');
                if (tbody) {
                    tbody.innerHTML = '';
                    rows.slice(0, 10).forEach(function(r, idx) {
                        var name = r[0] || 'Untitled Item';
                        var cat = r[1] || 'General';
                        var sku = r[2] || ('DJ-SKU-' + (idx + 1));
                        var regPrice = r[3] || '0';
                        var stock = r[5] || '10';

                        var isValid = name && parseFloat(regPrice) > 0;
                        var badge = isValid 
                            ? '<span class="dso-badge dso-badge-green">Valid ✓</span>' 
                            : '<span class="dso-badge dso-badge-red">Missing Title / Price</span>';

                        var tr = document.createElement('tr');
                        tr.innerHTML = 
                            '<td>#' + (idx + 1) + '</td>' +
                            '<td><strong>' + name + '</strong></td>' +
                            '<td>' + cat + '</td>' +
                            '<td><code>' + sku + '</code></td>' +
                            '<td>₹' + regPrice + '</td>' +
                            '<td>' + stock + ' units</td>' +
                            '<td>' + badge + '</td>';
                        tbody.appendChild(tr);
                    });
                }
                if (previewWrap) previewWrap.style.display = 'block';
            };
            reader.readAsText(file);
        },

        // ─── 23. Seller AI Side Drawer Toggle ───────────────
        toggleAiDrawer: function(open) {
            var drawer = document.getElementById('dso-ai-drawer');
            var backdrop = document.getElementById('dso-ai-backdrop');
            if (!drawer) return;
            var shouldOpen = typeof open === 'boolean' ? open : !drawer.classList.contains('dso-open');
            if (shouldOpen) {
                drawer.classList.add('dso-open');
                if (backdrop) backdrop.style.display = 'block';
                var inp = document.getElementById('dso-ai-user-input');
                if (inp) inp.focus();
            } else {
                drawer.classList.remove('dso-open');
                if (backdrop) backdrop.style.display = 'none';
            }
        },

        // ─── 24. Multi-Period Dashboard Charts (Chart.js) ───
        initDashboardCharts: function(chartData) {
            var canvas = document.getElementById('dso-sales-chart');
            if (!canvas || typeof Chart === 'undefined') return;

            var currentPeriod = '7d';
            var periodData = (chartData && chartData[currentPeriod]) ? chartData[currentPeriod] : {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                sales: [1200, 1850, 2400, 3100, 2800, 4200, 5100],
                orders: [3, 5, 6, 8, 7, 11, 14]
            };

            var ctx = canvas.getContext('2d');
            var gradient = ctx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(0, 102, 255, 0.25)');
            gradient.addColorStop(1, 'rgba(0, 102, 255, 0.00)');

            var chart = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: periodData.labels,
                    datasets: [
                        {
                            label: 'Gross Sales (₹)',
                            data: periodData.sales,
                            borderColor: '#0066ff',
                            backgroundColor: gradient,
                            borderWidth: 2.5,
                            fill: true,
                            tension: 0.35,
                            pointRadius: 4,
                            pointBackgroundColor: '#0066ff',
                            yAxisID: 'y'
                        },
                        {
                            label: 'Orders',
                            data: periodData.orders,
                            borderColor: '#06b6d4',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            borderDash: [4, 4],
                            tension: 0.35,
                            pointRadius: 3,
                            pointBackgroundColor: '#06b6d4',
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: {
                                boxWidth: 12,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                font: { size: 12, family: 'Inter, sans-serif', weight: 600 }
                            }
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleColor: '#fff',
                            bodyColor: '#cbd5e1',
                            padding: 12,
                            boxPadding: 6,
                            usePointStyle: true,
                            borderColor: '#334155',
                            borderWidth: 1,
                            callbacks: {
                                label: function(context) {
                                    if (context.datasetIndex === 0) {
                                        return ' Gross Sales: ₹' + (context.raw || 0).toLocaleString();
                                    }
                                    return ' Orders: ' + context.raw;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 11, family: 'Inter, sans-serif' }, color: '#64748b' }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            grid: { color: '#f1f5f9' },
                            ticks: {
                                font: { size: 11, family: 'Inter, sans-serif' },
                                color: '#64748b',
                                callback: function(v) { return '₹' + v; }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: {
                                font: { size: 11, family: 'Inter, sans-serif' },
                                color: '#06b6d4',
                                precision: 0
                            }
                        }
                    }
                }
            });

            this.state.charts['sales'] = chart;

            // Attach period switch buttons
            document.querySelectorAll('#dso-chart-filters button[data-period]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var period = this.getAttribute('data-period');
                    document.querySelectorAll('#dso-chart-filters button').forEach(function(b) { b.classList.remove('active'); });
                    btn.classList.add('active');

                    if (chartData && chartData[period]) {
                        var d = chartData[period];
                        chart.data.labels = d.labels;
                        chart.data.datasets[0].data = d.sales;
                        chart.data.datasets[1].data = d.orders;
                        chart.update();
                    }
                });
            });
        }
    };

    // ─── Global Clipboard Copy Helper ─────────────────────
    window.dsoCopyText = function(text, elem) {
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showCopyFeedback(elem);
            }).catch(function() {
                fallbackCopy(text, elem);
            });
        } else {
            fallbackCopy(text, elem);
        }
    };

    function fallbackCopy(text, elem) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            showCopyFeedback(elem);
        } catch (e) {}
        document.body.removeChild(textarea);
    }

    function showCopyFeedback(elem) {
        if (!elem) return;
        var originalHtml = elem.innerHTML;
        elem.innerHTML = '<code>Copied! ✓</code>';
        elem.style.background = '#d1fae5';
        elem.style.borderColor = '#10b981';
        elem.style.color = '#065f46';
        setTimeout(function() {
            elem.innerHTML = originalHtml;
            elem.style.background = '';
            elem.style.borderColor = '';
            elem.style.color = '';
        }, 1500);
    }

    // ─── Auto-init on DOMContentLoaded ────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { DSO.init(); });
    } else {
        DSO.init();
    }

})(window, document);
