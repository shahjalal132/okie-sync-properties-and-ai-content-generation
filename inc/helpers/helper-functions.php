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
            // Extract clean image name
            $image_name = basename( parse_url( $image, PHP_URL_PATH ) );

            // Get WordPress upload directory
            $upload_dir = wp_upload_dir();

            // Ensure the upload directory exists
            if ( !file_exists( $upload_dir['path'] ) ) {
                wp_mkdir_p( $upload_dir['path'] );
            }

            // Download the image from URL and save it to the upload directory
            $image_data = @file_get_contents( $image ); // Use @ to suppress warnings

            if ( $image_data === false ) {
                error_log( "Failed to download image: {$image}" );
                continue; // Skip to the next image
            }

            $image_file = $upload_dir['path'] . '/' . $image_name;
            $saved      = @file_put_contents( $image_file, $image_data ); // Use @ to suppress warnings

            if ( $saved === false ) {
                error_log( "Failed to save image: {$image_file}" );
                continue; // Skip to the next image
            }

            // Prepare image data to be attached to the product
            $file_path = $image_file;
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
            $gallery_ids   = !empty( $gallery_ids ) ? explode( ',', $gallery_ids ) : [];
            $gallery_ids[] = $attach_id;
            update_post_meta( $product_id, 'gallery', implode( ',', $gallery_ids ) );

            // Set the image as the product thumbnail
            if ( !has_post_thumbnail( $product_id ) ) {
                set_post_thumbnail( $product_id, $attach_id );
            }
        }
    }
}

// Function to set product images with unique image names
function set_product_images_with_unique_image_name( $product_id, $images ) {
    if ( !empty( $images ) && is_array( $images ) ) {

        $first_image = true;
        $gallery_ids = get_post_meta( $product_id, '_product_image_gallery', true );
        $gallery_ids = !empty( $gallery_ids ) ? explode( ',', $gallery_ids ) : [];

        foreach ( $images as $image_url ) {
            // Extract image name and generate a unique name using product_id
            $image_name        = basename( parse_url( $image_url, PHP_URL_PATH ) );
            $unique_image_name = $product_id . '-' . time() . '-' . $image_name;

            // Get WordPress upload directory
            $upload_dir = wp_upload_dir();

            // Download the image from URL and save it to the upload directory
            $image_data = file_get_contents( $image_url );

            if ( $image_data !== false ) {
                $image_file = $upload_dir['path'] . '/' . $unique_image_name;
                file_put_contents( $image_file, $image_data );

                // Prepare image data to be attached to the product
                $file_path = $upload_dir['path'] . '/' . $unique_image_name;
                $file_name = basename( $file_path );

                // Insert the image as an attachment
                $attachment = [
                    'post_mime_type' => mime_content_type( $file_path ),
                    'post_title'     => preg_replace( '/\.[^.]+$/', '', $file_name ),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                ];

                $attach_id = wp_insert_attachment( $attachment, $file_path, $product_id );

                // You need to generate the attachment metadata and update the attachment
                require_once( ABSPATH . 'wp-admin/includes/image.php' );
                $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
                wp_update_attachment_metadata( $attach_id, $attach_data );

                // Add the image to the product gallery
                $gallery_ids[] = $attach_id;

                // Set the first image as the featured image
                if ( $first_image ) {
                    set_post_thumbnail( $product_id, $attach_id );
                    $first_image = false;
                }
            }
        }

        // Update the product gallery meta field
        update_post_meta( $product_id, '_product_image_gallery', implode( ',', $gallery_ids ) );
    }
}


// Helper function to transform values
function transform_data( $data ) {
    if ( is_array( $data ) ) {
        // Transform each item in the array
        return array_map( function ($item) {
            return strtolower( str_replace( '_', '-', $item ) );
        }, $data );
    } elseif ( is_string( $data ) ) {
        // Transform a single string value
        return strtolower( str_replace( '_', '-', $data ) );
    }
    return $data; // Return as-is for unsupported types
}

// Helper: Fetch term IDs for given taxonomies
function match_terms_and_get_ids( $transformed_data, $taxonomies_map ) {
    $matched_terms = [];

    foreach ( $taxonomies_map as $data_key => $taxonomy_name ) {
        if ( taxonomy_exists( $taxonomy_name ) ) {
            // Fetch terms for the taxonomy
            $terms = get_terms( [ 'taxonomy' => $taxonomy_name, 'hide_empty' => false ] );

            if ( !is_wp_error( $terms ) && !empty( $terms ) ) {
                // Extract the corresponding transformed_data key
                $data_key_cleaned = str_replace( '_tax_name', '', $data_key );

                // Check if the cleaned key exists in transformed_data
                if ( isset( $transformed_data[$data_key_cleaned] ) ) {
                    // Ensure the transformed data is an array
                    $values_to_check = (array) $transformed_data[$data_key_cleaned];

                    foreach ( $terms as $term ) {
                        // Match values to term slug
                        foreach ( $values_to_check as $value ) {
                            if ( $value === $term->slug ) {
                                $matched_terms[$taxonomy_name][] = $term->term_id;
                            }
                        }
                    }
                }
            }
        }
    }

    return $matched_terms;
}

// Helper: Assign terms to a post
function assign_terms_to_post( $post_id, $terms_data, $taxonomies_map ) {
    foreach ( $terms_data as $taxonomy_key => $term_ids ) {
        $taxonomy_name = $taxonomies_map[$taxonomy_key] ?? $taxonomy_key;

        if ( !empty( $term_ids ) ) {
            wp_set_post_terms( $post_id, $term_ids, $taxonomy_name, true );
        }
    }
}