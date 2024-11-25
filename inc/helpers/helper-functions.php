<?php

/**
 * Helper Functions
 * 
 * @package WP Plugin Boilerplate
 */

function put_program_logs( $data ) {

    // Ensure the directory for logs exists
    $directory = PLUGIN_BASE_PATH . '/program_logs/';
    if ( !file_exists( $directory ) ) {
        mkdir( $directory, 0777, true );
    }

    // Construct the log file path
    $file_name = $directory . 'program_logs.log';

    // Append the current datetime to the log entry
    $current_datetime = date( 'Y-m-d H:i:s' );
    $data             = $data . ' - ' . $current_datetime;

    // Write the log entry to the file
    if ( file_put_contents( $file_name, $data . "\n\n", FILE_APPEND | LOCK_EX ) !== false ) {
        return "Data appended to file successfully.";
    } else {
        return "Failed to append data to file.";
    }
}

function set_property_gallery_images( $product_id, $images ) {
    if ( !empty( $images ) && is_array( $images ) ) {
        foreach ( $images as $image ) {

            // Extract image name
            $image_name = basename( $image );

            // Get WordPress upload directory
            $upload_dir = wp_upload_dir();

            // Download the image from URL and save it to the upload directory
            $image_data = file_get_contents( $image );

            if ( $image_data !== false ) {
                $image_file = $upload_dir['path'] . '/' . $image_name;
                file_put_contents( $image_file, $image_data );

                // Prepare image data to be attached to the product
                $file_path = $upload_dir['path'] . '/' . $image_name;
                $file_name = basename( $file_path );

                // Insert the image as an attachment
                $attachment = [
                    'post_mime_type' => mime_content_type( $file_path ),
                    'post_title'     => preg_replace( '/\.[^.]+$/', '', $file_name ),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                ];

                $attach_id = wp_insert_attachment( $attachment, $file_path, $product_id );

                // Add the image to the product gallery
                $gallery_ids   = get_post_meta( $product_id, 'gallery', true );
                $gallery_ids   = explode( ',', $gallery_ids );
                $gallery_ids[] = $attach_id;
                update_post_meta( $product_id, 'gallery', implode( ',', $gallery_ids ) );

                // Set the image as the product thumbnail
                set_post_thumbnail( $product_id, $attach_id );

                // if not set post-thumbnail then set a random thumbnail from gallery
                if ( !has_post_thumbnail( $product_id ) ) {
                    if ( !empty( $gallery_ids ) ) {
                        $random_attach_id = $gallery_ids[array_rand( $gallery_ids )];
                        set_post_thumbnail( $product_id, $random_attach_id );
                    }
                }

            }
        }
    }
}