<?php
/**
 * Plugin Name: WP Singular Post Redirect
 * Description: Redirect duplicate posts and revisions to the most relevant post based on title, tags, or related content.
 * Version: 1.0.1
 * Author: Kim Jacobsen
 */

// Hook into template_redirect to check for duplicate or revision posts.
add_action('template_redirect', 'wpsr_redirect_duplicate_posts');

function wpsr_redirect_duplicate_posts() {
    if (is_singular('post')) {
        global $post;

        // Get the current post ID and post title.
        $current_post_id = $post->ID;
        $current_post_title = $post->post_title;

        // Query for similar posts by title.
        $similar_posts = new WP_Query([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'post__not_in' => [$current_post_id], // Exclude the current post.
            's' => $current_post_title,
        ]);

        // Redirect if a similar post exists.
        if ($similar_posts->have_posts()) {
            $similar_posts->the_post();
            $target_url = get_permalink(get_the_ID());
            wp_reset_postdata();

            // Avoid redirecting to the same URL.
            if ($target_url !== get_permalink($current_post_id)) {
                wp_redirect($target_url, 301);
                exit;
            }
        }

        // Query for related posts by tags if no similar title match.
        $tags = wp_get_post_tags($current_post_id, ['fields' => 'ids']);
        if (!empty($tags)) {
            $related_posts = new WP_Query([
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => 1,
                'post__not_in' => [$current_post_id], // Exclude the current post.
                'tag__in' => $tags,
            ]);

            if ($related_posts->have_posts()) {
                $related_posts->the_post();
                $target_url = get_permalink(get_the_ID());
                wp_reset_postdata();

                // Avoid redirecting to the same URL.
                if ($target_url !== get_permalink($current_post_id)) {
                    wp_redirect($target_url, 301);
                    exit;
                }
            }
        }
    }
}

// Optional: Prevent duplicate posts from being created (admin side).
add_filter('wp_insert_post_data', 'wpsr_prevent_duplicate_posts', 10, 2);

function wpsr_prevent_duplicate_posts($data, $postarr) {
    if ($data['post_type'] === 'post' && $data['post_status'] === 'publish') {
        $existing_posts = new WP_Query([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            's' => $data['post_title'],
        ]);

        if ($existing_posts->have_posts()) {
            // If a duplicate post exists, set the new post to draft.
            $data['post_status'] = 'draft';
        }
    }

    return $data;
}
