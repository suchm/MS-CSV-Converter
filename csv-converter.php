<?php
/*
Plugin Name: MS CSV Converter
Description: Converts a CSV file into an HTML table
Shortcode: [csv_converter class="" url="" header="" group=""]
*/

/**
 * Shortcode to convert CSV data into HTML
 * @param array $atts attributes for the CSV Converter shortcode.
 *
 * @return html data.
 */
function csv_converter_shortcode( $atts ) {

    // Enqueue files
    enqueue_csv_converter_assets();

    // Add WP_CLI command - can be added to cron job to automatically update file
    WP_CLI::add_command( 'csv_update', 'update_csv_file' );

    // add a class to the table element
    $class  = isset( $atts['class'] ) ? $atts['class'] : 'csv-converter';
    // url for the .csv file
    $url    = isset( $atts['url'] ) ? $atts['url'] : false;
    // if header="true" then add th cells and titles class to the row
    $header = isset( $atts['header'] ) && $atts['header'] == "false" ? false : true;
    // if groups="true" then add group class to the row
    $group  = isset( $atts['group'] ) && $atts['group'] == "true" ? true : false;

    // Set transient name
    $option_name = 'csv_' . pathinfo( basename( $url ), PATHINFO_FILENAME );
    $csv_data    = get_option( $option_name ) ? get_option( $option_name ) : false;

    // get the csv data if previously saved
    if ( $csv_data && $csv_data['url'] === $url ) {
        return $csv_data['output'];
    } else {
        $option_exists = $csv_data ? true : false;
        return convert_csv_to_html( $option_name, $url, $header, $group, $class, $option_exists );
    }

}
// Add Shortcode
add_shortcode( 'csv_converter', 'csv_converter_shortcode' );

// Enqueue styles
function enqueue_csv_converter_assets() {
    wp_enqueue_style( 'csv-converter-css', get_template_directory_uri() . '/css/style', false, '1.0' );
}

// Remove http references from url
function remove_http($url) {
   $disallowed = array('http://', 'https://');
   foreach($disallowed as $d) {
      if(strpos($url, $d) === 0) {
         return str_replace($d, '', $url);
      }
   }
   return $url;
}

/**
 * Convert CSV data into HTML
 * @param string  $$option_name
 * @param string  $url          of the csv file.
 * @param boolean $header       if true format a header row.
 * @param boolean $group        if true format a group row.
 * @param string  $class        of the table
 *
 * @return html data.
 */
