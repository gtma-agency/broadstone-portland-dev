<?php

// flush rewrite rules for custom post types
function rentpress_flush_rewrite_rules()
{
    // if the plugin has been recently activated, flush the rules and remove the flag
    if (get_option('rentpress_flush_rewrite_rules_flag')) {
        flush_rewrite_rules();
        delete_option('rentpress_flush_rewrite_rules_flag');
    }
}
add_action('init', 'rentpress_flush_rewrite_rules', 20);
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

// recreate database if plugin is out of date
function rentpress_updateDatabaseTablesAfterPluginVersionChange()
{
    // if the plugin database versions do not match the current plugin version, then reactivate the plugin
    if (get_option('rentpress_plugin_version') !== RENTPRESS_PLUGIN_VERSION) {
        // update plugin version so that this doesn't run again
        update_option('rentpress_plugin_version', RENTPRESS_PLUGIN_VERSION);

        // run install scripts to make new DB and register post types
        require_once RENTPRESS_PLUGIN_ADMIN_DIR . 'admin_install.php';

        // remove the crons in case they were updated as well
        if (wp_next_scheduled('rentpress_cron_hook_start_incremental_data_sync')) {
            wp_clear_scheduled_hook('rentpress_cron_hook_start_incremental_data_sync');
        }
        if (wp_next_scheduled('rentpress_cron_hook_marketing_sync')) {
            wp_clear_scheduled_hook('rentpress_cron_hook_marketing_sync');
        }
        if (wp_next_scheduled('rentpress_cron_hook_pricing_sync')) {
            wp_clear_scheduled_hook('rentpress_cron_hook_pricing_sync');
        }
    }
}
add_action('init', 'rentpress_updateDatabaseTablesAfterPluginVersionChange', 20);

function func_load_admin_cpt_styles_and_scripts()
{
    wp_register_script('admin_cpt_js', RENTPRESS_PLUGIN_ADMIN_SCRIPTS_DIR . 'admin_cpt.js');
    wp_register_style('admin_cpt_styles', RENTPRESS_PLUGIN_ADMIN_STYLES_DIR . 'admin_cpt.css');
    wp_register_style('admin_icons', RENTPRESS_PLUGIN_ADMIN_STYLES_DIR . 'fontawesome.css');
    wp_enqueue_script('admin_cpt_js');
    wp_enqueue_style('admin_cpt_styles');
    wp_enqueue_style('admin_icons');
}

// make sure these files only run on admin pages
if (is_admin()) {
    require_once RENTPRESS_PLUGIN_ADMIN_VIEW_MENU_DIR . 'rentpress_sync_options.php';
    require_once RENTPRESS_PLUGIN_ADMIN_DIR . 'admin_ajax.php';
    //Tell WordPress to register the scripts
    add_action('admin_enqueue_scripts', 'func_load_admin_cpt_styles_and_scripts');
} else {
    // disable shortcode render in admin
    require_once RENTPRESS_PLUGIN_PUBLIC_SHORTCODES . 'rentpress_shortcodes.php';
}

// files that always need to run
require_once RENTPRESS_PLUGIN_ADMIN_DIR . 'admin_crons.php';
require_once RENTPRESS_PLUGIN_ADMIN_POSTS . 'register_rentpress_posts.php';
require_once RENTPRESS_PLUGIN_ADMIN_DIR . 'taxonomy/register_rentpress_taxonomies.php';
rentpress_registerAllPostTypes();

function rentpress_removeDBRowForDeletedPost($postid, $post)
{
    if ($post->post_type === 'rentpress_property') {
        require_once RENTPRESS_PLUGIN_DATA_ACCESS . 'data_layer.php';
        rentpress_deleteRowRowFromTableWithPostID('rentpress_properties', 'property_post_id', $postid);
    }

    if ($post->post_type === 'rentpress_floorplan') {
        require_once RENTPRESS_PLUGIN_DATA_ACCESS . 'data_layer.php';
        rentpress_deleteRowRowFromTableWithPostID('rentpress_floorplans', 'floorplan_post_id', $postid);
    }

    if ($post->post_type === 'rentpress_hood') {
        // do neighborhood specific stuff
    }
}
add_action('before_delete_post', 'rentpress_removeDBRowForDeletedPost', 9, 2);

// actions for rentpress add on's

