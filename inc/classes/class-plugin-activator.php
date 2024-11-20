<?php

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 */

class Plugin_Activator {

    public static function activate() {
        // Create sync users table
        global $wpdb;
        $table_name      = $wpdb->prefix . 'sync_properties';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id INT AUTO_INCREMENT,
            property_id VARCHAR(255) UNIQUE NULL,
            long_description TEXT NULL,
            short_description TEXT NULL,
            short_id VARCHAR(255) NULL,
            provider_short_id VARCHAR(255) NULL,
            website_url VARCHAR(255) UNIQUE NOT NULL,
            property_data JSON NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function create_csv_file_data_table() {
        // Create sync users table
        global $wpdb;
        $table_name      = $wpdb->prefix . 'sync_csv_file_data';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
             id INT AUTO_INCREMENT,
             website_url VARCHAR(300) UNIQUE NOT NULL,
             status VARCHAR(30) NOT NULL DEFAULT 'pending',
             created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
             updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
             PRIMARY KEY (id)
         ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

}