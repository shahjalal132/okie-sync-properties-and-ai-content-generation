<?php

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 */

class Plugin_Deactivator {

    public static function deactivate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sync_properties';
        $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
    }

    public static function remove_csv_file_data_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'sync_csv_file_data';
        $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
    }

}