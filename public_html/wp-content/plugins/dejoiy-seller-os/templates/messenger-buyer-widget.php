<?php
/**
 * DEJOIY Buyer Messenger Widget — Discord-Inspired
 * Floating chat widget on dejoiy.com buyer-facing pages
 */
if (!defined('ABSPATH')) exit;

$user_id = get_current_user_id();
$user_name = $user_email = '';
if ($user_id) {
    $u = get_userdata($user_id);
    $user_name = $u ? $u->display_name : '';
    $user_email = $u ? $u->user_email : '';
}

$current_prod_id = $current_prod_title = $current_prod_price = $current_prod_thumb = '';
$current_vendor_id = 2;

if (is_product()) {
    global $post;
    if ($post) {
        $current_prod_id = $post->ID;
        $current_prod_title = get_the_title($post->ID);
        $p = wc_get_product($post->ID);
        if ($p) {
            $current_prod_price = '₹' . number_format(floatval($p->get_price()), 2);
            $img_id = $p->get_image_id();
            $current_prod_thumb = $img_id ? wp_get_attachment_image_url($img_id, 'thumbnail') : '';
        }
        $v = get_post_field('post_author', $post->ID);
        if ($v) $current_vendor_id = $v;
    }
}
?>

<link rel="stylesheet" href="<?php echo DSO_URL; ?>assets/css/messenger-discord.css" />

<div class="msg-widget-root" id="msg-widget-root" style="pointer-events:none;">
    <!-- Launcher Button -->
    <button type="button" class="msg-launcher" id="msg-launcher" style="pointer-events:auto;">
        <div class="msg-launcher-pulse"></div>
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <span>Chat with Seller</span>
        <span class="msg-launcher-badge" id="msg-launcher-badge" style="display:none;">0</span>
    </button>

    <!-- Widget Modal -->
    <div class="msg-widget" id="msg-widget" style="pointer-events:auto;">
        <!-- Header -->
        <div class="msg-widget-header">
            <div class="msg-widget-brand">
                <div class="msg-widget-avatar">🏪</div>
                <div>
                    <div class="msg-widget-title">DEJOIY Seller Messenger</div>
                    <div class="msg-widget-sub"><span class="msg-widget-dot"></span> Online • Fast response</div>
                </div>
            </div>
            <button type="button" class="msg-widget-close" id="msg-widget-close" aria-label="Close">✕</button>
        </div>

        <!-- Product Context (if on product page) -->
        <?php if ($current_prod_id): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:8px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0;">
            <?php if ($current_prod_thumb): ?>
                <img src="<?php echo esc_url($current_prod_thumb); ?>" style="width:36px;height:36px;border-radius:6px;object-fit:cover;border:1px solid #e2e8f0;" alt="" />
            <?php endif; ?>
            <div style="flex:1;min-width:0;">
                <div style="font-size:12.5px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html($current_prod_title); ?></div>
                <div style="font-size:11px;color:#0066ff;font-weight:600;"><?php echo esc_html($current_prod_price); ?> • Verified Merchant</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Messages -->
        <div class="msg-widget-messages" id="msg-widget-messages">
            <div style="margin:auto;text-align:center;padding:24px 14px;">
                <div style="font-size:28px;margin-bottom:6px;">👋</div>
                <strong style="font-size:14px;color:#0f172a;">Have a question?</strong>
                <p style="font-size:12.5px;color:#64748b;margin:4px 0 0;">Ask about sizing, delivery, stock, or anything else.</p>
            </div>
        </div>

        <!-- Quick Prompts -->
        <div class="msg-widget-prompts" id="msg-widget-prompts">
            <button type="button" class="msg-widget-prompt" data-text="Is this item in stock and ready to ship?">⚡ In stock?</button>
            <button type="button" class="msg-widget-prompt" data-text="What is the expected delivery time to my pincode?">🚚 Delivery?</button>
            <button type="button" class="msg-widget-prompt" data-text="Is cash on delivery (COD) available?">💵 COD?</button>
        </div>

        <!-- Guest bar -->
        <?php if (!$user_id): ?>
        <div style="display:flex;gap:6px;padding:6px 12px;background:#f1f5f9;border-top:1px solid #e2e8f0;">
            <input type="text" id="msg-guest-name" placeholder="Your Name" style="flex:1;padding:6px 10px;font-size:11.5px;border-radius:6px;border:1px solid #cbd5e1;" />
            <input type="email" id="msg-guest-email" placeholder="Email (for replies)" style="flex:1;padding:6px 10px;font-size:11.5px;border-radius:6px;border:1px solid #cbd5e1;" />
        </div>
        <?php endif; ?>

        <!-- Composer -->
        <div class="msg-widget-composer">
            <input type="text" id="msg-widget-input" placeholder="Type message to seller..." autocomplete="off" />
            <button type="button" id="msg-widget-send" class="msg-widget-send" aria-label="Send">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </div>
    </div>
