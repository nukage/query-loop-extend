=== Query Loop Extend ===
Contributors: antigravity
Tags: query, loop, extend, php
Requires at least: 6.0
Tested up to: 6.4
Stable tag: 1.0.0
== Usage ==

1. Insert a "Query Loop" block.
2. In the block inspector (sidebar), look for the "Custom Query" panel.
3. Enter your custom PHP array modification logic.

== Custom PHP Logic ==

The code you enter must return an array of arguments that will be merged into the standard WP_Query arguments.
The variable `$query` contains the current query arguments (including accepted filter values).
The variable `$paged` contains the current page number.

**Simple Example: Change Post Type**
```php
$query['post_type'] = 'my_custom_type';
return $query;
```

**Advanced Example: Dynamic Pagination and Filtering**
```php
// Change posts per page
$query['posts_per_page'] = 5;

// Filter by category
$query['category_name'] = 'news';

// Offset is automatically recalculated if you change posts_per_page!
// No need to manually calculate offsets unless you have specific needs.

return $query;
```

== Features ==
*   **Safety:** Errors in your custom PHP code will be caught and displayed to admins on the frontend, preventing white screens of death.
*   **Pagination Fix:** Automatically fixes pagination offsets when you change `posts_per_page` dynamically.
*   **Convenience:** Maps common variable names like `postType` to `post_type` automatically.
License: GPLv2 or later

Extends the Core Query Loop block with custom PHP query capabilities.

== Description ==

This plugin allows advanced users to define query arguments for the Core Query Loop block using PHP.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/query-loop-extend` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