function convert_csv_to_html( $option_name, $url, $header, $group, $class_name, $option_exists ) {

    // This is for mac formated csv files
    ini_set( "auto_detect_line_endings", true );

    // Swap the url with the file path as fopen() can be funny opening the url
    $wp_upload_dir  = wp_upload_dir();
    $base_dir       = $wp_upload_dir['basedir'];
    $upload_segment = $_SERVER[HTTP_HOST] . '/' . substr($base_dir, strpos($base_dir, 'wp-content'));
    $file_path      = remove_http($url);
    $file_path      = str_replace($upload_segment, $base_dir, $file_path);
    $handle         = fopen( $file_path, "r" );

    if ( $handle ) {
        $data_array         = array();
        $group_array        = array();
        $titles_array       = array();
        $values_array       = array();
        $break_array        = array();
        $header_names       = array();
        $csv_class          = $class_name ? ' ' . $class_name : '';
        $is_header          = true;
        $is_group           = true;
        $count              = 0;
        $group_count        = 0;
        $title_count        = 0;
        $value_count        = 0;
        $break_count        = 0;
        $increment_value    = 0;
        $output             = '';
        $last_updated       = false;
        $last_updated_check = true;

        while ( $csvcontents = fgetcsv( $handle ) ) {
            // Check if it's an empty row
            if ( count( array_flip( $csvcontents ) ) == 1 ) {
                $is_header = true;
                $is_group = true;
                $type = 'break';

            } else if ( $header && $is_header ) {
                $is_header = false;
                $type = 'titles';

            } else if ( $group && $is_group ) {
                $is_group = false;
                $type = 'group';

            } else {
                $type = 'values';
            }

            foreach ( $csvcontents as $column ) {
                // Check if value contains last updated
                if ( $last_updated_check ) {
                    $last_updated_lower = strtolower( $column );
                    if ( strpos( $last_updated_lower, 'last update') !== false ) {
                        $last_updated = $column;
                        $last_updated_check = false;
                        break;
                    }
                }

                // Create the class name
                if ( $type === 'titles' ) {
                    $titles_array[] = $column;
                    $title_count++;
                }
                if ( $type === 'group' ) {
                    if ( $group_count < $title_count ) {
                        $group_array[] = $column;
                        $group_count++;
                    }
                    if ( $group_count === $title_count ) {
                        $group_count = 0;
                    }
                }
                if ( $type === 'values' ) {
                    $values_array[$increment_value][] = $column;
                    $value_count++;
                    if ( $value_count === $title_count ) {
                        $increment_value++;
                        $value_count = 0;
                    }
                }
                if ( $type === 'break' ) {
                    if ( $break_count < $title_count ) {
                        $break_array[] = $column;
                        $break_count++;
                    } else {
                        $break_count = 0;
                    }
                } 
            }

            if ( $type === 'titles' ) $data_array[$count]['titles'] = $titles_array;
            if ( $type === 'group' ) $data_array[$count]['group'] = $group_array;
            if ( $type === 'values' ) $data_array[$count]['values'] = $values_array;
            if ( $type === 'break' ) {
                $data_array[$count]['break'] = $break_array;
                $titles_array = array();
                $group_array = array();
                $values_array = array();
                $break_array = array();
                $title_count = 0;
                $count++;
            }
        }
    }
    fclose( $handle );

    //Desktop view
    $groupIndex = 0;
    $output .= $last_updated ? sprintf( '<p class="last-updated">%s</p>', $last_updated ) : '';
    $output .= sprintf( '<table class="desktop%s">', $csv_class );
    $output .= '<tbody>';

    foreach ( $data_array as $value ) {
        if( $value['titles'] ) {
            $groupIndex++;
            $column_count = 0;

            $output .= '<thead>';
            $output .= sprintf( '<tr class="%s group-%d">', 'titles', $groupIndex );
            foreach ( $value['titles'] as $t ) {
                $header_names[$column_count] = $t === '' ? '' : str_replace(' ', '-', trim( strtolower( preg_replace( '/[^a-zA-Z\s]/', '', $t ) ) ) );
                $class = $header_names && is_array( $header_names ) ? sprintf( ' class="%s"', $header_names[$column_count] ) : '';
                $column = $t == '' ? '&nbsp;' : $t;
                $output .= sprintf('<th%s>%s</th>', $class, $column);
                $column_count++;

            }
            $output .= '</tr></thead><tbody>';
        }
        if( $value['group'] ) { 
            $column_count = 0;
            $output .= sprintf( '<tr class="%s group-%d">', 'group', $groupIndex );
            foreach ( $value['group'] as $g ) {
                $class  = $header_names && is_array( $header_names ) ? sprintf( ' class="%s"', $header_names[$column_count] ) : '';
                $column = $g == '' ? '&nbsp;' : $g;
                $output .= sprintf('<td%s>%s</td>', $class, $column);
                $column_count++;

            }
            $output .= '</tr>';
        }
        if( $value['values'] ) { 
            $row_count = 0;
            foreach ( $value['values'] as $values ) {
                if ( ! empty( $values ) && is_array( $values ) ) {
                    $column_count = 0;
                    $oddClass = $row_count % 2 != 0 ? ' odd' : '';
                    $output .= sprintf( '<tr class="%s%s group-%d">', 'companies', $oddClass, $groupIndex );
                    foreach ( $values as $v ) {
                        $class  = $header_names && is_array( $header_names ) ? sprintf( ' class="%s"', $header_names[$column_count] ) : '';
                        $column = $v == '' ? '&nbsp;' : $v;
                        $output .= sprintf('<td%s>%s</td>', $class, $column);
                        $column_count++;
                    }
                    $output .= '</tr>';
                    $row_count++;
                }
            }
        }
        if( $value['break'] ) { 
            $column_count = 0;
            $output .= sprintf( '<tr class="%s">', 'no-border-bottom' );
            foreach ( $value['break'] as $b ) {
                $class  = $header_names && is_array( $header_names ) ? sprintf( ' class="%s"', $header_names[$column_count] ) : '';
                $column = $b == '' ? '&nbsp;' : $b;
                $output .= sprintf('<td%s>%s</td>', $class, $column);
                $column_count++;

            }
            $output .= '</tr></tbody>';
        }
    }
    $output .= '</tbody>';
    $output .= '</table>';

    //Mobile view
    foreach ( $data_array as $value ) {
        $output .= sprintf( '<div class="mobile%s">', $csv_class );

        if( $value['group'] ) { 
            $output .= '<h3>' . $value['group'][0] . '<i class="fa fa-caret-down"></i></h3>';
            $output .= '<div class="portfolio-container">';
        }
        if( $value['values'] ) { 
            foreach ( $value['values'] as $values ) {
                $output .= '<table>';
                $output .= '<tbody>';
                if ( ! empty( $values ) && is_array( $values ) ) {
                    $column_count = 0;
                    foreach ( $values as $v ) {
                        if( $value['titles'] ) { 
                            $titles = $value['titles'][$column_count] == '' ? '&nbsp;' : $value['titles'][$column_count];
                            $values = $v == '' ? '&nbsp;' : $v;
                            $class  =  $value['titles'][$column_count] === '' ? '' : str_replace(' ', '-', trim( strtolower( preg_replace( '/[^a-zA-Z\s]/', '', $value['titles'][$column_count] ) ) ) );
                            $oddClass = $column_count % 2 != 0 ? ' odd' : '';
                            $output .= sprintf( '<tr class="%s%s">', $class, $oddClass );
                            $output .= sprintf( '<th>%s</th>', $value['titles'][$column_count] );
                            $output .= sprintf(' <td>%s</td>', $v);
                            $output .= '</tr>';
                            $column_count++;
                        }
                    }
                }
                $output .= '</tbody>';
                $output .= '</table>';
            }
        }
        if( $value['group'] ) { 
            $output .= '</div>';
        }
        $output .= '</div>';
    }

    // If not part of the UTF-8 charset strip out the characters
    $output = iconv( "UTF-8", "ISO-8859-1//IGNORE", $output );

    // All the below data needs to be saved to run the cron job 
    $option_data = array();
    $option_data['time_created'] = time();
    $option_data['url']          = $url;
    $option_data['header']       = $header;
    $option_data['group']        = $group;
    $option_data['class']        = $class_name;
    $option_data['output']       = $output;

    // The update option function should only be run via the cron job so a return value isn't required
    if ( $option_exists ) {
        update_option( $option_name, $option_data );
    } else {
        add_option( $option_name, $option_data );
    }
    return $output;
}

