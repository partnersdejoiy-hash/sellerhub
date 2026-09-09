<?php
/**
 * DSA Seller — seller identity & compliance service.
 *
 * Owns: DEJOIY Seller ID (immutable), GST flow, KYC records, bank/payout
 * details, onboarding progress, eligibility decisions. Sensitive values are
 * stored server-side and masked in API output.
 *
 * Data lives in usermeta keyed by vendor id (WooCommerce/WCFM remain the
 * account system — no duplicate user tables).
 */
if (!defined('ABSPATH')) exit;

class DSA_Seller {

	const META_SELLER_ID   = '_dejoiy_seller_id';
	const META_PROFILE     = '_dejoiy_seller_profile';
	const META_GST         = '_dejoiy_seller_gst';
	const META_KYC         = '_dejoiy_seller_kyc';
	const META_BANK        = '_dejoiy_seller_bank';
	const META_ONBOARD     = '_dejoiy_seller_onboarding';
	const META_ELIGIBILITY = '_dejoiy_seller_eligibility';

	/**
	 * Get or create the permanent DEJOIY Seller ID for a vendor.
	 * Format: DJY-SLR-XXXXXX (sequential, never reused, never deleted).
	 */
	public static function seller_id($vendor_id) {
		$vendor_id = absint($vendor_id);
		if (!$vendor_id) return '';
		$existing = get_user_meta($vendor_id, self::META_SELLER_ID, true);
		if ($existing) return $existing;

		// Atomic-ish sequence via options table LAST_INSERT_ID trick.
		add_option('dejoiy_seller_id_sequence', 0, '', false);
		global $wpdb;
		$wpdb->query(
			"UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID(CAST(option_value AS UNSIGNED) + 1) WHERE option_name = 'dejoiy_seller_id_sequence'"
		);
		$next = absint($wpdb->get_var('SELECT LAST_INSERT_ID()'));
		if ($next < 1) $next = 1;
		$seller_id = 'DJY-SLR-' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
		update_user_meta($vendor_id, self::META_SELLER_ID, $seller_id);
		return $seller_id;
	}

	/**
	 * GST profile (validated format server-side; verification provider optional).
	 */
	public static function gst($vendor_id) {
		$raw = get_user_meta($vendor_id, self::META_GST, true);
		if (!is_array($raw)) $raw = [];
		$out = [
			'hasGstin'    => !empty($raw['gstin']),
			'gstin'       => isset($raw['gstin']) ? self::mask_gstin($raw['gstin']) : '',
			'legalName'   => isset($raw['legalName']) ? $raw['legalName'] : '',
			'state'       => isset($raw['state']) ? $raw['state'] : '',
			'status'      => isset($raw['status']) ? $raw['status'] : 'not_provided', // not_provided|pending|verified|rejected
			'pan'         => isset($raw['pan']) ? self::mask_pan($raw['pan']) : '',
			'businessType'=> isset($raw['businessType']) ? $raw['businessType'] : '',
		];
		return $out;
	}

