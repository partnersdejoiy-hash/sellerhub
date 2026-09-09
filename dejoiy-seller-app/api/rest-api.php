<?php
/**
 * DSA REST API — all endpoints under /wp-json/dsa/v1.
 * Vendor scoping enforced server-side via DSA_Auth::resolve_vendor().
 */
if (!defined('ABSPATH')) exit;

class DSA_REST_API {

	public static function register_routes() {
		$ns = 'dsa/v1';

		// ── Me / vendor context ──
		register_rest_route($ns, '/me', [
			'methods' => 'GET',
			'callback' => [__CLASS__, 'me'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);

		// ── Dashboard ──
		register_rest_route($ns, '/dashboard', [
			'methods' => 'GET',
			'callback' => [__CLASS__, 'dashboard'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
			'args' => ['range' => ['default' => '30d']],
		]);

		// ── Products ──
		register_rest_route($ns, '/products', [
			'methods' => 'GET',
			'callback' => [__CLASS__, 'products'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/products/(?P<id>\d+)', [
			'methods' => 'GET',
			'callback' => [__CLASS__, 'product_get'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/products', [
			'methods' => 'POST',
			'callback' => [__CLASS__, 'product_create'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/products/(?P<id>\d+)', [
			'methods' => ['POST', 'PUT', 'PATCH'],
			'callback' => [__CLASS__, 'product_update'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/products/(?P<id>\d+)', [
			'methods' => 'DELETE',
			'callback' => [__CLASS__, 'product_delete'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/products/(?P<id>\d+)/stock', [
			'methods' => 'POST',
			'callback' => [__CLASS__, 'product_stock'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/products/bulk', [
			'methods' => 'POST',
			'callback' => [__CLASS__, 'products_bulk'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/products/(?P<id>\d+)/duplicate', [
			'methods' => 'POST',
			'callback' => [__CLASS__, 'product_duplicate'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/products/(?P<id>\d+)/validate', [
			'methods' => 'GET',
			'callback' => [__CLASS__, 'product_validate'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);

		// ── Orders ──
		register_rest_route($ns, '/orders', [
			'methods' => 'GET',
			'callback' => [__CLASS__, 'orders'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/orders/counts', [
			'methods' => 'GET',
			'callback' => [__CLASS__, 'order_counts'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/orders/(?P<id>\d+)', [
			'methods' => 'GET',
			'callback' => [__CLASS__, 'order_detail'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/orders/(?P<id>\d+)/status', [
			'methods' => 'POST',
			'callback' => [__CLASS__, 'order_status'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/orders/(?P<id>\d+)/notes', [
			'methods' => 'POST',
			'callback' => [__CLASS__, 'order_note'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);

		// ── Customers ──
		register_rest_route($ns, '/customers', [
			'methods' => 'GET',
			'callback' => [__CLASS__, 'customers'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);

		// ── Reviews ──
		register_rest_route($ns, '/reviews', [
			'methods' => 'GET',
			'callback' => [__CLASS__, 'reviews'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/reviews/(?P<id>\d+)/reply', [
			'methods' => 'POST',
			'callback' => [__CLASS__, 'review_reply'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);

		// ── Notifications ──
		register_rest_route($ns, '/notifications', [
			'methods' => 'GET',
			'callback' => [__CLASS__, 'notifications'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);

		// ── Search ──
		register_rest_route($ns, '/search', [
			'methods' => 'GET',
			'callback' => [__CLASS__, 'search'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);

		// ── Store settings ──
		register_rest_route($ns, '/store', [
			'methods' => 'GET',
			'callback' => [__CLASS__, 'store_get'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/store', [
			'methods' => 'POST',
			'callback' => [__CLASS__, 'store_save'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);

		// ── Media upload ──
		register_rest_route($ns, '/media', [
			'methods' => 'POST',
			'callback' => [__CLASS__, 'media_upload'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);

		// ── Taxonomy (categories) ──
		register_rest_route($ns, '/categories', [
			'methods' => 'GET',
			'callback' => [__CLASS__, 'categories'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);

		// ── Seller identity: ID, GST, KYC, bank, onboarding ──
		register_rest_route($ns, '/seller/identity', [
			'methods' => 'GET',
			'callback' => [__CLASS__, 'seller_identity'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/seller/profile', [
			'methods' => 'POST',
			'callback' => [__CLASS__, 'seller_profile_save'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/seller/gst', [
			'methods' => 'POST',
			'callback' => [__CLASS__, 'seller_gst_save'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/seller/kyc', [
			'methods' => 'POST',
			'callback' => [__CLASS__, 'seller_kyc_save'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/seller/bank', [
			'methods' => 'POST',
			'callback' => [__CLASS__, 'seller_bank_save'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/seller/onboarding', [
			'methods' => 'GET',
			'callback' => [__CLASS__, 'seller_onboarding'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);

		// ── Seller identity: ID, GST, KYC, bank, onboarding ──
		register_rest_route($ns, '/seller/identity', [
			'methods' => 'GET',
			'callback' => [__CLASS__, 'seller_identity'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/seller/profile', [
			'methods' => 'POST',
			'callback' => [__CLASS__, 'seller_profile_save'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/seller/gst', [
			'methods' => 'POST',
			'callback' => [__CLASS__, 'seller_gst_save'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/seller/kyc', [
			'methods' => 'POST',
			'callback' => [__CLASS__, 'seller_kyc_save'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/seller/bank', [
			'methods' => 'POST',
			'callback' => [__CLASS__, 'seller_bank_save'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
		register_rest_route($ns, '/seller/onboarding', [
			'methods' => 'GET',
			'callback' => [__CLASS__, 'seller_onboarding'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);

		// ── Finance ──
		register_rest_route($ns, '/finance', [
			'methods' => 'GET',
			'callback' => [__CLASS__, 'finance'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);

		// ── JOI AI ──
		register_rest_route($ns, '/ai', [
			'methods' => 'POST',
			'callback' => [__CLASS__, 'ai'],
			'permission_callback' => ['DSA_Auth', 'require_seller_or_admin'],
		]);
	}

	/**
	 * Current user + vendor context.
	 */
	public static function me($request) {
		$user = wp_get_current_user();
		$vendor_id = DSA_Auth::current_vendor_id();
		$admin = DSA_Auth::is_admin();
		return rest_ensure_response([
			'user' => [
				'id' => $user->ID,
				'name' => $user->display_name,
				'email' => $user->user_email,
				'avatar' => get_avatar_url($user->ID, ['size' => 96]),
			],
			'isVendor' => (bool) $vendor_id || DSA_Auth::is_vendor(),
			'isAdmin' => $admin,
			'vendorId' => $vendor_id,
			'version' => DSA_VERSION,
			'storefrontUrl' => function_exists('wcfm_get_vendor_store_url') && $vendor_id ? wcfm_get_vendor_store_url($vendor_id) : home_url('/'),
		]);
	}

	/**
	 * Dashboard payload.
	 */
	public static function dashboard($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		return rest_ensure_response(DSA_Analytics::dashboard($vendor, $admin, $request->get_param('range')));
	}

	// ── Products ──

	public static function products($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		return rest_ensure_response(DSA_Products::query($vendor, $admin, [
			'page' => $request->get_param('page'),
			'per_page' => $request->get_param('per_page'),
			'status' => $request->get_param('status'),
			'search' => $request->get_param('search'),
			'stock' => $request->get_param('stock'),
		]));
	}

	public static function product_get($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		$p = wc_get_product(absint($request['id']));
		if (!$p) return new WP_Error('dsa_not_found', 'Product not found.', ['status' => 404]);
		if (!DSA_Products::owns($p->get_id(), $vendor, $admin)) {
			return new WP_Error('dsa_forbidden', 'You do not own this product.', ['status' => 403]);
		}
		return rest_ensure_response(DSA_Products::serialize($p));
	}

	public static function product_create($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		$body = DSA_Http::body($request);
		$res = DSA_Products::create($body, $vendor, $admin);
		if (is_wp_error($res)) return $res;
		return rest_ensure_response($res);
	}

	public static function product_update($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		$body = DSA_Http::body($request);
		$res = DSA_Products::update(absint($request['id']), $body, $vendor, $admin);
		if (is_wp_error($res)) return $res;
		return rest_ensure_response($res);
	}

	public static function product_delete($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		$pid = absint($request['id']);
		if (!DSA_Products::owns($pid, $vendor, $admin)) {
			return new WP_Error('dsa_forbidden', 'You do not own this product.', ['status' => 403]);
		}
		$p = wc_get_product($pid);
		if (!$p) return new WP_Error('dsa_not_found', 'Product not found.', ['status' => 404]);
		$force = $request->get_param('force');
		$p->delete($force && 'true' === $force);
		return rest_ensure_response(['deleted' => true]);
	}

	public static function product_stock($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		$pid = absint($request['id']);
		if (!DSA_Products::owns($pid, $vendor, $admin)) {
			return new WP_Error('dsa_forbidden', 'You do not own this product.', ['status' => 403]);
		}
		$body = DSA_Http::body($request);
		$qty = isset($body['stock']) ? (int) $body['stock'] : 0;
		$delta = !empty($body['delta']);
		$res = DSA_Products::set_stock($pid, $qty, $delta);
		if (is_wp_error($res)) return $res;
		return rest_ensure_response($res);
	}

	public static function product_duplicate($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		$res = DSA_Products::duplicate(absint($request['id']), $vendor, $admin);
		if (is_wp_error($res)) return $res;
		return rest_ensure_response($res);
	}

	public static function product_validate($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		$pid = absint($request['id']);
		if (!DSA_Products::owns($pid, $vendor, $admin)) {
			return new WP_Error('dsa_forbidden', 'You do not own this product.', ['status' => 403]);
		}
		$p = wc_get_product($pid);
		if (!$p) return new WP_Error('dsa_not_found', 'Product not found.', ['status' => 404]);
		$res = DSA_Products::validate($p);
		if (is_wp_error($res)) {
			return new WP_Error('dsa_validation', $res->get_error_message(), ['status' => 422]);
		}
		return rest_ensure_response(['valid' => true]);
	}

	public static function products_bulk($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		$body = DSA_Http::body($request);
		$action = isset($body['action']) ? sanitize_key($body['action']) : '';
		$ids = isset($body['ids']) && is_array($body['ids']) ? array_map('absint', $body['ids']) : [];
		$value = isset($body['value']) && is_array($body['value']) ? $body['value'] : [];
		if (!$action || !$ids) {
			return new WP_Error('dsa_bad_request', 'action and ids are required.', ['status' => 400]);
		}
		return rest_ensure_response(DSA_Products::bulk($action, $ids, $value, $vendor, $admin));
	}

	// ── Orders ──

	public static function orders($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		return rest_ensure_response(DSA_Orders::query($vendor, $admin, [
			'status' => $request->get_param('status'),
			'search' => $request->get_param('search'),
			'page' => $request->get_param('page'),
			'per_page' => $request->get_param('per_page'),
		]));
	}

	public static function order_counts($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		return rest_ensure_response(DSA_Orders::status_counts($vendor, $admin));
	}

	public static function order_detail($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		$res = DSA_Orders::detail(absint($request['id']), $vendor, $admin);
		if (is_wp_error($res)) return $res;
		return rest_ensure_response($res);
	}

	public static function order_status($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		$body = DSA_Http::body($request);
		$status = isset($body['status']) ? $body['status'] : '';
		$note = isset($body['note']) ? sanitize_text_field($body['note']) : '';
		$res = DSA_Orders::update_status(absint($request['id']), $status, $vendor, $admin, $note);
		if (is_wp_error($res)) return $res;
		return rest_ensure_response($res);
	}

	public static function order_note($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		$body = DSA_Http::body($request);
		$content = isset($body['content']) ? $body['content'] : '';
		$customer = !empty($body['customer']);
		$res = DSA_Orders::add_note(absint($request['id']), $content, $vendor, $admin, $customer);
		if (is_wp_error($res)) return $res;
		return rest_ensure_response($res);
	}

	// ── Customers ──

	public static function customers($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		// Build from vendor's orders (seller-owned data only).
		$result = DSA_Orders::query($vendor, $admin, ['per_page' => 50, 'page' => max(1, absint($request->get_param('page')))]);
		$by_email = [];
		foreach ($result['items'] as $o) {
			$email = $o['customer']['email'];
			if (!$email) continue;
			if (!isset($by_email[$email])) {
				$by_email[$email] = [
					'name' => $o['customer']['name'], 'email' => $email, 'orders' => 0, 'spent' => 0.0, 'lastOrder' => $o['date'],
				];
			}
			$by_email[$email]['orders']++;
			$by_email[$email]['spent'] += (float) $o['total'];
			if ($o['date'] > $by_email[$email]['lastOrder']) $by_email[$email]['lastOrder'] = $o['date'];
		}
		$items = array_values($by_email);
		usort($items, function ($a, $b) { return $b['spent'] <=> $a['spent']; });
		return rest_ensure_response(['items' => $items, 'total' => count($items)]);
	}

	// ── Reviews ──

	public static function reviews($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		return rest_ensure_response(DSA_Vendor::unanswered_reviews($vendor, $admin, 50));
	}

	public static function review_reply($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		$body = DSA_Http::body($request);
		$comment_id = absint($request['id']);
		$content = isset($body['content']) ? wp_kses_post($body['content']) : '';
		if (!$content) return new WP_Error('dsa_bad_request', 'Reply content required.', ['status' => 400]);
		$comment = get_comment($comment_id);
		if (!$comment) return new WP_Error('dsa_not_found', 'Review not found.', ['status' => 404]);
		if (!$admin && $vendor) {
			$owned = in_array((int) $comment->comment_post_ID, DSA_Vendor::vendor_product_ids($vendor), true);
			if (!$owned) return new WP_Error('dsa_forbidden', 'Not your product review.', ['status' => 403]);
		}
		$reply_id = wp_insert_comment([
			'comment_post_ID' => $comment->comment_post_ID,
			'comment_parent' => $comment_id,
			'comment_author' => wp_get_current_user()->display_name,
			'comment_author_email' => wp_get_current_user()->user_email,
			'comment_content' => $content,
			'comment_approved' => 1,
			'comment_type' => 'review',
			'user_id' => get_current_user_id(),
		]);
		return rest_ensure_response(['replied' => (bool) $reply_id, 'replyId' => $reply_id]);
	}

	// ── Notifications / Search / Store ──

	public static function notifications($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		return rest_ensure_response(DSA_Vendor::notifications($vendor, $admin));
	}

	public static function search($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		$q = sanitize_text_field((string) $request->get_param('q'));
		if (strlen($q) < 2) return rest_ensure_response(['products' => [], 'orders' => []]);
		$products = DSA_Products::query($vendor, $admin, ['search' => $q, 'per_page' => 5]);
		$orders = DSA_Orders::query($vendor, $admin, ['search' => $q, 'per_page' => 5]);
		return rest_ensure_response([
			'products' => $products['items'],
			'orders' => $orders['items'],
		]);
	}

	public static function store_get($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		return rest_ensure_response(DSA_Vendor::shop($vendor));
	}

	public static function store_save($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		if (!$vendor) return new WP_Error('dsa_forbidden', 'Vendor account required.', ['status' => 403]);
		$body = DSA_Http::body($request);
		$res = DSA_Vendor::save_shop($vendor, $body);
		if (is_wp_error($res)) return $res;
		return rest_ensure_response($res);
	}

	// ── Media ──

	public static function media_upload($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$files = $request->get_file_params();
		if (empty($files['file'])) {
			return new WP_Error('dsa_bad_request', 'No file uploaded.', ['status' => 400]);
		}
		$file = $files['file'];
		if (!empty($file['error'])) {
			return new WP_Error('dsa_upload_error', 'Upload failed.', ['status' => 400]);
		}
		$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
		$check = wp_check_filetype($file['name']);
		if (!$check['type'] || !in_array($check['type'], $allowed, true)) {
			return new WP_Error('dsa_upload_type', 'Only JPG, PNG, WebP, GIF images allowed.', ['status' => 400]);
		}
		$id = media_handle_upload('file', 0);
		if (is_wp_error($id)) return $id;
		return rest_ensure_response([
			'id' => $id,
			'url' => wp_get_attachment_url($id),
			'thumb' => wp_get_attachment_image_url($id, 'medium'),
		]);
	}

	// ── Categories ──

	public static function categories($request) {
		$terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => 200]);
		$out = [];
		foreach ($terms as $t) {
			if (is_wp_error($t)) continue;
			$out[] = ['id' => $t->term_id, 'name' => $t->name, 'parent' => $t->parent];
		}
		return rest_ensure_response($out);
	}

	// ── Seller identity endpoints ──

	public static function seller_identity($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		if (!$vendor) return new WP_Error('dsa_forbidden', 'Vendor account required.', ['status' => 403]);
		return rest_ensure_response([
			'sellerId'    => DSA_Seller::seller_id($vendor),
			'immutable'   => true,
			'profile'     => DSA_Seller::profile($vendor),
			'gst'         => DSA_Seller::gst($vendor),
			'kyc'         => DSA_Seller::kyc($vendor),
			'bank'        => DSA_Seller::bank($vendor),
			'eligibility' => DSA_Seller::eligibility($vendor),
		]);
	}

	public static function seller_profile_save($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		if (!$vendor) return new WP_Error('dsa_forbidden', 'Vendor account required.', ['status' => 403]);
		$res = DSA_Seller::save_profile($vendor, DSA_Http::body($request));
		if (is_wp_error($res)) return $res;
		return rest_ensure_response($res);
	}

	public static function seller_gst_save($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		if (!$vendor) return new WP_Error('dsa_forbidden', 'Vendor account required.', ['status' => 403]);
		$res = DSA_Seller::save_gst($vendor, DSA_Http::body($request));
		if (is_wp_error($res)) return $res;
		return rest_ensure_response($res);
	}

	public static function seller_kyc_save($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		if (!$vendor) return new WP_Error('dsa_forbidden', 'Vendor account required.', ['status' => 403]);
		$res = DSA_Seller::save_kyc($vendor, DSA_Http::body($request));
		if (is_wp_error($res)) return $res;
		return rest_ensure_response($res);
	}

	public static function seller_bank_save($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		if (!$vendor) return new WP_Error('dsa_forbidden', 'Vendor account required.', ['status' => 403]);
		$res = DSA_Seller::save_bank($vendor, DSA_Http::body($request));
		if (is_wp_error($res)) return $res;
		return rest_ensure_response($res);
	}

	public static function seller_onboarding($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		if (!$vendor) return new WP_Error('dsa_forbidden', 'Vendor account required.', ['status' => 403]);
		return rest_ensure_response(DSA_Seller::onboarding($vendor));
	}

	// ── Finance ──

	public static function finance($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		return rest_ensure_response(DSA_Finance::summary($vendor, DSA_Auth::is_admin()));
	}

	// ── JOI AI ──

	public static function ai($request) {
		$vendor = DSA_Auth::resolve_vendor($request);
		if (is_wp_error($vendor)) return $vendor;
		$admin = DSA_Auth::is_admin();
		$body = DSA_Http::body($request);
		$message = isset($body['message']) ? sanitize_text_field($body['message']) : '';
		if (!$message) return new WP_Error('dsa_bad_request', 'Message required.', ['status' => 400]);

		// Rule-based, data-grounded assistant. Never invents numbers.
		$m = strtolower($message);
		$dash = DSA_Analytics::dashboard($vendor, $admin, '7d');

		if (strpos($m, 'pending') !== false || strpos($m, 'processing') !== false) {
			$r = DSA_Orders::query($vendor, $admin, ['status' => 'processing', 'per_page' => 5]);
			$list = implode(', ', array_map(function ($o) { return '#' . $o['number'] . ' (' . DSA_Http::money($o['total'], $o['currency']) . ')'; }, $r['items']));
			return rest_ensure_response(['reply' => 'You have ' . $r['total'] . ' processing orders.' . ($list ? ' Latest: ' . $list . '.' : ' Sab clear hai.')]);
		}
		if (strpos($m, 'low') !== false && strpos($m, 'stock') !== false) {
			$low = DSA_Vendor::low_stock_products($vendor, $admin, 10);
			if (!$low) return rest_ensure_response(['reply' => 'No low-stock products. Inventory healthy hai.']);
			$names = implode(', ', array_slice(array_map(function ($p) { return $p['name'] . ' (' . $p['stock'] . ' left)'; }, $low), 0, 5));
			return rest_ensure_response(['reply' => count($low) . ' products low on stock: ' . $names . '.']);
		}
		if (strpos($m, 'sell') !== false || strpos($m, 'revenue') !== false || strpos($m, 'week') !== false) {
			return rest_ensure_response(['reply' => 'This week: ' . count($dash['series']) . ' days tracked, revenue ' . DSA_Http::money($dash['summary']['revenue']) . ' from ' . $dash['summary']['orders'] . ' orders. Best seller: ' . ($dash['bestSellers'] ? $dash['bestSellers'][0]['name'] : '—') . '.']);
		}
		if (strpos($m, 'attention') !== false || strpos($m, 'kya') !== false) {
			$lines = $dash['attention'] ? implode('; ', array_map(function ($a) { return $a['label']; }, $dash['attention'])) : 'sab kuch theek hai';
			return rest_ensure_response(['reply' => 'Needs attention: ' . $lines . '.']);
		}
		if (strpos($m, 'best') !== false || strpos($m, 'top') !== false) {
			$b = $dash['bestSellers'];
			if (!$b) return rest_ensure_response(['reply' => 'No sales yet in this period.']);
			return rest_ensure_response(['reply' => 'Top products: ' . implode(', ', array_map(function ($p) { return $p['name'] . ' (' . $p['qty'] . ' sold)'; }, array_slice($b, 0, 3))) . '.']);
		}
		return rest_ensure_response(['reply' => 'Main aapki sales, orders, stock aur reviews ke baare me real data se bata sakta hoon. Try: "show pending orders", "low stock", "this week sales", "needs attention".']);
	}
}
