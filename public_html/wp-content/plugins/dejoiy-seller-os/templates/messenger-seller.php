<?php
/**
 * DEJOIY Seller Messenger — Discord-Inspired Real-Time Chat
 * Split-pane: Thread sidebar (left) + Chat (right)
 * Mobile: Full-screen thread list → tap → full-screen chat
 */
if (!defined('ABSPATH')) exit;

$self = new DSO_Messenger();
$vendor_id = $self->get_vendor_id();
$selected_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Auto-create thread from order_id param
if (!$selected_id && $order_id) {
    global $wpdb;
    $table_conv = $wpdb->prefix . 'dso_conversations';
    $found_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_conv} WHERE order_id = %d ORDER BY id DESC LIMIT 1", $order_id));
    if ($found_id) {
        $selected_id = intval($found_id);
    } else {
        $order = wc_get_order($order_id);
        if ($order) {
            $buyer_id = $order->get_customer_id() ?: 0;
            $buyer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) ?: 'Customer';
            $buyer_email = $order->get_billing_email();
            $buyer_phone = $order->get_billing_phone();
            $order_num = $order->get_order_number();
            $order_tot = '₹' . number_format(floatval($order->get_total()), 2);
            $vendor_user = get_user_by('id', $vendor_id);
            $vendor_name = $vendor_user ? $vendor_user->display_name : 'DEJOIY Partner';
            if (function_exists('wcfm_get_vendor_store_info')) {
                $store = wcfm_get_vendor_store_info($vendor_id);
                if (!empty($store['name'])) $vendor_name = $store['name'];
            }
            $token = wp_generate_password(32, false);
            $now = current_time('mysql');
            $first_product_id = 0;
            $first_product_name = '';
            $items = $order->get_items();
            if (!empty($items)) {
                $fi = reset($items);
                $first_product_id = $fi->get_product_id();
                $first_product_name = $fi->get_name();
            }
            $wpdb->insert($table_conv, [
                'thread_token' => $token, 'buyer_id' => $buyer_id, 'buyer_name' => $buyer_name,
                'buyer_email' => $buyer_email, 'buyer_phone' => $buyer_phone,
                'vendor_id' => $vendor_id ?: 1, 'vendor_name' => $vendor_name,
                'product_id' => $first_product_id, 'product_name' => $first_product_name,
                'order_id' => $order_id, 'order_number' => $order_num, 'order_total' => $order_tot,
                'subject' => 'Order #' . $order_num, 'status' => 'open',
                'last_message' => 'Thread started for Order #' . $order_num,
                'last_message_at' => $now, 'last_sender' => 'seller',
                'unread_seller' => 0, 'unread_buyer' => 0,
                'created_at' => $now, 'updated_at' => $now
            ]);
            $selected_id = $wpdb->insert_id;
            $wpdb->insert($wpdb->prefix . 'dso_messages', [
                'conversation_id' => $selected_id, 'sender_type' => 'system', 'sender_id' => 0,
                'sender_name' => 'DEJOIY System',
                'message' => 'Thread opened for Order #' . $order_num . '. You can now message ' . esc_html($buyer_name) . '.',
                'created_at' => $now
            ]);
        }
    }
}
?>

<link rel="stylesheet" href="<?php echo DSO_URL; ?>assets/css/messenger-discord.css" />

