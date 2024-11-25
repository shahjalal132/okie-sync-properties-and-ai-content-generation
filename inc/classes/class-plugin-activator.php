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
            name VARCHAR(255) NULL,
            location VARCHAR(300) NULL,
            building_type VARCHAR(255) NULL,
            number_of_rooms INT NULL,
            max_price_per_room VARCHAR(255) NULL,
            sda_design_category VARCHAR(255) NULL,
            booked_status VARCHAR(255) NULL,
            vacancy INT NULL,
            has_fire_sprinklers INT NULL,
            has_breakout_room INT NULL,
            onsite_overnight_assistance INT NULL,
            email VARCHAR(255) NULL,
            phone VARCHAR(255) NULL,
            website1 VARCHAR(255) NULL,
            website2 VARCHAR(255) NULL,
            website3 VARCHAR(255) NULL,
            website4 VARCHAR(255) NULL,
            website5 VARCHAR(255) NULL,
            image_urls TEXT NULL,
            long_description TEXT NULL,
            website_url VARCHAR(255) UNIQUE NOT NULL,
            property_data LONGTEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            is_synced VARCHAR(30) NOT NULL DEFAULT 'no',
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
            name VARCHAR(300) NULL,
            location VARCHAR(255) NULL,
            building_type VARCHAR(255) NULL,
            number_of_rooms INT NULL,
            max_price_per_room VARCHAR(255) NULL,
            sda_design_category VARCHAR(255) NULL,
            booked_status VARCHAR(255) NULL,
            vacancy INT NULL,
            has_fire_sprinklers INT NULL,
            has_breakout_room INT NULL,
            onsite_overnight_assistance INT NULL,
            email VARCHAR(255) NULL,
            phone VARCHAR(255) NULL,
            website1 VARCHAR(255) NULL,
            website2 VARCHAR(255) NULL,
            website3 VARCHAR(255) NULL,
            website4 VARCHAR(255) NULL,
            website5 VARCHAR(255) NULL,
            website_url VARCHAR(300) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
         ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

}