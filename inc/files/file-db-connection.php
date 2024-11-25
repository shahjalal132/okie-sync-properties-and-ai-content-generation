<?php

require PLUGIN_BASE_PATH . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

global $wpdb;

// Initialize Eloquent
$capsule = new Capsule;

// Configure the database connection
$capsule->addConnection( [
    'driver'    => 'mysql',
    'host'      => DB_HOST,      // WordPress database host
    'database'  => DB_NAME,      // WordPress database name
    'username'  => DB_USER,      // WordPress database username
    'password'  => DB_PASSWORD,  // WordPress database password
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => $wpdb->prefix, // WordPress table prefix
] );

// Set the global Eloquent instance
$capsule->setAsGlobal();
$capsule->bootEloquent();
