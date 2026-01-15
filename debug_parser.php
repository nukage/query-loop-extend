<?php
// Adjust path to wp-load.php. Plugin dir is wp-content/plugins/query-loop-extend
require_once __DIR__ . '/../../../wp-load.php';

echo "Debugging Block Parser...\n";

// Get the specific page ID 1126
$post = get_post(1126);

if (!$post) {
    die("Post 1126 not found");
}

echo "Post Content Length: " . strlen($post->post_content) . "\n";
echo "Searching for custom_query_php in raw content: ";
if (strpos($post->post_content, 'custom_query_php') !== false) {
    echo "FOUND\n";
} else {
    echo "NOT FOUND\n";
}

// Parse blocks
$blocks = parse_blocks($post->post_content);

function recursive_search_query_blocks($blocks)
{
    foreach ($blocks as $block) {
        if ($block['blockName'] === 'core/query') {
            echo "Found core/query block.\n";
            echo "Attributes: " . print_r($block['attrs'], true) . "\n";
            if (isset($block['attrs']['custom_query_php'])) {
                echo "custom_query_php IS present in parse_blocks output.\n";
            } else {
                echo "custom_query_php IS NOT present in parse_blocks output.\n";
            }
        }

        if (!empty($block['innerBlocks'])) {
            recursive_search_query_blocks($block['innerBlocks']);
        }
    }
}

recursive_search_query_blocks($blocks);

// Check Registry
$registry = WP_Block_Type_Registry::get_instance()->get_registered('core/query');
if (isset($registry->attributes['custom_query_php'])) {
    echo "Registry HAS custom_query_php defined.\n";
} else {
    echo "Registry DOES NOT HAVE custom_query_php defined.\n";
}
