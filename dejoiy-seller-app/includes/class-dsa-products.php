<?php
/**
 * DSA Products — vendor-scoped catalog service: list, detail, create, update, stock, bulk, variations.
 */
if (!defined('ABSPATH')) exit;

class DSA_Products {

	/**
	 * Vendor-scoped product query.
	 */
	public static function query($vendor_id, $admin, $args = []) {
		$page     = max(1, absint(isset($args['page']) ? $args['page'] : 1));
		$per_page = min(60, max(1, absint(isset($args['per_page']) ? $args['per_page'] : 20)));
		$status   = isset($args['status']) && $args['status'] ? sanitize_key($args['status']) : 'all';
		$search   = isset($args['search']) ? sanitize_text_field($args['search']) : '';
		$type     = isset($args['type']) ? sanitize_key($args['type']) : '';
		$stock    = isset($args['stock']) ? sanitize_key($args['stock']) : '';

		$post_status = [];
		switch ($status) {
			case 'all': $post_status = ['publish', 'draft', 'pending', 'private']; break;
			case 'published': $post_status = ['publish']; break;
			case 'draft': $post_status = ['draft', 'pending', 'private']; break;
			case 'trash': $post_status = ['trash']; break;
			default: $post_status = [$status];
		}

		$query_args = [
			'post_type'      => 'product',
			'post_status'    => $post_status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		];
		if (!$admin && $vendor_id) {
			$query_args['author'] = (int) $vendor_id;
		}
		if ($search) {
			$query_args['s'] = $search;
		}
		if ($type) {
			$tax_query_for_type = 'product-type'; // handled post-query below.
		}

		$q = new WP_Query($query_args);
		$items = [];
		foreach ($q->posts as $post) {
			$p = wc_get_product($post->ID);
			if ($p) $items[] = self::serialize($p);
		}

		// Post-filter by type / stock state (accurate over fast).
		if ($type || $stock) {
			$threshold = (int) get_option('woocommerce_notify_low_stock_amount', 5);
			$items = array_values(array_filter($items, function ($it) use ($type, $stock, $threshold) {
				if ($type && $it['type'] !== $type) return false;
				if ($stock) {
					if ('out' === $stock && 'outofstock' !== $it['stockStatus']) return false;
					if ('low' === $stock && !($it['managingStock'] && null !== $it['stock'] && $it['stock'] > 0 && $it['stock'] <= $threshold)) return false;
					if ('in' === $stock && 'outofstock' === $it['stockStatus']) return false;
				}
				return true;
			}));
		}

		return ['items' => $items, 'total' => (int) $q->found_posts];
	}

	/**
	 * Serialize a product for list + editor use.
	 */
	public static function serialize($p) {
		$author = (int) get_post_field('post_author', $p->get_id());
		$cats = [];
		foreach ($p->get_category_ids() as $cid) {
			$t = get_term($cid, 'product_cat');
			if ($t && !is_wp_error($t)) $cats[] = ['id' => $t->term_id, 'name' => $t->name];
		}
		$tags = [];
		foreach ($p->get_tag_ids() as $tid) {
			$t = get_term($tid, 'product_tag');
			if ($t && !is_wp_error($t)) $tags[] = ['id' => $t->term_id, 'name' => $t->name];
		}

		// WCFM commission (vendor override else global).
		$commission = get_post_meta($p->get_id(), '_wcfm_commission', true);
		$global_commission = get_option('wcfm_commission_options', []);

		$data = [
			'id'           => $p->get_id(),
			'name'         => $p->get_name(),
			'status'       => $p->get_status(),
			'type'         => $p->get_type(),
			'sku'          => $p->get_sku(),
			'price'        => (float) $p->get_price('edit'),
			'regularPrice' => (float) $p->get_regular_price('edit'),
			'salePrice'    => $p->get_sale_price('edit') !== '' ? (float) $p->get_sale_price('edit') : null,
			'stock'        => $p->get_stock_quantity(),
			'stockStatus'  => $p->get_stock_status(),
			'managingStock'=> $p->get_manage_stock(),
			'image'        => wp_get_attachment_url($p->get_image_id()),
			'gallery'      => array_map('wp_get_attachment_url', $p->get_gallery_image_ids()),
			'categories'   => $cats,
			'tags'         => $tags,
			'dateCreated'  => $p->get_date_created() ? $p->get_date_created()->date_i18n('c') : '',
			'dateModified' => $p->get_date_modified() ? $p->get_date_modified()->date_i18n('c') : '',
			'rating'       => (float) $p->get_average_rating(),
			'ratingCount'  => (int) $p->get_rating_count(),
			'sales'        => (int) $p->get_total_sales('edit'),
			'featured'     => (bool) $p->get_featured(),
			'catalogVisibility' => $p->get_catalog_visibility(),
			'virtual'      => $p->get_virtual(),
			'downloadable' => $p->get_downloadable(),
			'authorId'     => $author,
			'editUrl'      => admin_url('post.php?post=' . $p->get_id() . '&action=edit'),
		];

		if ('simple' === $p->get_type()) {
			$data['weight'] = (float) $p->get_weight();
			$data['dimensions'] = [
				'length' => (float) $p->get_length(), 'width' => (float) $p->get_width(), 'height' => (float) $p->get_height(),
			];
			$data['taxClass'] = $p->get_tax_class();
			$data['taxStatus'] = $p->get_tax_status();
			$data['shippingClass'] = $p->get_shipping_class_id();
			$data['description'] = $p->get_description();
			$data['shortDescription'] = $p->get_short_description();
			$data['purchaseNote'] = $p->get_purchase_note();
			$data['reviewsAllowed'] = $p->get_reviews_allowed();
			$data['lowStockThreshold'] = $p->get_low_stock_amount();
			$data['backorders'] = $p->get_backorders();
		}
		if ('variable' === $p->get_type()) {
			$data['variations'] = [];
			foreach ($p->get_children() as $vid) {
				$v = wc_get_product($vid);
				if (!$v) continue;
				$attrs = [];
				foreach ($v->get_attributes() as $k => $val) {
					$attrs[sanitize_title($k)] = $val;
				}
				$data['variations'][] = [
					'id' => $v->get_id(),
					'sku' => $v->get_sku(),
					'price' => (float) $v->get_price('edit'),
					'regularPrice' => (float) $v->get_regular_price('edit'),
					'salePrice' => $v->get_sale_price('edit') !== '' ? (float) $v->get_sale_price('edit') : null,
					'stock' => $v->get_stock_quantity(),
					'stockStatus' => $v->get_stock_status(),
					'image' => wp_get_attachment_url($v->get_image_id()),
					'attributes' => $attrs,
					'status' => $v->get_status(),
				];
			}
		}
		return $data;
	}