	/**
	 * Save GST info. Format-validated; marked "pending" until a verification
	 * provider confirms. No provider is configured yet — never auto-verify.
	 */
	public static function save_gst($vendor_id, $data) {
		$gstin = isset($data['gstin']) ? strtoupper(preg_replace('/\s+/', '', sanitize_text_field($data['gstin']))) : '';
		$has = !empty($gstin);
		if ($has && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin)) {
			return new WP_Error('dsa_gst_invalid', 'GSTIN format is invalid. It should look like 22AAAAA0000A1Z5.', ['status' => 400]);
		}
		$pan = '';
		if ($has && strlen($gstin) >= 10) {
			$pan = substr($gstin, 2, 10); // PAN is embedded in GSTIN.
		} elseif (!empty($data['pan'])) {
			$pan = strtoupper(preg_replace('/\s+/', '', sanitize_text_field($data['pan'])));
			if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) {
				return new WP_Error('dsa_pan_invalid', 'PAN format is invalid. It should look like ABCDE1234F.', ['status' => 400]);
			}
		}
		$state = isset($data['state']) ? sanitize_text_field($data['state']) : '';
		if ($has && empty($state)) {
			$codes = self::gst_state_codes();
			$code = substr($gstin, 0, 2);
			if (isset($codes[$code])) $state = $codes[$code];
		}
		$record = [
			'gstin'        => $gstin,
			'legalName'    => isset($data['legalName']) ? sanitize_text_field($data['legalName']) : '',
			'state'        => $state,
			'pan'          => $pan,
			'businessType' => isset($data['businessType']) ? sanitize_text_field($data['businessType']) : '',
			'status'       => $has ? 'pending' : 'not_provided',
			'updated'      => gmdate('c'),
		];
		update_user_meta($vendor_id, self::META_GST, $record);

		// Eligibility re-evaluation on GST change.
		self::evaluate_eligibility($vendor_id);
		return self::gst($vendor_id);
	}

	public static function gst_state_codes() {
		return [
			'01' => 'Jammu & Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh',
			'05' => 'Uttarakhand', '06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh',
			'10' => 'Bihar', '11' => 'Sikkim', '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur',
			'15' => 'Mizoram', '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal',
			'20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh', '24' => 'Gujarat',
			'27' => 'Maharashtra', '29' => 'Karnataka', '30' => 'Goa', '31' => 'Lakshadweep', '32' => 'Kerala',
			'33' => 'Tamil Nadu', '34' => 'Puducherry', '35' => 'Andaman & Nicobar', '36' => 'Telangana', '37' => 'Andhra Pradesh',
		];
	}

	/**
	 * KYC record. Documents are stored as attachment ids; numbers masked on read.
	 */
	public static function kyc($vendor_id) {
		$raw = get_user_meta($vendor_id, self::META_KYC, true);
		if (!is_array($raw)) $raw = [];
		return [
			'panName'     => isset($raw['panName']) ? $raw['panName'] : '',
			'panNumber'   => isset($raw['panNumber']) ? self::mask_pan($raw['panNumber']) : '',
			'panDocId'    => isset($raw['panDocId']) ? absint($raw['panDocId']) : 0,
			'addressProof'=> isset($raw['addressProof']) ? $raw['addressProof'] : '',
			'addressDocId'=> isset($raw['addressDocId']) ? absint($raw['addressDocId']) : 0,
			'status'      => isset($raw['status']) ? $raw['status'] : 'pending', // pending|submitted|verified|rejected
			'submittedAt' => isset($raw['submittedAt']) ? $raw['submittedAt'] : '',
			'remarks'     => isset($raw['remarks']) ? $raw['remarks'] : '',
		];
	}

	public static function save_kyc($vendor_id, $data) {
		$raw = get_user_meta($vendor_id, self::META_KYC, true);
		if (!is_array($raw)) $raw = [];
		if (isset($data['panName'])) $raw['panName'] = sanitize_text_field($data['panName']);
		if (isset($data['panNumber'])) {
			$pan = strtoupper(preg_replace('/\s+/', '', sanitize_text_field($data['panNumber'])));
			if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) {
				return new WP_Error('dsa_pan_invalid', 'PAN format is invalid. Example: ABCDE1234F.', ['status' => 400]);
			}
			$raw['panNumber'] = $pan;
		}
		if (isset($data['panDocId'])) $raw['panDocId'] = absint($data['panDocId']);
		if (isset($data['addressProof'])) $raw['addressProof'] = sanitize_text_field($data['addressProof']);
		if (isset($data['addressDocId'])) $raw['addressDocId'] = absint($data['addressDocId']);
		if (empty($raw['submittedAt']) || !empty($data['resubmit'])) {
			$raw['submittedAt'] = gmdate('c');
		}
		// Seller can submit for review; verification is an admin action.
		$complete = !empty($raw['panNumber']) && !empty($raw['panName']);
		if ('verified' !== ($raw['status'] ?? '')) {
			$raw['status'] = $complete ? 'submitted' : 'pending';
		}
		update_user_meta($vendor_id, self::META_KYC, $raw);
		return self::kyc($vendor_id);
	}

	/**
	 * Bank / payout details. Account numbers are masked on read; full value
	 * only lives in the DB and is never returned through the API.
	 */
	public static function bank($vendor_id) {
		$raw = get_user_meta($vendor_id, self::META_BANK, true);
		if (!is_array($raw)) $raw = [];
		return [
			'holderName'   => isset($raw['holderName']) ? $raw['holderName'] : '',
			'bankName'     => isset($raw['bankName']) ? $raw['bankName'] : '',
			'accountMasked'=> isset($raw['accountNumber']) ? self::mask_account($raw['accountNumber']) : '',
			'ifsc'         => isset($raw['ifsc']) ? $raw['ifsc'] : '',
			'accountType'  => isset($raw['accountType']) ? $raw['accountType'] : '',
			'upi'          => isset($raw['upi']) ? $raw['upi'] : '',
			'status'       => isset($raw['status']) ? $raw['status'] : 'pending',
		];
	}

	public static function save_bank($vendor_id, $data) {
		$raw = get_user_meta($vendor_id, self::META_BANK, true);
		if (!is_array($raw)) $raw = [];
		if (isset($data['holderName'])) $raw['holderName'] = sanitize_text_field($data['holderName']);
		if (isset($data['bankName'])) $raw['bankName'] = sanitize_text_field($data['bankName']);
		if (isset($data['accountNumber'])) {
			$acct = preg_replace('/\s+/', '', sanitize_text_field($data['accountNumber']));
			// Allow masked re-save (seller saved without changing).
			if (false === strpos($acct, 'X') && false === strpos($acct, '*')) {
				if (!preg_match('/^[0-9]{9,18}$/', $acct)) {
					return new WP_Error('dsa_acct_invalid', 'Account number must be 9-18 digits.', ['status' => 400]);
				}
				$raw['accountNumber'] = $acct;
			}
		}
		if (isset($data['ifsc'])) {
			$ifsc = strtoupper(preg_replace('/\s+/', '', sanitize_text_field($data['ifsc'])));
			if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) {
				return new WP_Error('dsa_ifsc_invalid', 'IFSC format is invalid. Example: SBIN0001234.', ['status' => 400]);
			}
			$raw['ifsc'] = $ifsc;
		}
		if (isset($data['accountType']) && in_array($data['accountType'], ['savings', 'current'], true)) {
			$raw['accountType'] = $data['accountType'];
		}
		if (isset($data['upi'])) {
			$upi = sanitize_text_field($data['upi']);
			if ($upi && !preg_match('/^[a-zA-Z0-9._-]{2,}@[a-zA-Z]{2,}$/', $upi)) {
				return new WP_Error('dsa_upi_invalid', 'UPI ID format is invalid. Example: name@okbank.', ['status' => 400]);
			}
			$raw['upi'] = $upi;
		}
		$complete = !empty($raw['holderName']) && !empty($raw['accountNumber']) && !empty($raw['ifsc']);
		$raw['status'] = $complete ? 'saved' : 'pending';
		update_user_meta($vendor_id, self::META_BANK, $raw);
		return self::bank($vendor_id);
	}

	/**
	 * Non-GST / overall eligibility decision. Rule-based, transparent, and
	 * honest: DEJOIY policy, not legal advice.
	 */
	public static function eligibility($vendor_id) {
		$raw = get_user_meta($vendor_id, self::META_ELIGIBILITY, true);
		if (!is_array($raw)) $raw = [];
		return [
			'sellerType'  => isset($raw['sellerType']) ? $raw['sellerType'] : '', // gst | non_gst
			'status'      => isset($raw['status']) ? $raw['status'] : 'under_review', // eligible|restricted|under_review
			'reasons'     => isset($raw['reasons']) && is_array($raw['reasons']) ? $raw['reasons'] : [],
			'categories'  => isset($raw['categories']) && is_array($raw['categories']) ? $raw['categories'] : [],
			'decidedAt'   => isset($raw['decidedAt']) ? $raw['decidedAt'] : '',
		];
	}

	public static function evaluate_eligibility($vendor_id) {
		$gst = get_user_meta($vendor_id, self::META_GST, true);
		$kyc = get_user_meta($vendor_id, self::META_KYC, true);
		$has_gst = is_array($gst) && !empty($gst['gstin']);
		$reasons = [];

		if ($has_gst) {
			$seller_type = 'gst';
			// GST present + PAN (embedded) + KYC PAN name => eligible after KYC submit.
			if (is_array($kyc) && 'verified' === ($kyc['status'] ?? '')) {
				$status = 'eligible';
			} elseif (is_array($kyc) && 'submitted' === ($kyc['status'] ?? '')) {
				$status = 'under_review';
				$reasons[] = 'KYC verification in progress.';
			} else {
				$status = 'under_review';
				$reasons[] = 'Complete KYC to activate selling.';
			}
		} else {
			$seller_type = 'non_gst';
			// Non-GST: allowed for categories without mandatory GST per DEJOIY policy,
			// restricted volumes apply. Kept conservative and explicit.
			$reasons[] = 'Non-GST selling is limited to eligible categories under DEJOIY policy and applicable Indian tax rules.';
			if (is_array($kyc) && 'verified' === ($kyc['status'] ?? '')) {
				$status = 'eligible';
			} elseif (is_array($kyc) && 'submitted' === ($kyc['status'] ?? '')) {
				$status = 'under_review';
				$reasons[] = 'KYC verification in progress.';
			} else {
				$status = 'under_review';
				$reasons[] = 'Complete KYC to activate selling.';
			}
		}
		$record = [
			'sellerType' => $seller_type,
			'status'     => $status,
			'reasons'    => $reasons,
			'categories' => isset($raw_categories) ? $raw_categories : [],
			'decidedAt'  => gmdate('c'),
		];
		update_user_meta($vendor_id, self::META_ELIGIBILITY, $record);
		return $record;
	}

	/**
	 * Onboarding checklist with completion percentage. Resumable by design —
	 * state derives from saved records, so progress survives logout/sessions.
	 */
	public static function onboarding($vendor_id) {
		$profile = get_user_meta($vendor_id, self::META_PROFILE, true);
		$gst = get_user_meta($vendor_id, self::META_GST, true);
		$kyc = get_user_meta($vendor_id, self::META_KYC, true);
		$bank = get_user_meta($vendor_id, self::META_BANK, true);
		$elig = self::eligibility($vendor_id);

		$steps = [
			['key' => 'account', 'label' => 'Account created', 'done' => true, 'link' => '/settings'],
			['key' => 'business', 'label' => 'Business information', 'done' => is_array($profile) && !empty($profile['businessType']), 'link' => '/onboarding'],
			['key' => 'gst', 'label' => 'GST status (Yes/No)', 'done' => is_array($gst) && (isset($gst['gstin']) || isset($gst['nonGstDeclared'])), 'link' => '/onboarding'],
			['key' => 'kyc', 'label' => 'KYC submission', 'done' => is_array($kyc) && in_array($kyc['status'] ?? '', ['submitted', 'verified'], true), 'link' => '/onboarding'],
			['key' => 'bank', 'label' => 'Bank / payout setup', 'done' => is_array($bank) && 'saved' === ($bank['status'] ?? ''), 'link' => '/onboarding'],
			['key' => 'store', 'label' => 'Store profile setup', 'done' => false, 'link' => '/store'],
			['key' => 'firstProduct', 'label' => 'Add first product', 'done' => false, 'link' => '/products/new'],
		];
		$shop = DSA_Vendor::shop($vendor_id);
		$steps[5]['done'] = !empty($shop['logo']) || !empty($shop['address1']);
		$own = DSA_Vendor::vendor_product_ids($vendor_id);
		$steps[6]['done'] = count($own) > 0;

		$done = 0;
		foreach ($steps as $s) { if ($s['done']) $done++; }
		return [
			'steps'      => $steps,
			'completed'  => $done,
			'total'      => count($steps),
			'percent'    => (int) round($done / count($steps) * 100),
			'next'       => null,
			'sellerType' => $elig['sellerType'],
			'eligibility'=> $elig['status'],
		];
	}

	/**
	 * Business profile (registration step 2/3 data).
	 */
	public static function profile($vendor_id) {
		$raw = get_user_meta($vendor_id, self::META_PROFILE, true);
		if (!is_array($raw)) $raw = [];
		return [
			'businessType' => isset($raw['businessType']) ? $raw['businessType'] : '',
			'legalName'    => isset($raw['legalName']) ? $raw['legalName'] : '',
			'dob'          => isset($raw['dob']) ? $raw['dob'] : '',
			'storeCategory'=> isset($raw['storeCategory']) ? $raw['storeCategory'] : '',
			'supportEmail' => isset($raw['supportEmail']) ? $raw['supportEmail'] : '',
			'website'      => isset($raw['website']) ? $raw['website'] : '',
			'nonGstDeclared'=> !empty($raw['nonGstDeclared']),
			'declaration'  => !empty($raw['declaration']),
		];
	}

	public static function save_profile($vendor_id, $data) {
		$raw = get_user_meta($vendor_id, self::META_PROFILE, true);
		if (!is_array($raw)) $raw = [];
		$allowed = ['businessType', 'legalName', 'dob', 'storeCategory', 'supportEmail', 'website'];
		foreach ($allowed as $key) {
			if (isset($data[$key])) $raw[$key] = sanitize_text_field($data[$key]);
		}
		if (isset($data['nonGstDeclared'])) $raw['nonGstDeclared'] = (bool) $data['nonGstDeclared'];
		if (isset($data['declaration'])) $raw['declaration'] = (bool) $data['declaration'];
		if (isset($data['businessType']) && !in_array($data['businessType'], ['individual', 'proprietorship', 'partnership', 'llp', 'pvt_ltd', 'public_ltd', 'other'], true)) {
			return new WP_Error('dsa_business_type', 'Invalid business type.', ['status' => 400]);
		}
		update_user_meta($vendor_id, self::META_PROFILE, $raw);
		self::evaluate_eligibility($vendor_id);
		return self::profile($vendor_id);
	}

	// ── Masking helpers ──
	public static function mask_pan($pan) {
		if (!$pan || strlen($pan) < 10) return '';
		return substr($pan, 0, 3) . 'XXXX' . substr($pan, 7, 1) . substr($pan, 9, 1);
	}
	public static function mask_gstin($g) {
		if (!$g || strlen($g) < 15) return $g;
		return substr($g, 0, 2) . 'XXXXX' . substr($g, 7, 4) . 'X' . substr($g, 12) ;
	}
	public static function mask_account($a) {
		if (!$a) return '';
		return str_repeat('X', max(0, strlen($a) - 4)) . substr($a, -4);
	}
}
