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

// Helper function to transform values
function transform_data($data) {
    if (is_array($data)) {
        // Transform each item in the array
        return array_map(function($item) {
            return strtolower(str_replace('_', '-', $item));
        }, $data);
    } elseif (is_string($data)) {
        // Transform a single string value
        return strtolower(str_replace('_', '-', $data));
    }
    return $data; // Return as-is for unsupported types
}

// Helper: Fetch term IDs for given taxonomies
function match_terms_and_get_ids($transformed_data, $taxonomies_map) {
    $matched_terms = [];

    foreach ($taxonomies_map as $data_key => $taxonomy_name) {
        if (taxonomy_exists($taxonomy_name)) {
            // Fetch terms for the taxonomy
            $terms = get_terms(['taxonomy' => $taxonomy_name, 'hide_empty' => false]);

            if (!is_wp_error($terms) && !empty($terms)) {
                // Extract the corresponding transformed_data key
                $data_key_cleaned = str_replace('_tax_name', '', $data_key);

                // Check if the cleaned key exists in transformed_data
                if (isset($transformed_data[$data_key_cleaned])) {
                    // Ensure the transformed data is an array
                    $values_to_check = (array) $transformed_data[$data_key_cleaned];

                    foreach ($terms as $term) {
                        // Match values to term slug
                        foreach ($values_to_check as $value) {
                            if ($value === $term->slug) {
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
function assign_terms_to_post($post_id, $terms_data, $taxonomies_map) {
    foreach ($terms_data as $taxonomy_key => $term_ids) {
        $taxonomy_name = $taxonomies_map[$taxonomy_key] ?? $taxonomy_key;

        if (!empty($term_ids)) {
            wp_set_post_terms($post_id, $term_ids, $taxonomy_name, true);
        }
    }
}