	/**
	 * Verify the vendor owns a product; admins bypass.
	 */
	public static function owns($product_id, $vendor_id, $admin) {
		if ($admin) return true;
		if (!$vendor_id) return false;
		return (int) get_post_field('post_author', $product_id) === (int) $vendor_id;
	}

	/**
	 * Create a product with full field support.
	 */
	public static function create($payload, $vendor_id, $admin) {
		$name = isset($payload['name']) ? sanitize_text_field(wp_unslash($payload['name'])) : '';
		if (!$name) {
			return new WP_Error('dsa_name_required', 'Product name is required.', ['status' => 400]);
		}
		$type = isset($payload['type']) && in_array($payload['type'], ['simple', 'variable', 'grouped', 'external'], true) ? $payload['type'] : 'simple';

		$post_arr = [
			'post_title'  => $name,
			'post_status' => isset($payload['status']) && in_array($payload['status'], ['publish', 'draft', 'pending', 'private'], true) ? $payload['status'] : 'draft',
			'post_type'   => 'product',
			'post_author' => $vendor_id ? (int) $vendor_id : get_current_user_id(),
		];
		if (!$admin && !$vendor_id) {
			return new WP_Error('dsa_forbidden', 'Seller access required.', ['status' => 403]);
		}
		$post_id = wp_insert_post($post_arr, true);
		if (is_wp_error($post_id)) return $post_id;

		wp_set_object_terms($post_id, $type, 'product_type');
		$p = wc_get_product($post_id);
		$p = self::apply_fields($p, $payload, $vendor_id, $admin);

		return self::serialize(wc_get_product($post_id));
	}

	/**
	 * Update product fields.
	 */
	public static function update($product_id, $payload, $vendor_id, $admin) {
		$p = wc_get_product($product_id);
		if (!$p) return new WP_Error('dsa_not_found', 'Product not found.', ['status' => 404]);
		if (!self::owns($product_id, $vendor_id, $admin)) {
			return new WP_Error('dsa_forbidden', 'You do not own this product.', ['status' => 403]);
		}
		if (isset($payload['name'])) {
			wp_update_post(['ID' => $product_id, 'post_title' => sanitize_text_field(wp_unslash($payload['name']))]);
		}
		$p = wc_get_product($product_id);
		$p = self::apply_fields($p, $payload, $vendor_id, $admin);
		return self::serialize(wc_get_product($product_id));
	}

