<?php
// Adjust path to wp-load.php. Plugin dir is wp-content/plugins/query-loop-extend
// So we need to go up 3 levels: ../../../wp-load.php
require_once __DIR__ . '/../../../wp-load.php';

$args = array(
    'post_type' => 'page',
    'posts_per_page' => 1,
    'post_status' => 'publish',
    'orderby' => 'modified',
);
$query = new WP_Query($args);

if ($query->have_posts()) {
    while ($query->have_posts()) {
        $query->the_post();
        echo "Post ID: " . get_the_ID() . "\n";
        echo "Content Snippet (first 2000 chars):\n";
        echo substr(get_the_content(), 0, 2000);
        echo "\n\nFull Content Search for 'custom_query_php':\n";
        if (strpos(get_the_content(), 'custom_query_php') !== false) {
            echo "FOUND 'custom_query_php' in content!\n";
            // Also print the context around it
            $pos = strpos(get_the_content(), 'custom_query_php');
            echo "Context: " . substr(get_the_content(), max(0, $pos - 100), 200) . "\n";
        } else {
            echo "NOT FOUND 'custom_query_php' in content.\n";
        }
    }
} else {
    echo "No pages found.";
}
