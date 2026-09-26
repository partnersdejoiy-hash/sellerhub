<?php
/**
 * DSO Reviews
 */
if (!defined('ABSPATH')) exit;

class DSO_Reviews {

    public function render() {
        $user_id = get_current_user_id();
        $plugin = Dejoiy_Seller_OS::instance();
        $vendor_id = $plugin->get_vendor_id($user_id);
        $data = $this->get_reviews_data($vendor_id);

        ?>
        <div class="dso-page dso-reviews">
            <div class="dso-page-header">
                <div>
                    <h1>Reviews</h1>
                    <p>Manage customer reviews for your store and products</p>
                </div>
            </div>

            <!-- Rating Overview -->
            <div class="dso-grid-2">
                <div class="dso-card dso-rating-overview">
                    <div class="dso-rating-big">
                        <span class="dso-rating-number"><?php echo $data['avg_rating'] > 0 ? number_format($data['avg_rating'], 1) : '—' ?></span>
                        <div class="dso-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="dso-star <?php echo $i <= round($data['avg_rating']) ? 'dso-star-filled' : '' ?>">★</span>
                            <?php endfor; ?>
                        </div>
                        <span class="dso-rating-count"><?php echo $data['total_reviews'] ?> reviews</span>
                    </div>
                    <div class="dso-rating-bars">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <div class="dso-rating-bar-row">
                                <span><?php $i ?>★</span>
                                <div class="dso-rating-bar"><div class="dso-rating-bar-fill" style="width: <?php echo $data['total_reviews'] > 0 ? ($data['distribution'][$i] / $data['total_reviews']) * 100 : 0 ?>%"></div></div>
                                <span><?php echo $data['distribution'][$i] ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="dso-card">
                    <div class="dso-card-header"><h3>Quick Stats</h3></div>
                    <div class="dso-card-body">
                        <div class="dso-info-list">
                            <div class="dso-info-item"><span class="dso-info-label">Pending Reviews</span><span><?php echo $data['pending_count'] ?></span></div>
                            <div class="dso-info-item"><span class="dso-info-label">Approved Reviews</span><span><?php echo $data['approved_count'] ?></span></div>
                            <div class="dso-info-item"><span class="dso-info-label">Responded</span><span><?php echo $data['responded_count'] ?></span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reviews List -->
            <div class="dso-card">
                <div class="dso-card-header"><h3>All Reviews</h3></div>
                <div class="dso-reviews-list">
                    <?php if (empty($data['reviews'])): ?>
                        <div class="dso-empty-state">
                            <div class="dso-empty-icon">⭐</div>
                            <h3>No reviews yet</h3>
                            <p>Customer reviews will appear here once they review your products.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($data['reviews'] as $review): ?>
                            <div class="dso-review-item">
                                <div class="dso-review-header">
                                    <div class="dso-review-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="dso-star <?php echo $i <= $review['rating'] ? 'dso-star-filled' : '' ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="dso-review-date"><?php echo esc_html($review['date']) ?></span>
                                </div>
                                <p class="dso-review-product">Product: <?php echo esc_html($review['product']) ?></p>
                                <p class="dso-review-customer">By: <?php echo esc_html($review['customer']) ?></p>
                                <p class="dso-review-text"><?php echo esc_html($review['review']) ?></p>
                                <?php if (!empty($review['response'])): ?>
                                    <div class="dso-review-response">
                                        <strong>Your Response:</strong>
                                        <p><?php echo esc_html($review['response']) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function get_reviews_data($vendor_id) {
        global $wpdb;

        $reviews = [];
        $avg_rating = 0;
        $total_reviews = 0;
        $pending_count = 0;
        $approved_count = 0;
        $responded_count = 0;
        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        if ($vendor_id) {
            $has_wcfm_tbl = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wcfm_marketplace_reviews'");
            if ($has_wcfm_tbl) {
                // Average rating
                $avg = $wpdb->get_var($wpdb->prepare(
                    "SELECT AVG(rrm.value) FROM {$wpdb->prefix}wcfm_marketplace_review_rating_meta rrm
                    INNER JOIN {$wpdb->prefix}wcfm_marketplace_reviews r ON rrm.review_id = r.ID
                    WHERE r.vendor_id = %d AND rrm.key = 'rating'",
                    $vendor_id
                ));
                $avg_rating = $avg ? floatval($avg) : 0;

                // Counts
                $total_reviews = intval($wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}wcfm_marketplace_reviews WHERE vendor_id = %d AND approved = 1",
                    $vendor_id
                )));

                $pending_count = intval($wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}wcfm_marketplace_reviews WHERE vendor_id = %d AND approved = 0",
                    $vendor_id
                )));

                $approved_count = $total_reviews;

                // Reviews list
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}wcfm_marketplace_reviews WHERE vendor_id = %d ORDER BY created DESC LIMIT 20",
                    $vendor_id
                ));

                if (!empty($rows)) {
                    foreach ($rows as $row) {
                        $rating = intval($row->review_rating ?? 0);
                        if ($rating >= 1 && $rating <= 5) $distribution[$rating]++;

                        $reviews[] = [
                            'rating' => $rating,
                            'review' => $row->review_description ?? '',
                            'customer' => get_the_author_meta('display_name', $row->author_id ?? 0),
                            'product' => '',
                            'date' => $row->created ? date('M j, Y', strtotime($row->created)) : '—',
                            'response' => '',
                        ];
                    }
                }
            }

            // Fallback / standard WooCommerce product reviews
            if (empty($reviews)) {
                $pids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'product' AND post_author = %d", $vendor_id));
                if (!empty($pids)) {
                    $pids_in = implode(',', array_map('intval', $pids));
                    $comm_rows = $wpdb->get_results("SELECT c.*, cm.meta_value as rating FROM {$wpdb->prefix}comments c LEFT JOIN {$wpdb->prefix}commentmeta cm ON c.comment_ID = cm.comment_id AND cm.meta_key = 'rating' WHERE c.comment_post_ID IN ({$pids_in}) AND c.comment_type = 'review' AND c.comment_approved = '1' ORDER BY c.comment_date DESC LIMIT 20");
                    if (!empty($comm_rows)) {
                        $total_reviews = count($comm_rows);
                        $approved_count = $total_reviews;
                        $rating_sum = 0;
                        foreach ($comm_rows as $cr) {
                            $r = intval($cr->rating ?: 5);
                            $rating_sum += $r;
                            if ($r >= 1 && $r <= 5) $distribution[$r]++;
                            $reviews[] = [
                                'rating' => $r,
                                'review' => $cr->comment_content,
                                'customer' => $cr->comment_author,
                                'product' => get_the_title($cr->comment_post_ID),
                                'date' => date('M j, Y', strtotime($cr->comment_date)),
                                'response' => '',
                            ];
                        }
                        $avg_rating = $total_reviews > 0 ? round($rating_sum / $total_reviews, 1) : 0;
                    }
                }
            }
        }

        return compact('avg_rating', 'total_reviews', 'pending_count', 'approved_count', 'responded_count', 'distribution', 'reviews');
    }
}
