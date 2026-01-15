<?php
/**
 * Plugin Name: Query Loop Extend
 * Description: Extends the Core Query Loop block with custom PHP query capabilities.
 * Version: 1.0.0
 * Author: Antigravity
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Debug render_block_data.
 */
add_filter('render_block_data', function ($parsed_block, $source_block, $parent_block) {
    if ($parsed_block['blockName'] === 'core/query') {
        $has_attr = isset($parsed_block['attrs']['custom_query_php']) ? 'YES' : 'NO';
        $attr_val = $has_attr === 'YES' ? $parsed_block['attrs']['custom_query_php'] : '';

        file_put_contents(
            __DIR__ . '/debug_log.txt',
            "render_block_data Hit for core/query.\n" .
            "Has custom_query_php? $has_attr. Value: $attr_val\n",
            FILE_APPEND
        );
    }
    return $parsed_block;
}, 10, 3);

/**
 * Enqueue editor assets.
 */
function query_loop_extend_enqueue_editor_assets()
{
    wp_enqueue_script(
        'query-loop-extend-editor',
        plugins_url('assets/editor.js', __FILE__),
        array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-hooks', 'wp-data', 'wp-block-library', 'wp-edit-post'),
        filemtime(plugin_dir_path(__FILE__) . 'assets/editor.js'),
        true
    );
}
add_action('enqueue_block_editor_assets', 'query_loop_extend_enqueue_editor_assets');

// Use a global to store the RAW CODE between the data filter and the query filter.
global $query_loop_extend_code;
global $query_loop_extend_error;
$query_loop_extend_code = null;
$query_loop_extend_error = null;

add_filter('render_block_data', function ($parsed_block, $source_block, $parent_block) {
    global $query_loop_extend_code;
    global $query_loop_extend_error;

    // Only target the Query Loop block
    if ($parsed_block['blockName'] === 'core/query') {
        // Reset for this block instance
        $query_loop_extend_code = null;
        $query_loop_extend_error = null;

        if (isset($parsed_block['attrs']['custom_query_php']) && !empty($parsed_block['attrs']['custom_query_php'])) {
            // Just store the code for now. detection of context happens later.
            $query_loop_extend_code = $parsed_block['attrs']['custom_query_php'];
        }
    }
    return $parsed_block;
}, 20, 3);

/**
 * Render block filter to show errors.
 */
add_filter('render_block', function ($block_content, $block) {
    global $query_loop_extend_error;

    if ('core/query' === $block['blockName'] && !empty($query_loop_extend_error)) {
        if (current_user_can('edit_posts')) {
            $error_message = sprintf(
                '<div style="border: 1px solid #cc0000; background: #ffeae8; color: #cc0000; padding: 10px; margin-bottom: 10px;"><strong>Query Loop PHP Error:</strong> %s</div>',
                esc_html($query_loop_extend_error)
            );
            $block_content = $error_message . $block_content;
        }
        // Reset error after handling
        $query_loop_extend_error = null;
    }

    return $block_content;
}, 10, 2);

/**
 * Apply the custom args to the actual WP_Query.
 * This runs inside the block's render_callback, where we have the correct context (page).
 */
add_filter('query_loop_block_query_vars', function ($query_args, $block, $page) {
    global $query_loop_extend_code;
    global $query_loop_extend_error;

    if (!empty($query_loop_extend_code)) {
        $custom_php = $query_loop_extend_code;

        // Closure to isolate scope
        $closure = function () use ($custom_php, $query_args, $page) {
            global $query_loop_extend_error;

            // Aliases/Context for the user
            $query = $query_args;
            $paged = $page; // The correct page number calculated by the block

            try {
                $code = trim($custom_php);
                if (substr($code, 0, 5) === '<?php') {
                    $code = substr($code, 5);
                }
                if (substr($code, -2) === '?>') {
                    $code = substr($code, 0, -2);
                }

                // Execute
                $result = eval ($code);

                if (is_array($result)) {
                    // Compatibility Shim: Map common block attributes (camelCase) to WP_Query args (snake_case)
                    // This helps users who might guess keys based on block attributes
                    $map = [
                        'postType' => 'post_type',
                        'perPage' => 'posts_per_page',
                        'orderBy' => 'orderby',
                    ];

                    foreach ($map as $blockKey => $queryKey) {
                        if (isset($result[$blockKey]) && !isset($result[$queryKey])) {
                            $result[$queryKey] = $result[$blockKey];
                            unset($result[$blockKey]); // Clean up
                        }
                    }

                    $final_args = array_merge($query_args, $result);

                    // Logic Fix: If user modifies 'posts_per_page' but does not set an explicit 'offset',
                    // the default offset calculated by WP (based on Block Settings) will be wrong.
                    // Example: Block says 10, User says 2. Page 2 -> WP offset 10. We want offset 2.
                    if (isset($result['posts_per_page']) && !isset($result['offset'])) {
                        $per_page = (int) $final_args['posts_per_page'];
                        // Use the strict page number passed to this filter
                        $current_page = max(1, (int) $page);
                        $final_args['offset'] = ($current_page - 1) * $per_page;

                        // If we are adjusting found_posts (from previous step), incorporate it here?
                        // The previous step handled explicit 'qle_offset_adjustment'. 
                        // If the user has a custom offset logic, they usually set 'offset' explicitly.
                        // Here we are handling the "standard" case where they just want to change page size.
                    }

                    return $final_args;
                }
            } catch (Throwable $e) {
                $query_loop_extend_error = $e->getMessage();
            }

            return $query_args;
        };

        // Run it
        $query_args = $closure();


    }
    return $query_args;
}, 10, 3);

/**
 * Adjust found_posts when using custom offset to fix pagination.
 */
add_filter('found_posts', function ($found_posts, $query) {
    if (isset($query->query_vars['qle_offset_adjustment'])) {
        $offset = (int) $query->query_vars['qle_offset_adjustment'];
        $found_posts = max(0, $found_posts - $offset);
    }
    return $found_posts;
}, 10, 2);

/**
 * Register block attributes.
 * We need to filter the metadata of core/query to add our attribute.
 */
function query_loop_extend_register_attributes($args, $name)
{
    if ('core/query' === $name) {
        if (!isset($args['attributes'])) {
            $args['attributes'] = array();
        }
        $args['attributes']['custom_query_php'] = array(
            'type' => 'string',
            'default' => '',
        );
        // Hidden attribute to transfer processed args
        $args['attributes']['__custom_query_args'] = array(
            'type' => 'object',
            'default' => array(),
        );
        file_put_contents(__DIR__ . '/debug_log.txt', "Registered attribute for core/query.\n", FILE_APPEND);
    }
    return $args;
}
add_filter('register_block_type_args', 'query_loop_extend_register_attributes', 20, 2);