	/**
	 * Apply payload fields to a product object.
	 */
	private static function apply_fields($p, $payload, $vendor_id, $admin) {
		$product_id = $p->get_id();

		if (isset($payload['status']) && in_array($payload['status'], ['publish', 'draft', 'pending', 'private'], true)) {
			wp_update_post(['ID' => $product_id, 'post_status' => $payload['status']]);
		}
		if (isset($payload['description'])) $p->set_description(wp_kses_post(wp_unslash($payload['description'])));
		if (isset($payload['shortDescription'])) $p->set_short_description(wp_kses_post(wp_unslash($payload['shortDescription'])));
		if (isset($payload['sku'])) $p->set_sku(sanitize_text_field($payload['sku']));
		if (isset($payload['regularPrice'])) $p->set_regular_price((string) floatval($payload['regularPrice']));
		if (isset($payload['salePrice'])) $p->set_sale_price($payload['salePrice'] === '' || null === $payload['salePrice'] ? '' : (string) floatval($payload['salePrice']));
		if (isset($payload['featured'])) $p->set_featured((bool) $payload['featured']);
		if (isset($payload['catalogVisibility']) && in_array($payload['catalogVisibility'], ['visible', 'catalog', 'search', 'hidden'], true)) {
			$p->set_catalog_visibility($payload['catalogVisibility']);
		}
		if (isset($payload['virtual'])) $p->set_virtual((bool) $payload['virtual']);
		if (isset($payload['downloadable'])) $p->set_downloadable((bool) $payload['downloadable']);
		if (isset($payload['reviewsAllowed'])) $p->set_reviews_allowed((bool) $payload['reviewsAllowed']);
		if (isset($payload['purchaseNote'])) $p->set_purchase_note(sanitize_textarea_field(wp_unslash($payload['purchaseNote'])));
		if (isset($payload['slug'])) $p->set_slug(sanitize_title($payload['slug']));

		// Tax.
		if (isset($payload['taxStatus']) && in_array($payload['taxStatus'], ['taxable', 'shipping', 'none'], true)) $p->set_tax_status($payload['taxStatus']);
		if (isset($payload['taxClass']) && is_string($payload['taxClass'])) $p->set_tax_class(sanitize_text_field($payload['taxClass']));

		// Inventory.
		if (isset($payload['manageStock'])) $p->set_manage_stock((bool) $payload['manageStock']);
		if ($p->get_manage_stock()) {
			if (isset($payload['stock'])) $p->set_stock_quantity(absint($payload['stock']));
			if (isset($payload['lowStockThreshold'])) $p->set_low_stock_amount(absint($payload['lowStockThreshold']));
			if (isset($payload['backorders']) && in_array($payload['backorders'], ['no', 'notify', 'yes'], true)) $p->set_backorders($payload['backorders']);
		} else {
			if (isset($payload['stockStatus']) && in_array($payload['stockStatus'], ['instock', 'outofstock', 'onbackorder'], true)) {
				$p->set_stock_status($payload['stockStatus']);
			}
		}

		// Shipping.
		if (isset($payload['weight'])) $p->set_weight((string) floatval($payload['weight']));
		if (isset($payload['dimensions']) && is_array($payload['dimensions'])) {
			$d = $payload['dimensions'];
			$p->set_length(isset($d['length']) ? (string) floatval($d['length']) : '');
			$p->set_width(isset($d['width']) ? (string) floatval($d['width']) : '');
			$p->set_height(isset($d['height']) ? (string) floatval($d['height']) : '');
		}
		if (isset($payload['shippingClass'])) $p->set_shipping_class_id(absint($payload['shippingClass']));

		// Categories/tags (ids).
		if (isset($payload['categoryIds']) && is_array($payload['categoryIds'])) {
			wp_set_object_terms($product_id, array_map('absint', $payload['categoryIds']), 'product_cat');
		}
		if (isset($payload['tagIds']) && is_array($payload['tagIds']) ) {
			wp_set_object_terms($product_id, array_map('absint', $payload['tagIds']), 'product_tag');
		}
		// Tag strings convenience.
		if (isset($payload['tags']) && is_array($payload['tags'])) {
			wp_set_object_terms($product_id, array_map('sanitize_text_field', $payload['tags']), 'product_tag');
		}

		// Media.
		if (isset($payload['imageId'])) $p->set_image_id(absint($payload['imageId']));
		if (isset($payload['galleryIds']) && is_array($payload['galleryIds'])) {
			$p->set_gallery_image_ids(array_map('absint', $payload['galleryIds']));
		}

		$p->save();

		// Variations for variable products.
		if (isset($payload['variations']) && is_array($payload['variations']) && 'variable' === $p->get_type()) {
			self::sync_variations($p, $payload['variations'], $payload);
		}
		return wc_get_product($product_id);
	}