/**
 * WP_CLI command to update the data from csv file
 * @param array  $args
 * @param array  $assoc_args
 */
function update_csv_file( $args, $assoc_args ){

    WP_CLI::log( 'Looking for saved csv data from the options table' );

    global $wpdb;
    $sql = "SELECT `option_name` AS `name`, `option_value` AS `value`
            FROM  $wpdb->options
            WHERE `option_name` LIKE '%csv_%'
            ORDER BY `option_name`";

    $results  = $wpdb->get_results( $sql );

    if ( ! empty( $results ) && is_array( $results ) ) {
        $wp_upload = wp_upload_dir();
        foreach ( $results as $result ) {
            $csv_data           = unserialize( $result->value );
            $file_name          = str_replace( 'csv_', '', $result->name ) . '.csv';
            $file_path          = str_replace( $wp_upload['baseurl'], $wp_upload['basedir'], str_replace( 'www.', '', $csv_data['url'] ) );
            $file_modified_time = filemtime( $file_path ); // time the csv file was modified

            WP_CLI::log( $file_name . ' modified time: ' .  date( 'Y-m-d H:i:s', $file_modified_time ) );

            if ( $file_modified_time > $csv_data['time_created'] ) {
                WP_CLI::log( 'Updating data from ' . $file_path );
                convert_csv_to_html( $result->name, $csv_data['url'], $csv_data['header'], $csv_data['group'], $csv_data['class'], true );
            }
        }
    }
}
?>
