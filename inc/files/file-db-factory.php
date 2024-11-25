<?php

use Illuminate\Database\Capsule\Manager as DB;

function get_posts_via_query_builder() {

    // Fetch all published posts
    $posts = DB::table( 'posts' )
        ->where( 'post_status', 'publish' )
        ->where( 'post_type', 'post' )
        ->get();

    foreach ( $posts as $post ) {
        echo $post->post_title . "\n";
    }
}