<div class="dso-page" style="padding:0;">
    <div class="msg-app" id="msg-app">
        <!-- ═══ LEFT: Thread Sidebar ═══ -->
        <div class="msg-sidebar" id="msg-sidebar">
            <div class="msg-sidebar-header">
                <div class="msg-sidebar-title"><span class="msg-icon">💬</span> Messages</div>
                <div class="msg-search">
                    <svg class="msg-search-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="msg-search-input" placeholder="Search conversations..." />
                </div>
                <div class="msg-filters">
                    <button type="button" class="msg-filter-pill active" data-filter="all">All</button>
                    <button type="button" class="msg-filter-pill" data-filter="unread">Unread <span class="msg-filter-badge" id="msg-unread-badge" style="display:none;">0</span></button>
                    <button type="button" class="msg-filter-pill" data-filter="orders">📋 Orders</button>
                    <button type="button" class="msg-filter-pill" data-filter="resolved">✓ Resolved</button>
                </div>
            </div>
            <div class="msg-threads" id="msg-threads">
                <div class="msg-loading"><div class="msg-spinner"></div> Loading messages...</div>
            </div>
        </div>

        <!-- ═══ RIGHT: Chat Pane ═══ -->
        <div class="msg-chat" id="msg-chat">
            <!-- Empty State -->
            <div class="msg-empty-chat" id="msg-empty-chat">
                <div class="msg-empty-chat-icon">💬</div>
                <h3>Select a conversation</h3>
                <p>Choose a customer message from the sidebar to view context and reply in real time.</p>
            </div>

            <!-- Active Chat -->
            <div class="msg-active-chat" id="msg-active-chat" style="display:none;">
                <!-- Header -->
                <div class="msg-chat-header">
                    <div class="msg-chat-header-left">
                        <button type="button" class="msg-back-btn" id="msg-back-btn" aria-label="Back">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                        <div class="msg-chat-avatar" id="msg-chat-avatar">C</div>
                        <div class="msg-chat-info">
                            <div class="msg-chat-name">
                                <span id="msg-chat-name">Customer</span>
                                <span class="msg-status-badge msg-status-open" id="msg-status-badge">● Open</span>
                            </div>
                            <div class="msg-chat-sub" id="msg-chat-sub">Verified Buyer</div>
                        </div>
                    </div>
                    <div class="msg-chat-header-right" id="msg-header-right"></div>
                </div>

                <!-- Context Bar -->
                <div class="msg-context-bar" id="msg-context-bar"></div>

                <!-- Messages -->
                <div class="msg-messages" id="msg-messages"></div>

                <!-- Typing Indicator -->
                <div class="msg-typing" id="msg-typing">
                    <div class="msg-typing-dots"><span></span><span></span><span></span></div>
                    <span class="msg-typing-text">Customer is typing...</span>
                </div>

                <!-- Quick Replies -->
                <div class="msg-quick-replies" id="msg-quick-replies">
                    <button type="button" class="msg-quick-chip" data-reply="Your order is being processed and will be dispatched shortly. 📦">📦 Processing</button>
                    <button type="button" class="msg-quick-chip" data-reply="Your order has been dispatched! Track it from your account. 🚚">🚚 Shipped</button>
                    <button type="button" class="msg-quick-chip" data-reply="This product is in stock and genuine with DEJOIY Buyer Protection. ✅">✅ In Stock</button>
                    <button type="button" class="msg-quick-chip" data-reply="We offer 7-day hassle-free replacement under our seller policy. 🔄">🔄 Returns</button>
                </div>

                <!-- Composer -->
                <div class="msg-composer">
                    <textarea id="msg-input" class="msg-composer-input" placeholder="Type a message... (Enter to send, Shift+Enter for newline)" rows="1"></textarea>
                    <button type="button" id="msg-send-btn" class="msg-send-btn" aria-label="Send">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    var REST = '/?section=messages&dso_msg_api=';
    window.addEventListener('unhandledrejection', function(){
        var box = document.getElementById('msg-threads');
        if (box && box.querySelector('.msg-loading')) {
            box.innerHTML = '<div class="msg-empty"><h3>Could not load messages</h3><p>Please check your connection and refresh the page.</p></div>';
        }
    });
    var VID = <?php echo (int) $vendor_id; ?>;
    var activeConvId = <?php echo $selected_id ?: 0; ?>;
    var activeFilter = 'all';
    var searchQuery = '';
    var lastMsgId = 0;
    var pollTimer = null;
    var currentThread = null;
    var lastSenderType = '';

    // ── Audio ──
    function chime(incoming) {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            var o = ctx.createOscillator(), g = ctx.createGain();
            o.connect(g); g.connect(ctx.destination);
            var t = ctx.currentTime;
            if (incoming) {
                o.frequency.setValueAtTime(587, t);
                o.frequency.exponentialRampToValueAtTime(880, t + 0.12);
                g.gain.setValueAtTime(0.07, t);
                g.gain.exponentialRampToValueAtTime(0.001, t + 0.25);
                o.start(t); o.stop(t + 0.25);
            } else {
                o.frequency.setValueAtTime(440, t);
                o.frequency.exponentialRampToValueAtTime(659, t + 0.08);
                g.gain.setValueAtTime(0.05, t);
                g.gain.exponentialRampToValueAtTime(0.001, t + 0.18);
                o.start(t); o.stop(t + 0.18);
            }
        } catch(e){}
    }

    function esc(s){ return String(s == null ? '' : s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','':'&#39;'}[c]; }); }
// ── Threads ──
    function loadThreads() {
        fetch(REST + 'threads&role=seller&vendor_id=' + VID + '&status=' + activeFilter + '&q=' + encodeURIComponent(searchQuery), {credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(d){
            if (!d.success) return;
            renderThreads(d.threads);
            updateBadges(d.total_unread);
            if (activeConvId && !currentThread) selectThread(activeConvId);
            })
            .catch(function(){
                var box = document.getElementById("msg-threads");
                if (box) box.innerHTML = '<div class="msg-empty"><h3>Could not load messages</h3><p>Please check your connection and <button onclick="loadThreads()">Retry</button>.</p></div>';
            });
    }

    function updateBadges(count) {
        var b = document.getElementById('msg-unread-badge');
        if (b) { b.textContent = count; b.style.display = count > 0 ? 'inline-block' : 'none'; }
        var nb = document.querySelector('.dso-nav-badge-messages');
        if (nb) { nb.textContent = count > 0 ? count : ''; nb.style.display = count > 0 ? 'inline-block' : 'none'; }
    }

    function renderThreads(threads) {
        var el = document.getElementById('msg-threads');
        if (!el) return;
        if (!threads || !threads.length) {
            el.innerHTML = '<div class="msg-empty-threads"><div class="msg-empty-icon">📬</div><div class="msg-empty-title">No conversations</div><div class="msg-empty-desc">Customer messages will appear here in real time.</div></div>';
            return;
        }
        var h = '';
        threads.forEach(function(t) {
            var active = t.id == activeConvId ? ' active' : '';
            var resolved = t.status === 'resolved' ? ' msg-thread-resolved' : '';
            var unread = parseInt(t.unread_seller) || 0;
            var tag = t.order_number ? '<span class="msg-thread-tag order-tag">#' + esc(t.order_number) + '</span>' : (t.product_name ? '<span class="msg-thread-tag">📦 ' + esc(t.product_name.substring(0, 25)) + '</span>' : '');
            var initials = (t.buyer_initials || 'C');
            h += '<div class="msg-thread' + active + resolved + '" data-id="' + t.id + '">' +
                    '<div class="msg-thread-avatar">' + initials +
                        '<div class="msg-online-dot"></div>' +
                    '</div>' +
                    '<div class="msg-thread-body">' +
                        '<div class="msg-thread-top">' +
                            '<span class="msg-thread-name">' + esc(t.buyer_name) + '</span>' +
                            '<span class="msg-thread-time">' + esc(t.time_ago) + '</span>' +
                        '</div>' +
                        '<div>' + tag + '</div>' +
                        '<div class="msg-thread-preview">' + esc(t.last_message_display) + '</div>' +
                    '</div>' +
                    (unread > 0 ? '<div class="msg-thread-unread">' + unread + '</div>' : '') +
                '</div>';
        });
        el.innerHTML = h;
        el.querySelectorAll('.msg-thread').forEach(function(item) {
            item.addEventListener('click', function() { selectThread(parseInt(this.dataset.id)); });
        });
    }

    // ── Thread Selection ──
    function selectThread(id) {
        activeConvId = id;
        lastMsgId = 0;
        lastSenderType = '';
        currentThread = null;

        document.querySelectorAll('.msg-thread').forEach(function(el) { el.classList.toggle('active', el.dataset.id == id); });

        // Mobile: show chat
        document.getElementById('msg-sidebar').classList.add('hidden');
        document.getElementById('msg-chat').classList.add('visible');
        document.getElementById('msg-empty-chat').style.display = 'none';
        document.getElementById('msg-active-chat').style.display = 'flex';

        loadMessages(true);
        markRead(id);

        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(function(){ pollNew(); }, 2500);
    }

    function markRead(id) {
        fetch(REST + 'mark-read', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({conversation_id:id,role:'seller'}), credentials:'same-origin'})
        .then(function(){ loadThreads(); });
    }

    // ── Messages ──
    function loadMessages(full) {
        var url = REST + 'messages&conversation_id=' + activeConvId + (full ? '' : '&since_id=' + lastMsgId);
        fetch(url, {credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(d){
            if (!d.success) return;
            currentThread = d.thread;
            updateHeader(d.thread);
            updateContext(d.thread);

            var box = document.getElementById('msg-messages');
            if (full) box.innerHTML = '';

            if (d.messages && d.messages.length) {
                var prevDate = full ? '' : null;
                d.messages.forEach(function(m) {
                    appendBubble(m, prevDate);
                    if (m.id > lastMsgId) lastMsgId = m.id;
                });
                scrollBottom();
            }
        });
    }

    function pollNew() {
        if (!activeConvId) return;
        fetch(REST + 'messages&conversation_id=' + activeConvId + '&since_id=' + lastMsgId, {credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(d){
            if (!d.success || !d.messages || !d.messages.length) return;
            var incoming = false;
            d.messages.forEach(function(m) {
                appendBubble(m, null);
                if (m.id > lastMsgId) lastMsgId = m.id;
                if (m.sender_type === 'buyer') incoming = true;
            });
            scrollBottom();
            if (incoming) { chime(true); markRead(activeConvId); }
        });
    }

    function appendBubble(m, prevDate) {
        var box = document.getElementById('msg-messages');
        var dateStr = m.formatted_date || '';
        if (dateStr && dateStr !== prevDate && prevDate !== null) {
            var div = document.createElement('div');
            div.className = 'msg-date-divider';
            div.innerHTML = '<span>' + esc(dateStr) + '</span>';
            box.appendChild(div);
        }
        if (dateStr) prevDate = dateStr;

        var isSeller = m.sender_type === 'seller';
        var isSystem = m.sender_type === 'system';
        var isSameSender = (m.sender_type === lastSenderType);
        var row = document.createElement('div');

        if (isSystem) {
            row.className = 'msg-system';
            row.innerHTML = '<span class="msg-system-text">🔧 ' + m.message_html + '</span>';
            box.appendChild(row);
            lastSenderType = 'system';
            return;
        }

        var dir = isSeller ? 'outgoing' : 'incoming';
        var noAvatar = isSameSender;
        row.className = 'msg-row ' + dir + (noAvatar ? ' no-avatar' : '');

        var avatarHtml = '<div class="msg-row-avatar">' + (isSeller ? 'S' : (m.sender_name || 'C').substring(0,1).toUpperCase()) + '</div>';
        var senderHtml = isSameSender ? '' : '<div class="msg-sender ' + (isSeller ? 'msg-seller-name' : 'msg-buyer-name') + '">' + (isSeller ? 'You' : esc(m.sender_name)) + ' <span class="msg-sender-time">' + m.formatted_time + '</span></div>';

        row.innerHTML = avatarHtml + '<div class="msg-bubble-wrap">' + senderHtml + '<div class="msg-bubble">' + m.message_html + '</div></div>';
        box.appendChild(row);
        lastSenderType = m.sender_type;
    }

    function updateHeader(t) {
        document.getElementById('msg-chat-avatar').textContent = (t.buyer_name || 'C').substring(0, 2).toUpperCase();
        document.getElementById('msg-chat-name').textContent = t.buyer_name;
        document.getElementById('msg-chat-sub').textContent = (t.buyer_email || 'Verified Buyer') + (t.buyer_phone ? ' • ' + t.buyer_phone : '');

        var badge = document.getElementById('msg-status-badge');
        if (t.status === 'resolved') {
            badge.className = 'msg-status-badge msg-status-resolved';
            badge.textContent = '✓ Resolved';
        } else {
            badge.className = 'msg-status-badge msg-status-open';
            badge.textContent = '● Open';
        }

        var right = document.getElementById('msg-header-right');
        var h = '';
        if (t.order_id && t.order_number) h += '<a href="?section=order-detail&id=' + t.order_id + '" class="msg-header-action" target="_blank">📋 #' + esc(t.order_number) + '</a>';
        h += '<button type="button" class="msg-header-action" id="msg-resolve-btn">' + (t.status === 'resolved' ? '🔄 Reopen' : '✓ Resolve') + '</button>';
        right.innerHTML = h;

        document.getElementById('msg-resolve-btn').addEventListener('click', function() {
            var newSt = (t.status === 'resolved') ? 'open' : 'resolved';
            fetch(REST + 'status', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({conversation_id:activeConvId, status:newSt}), credentials:'same-origin'})
            .then(function(){ loadThreads(); loadMessages(true); });
        });
    }

    function updateContext(t) {
        var bar = document.getElementById('msg-context-bar');
        if (t.product_name || t.order_number) {
            var h = '<div style="display:flex;align-items:center;gap:12px;">';
            if (t.product_image) h += '<img src="' + esc(t.product_image) + '" style="width:40px;height:40px;border-radius:8px;object-fit:cover;border:1px solid #e2e8f0;" alt="" />';
            h += '<div class="msg-context-info">';
            if (t.product_name) h += '<div class="msg-context-title">' + esc(t.product_name) + (t.product_price ? ' • <span style="color:#0066ff;">' + esc(t.product_price) + '</span>' : '') + '</div>';
            if (t.order_number) h += '<div class="msg-context-meta">Order #' + esc(t.order_number) + (t.order_total ? ' • ' + esc(t.order_total) : '') + '</div>';
            h += '</div></div>';
            bar.innerHTML = h;
            bar.classList.add('visible');
        } else {
            bar.classList.remove('visible');
        }
    }

    function scrollBottom() {
        var box = document.getElementById('msg-messages');
        requestAnimationFrame(function(){ box.scrollTop = box.scrollHeight; });
    }

    // ── Send ──
    function send() {
        var input = document.getElementById('msg-input');
        var text = input.value.trim();
        if (!text || !activeConvId) return;
        input.value = '';
        input.style.height = 'auto';

        fetch(REST + 'send', {method:'POST', headers:{'Content-Type':'application/json'},
            body:JSON.stringify({conversation_id:activeConvId, message:text, sender_type:'seller'}),
            credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(d){
            if (d.success && d.message) {
                appendBubble(d.message, null);
                if (d.message.id > lastMsgId) lastMsgId = d.message.id;
                scrollBottom();
                chime(false);
                loadThreads();
            }
        });
    }

    // ── Events ──
    var msgInput = document.getElementById('msg-input');
    var sendBtn = document.getElementById('msg-send-btn');

    sendBtn.addEventListener('click', send);
    msgInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
    });
    msgInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    // Quick replies
    document.querySelectorAll('.msg-quick-chip').forEach(function(chip) {
        chip.addEventListener('click', function() {
            msgInput.value = this.dataset.reply;
            msgInput.focus();
        });
    });

    // Filters
    document.querySelectorAll('.msg-filter-pill').forEach(function(pill) {
        pill.addEventListener('click', function() {
            document.querySelectorAll('.msg-filter-pill').forEach(function(p){ p.classList.remove('active'); });
            this.classList.add('active');
            activeFilter = this.dataset.filter;
            loadThreads();
        });
    });

    // Search
    var sTimer;
    document.getElementById('msg-search-input').addEventListener('input', function() {
        clearTimeout(sTimer);
        var v = this.value;
        sTimer = setTimeout(function(){ searchQuery = v; loadThreads(); }, 250);
    });

    // Mobile back
    document.getElementById('msg-back-btn').addEventListener('click', function() {
        document.getElementById('msg-sidebar').classList.remove('hidden');
        document.getElementById('msg-chat').classList.remove('visible');
        if (pollTimer) clearInterval(pollTimer);
    });

    // Initial load
    loadThreads();
})();
</script>