	/**
	 * Create/update variations.
	 */
	private static function sync_variations($parent, $variations, $payload) {
		$existing = $parent->get_children();
		$kept = [];
		foreach ($variations as $v) {
			$vid = isset($v['id']) ? absint($v['id']) : 0;
			if ($vid && in_array($vid, $existing, true)) {
				$variation = wc_get_product($vid);
			} else {
				$variation = new WC_Product_Variation();
				$variation->set_parent_id($parent->get_id());
			}
			if (isset($v['sku'])) $variation->set_sku(sanitize_text_field($v['sku']));
			if (isset($v['regularPrice'])) $variation->set_regular_price((string) floatval($v['regularPrice']));
			if (isset($v['salePrice'])) $variation->set_sale_price($v['salePrice'] === '' || null === $v['salePrice'] ? '' : (string) floatval($v['salePrice']));
			if (isset($v['stock'])) { $variation->set_manage_stock(true); $variation->set_stock_quantity(absint($v['stock'])); }
			if (isset($v['stockStatus']) && in_array($v['stockStatus'], ['instock', 'outofstock', 'onbackorder'], true)) {
				$variation->set_stock_status($v['stockStatus']);
			}
			if (isset($v['status']) && in_array($v['status'], ['publish', 'private', 'draft'], true)) $variation->set_status($v['status']);
			if (isset($v['imageId'])) $variation->set_image_id(absint($v['imageId']));
			if (isset($v['attributes']) && is_array($v['attributes'])) {
				$attrs = [];
				foreach ($v['attributes'] as $key => $value) {
					$key = sanitize_title($key);
					$value = sanitize_text_field($value);
					$attr = new WC_Product_Attribute();
					// Attach as custom (local) attribute data on the variation.
					$attrs[$key] = $value;
				}
				$variation->set_attributes($attrs);
			}
			$variation->save();
			$kept[] = $variation->get_id();
		}
		// Remove dropped variations.
		foreach ($existing as $eid) {
			if (!in_array($eid, $kept, true)) {
				$child = wc_get_product($eid);
				if ($child) $child->delete(true);
			}
		}
		$parent->set_children($kept);
		// Keep parent in sync.
		$attr_payload = isset($payload['attributes']) && is_array($payload['attributes']) ? $payload['attributes'] : [];
		if ($attr_payload) {
			$attributes = [];
			foreach ($attr_payload as $a) {
				if (empty($a['name'])) continue;
				$attribute = new WC_Product_Attribute();
				$attribute->set_name(sanitize_text_field($a['name']));
				$attribute->set_options(array_map('sanitize_text_field', isset($a['options']) ? $a['options'] : []));
				$attribute->set_variation(true);
				$attribute->set_visible(true);
				$attribute->set_position(count($attributes));
				$attributes[] = $attribute;
			}
			$parent->set_attributes($attributes);
			$parent->save();
		}
	}

	/**
	 * Adjust stock (delta or absolute) with wc_update_product_stock.
	 */
	public static function set_stock($product_id, $qty, $delta = false) {
		$p = wc_get_product($product_id);
		if (!$p) return new WP_Error('dsa_not_found', 'Product not found.', ['status' => 404]);
		$new = wc_update_product_stock($p, (int) $qty, $delta ? 'increase' : 'set');
		return ['id' => $product_id, 'stock' => null === $new ? null : (int) $new, 'stockStatus' => wc_get_product($product_id)->get_stock_status()];
	}

	/**
	 * Bulk actions.
	 */
	public static function bulk($action, $ids, $value, $vendor_id, $admin) {
		$results = ['updated' => 0, 'errors' => []];
		foreach ($ids as $pid) {
			$pid = absint($pid);
			if (!$pid || !self::owns($pid, $vendor_id, $admin)) {
				$results['errors'][] = ['id' => $pid, 'message' => 'Not yours'];
				continue;
			}
			switch ($action) {
				case 'publish':
				case 'draft':
					wp_update_post(['ID' => $pid, 'post_status' => 'publish' === $action ? 'publish' : 'draft']);
					$results['updated']++;
					break;
				case 'delete':
					$p = wc_get_product($pid);
					if ($p) { $p->delete(true); $results['updated']++; }
					break;
				case 'trash':
					$p = wc_get_product($pid);
					if ($p) { $p->delete(); $results['updated']++; }
					break;
				case 'price':
					$p = wc_get_product($pid);
					if ($p && 'simple' === $p->get_type()) {
						if (isset($value['regularPrice'])) $p->set_regular_price((string) floatval($value['regularPrice']));
						if (isset($value['salePrice'])) $p->set_sale_price($value['salePrice'] === '' ? '' : (string) floatval($value['salePrice']));
						$p->save(); $results['updated']++;
					}
					break;
				case 'stock':
					self::set_stock($pid, isset($value['stock']) ? (int) $value['stock'] : 0);
					$results['updated']++;
					break;
				case 'category':
					if (isset($value['categoryIds'])) {
						wp_set_object_terms($pid, array_map('absint', $value['categoryIds']), 'product_cat');
						$results['updated']++;
					}				break;
		}
		}
		return $results;
	}
}