</div>

<script>
(function(){
    var REST = '/wp-json/dejoiy/v1/messenger/';
    var curProdId = <?php echo (int) $current_prod_id ?: 0; ?>;
    var curVendorId = <?php echo (int) $current_vendor_id; ?>;
    var curOrderId = 0;
    var logged = <?php echo $user_id ? 'true' : 'false'; ?>;
    var uName = '<?php echo esc_js($user_name); ?>';
    var uEmail = '<?php echo esc_js($user_email); ?>';
    var activeToken = localStorage.getItem('djy_tok_' + curVendorId + '_' + curProdId) || localStorage.getItem('djy_tok_active') || '';
    var activeConvId = parseInt(localStorage.getItem('djy_conv_active') || '0');
    var lastId = 0;
    var poll = null;

    var launcher = document.getElementById('msg-launcher');
    var widget = document.getElementById('msg-widget');
    var closeBtn = document.getElementById('msg-widget-close');
    var input = document.getElementById('msg-widget-input');
    var sendBtn = document.getElementById('msg-widget-send');
    var messagesBox = document.getElementById('msg-widget-messages');

    function toggle(open) {
        if (open) {
            widget.classList.add('open');
            launcher.style.display = 'none';
            if (input) input.focus();
            if (activeToken && activeConvId) loadMsgs(true);
            if (poll) clearInterval(poll);
            poll = setInterval(pollMsgs, 2500);
        } else {
            widget.classList.remove('open');
            launcher.style.display = 'flex';
            if (poll) clearInterval(poll);
        }
    }

    launcher.addEventListener('click', function(){ toggle(true); });
    closeBtn.addEventListener('click', function(){ toggle(false); });

    // Global API for "Contact Seller" buttons
    window.dejoiyOpenSellerChat = function(p,v,o) {
        if (p) curProdId = p;
        if (v) curVendorId = v;
        if (o) curOrderId = o;
        toggle(true);
    };

    window.dejoiyOpenSellerChatForOrder = function(orderId, orderNum, prodId, prodTitle, prodThumb, prodPrice, vendorId, storeName) {
        if (orderId) curOrderId = orderId;
        if (prodId) curProdId = prodId;
        if (vendorId) curVendorId = vendorId;

        var sub = document.querySelector('.msg-widget-sub');
        if (sub && storeName) sub.innerHTML = '<span class="msg-widget-dot"></span> ' + esc(storeName) + ' • Merchant';

        activeConvId = 0;
        activeToken = '';
        messagesBox.innerHTML = '<div style="padding:20px;text-align:center;color:#64748b;font-size:12.5px;">Connecting with ' + esc(storeName || 'Seller') + '...</div>';

        fetch(REST + 'threads?role=buyer&order_id=' + orderId, {credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(d){
            if (d.success && d.threads && d.threads.length) {
                var found = d.threads.find(function(t){return t.vendor_id == vendorId;}) || d.threads[0];
                if (found) { activeConvId = found.id; activeToken = found.thread_token; loadMsgs(true); return; }
            }
            messagesBox.innerHTML = '<div style="padding:20px 16px;text-align:center;background:#f8fafc;border-radius:12px;margin:12px;border:1px dashed #cbd5e1;"><div style="font-size:28px;margin-bottom:6px;">💬</div><strong style="font-size:13.5px;color:#0f172a;">Contact ' + esc(storeName || 'Seller') + '</strong><p style="font-size:12px;color:#64748b;margin:4px 0 0;">Regarding Order #' + esc(orderNum) + '</p></div>';
        });

        toggle(true);
    };

    function loadMsgs(initial) {
        var url = REST + 'messages?thread_token=' + encodeURIComponent(activeToken) + '&conversation_id=' + activeConvId;
        fetch(url, {credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(d){
            if (!d.success || !d.messages) return;
            if (initial) messagesBox.innerHTML = '';
            d.messages.forEach(function(m) {
                appendBub(m);
                if (m.id > lastId) lastId = m.id;
            });
            messagesBox.scrollTop = messagesBox.scrollHeight;
            markRd();
        });
    }

    function pollMsgs() {
        if (!activeToken && !activeConvId) return;
        fetch(REST + 'messages?thread_token=' + encodeURIComponent(activeToken) + '&conversation_id=' + activeConvId + '&since_id=' + lastId, {credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(d){
            if (!d.success || !d.messages || !d.messages.length) return;
            d.messages.forEach(function(m) { appendBub(m); if (m.id > lastId) lastId = m.id; });
            messagesBox.scrollTop = messagesBox.scrollHeight;
            markRd();
        });
    }

    function markRd() {
        if (!activeConvId) return;
        fetch(REST + 'mark-read', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({conversation_id:activeConvId,role:'buyer'}), credentials:'same-origin'});
    }

    function appendBub(m) {
        var isMe = m.sender_type === 'buyer';
        var div = document.createElement('div');
        div.className = 'msg-widget-msg ' + (isMe ? 'out' : 'in');
        div.innerHTML = '<div class="msg-widget-bubble">' + (isMe ? '' : '<div class="msg-widget-sender">' + esc(m.sender_name) + '</div>') +
            '<div>' + m.message_html + '</div>' +
            '<div class="msg-widget-time">' + m.formatted_time + '</div></div>';
        messagesBox.appendChild(div);
    }

    function sendMsg(text) {
        text = text || (input ? input.value.trim() : '');
        if (!text) return;
        if (input) input.value = '';

        var gName = uName, gEmail = uEmail;
        if (!logged) {
            var n = document.getElementById('msg-guest-name');
            var e = document.getElementById('msg-guest-email');
            if (n && n.value.trim()) gName = n.value.trim();
            if (e && e.value.trim()) gEmail = e.value.trim();
        }

        if (!activeConvId && !activeToken) {
            fetch(REST + 'start', {method:'POST', headers:{'Content-Type':'application/json'},
                body:JSON.stringify({vendor_id:curVendorId, product_id:curProdId, order_id:curOrderId, buyer_name:gName, buyer_email:gEmail, message:text}),
                credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(d){
                if (d.success && d.conversation_id) {
                    activeConvId = d.conversation_id;
                    activeToken = d.thread_token;
                    localStorage.setItem('djy_conv_active', activeConvId);
                    localStorage.setItem('djy_tok_active', activeToken);
                    localStorage.setItem('djy_tok_' + curVendorId + '_' + curProdId, activeToken);
                    loadMsgs(true);
                }
            });
        } else {
            fetch(REST + 'send', {method:'POST', headers:{'Content-Type':'application/json'},
                body:JSON.stringify({conversation_id:activeConvId, thread_token:activeToken, message:text, sender_type:'buyer'}),
                credentials:'same-origin'})
            .then(function(r){return r.json();})
            .then(function(d){
                if (d.success && d.message) {
                    appendBub(d.message);
                    if (d.message.id > lastId) lastId = d.message.id;
                    messagesBox.scrollTop = messagesBox.scrollHeight;
                }
            });
        }
    }

    sendBtn.addEventListener('click', function(){ sendMsg(); });
    input.addEventListener('keydown', function(e){ if (e.key === 'Enter') { e.preventDefault(); sendMsg(); } });

    document.querySelectorAll('.msg-widget-prompt').forEach(function(chip) {
        chip.addEventListener('click', function() {
            sendMsg(this.dataset.text);
            var prompts = document.getElementById('msg-widget-prompts');
            if (prompts) prompts.style.display = 'none';
        });
    });

    function esc(s) {
        if (!s) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }
})();
</script>
