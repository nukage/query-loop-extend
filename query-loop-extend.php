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

// Use a global to store args between the data filter and the query filter.
// Using a simple global instead of a class for simplicity in this functional file.
global $query_loop_extend_custom_args;
$query_loop_extend_custom_args = null;

add_filter('render_block_data', function ($parsed_block, $source_block, $parent_block) {
    global $query_loop_extend_custom_args;

    // Reset for every block to prevent leaking
    // Actually, we should only reset if we are about to process a core/query block
    if ($parsed_block['blockName'] === 'core/query') {
        $query_loop_extend_custom_args = null;

        if (isset($parsed_block['attrs']['custom_query_php']) && !empty($parsed_block['attrs']['custom_query_php'])) {
            $custom_php = $parsed_block['attrs']['custom_query_php'];
            $query_args = isset($parsed_block['attrs']['query']) ? $parsed_block['attrs']['query'] : array();

            $closure = function () use ($custom_php, $query_args) {
                // Alias for user convenience
                $query = $query_args;
                try {
                    $code = trim($custom_php);
                    if (substr($code, 0, 5) === '<?php') {
                        $code = substr($code, 5);
                    }
                    if (substr($code, -2) === '?>') {
                        $code = substr($code, 0, -2);
                    }
                    $result = eval ($code);
                    if (is_array($result)) {
                        return array_merge($query_args, $result); // Return merged to capture user intent
                    }
                } catch (Throwable $e) {
                }
                return $query_args;
            };

            // Store result in global
            $query_loop_extend_custom_args = $closure();

            // Log successful calculation
            file_put_contents(__DIR__ . '/debug_log.txt', "Global - Calculated: " . print_r($query_loop_extend_custom_args, true) . "\n", FILE_APPEND);
        }
    }

    return $parsed_block;
}, 20, 3);

/**
 * Apply the custom args to the actual WP_Query.
 */
add_filter('query_loop_block_query_vars', function ($query_args, $block, $page) {
    global $query_loop_extend_custom_args;

    if (!empty($query_loop_extend_custom_args)) {
        // Merge our global custom args into the WP Query args
        // We assume the global strictly corresponds to the "current" block because render_block happens sequentially.

        // Verify we are indeed in a query loop block context (redundant but safe)
        // Note: $block is the WP_Block instance.

        $custom = $query_loop_extend_custom_args;
        if (is_array($custom)) {
            $query_args = array_merge($query_args, $custom);

            file_put_contents(__DIR__ . '/debug_log.txt', "Global - Applied Data: " . print_r($custom, true) . "\n", FILE_APPEND);
        }

        // Clear it after use to ensure it doesn't affect nested or subsequent blocks erroneously
        //$query_loop_extend_custom_args = null; 
    }
    return $query_args;
}, 10, 3);

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
