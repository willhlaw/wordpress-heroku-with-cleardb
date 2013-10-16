<?php
if ( !class_exists( 'Simple_Map' ) ) {

	class Simple_Map {
	
		var $plugin_url;
		var $plugin_domain = 'SimpleMap';
		
		// Initialize the plugin
		function Simple_Map() {
			
			$plugin_dir = basename( SIMPLEMAP_PATH );
			load_plugin_textdomain( $this->plugin_domain, 'wp-content/plugins/' . $plugin_dir . '/lang', $plugin_dir . '/lang' );
			
			$this->plugin_url = SIMPLEMAP_URL;
						
			// Add shortcode handler
			add_shortcode( 'simplemap', array( &$this, 'display_map' ) );
						
			// Enqueue frontend scripts & styles into <head>
			add_action( 'template_redirect', array( &$this, 'enqueue_frontend_scripts_styles' ) );
			
			// Enqueue backend scripts
			add_action( 'init', array( &$this, 'enqueue_backend_scripts_styles' ) );

			// Add hook for master js file
			add_action( 'template_redirect', array( &$this, 'google_map_js_script' ) );

			// Add hook for general options js file
			add_action( 'init', array( &$this, 'general_options_js_script' ) );
			
			// Query vars
			add_filter( 'query_vars', array( &$this, 'register_query_vars' ) );

			// Backwards compat for core sm taxonomies
			add_filter( 'sm_category-text', array( &$this, 'backwards_compat_categories_text' ) );
			add_filter( 'sm_tag-text', array( &$this, 'backwards_compat_tags_text' ) );
			add_filter( 'sm_day-text', array( &$this, 'backwards_compat_days_text' ) );
			add_filter( 'sm_time-text', array( &$this, 'backwards_compat_times_text' ) );
            
		}

		// This function generates the code to display the map
		function display_map( $atts ) {

			$options = $this->get_options();

			$atts = $this->parse_shortcode_atts( $atts );

			extract( $atts );

			$to_display = '';

			$to_display .= $this->location_search_form( $atts );

			if ( $powered_by )
				$to_display .= '<div id="powered_by_simplemap">' . sprintf( __( 'Powered by %s SimpleMap', 'SimpleMap' ), '<a href="http://simplemap-plugin.com/" target="_blank">' ) . '</a></div>';

			// Hide map?
			$hidemap = ( $hide_map ) ? "display:none; " : '';

			// Hide list?
			$hidelist = $hide_list ? "display:none; " : '';
			
			// Map Width and height
			$map_width = ( '' == $map_width ) ? $options['map_width'] : $map_width;
			$map_height = ( '' == $map_height ) ? $options['map_height'] : $map_height;

			// Updating Div
			$sm_updating_img_src = apply_filters( 'sm_updating_img_src', SIMPLEMAP_URL . '/inc/images/loading.gif' );
			$sm_updating_div_size = apply_filters( 'sm_updating_img_size', 'height:' . $map_height . ';width:' . $map_width . ';' );
			$to_display .= '<div id="simplemap-updating" style="'. $sm_updating_div_size. '"><img src="' . $sm_updating_img_src . '" alt="' . __( 'Loading new locations', 'SimpleMap' ) . '" /></div>';

			$to_display .= '<div id="simplemap" style="' . $hidemap . 'width: ' . $map_width . '; height: ' . $map_height . ';"></div>';
			$to_display .= '<div id="results" style="' . $hidelist . 'width: ' . $map_width . ';"></div>';
			$to_display .= '<script type="text/javascript">';
			$to_display .= '(function($) { ';

			// Load Locations
			$is_sm_search = isset( $_REQUEST['location_is_search_results'] ) ? 1 : 0;

						
			$do_search_function = '
				load_simplemap( lat, lng, aspid, ascid, asma, shortcode_zoom_level, map_type, shortcode_autoload );
				//searchLocations( ' . absint( $is_sm_search ) . ' );
			';

			$to_display .= '$(document).ready(function() {
				var lat = "' . esc_js( $default_lat ) . '";
				var lng = "' . esc_js( $default_lng ) . '";
				var aspid = "' . esc_js( $adsense_publisher_id ) . '";
				var ascid = "' . esc_js( $adsense_channel_id ) . '";
				var asma = "' . esc_js( $adsense_max_ads ) . '";
				var shortcode_zoom_level = "' . esc_js( $zoom_level ) . '";
				var map_type = "' . esc_js( $map_type ) . '";
				var shortcode_autoload = "' . esc_js( $autoload ) . '";
				var auto_locate = "' . esc_js( $options['auto_locate'] ) . '";
				var sm_autolocate_complete = false;
				geocoder = new google.maps.Geocoder();

				if ( !' . absint( $is_sm_search ) . ' && auto_locate == "ip" ) {
					jQuery.getJSON( "http://freegeoip.net/json/?callback=?", function(location) {
						lat = location.latitude;
						lng = location.longitude;

                    	if ( document.getElementById("location_search_city_field") ) {
							document.getElementById("location_search_city_field").value = location.city;
						}
	                    if ( document.getElementById("location_search_country_field") ) {
							document.getElementById("location_search_country_field").value = location.country_code;
						}
        	            if ( document.getElementById("location_search_state_field") ) {
							document.getElementById("location_search_state_field").value = location.region_code;
						}
                	    if ( document.getElementById("location_search_zip_field") ) {
							document.getElementById("location_search_zip_field").value = location.zipcode;
						}
						if ( document.getElementById("location_search_default_lat" ) ) {
							document.getElementById("location_search_default_lat").value = lat;
						}
						if ( document.getElementById("location_search_default_lng" ) ) {
							document.getElementById("location_search_default_lng").value = lng;
						}
						' . $do_search_function . '
						searchLocations( 1 );
					}).error(function() {
					 	' . $do_search_function . '
						searchLocations( ' . absint( $is_sm_search ) . ' );
					});
				}
				else if ( !' . absint( $is_sm_search ) . ' && auto_locate == "html5" ) {
					// Ugly hack for FireFox "Not Now" option
					setTimeout(function () { 
						if ( sm_autolocate_complete == false ) {
							' . $do_search_function . ' searchLocations( 0 );
						}
					}, 10000);

					navigator.geolocation.getCurrentPosition(
						function(position) {
							lat = position.coords.latitude;
							lng = position.coords.longitude;
							if ( document.getElementById("location_search_default_lat" ) ) {
								document.getElementById("location_search_default_lat").value = lat;
							}
							if ( document.getElementById("location_search_default_lng" ) ) {
								document.getElementById("location_search_default_lng").value = lng;
							}
							sm_autolocate_complete = true;
							' . $do_search_function . '
							searchLocations( 1 );
						},
						function(error) {
							sm_autolocate_complete = true;
							' . $do_search_function . '
							searchLocations( ' . absint( $is_sm_search ) . ' );
						},
						{
							maximumAge:300000,
							timeout:5000
						}
					);
				}
				else {
					sm_autolocate_complete = true;
					' . $do_search_function . '
					searchLocations( ' . absint( $is_sm_search ) . ' );
				}
			';

			$to_display .= '});';
			$to_display .= '})(jQuery);';
			$to_display .= '</script>';

			return apply_filters( 'sm-display-map', $to_display, $atts );
		}

		// This function returns the location search form
		function location_search_form( $atts ) {
			global $post;

			// Grab default SimpleMap options
			$options = $this->get_options();

			// Merge default simplemap options with default shortcode options and provided shortcode options
			$atts = $this->parse_shortcode_atts( $atts );

			// Create individual vars for each att
			extract( $atts );

			// Array of the names for all taxonomies registered with sm-location post type
			$sm_tax_names = get_object_taxonomies( 'sm-location' );

			// Array of field names for this form (with label syntax stripped
			$form_field_names = $this->get_form_field_names_from_shortcode_atts( $search_fields );

			// Form onsubmit, action, and method values
			$on_submit = apply_filters( 'sm-location-search-onsubmit', ' onsubmit="searchLocations( 1 ); return false; "', $post->ID );
			$action = apply_filters( 'sm-locaiton-search-action', get_permalink(), $post->ID );
			$method = apply_filters( 'sm-location-search-method', 'post', $post->ID );			

			// Form Field Values
			$address_value		= get_query_var( 'location_search_address' );
			$city_value 		= isset( $_REQUEST['location_search_city'] ) ? $_REQUEST['location_search_city'] : '';
			$state_value 		= isset( $_REQUEST['location_search_state'] ) ? $_REQUEST['location_search_state'] : '';
			$zip_value 			= get_query_var( 'location_search_zip' );
			$country_value 		= get_query_var( 'location_search_country' );
			$radius_value	 	= isset( $_REQUEST['location_search_distance'] ) ? $_REQUEST['location_search_distance'] : $radius;
			$limit_value		= isset( $_REQUEST['location_search_limit'] ) ? $_REQUEST['location_search_limit'] : $limit;
			$is_sm_search		= isset( $_REQUEST['location_is_search_results'] ) ? 1 : 0;

			// Normal Field inputs
			$ffi['street']		= array( 'label' => apply_filters( 'sm-search-label-street', __( 'Street: ', 'SimpleMap' ), $post ), 'input' => '<input type="text" id="location_search_address_field" name="location_search_address" value="' . esc_attr( $address_value ) . '" />' );
			$ffi['city']		= array( 'label' => apply_filters( 'sm-search-label-city', __( 'City: ', 'SimpleMap' ), $post ), 'input' => '<input type="text"  id="location_search_city_field" name="location_search_city" value="' . esc_attr( $city_value ) . '" />' );
			$ffi['state']		= array( 'label' => apply_filters( 'sm-search-label-state', __( 'State: ', 'SimpleMap' ), $post ), 'input' => '<input type="text" id="location_search_state_field" name="location_search_state" value="' . esc_attr( $state_value ) . '" />' );
			$ffi['zip']			= array( 'label' => apply_filters( 'sm-search-label-zip', __( 'Zip: ', 'SimpleMap' ), $post ), 'input' => '<input type="text" id="location_search_zip_field" name="location_search_zip" value="' . esc_attr( $zip_value ) . '" />' );
			$ffi['country']		= array( 'label' => apply_filters( 'sm-search-label-country', __( 'Country: ', 'SimpleMap' ), $post ), 'input' => '<input type="text" id="location_search_country_field" name="location_search_country" value="' . esc_attr( $country_value ) . '" />' );
			$ffi['empty']		= array( 'label' => '', 'input' => '' );
			$ffi['submit']		= array( 'label' => '', 'input' => '<input type="submit" value="' . apply_filters( 'sm-search-label-search', __('Search', 'SimpleMap'), $post ) . '" id="location_search_submit_field" class="submit" />' );
			$ffi['distance']	= $this->add_distance_field( $radius_value, $units );

			$hidden_fields = array();

			// Visible Taxonomy Field Inputs
			foreach ( $sm_tax_names as $tax_name ) {
				if ( in_array( $tax_name, $form_field_names ) && $this->show_taxonomy_filter( $atts, $tax_name ) )
					$ffi[$tax_name] = $this->add_taxonomy_fields( $atts, $tax_name );
				else
					$hidden_fields[] = '<input type="hidden" name="location_search_' . str_replace( '-', '_', $tax_name ) . '_field" value="1" />';
			}

			// More Taxonomy Fields
			foreach ( $sm_tax_names as $tax_name ) {
				$hidden_fields[] = '<input type="hidden" id="avail_' . str_replace( '-', '_', $tax_name ) . '" value="' . $atts[str_replace( '-', '_', $tax_name )] . '" />';
			}

			// Hide search?
			$hidesearch = $hide_search ? " style='display:none;' " : '';

			$location_search  = '<div id="map_search" >';
			$location_search .= '<a id="map_top"></a>';
			$location_search .= '<form ' . $on_submit . ' name="location_search_form" id="location_search_form" action="' . $action . '" method="' . $method . '">';

			$location_search .= '<table class="location_search"' . $hidesearch . '>';

			$location_search .= apply_filters( 'sm-location-search-table-top', '', $post );

			$location_search .= '<tr><td colspan="' . $search_form_cols . '" class="location_search_title">' . apply_filters( 'sm-location-search-title', $search_title, $post->ID ) . '</td></tr>';

			// Loop through field inputs and print table
			$search_form_tr = 0;
			$search_form_trs = array();
			$search_field_td = 1;
			$search_fields = explode( '||', $search_fields);

			if ( ! in_array( 'submit', $search_fields ) ) {
				$hidden_fields[] = '<input type="submit" style="display: none;" />';
			}

			foreach ( $search_fields as $field_key => $field_labelvalue ) {
				switch( substr( $field_labelvalue, 0, 8 ) ) {
					case 'labelbr_' :
						$field_label	= true;
						$field_br 		= '<br />';
						$field_value 	= substr( $field_labelvalue, 8 );
						break;

					case 'labelsp_' :
						$field_label	= true;
						$field_br		= '&nbsp';
						$field_value	= substr( $field_labelvalue, 8 );
						break;

					case 'labeltd_' :
						$field_label	= true;
						$field_br		= "</td>\n\t\t<td>";
						$field_value	= substr( $field_labelvalue, 8 );
						break;

					default :
						$field_label	= false;
						$field_br		= '';
						$field_value	= $field_labelvalue;
				}

				// Back compat for class names
				switch ( $field_value ) {
					case 'sm-category' :
						$class_value = 'cat';
						break;
					case 'sm-tag' :
						$class_value = 'tag';
						break;
					case 'sm-day' :
						$class_value = 'day';
						break;
					case 'sm-time' :
						$class_value = 'time';
						break;
					case 'address' :
						$class_value = 'street';
						break;
					default :
						$class_value = $field_value;
				}

				// Print open TR if on column 1
				if ( 1 === $search_field_td ) {
					$search_form_tr_data = "\n\t<tr id='location_search_" . esc_attr( $search_form_tr ) . "_tr' class='location_search_row'>";
					$search_form_tr++;
				}

				// Print field for this position
				if ( 'span' == $field_value ) {
					if ( $tr_data_array = explode( '<td ', $search_form_tr_data ) ) {
						$target_td = end( $tr_data_array );

						end( $tr_data_array );
						$key = key( $tr_data_array );

						if ( 'colspan="' != substr( $target_td, 0, 9 ) ) {
							$tr_data_array[$key] = 'colspan="2" ' . $target_td;
							//echo "<pre>";print_r($tr_data_array);die();
						} else {
							$numcells = substr( $target_td, 9, 1);
							$tr_data_array[$key] = substr_replace( $target_td, $numcells + 1, 9, 1 );
						}

						$search_form_tr_data = implode( '<td ', $tr_data_array );
					}
				} else {
					// The extra column needs to be counted independent of whether the field_value isset so that we don't lose count.
					if ( "</td>\n\t\t<td>" == $field_br ) {
						$search_field_td++;
						$field_br = "</td>\n\t\t<td id='location_search_" . esc_attr( substr( $field_labelvalue, 8 ) ) . "_fields'>";
					}

					if ( isset( $ffi[$field_value] ) && 'empty' != $field_value && 'span' != $field_value ) {
						// If the field_br is a space, we need to wrap the label in a div so that it floats left too.
						if ( '&nbsp' == $field_br ) {
							$ffi[$field_value]['label'] = '<div class="float_text_left">' . $ffi[$field_value]['label'] . '</div>';
						}

						$taxonomy_class = ( isset( $options['taxonomies'][$field_value] ) ? 'location_search_taxonomy_cell' : '' );
						$search_form_tr_data .= "\n\t\t<td class='location_search_" . esc_attr( $class_value ) . "_cell $taxonomy_class location_search_cell'>";

						if ( $field_label ) {
							if ( isset( $ffi[$field_value]['label'] ) ) {
								$search_form_tr_data .= $ffi[$field_value]['label'];
							}

							$search_form_tr_data .= $field_br;
						}

						$search_form_tr_data .= isset( $ffi[$field_value]['input'] ) ? $ffi[$field_value]['input'] . '</td>' : '</td>';
					} else {
						$search_form_tr_data .= "\n\t\t<td class='location_search_empty_cell location_search_cell'></td>";
					}
				}

				// Print close TR if on column 3 or higher (for safety)
				if ( $search_form_cols <= $search_field_td ) {
					$search_form_tr_data .= "\n\t</tr>";
					$search_field_td = 0;

					// Only keep the rows that contain an actionable item.
					if ( strpos( $search_form_tr_data, 'input' ) || strpos( $search_form_tr_data, 'select' ) ) {
						$search_form_trs[] = $search_form_tr_data;
					}
				}

				// Bump search field count
				$search_field_td++;
			}

			// Add table fields
			if ( ! empty( $search_form_trs ) ) {
				$location_search .= implode( ' ', $search_form_trs );
			}

			$location_search .= apply_filters( 'sm-location-search-before-submit', '', $post );

			$location_search .= '</table>';

			// Add hidden fields
			if ( ! empty( $hidden_fields ) ) {
				$location_search .= implode( ' ', $hidden_fields );
			}

			// Lat / Lng
			$location_search .= "<input type='hidden' id='location_search_default_lat' value='" . $default_lat . "' />";
			$location_search .= "<input type='hidden' id='location_search_default_lng' value='" . $default_lng . "' />";

			// Hidden value for limit
			$location_search .= "<input type='hidden' id='location_search_limit' value='" . $limit_value . "' />";

			// Hidden value set to true if we got here via search
			$location_search .= "<input type='hidden' id='location_is_search_results' name='sm-location-search' value='" . $is_sm_search . "' />";

			$location_search .= '</form>';
			$location_search .= '</div>'; // close map_search div

			return apply_filters( 'sm_location_search_form', $location_search, $atts );
		}

		// Separates form field names from label syntax attached to them when submitted via shortcode
		function get_form_field_names_from_shortcode_atts( $fields ) {

			// String to array
			$fields = explode( '||', $fields);

			foreach( $fields as $key => $field ) {

				switch( substr( $field, 0, 8 ) ) {

					case 'labelbr_' :
						$field_names[] 	= substr( $field, 8 );
						break;
					case 'labelsp_' :
						$field_names[]	= substr( $field, 8 );
						break;
					case 'labeltd_' :
						$field_names[]	= substr( $field, 8 );
						break;
					default :
						$field_names[]	= $field;
				}

			}

			return (array) $field_names;

		}

		// Determines if we're supposed to show this taxonomy's filter options in the form
		function show_taxonomy_filter( $atts, $tax_name ) {

			// Convert tax_name to PHP safe equiv
			$php_tax_name = str_replace( '-', '_', $tax_name );

			// Convert Given Taxonomy's 'show filter' into a generic one
			$key = 'show_' . $php_tax_name . '_filter';
			$show_taxes_filter = $atts[$key];

			if ( false != $show_taxes_filter && 'false' != $show_taxes_filter )
				return true;

			return false;

		}

		// Adds Distance field to form
		function add_distance_field( $radius_value, $units ) {
			global $post;

			// Distance
			$distance_input		= '<select id="location_search_distance_field" name="location_search_distance" >';
			foreach ( $this->get_search_radii() as $value ) {
				$r = (int) $value;
				$distance_input .= '<option value="' . $value . '"' . selected( $radius_value, $value, false ) . '>' . $value . ' ' . $units . "</option>\n";
			}
			$distance_input .= '</select>';

			return array( 'label' => apply_filters( 'sm-search-label-distance', __( 'Select a distance: ', 'SimpleMap' ), $post ), 'input' => $distance_input );
		
		}

		// Adds taxonomy fields to search form
		function add_taxonomy_fields( $atts, $taxonomy ) {
			global $post;

			// Get taxonomy object or return empty;
			if ( ! $tax_object = get_taxonomy( $taxonomy ) )
				return '';

			$options = $this->get_options();

			$atts = $this->parse_shortcode_atts( $atts );

			extract( $atts );

			$php_taxonomy = str_replace( '-', '_', $taxonomy );

			// Convert Specific Taxonomy var names and var values to Generic var names and var values
			$taxonomies 		= $atts[$php_taxonomy];
			$tax_hidden_name	= 'avail_' . $php_taxonomy;	
			$show_taxes_filter	= $atts['show_' . $php_taxonomy . '_filter'];
			$tax_field_name		= $php_taxonomy;

			// This originates at the comma separated list of taxonomy ids in the shortcode. ie: sm_category='1,3,5'
			$taxes_avail = $atts[$tax_hidden_name];

			// Place available taxes in array
			$taxes_avail = explode( ',', $taxes_avail );
			$taxes_array = array();

			// Loop through all cats and create array of available cats
			if ( $all_taxes = get_terms( $taxonomy ) ) {

				foreach ( $all_taxes as $key => $value ){
					if ( '' == $taxes_avail[0] || in_array( $value->term_id, $taxes_avail ) ) {
						$taxes_array[] = $value->term_id;
					}
				}

			}

			$taxes_avail = $taxes_array;

			// Show taxes filters if allowed
			$tax_search = '';
			$tax_label  = apply_filters( $php_taxonomy . '-text', __( $tax_object->labels->singular_name . ': ' ), 'SimpleMap' );

			$taxes_array = apply_filters( 'sm-search-from-taxonomies', $taxes_array, $taxonomy );

			if ( 'checkboxes' == $taxonomy_field_type ) {
				// Print checkbox for each available cat
				foreach( $taxes_array as $key => $taxid ) {
					if( $term = get_term_by( 'id', $taxid, $taxonomy ) ) {
						$tax_checked = isset( $_REQUEST['location_search_' . $tax_field_name . '_' . esc_attr( $term->term_id ) . 'field'] ) ? ' checked="checked" ' : '';
						$tax_search .= '<label for="location_search_' . $tax_field_name . '_field_' . esc_attr( $term->term_id ) . '" class="no-linebreak"><input rel="location_search_' . $tax_field_name . '_field" type="checkbox" name="location_search_' . $tax_field_name . '_' . esc_attr( $term->term_id ) . 'field" id="location_search_' . $tax_field_name . '_field_' . esc_attr( $term->term_id ) . '" value="' . esc_attr( $term->term_id ) . '" ' . $tax_checked . '/> ' . esc_attr( $term->name ) . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</label> ';
					}
				}
			} elseif( 'select' == $taxonomy_field_type ) {
				// Print selectbox if that's what we're doing
				$tax_select  = "<select id='location_search_" . esc_attr( $tax_field_name ) . "_select' name='location_search_" . esc_attr( $tax_field_name ) . "_select' >";
				$tax_select .= "<option value=''>" . apply_filters( 'sm-search-tax-select-default', __( 'Select a value', 'SimpleMap' ), $taxonomy ) . "</option>";
				foreach( $taxes_array as $key => $taxid ) {
					if( $term = get_term_by( 'id', $taxid, $taxonomy ) ) {
						$tax_checked = isset( $_REQUEST['location_search_' . esc_attr( $tax_field_name ) . '_select' ] ) ? ' selected="selected" ' : '';
						$tax_select .= '<option rel="location_search_' . esc_attr( $tax_field_name ) . '_select_val"' . ' value="' . esc_attr( $term->term_id ) . '" ' . $tax_checked . '>' . esc_attr( $term->name ) . '</option>';
					}
				}
				$tax_select .= "</select>";

				if ( ! empty( $taxid ) )
					$tax_search .= $tax_select;
			}

			return array( 'label' => $tax_label, 'input' => $tax_search );
		}

		// This function enqueues all the javascript and stylesheets
		function enqueue_frontend_scripts_styles() {
			global $post;
			$options = $this->get_options();

			// Rewrite rules if we've changed the location permalink slug
			if ( get_option( 'sm-rewrite-rules' ) ) {
				global $wp_rewrite;
				$wp_rewrite->flush_rules();
				delete_option( 'sm-rewrite-rules' );
			}

			// Frontend only
			if ( ! is_admin() && is_object( $post ) || apply_filters( 'sm-force-frontend-js', '__return_false' ) ) {
				// Bail if we're not showing on all pages and this isn't a map page
				if ( ! in_array( $post->ID, explode( ',', $options['map_pages'] ) ) && ! in_array( 0, explode( ',', $options['map_pages'] ) ) )
					return false;

				// Check for use of custom stylesheet and load styles
				if ( strstr( $options['map_stylesheet'], 'simplemap-styles' ) )
					$style_url = plugins_url() . '/' . $options['map_stylesheet'];
				else
					$style_url = SIMPLEMAP_URL . '/' . $options['map_stylesheet'];

				// Load styles
				wp_enqueue_style( 'simplemap-map-style', $style_url );

				// Scripts
				wp_enqueue_script( 'simplemap-master-js', get_home_url() . '?simplemap-master-js=1&smpid=' . $post->ID, array( 'jquery' ) );

				// Google API v3 does not need a key
				$url_params = array(
					'sensor' => 'false',
					'v' => '3',
					'language' => $options['default_language'],
					'region' => $options['default_country'],
				);
				if ( $options['adsense_for_maps'] ) {
					$url_params['libraries'] = 'adsense';
				}
				wp_enqueue_script( 'simplemap-google-api', SIMPLEMAP_MAPS_JS_API . http_build_query( $url_params, '', '&amp;' ) );
			}
		}

		// This function enqueues all the javascript and stylesheets
		function enqueue_backend_scripts_styles() {
			// Admin only
			if ( is_admin() ) {
				$options = $this->get_options();
				wp_enqueue_style( 'simplemap-admin', SIMPLEMAP_URL . '/inc/styles/admin.css' );

				// SimpleMap General options
				if ( isset( $_GET['page'] ) && 'simplemap' == $_GET['page'] )
					wp_enqueue_script( 'simplemap-general-options-js', get_home_url() . '/?simplemap-general-options-js', array( 'jquery' ) );

				// Google API v3 does not need a key
				$url_params = array(
					'v' => '3',
					'sensor' => 'false',
					'language' => $options['default_language'],
					'region' => $options['default_country'],
				);
				wp_enqueue_script( 'simplemap-google-api', SIMPLEMAP_MAPS_JS_API . http_build_query( $url_params, '', '&amp;' ) );
			}
		}

		// JS Script for general options page
		function general_options_js_script() {
			if ( ! isset( $_GET['simplemap-general-options-js'] ) )
				return;

			header( 'Status: 200 OK', false, 200 );
			header( 'Content-type: application/x-javascript' );
			do_action( 'sm-master-js-headers' );

			$options = $this->get_options();

			do_action( 'sm-general-options-js' );
			?>
			function codeAddress() {
				// if this is modified, modify mirror function in master-js php function
				var d_address = document.getElementById("default_address").value;

				geocoder = new google.maps.Geocoder();
				geocoder.geocode( { 'address': d_address }, function( results, status ) {
					if ( status == google.maps.GeocoderStatus.OK ) {
						var latlng = results[0].geometry.location;
						document.getElementById("default_lat").value = latlng.lat();
						document.getElementById("default_lng").value = latlng.lng();
					} else {
						alert("Geocode was not successful for the following reason: " + status);
					}
				});
			}
			<?php
			die();
		}

		// This function prints the JS
		function google_map_js_script() {
			if ( ! isset( $_GET['simplemap-master-js'] ) )
				return;

			header( "HTTP/1.1 200 OK" );
			header( "Content-type: application/x-javascript" );
			$options = $this->get_options();

			?>
			var default_lat 			= <?php echo esc_js( $options['default_lat'] ); ?>;
			var default_lng 			= <?php echo esc_js( $options['default_lng'] ); ?>;
			var default_radius 			= <?php echo esc_js( $options['default_radius'] ); ?>;
			var zoom_level 				= '<?php echo esc_js( $options['zoom_level'] ); ?>';
			var map_width 				= '<?php echo esc_js( $options['map_width'] ); ?>';
			var map_height 				= '<?php echo esc_js( $options['map_height'] ); ?>';
			var special_text 			= '<?php echo esc_js( $options['special_text'] ); ?>';
			var units 					= '<?php echo esc_js( $options['units'] ); ?>';
			var limit 					= '<?php echo esc_js( $options['results_limit'] ); ?>';
			var plugin_url 				= '<?php echo esc_js( SIMPLEMAP_URL ); ?>';
			var visit_website_text 		= '<?php echo apply_filters( 'sm-visit-website-text', __( 'Visit Website', 'SimpleMap' ) ); ?>';
			var get_directions_text		= '<?php echo apply_filters( 'sm-get-directions-text', __( 'Get Directions', 'SimpleMap' ) ); ?>';
			var location_tab_text		= '<?php echo apply_filters( 'sm-location-text', __( 'Location', 'SimpleMap' ) ); ?>';
			var description_tab_text	= '<?php echo apply_filters( 'sm-description-text', __( 'Description', 'SimpleMap' ) ); ?>';
			var phone_text				= '<?php echo apply_filters( 'sm-phone-text', __( 'Phone', 'SimpleMap' ) ); ?>';
			var fax_text				= '<?php echo apply_filters( 'sm-fax-text', __( 'Fax', 'SimpleMap' ) ); ?>';
			var email_text				= '<?php echo apply_filters( 'sm-email-text', __( 'Email', 'SimpleMap' ) ); ?>';

			var taxonomy_text = {};
			<?php
			if ( $taxonomies = $this->get_sm_taxonomies( 'array', '', true, 'object' ) ) {
				foreach( $taxonomies as $taxonomy ) {
					?>
					taxonomy_text.<?php echo $taxonomy->name; ?> = '<?php echo apply_filters( $taxonomy->name . '-text', __( $taxonomy->labels->name, 'SimpleMap' ) ); ?>';
					<?php
				}
			}
			?>
			var noresults_text			= '<?php echo apply_filters( 'sm-no-results-found-text', __( 'No results found.', 'SimpleMap' ) ); ?>';
			var default_domain 			= '<?php echo esc_js( $options['default_domain'] ); ?>';
			var address_format 			= '<?php echo esc_js( $options['address_format'] ); ?>';
			var siteurl					= '<?php echo esc_js( get_home_url() ); ?>';
			var map;
			var geocoder;
			var autoload				= '<?php echo esc_js( $options['autoload'] ); ?>';
			var auto_locate				= '<?php echo esc_js( $options['auto_locate'] ); ?>';
			var markersArray = [];
			var infowindowsArray = [];

			/* (C) 2009 Ivan Boldyrev <lispnik@gmail.com>
			 *
			 * Fgh is a fast GeoHash implementation in JavaScript.
			 *
			 * Fgh is free software; you can redistribute it and/or modify
			 * it under the terms of the GNU General Public License as published by
			 * the Free Software Foundation; either version 3 of the License, or
			 * (at your option) any later version.
			 *
			 * Fgh is distributed in the hope that it will be useful,
			 * but WITHOUT ANY WARRANTY; without even the implied warranty of
			 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
			 * GNU General Public License for more details.
			 *
			 * You should have received a copy of the GNU General Public License
			 * along with this software; if not, see <http://www.gnu.org/licenses/>.
			 */

			(function () {
			    var _tr = "0123456789bcdefghjkmnpqrstuvwxyz";
			    /* This is a table of i => "even bits of i combined".  For example:
			     * #b10101 => #b111
			     * #b01111 => #b011
			     * #bABCDE => #bACE
			     */
			    var _dm = [0, 1, 0, 1, 2, 3, 2, 3, 0, 1, 0, 1, 2, 3, 2, 3, 
			               4, 5, 4, 5, 6, 7, 6, 7, 4, 5, 4, 5, 6, 7, 6, 7];

			    /* This is an opposit of _tr table: it maps #bABCDE to
			     * #bA0B0C0D0E.
			     */
			    var _dr = [0, 1, 4, 5, 16, 17, 20, 21, 64, 65, 68, 69, 80,
			               81, 84, 85, 256, 257, 260, 261, 272, 273, 276, 277,
			               320, 321, 324, 325, 336, 337, 340, 341];

			    function _cmb (str, pos) {
			        return (_tr.indexOf(str.charAt(pos)) << 5) | (_tr.indexOf(str.charAt(pos+1)));
			    };

			    function _unp(v) {
			        return _dm[v & 0x1F] | (_dm[(v >> 6) & 0xF] << 3);
			    }

			    function _sparse (val) {
			        var acc = 0, off = 0;

			        while (val > 0) {
			            low = val & 0xFF;
			            acc |= _dr[low] << off;
			            val >>= 8;
			            off += 16;
			        }
			        return acc;
			    }

			    window['Fgh'] = {
			        decode: function (str) {
			            var L = str.length, i, w, ln = 0.0, lt = 0.0;

			            // Get word; handle odd size of string.
			            if (L & 1) {
			                w = (_tr.indexOf(str.charAt(L-1)) << 5);
			            } else {
			                w = _cmb(str, L-2);
			            }
			            lt = (_unp(w)) / 32.0;
			            ln = (_unp(w >> 1)) / 32.0;
			            
			            for (i=(L-2) & ~0x1; i>=0; i-=2) {
			                w = _cmb(str, i);
			                lt = (_unp(w) + lt) / 32.0;
			                ln = (_unp(w>>1) + ln) / 32.0;
			            }
			            return {lat:  180.0*(lt-0.5), lon: 360.0*(ln-0.5)};
			        },
			        
			        encode: function (lat, lon, bits) {
			            lat = lat/180.0+0.5;
			            lon = lon/360.0+0.5;
			            
			            /* We generate two symbols per iteration; each symbol is 5
			             * bits; so we divide by 2*5 == 10.
			             */
			            var r = '', l = Math.ceil(bits/10), hlt, hln, b2, hi, lo, i;

			            for (i = 0; i < l; ++i) {
			                lat *= 0x20;
			                lon *= 0x20;

			                hlt = Math.min(0x1F, Math.floor(lat));
			                hln = Math.min(0x1F, Math.floor(lon));
			                
			                lat -= hlt;
			                lon -= hln;
			                
			                b2 = _sparse(hlt) | (_sparse(hln) << 1);
			                
			                hi = b2 >> 5;
			                lo = b2 & 0x1F;

			                r += _tr.charAt(hi) + _tr.charAt(lo);
			            }
			            
			            r = r.substr(0, Math.ceil(bits/5));
			            return r;
			        },
			    
			        checkValid: function(str) {
			            return !!str.match(/^[0-9b-hjkmnp-z]+$/);
			        }
			    }
			})();
			//var directionsDisplay;
			//var directionsService;
			//var directionsResults;

			/* Function: GmapDirections class
			 * Author: willhlaw <will.lawrence [at] gmail>
			 * Dependencies: jQuery and Google.maps object needs to be instantiated.
			 *               Also relies on Ivan Boldyrev's Fgh fast GeoHash implementation in JavaScript.
			 */
			function GmapDirections(mapContainerId, options) {
				var thisObj = this; //so that this can be bound to something in an event handler
				var options = options || {}; //prevents undefined errors if no options parameter is passed in. (e.g. options.option1 will no longer complain about opdtions object being undefined)
				thisObj.display = null; //Google DirectionsRenderer object
				thisObj.service = null; //Google DirectionsServive object
				thisObj.resultsDiv = null; //where results from Google Directions API html will be put
				thisObj.resultsDivId = null; 
				thisObj.markerWaypoints = []; //marker array for direction results
				thisObj.geoHashBitLen = options.geoHashBitLen || 24; //used for Fgh encode function where bitlen purpose is to geohash to close-by markers to a one or two character difference for unique comparison and find matches between marker directions and waypoints.

				//set options or defaults
				thisObj.mapContainerId = mapContainerId || 'simplemap';
				thisObj.directionRendererOpts = options.directionRendererOpts || ({
					suppressMarkers: true, 
					preserveViewport: false, 
					draggable: true
				});
				thisObj.travelMode = options.travelMode; // use default option later in call, because google object may not be defined yet. google.maps.DirectionsTravelMode.DRIVING; //cannot be Transit because multiple waypoints does not work for transit
				thisObj.optimizeWaypoints = options.optimizeWaypoints; // use default option later because google may not be defined.
				thisObj.resultsDivId = options.resultsDivId || thisObj.mapContainerId + "-results";

				thisObj.startPointDivID = options.startPointDivID || "gd-start";
				thisObj.startPointID = options.startPointID || "gd-startPoint";

				if(typeof(thisObj.setDisplay)==='undefined') {//guarantees one time prototyping 
					GmapDirections.prototype.setDisplay = function (map) {
						thisObj.display = new google.maps.DirectionsRenderer(thisObj.directionRendererOpts);
						thisObj.display.setMap(map);

						//get or create a div to populate the directions
						thisObj.results = document.getElementById(thisObj.resultsDivId);
						if (thisObj.results === null) {
							//create div for directions 
							var el = document.createElement("div");
							el.id = thisObj.resultsDivId;
							insertAfter(document.getElementById( mapContainerId ), el);
							thisObj.results = el;
						}

						thisObj.display.setPanel(thisObj.results);

						//instead of below function to un-overlap the A and B icons with start and destination text, use css for adp-text class instead
						/* 
						google.maps.event.addListener(directions.display, 'directions_changed', function() {
							//un-overlap the A and B icons and the start and destination text (which are class .adp-text.
							setTimeout(function() {
								jQuery(".adp-text").css('width', 0); //this value may need to be tweaked per page or commented out to use css for adp-text class instead
							}, 1000);
						});
						*/

						thisObj.service = new google.maps.DirectionsService();
					}
				}

				if(typeof(thisObj.clearDirections)==='undefined') {//guarantees one time prototyping 
					GmapDirections.prototype.clearDirections = function () {
						thisObj.display.set('directions', null);
					}
				}

				/**
				* For each step, place a marker, and add the text to the marker's
				* info window. Also attach the marker to an array so we can keep track of it
				*/
				if(typeof(thisObj.computeDirections)==='undefined') {//guarantees one time prototyping 
					GmapDirections.prototype.showSteps = function (directionResult) {
						var myRoute = directionResult.routes[0].legs[0];
						var stepDisplay = new google.maps.InfoWindow();

						for (var i = 0; i < myRoute.steps.length; i++) {
							var marker = new google.maps.Marker({
								position: myRoute.steps[i].start_point,
								map: map
							});
							google.maps.event.addListener(marker, 'click', function() {
								stepDisplay.setContent("Howdy");
								stepDisplay.open(map, marker);
							});

							//keep track of markers
							thisObj.markerWaypoints[i] = marker;
						}
					}
				}

				/**
				 * Get the directions from google
				 * @param string start (start address) //defaults to on page startPoint address
				 * @param integer endLat  //number from placemark. But it could be an Address string
				 * @param integer endLng  //number from placemark. But if endLat is an address, this value should be "address" as an indicator.
				 */
				if(typeof(thisObj.computeDirections)==='undefined') {//guarantees one time prototyping 
					GmapDirections.prototype.computeDirections = function (start, endLat, endLng) {

						//if startPoint does not exist, then create it
						var startAddress = null;
						var startPoint = document.getElementById(thisObj.startPointID); //startPointDivID default is 'gd-startPoint'
						if (!startPoint) {
							var startPointDiv = document.createElement("div");
							startPointDiv.id = thisObj.startPointDivID;
							startPointDiv.innerHTML =  "<table style='margin-top: 2px; width: 0'><tr><td>" + "<label for='" + thisObj.startPointID + "'>Your trip's starting address:</label>" + "</td>" +
							"<td>" + "<span contenteditable='true' id=" + thisObj.startPointID + " style='border: 1px solid #ddd; margin-top: 4px' />" + "</td>" +
							"<td>" + "<input type='button' id='gd-reGetDirections' value='Recalculate Trip' />" + "</td></tr></table>" + 
							"<br/>";
							insertAfter(document.getElementById( mapContainerId ), startPointDiv);
							//add click event to recalculate with new start point with other stops / waypoints the same.
							jQuery('#gd-reGetDirections').click(function() {
								thisObj.computeDirections(); //computeDirections handles all the defaults (grabs gd-startPoint from page and uses last waypoint)
							});
							startPoint = document.getElementById(thisObj.startPointID);
							startAddress = start;
						} else {
							startAddress = startPoint.innerHTML;
						}

						startPoint.innerHTML = startAddress; //sets it in case it is the first address from infowindow
						//need to remove the last waypoint object because it is the same as our destination, and destination is required to be passed in
						if (thisObj.endWaypoint == "") {
							//there are no more waypoints so clear map and results of directions
							thisObj.clearDirections();
						}
						else {
							var savedEndPoint = thisObj.waypoints[thisObj.endWaypoint];
							thisObj.removeStop(thisObj.endWaypoint); //temporarily remove last waypoint from waypoints array for Google
							var request = {
								origin: startAddress, 
								destination: savedEndPoint.location,
								waypoints: thisObj.getStops(),
								optimizeWaypoints: thisObj.optimizeWaypoints || true,
								provideRouteAlternatives: true,
								travelMode: thisObj.travelMode || google.maps.DirectionsTravelMode.DRIVING
							};
							thisObj.service.route(request, function(response, status) {
								if (status == google.maps.DirectionsStatus.OK) {
									thisObj.display.setDirections(response);
									thisObj.showSteps(response);
								} else {
									alert('Error generating directions. Please try entering another address.');
									startPoint.innerHTML = "Please try another address";
								}
							});
							//add endpoint back to waypoints because we removed it above only temporarily
							var endLat = savedEndPoint.location.split(",")[0];
							var endLng = savedEndPoint.location.split(",")[1];
							thisObj.addStop(endLat, endLng, savedEndPoint.title, null, true);
						}

					} // end computeDirections
				}

				if(typeof(thisObj.wrapInfowindow)==='undefined') {//guarantees one time prototyping 
					GmapDirections.prototype.wrapInfowindow = function (windowContent, windowTitle) {
						//set up default values for case when user has already chosen a startPoint and at least one stop / waypoint
						var title = "<div style='display: none' id='gd-windowTitle'>" + windowTitle + "</div>";
						var label = "<label>Would you like to go here?</label>";
						var input = "";
						var addButton = "<input type='button' id='gd-goGetDirections' value='Add to Trip' />";
						var removeButton = "<input type='button' id='gd-removeAndGetDirections' value='Drop from Trip' />";
						if (!document.getElementById('gd-startPoint') || !document.getElementById('gd-startPoint').innerHTML) {
							//no address has been set, so prompt user inside infowindow for first time
							label = "<label>Would you like to go here? (Enter your starting address):</label>";							
							input = "<input type='text' id='gd-startAddress' />";
							removeButton = "";
						}
						var wrapper = "<div id='wrapper'>" + "<br/>" + title + label + input + addButton + removeButton + 
						"<hr>" +
						windowContent +
						"</div>";
						return wrapper;
					}
				}

				if(typeof(thisObj.setDirections)==='undefined') {//guarantees one time prototyping 
					GmapDirections.prototype.setDirections = function (clickedMarker, infoWindow) {
						var lat = clickedMarker.position.lat();
						var lng = clickedMarker.position.lng();
						google.maps.event.addDomListener(infoWindow, 'domready', function() {
							jQuery('#gd-goGetDirections').click(function() {
								//figure out if this is first time and start is from infowindow (gd-startAddress) or we are adding a stop / waypoint and start is from gd-startPoint which is default so pass in null for start
								var title = (document.getElementById('gd-windowTitle') !== null) ? document.getElementById('gd-windowTitle').innerHTML : "";
								var start = (document.getElementById('gd-startAddress') !== null) ? document.getElementById('gd-startAddress').value : null;
								thisObj.addStop(lat, lng, title, null, true); //TODO: embed start address more into directions object instead of relying on gd-startPoint value
								thisObj.computeDirections(start, lat, lng);
								infoWindow.close();
							});
						});
						google.maps.event.addDomListener(infoWindow, 'domready', function() {
							jQuery('#gd-removeAndGetDirections').click(function() {
								thisObj.removeStop(lat, lng);
								thisObj.setNewEndpoint();
								//remove a stop / waypoint so start will be from gd-startPoint which is default so pass in null for start and nothing for lat and lng because we want waypoint to be used
								thisObj.computeDirections();
								infoWindow.close();
							});
						});
					}
				}

				/************ Functions for managing waypoints or "Stops" along the route ***************/

				thisObj.waypoints = new Array(); //associative array with geohash as keys and values as waypoints objects for Google's Directions waypoints
				thisObj.waypointsLength = 0; //keep track of length of associative array
				thisObj.startWaypoint = "";
				thisObj.endWaypoint = "";

				//getStops returns a non-associative, indexable waypoints array with just location and stopover to match the google directions waypoints array objects
				if(typeof(thisObj.getStops)==='undefined') {//guarantees one time prototyping 
					GmapDirections.prototype.getStops = function () {
						var arrayStops = [];
						var waypoint = {};
						var gWaypoint = {};
						for (var key in thisObj.waypoints) {
							waypoint = thisObj.waypoints[key];
							gWaypoint = {
								location : waypoint.location,
								stopover : waypoint.stopover
							}
							arrayStops.push(gWaypoint);
						}
						return arrayStops;
					}
				}

				//setNewEndpoint with geoHashKey parameter or iterates through associative array and assigns thisObj.endWaypoint to the last value
				if(typeof(thisObj.setNewEndpoint)==='undefined') {//guarantees one time prototyping 
					GmapDirections.prototype.setNewEndpoint = function (geoHashKey) {
						thisObj.endWaypoint = geoHashKey || "";
						if (thisObj.endWaypoint === "") {
							//assign endWaypoint to the last waypoint in the array
							for (var key in thisObj.waypoints) {
								thisObj.endWaypoint = key;
							}
						}
						return thisObj.endWaypoint;
					}
				}

				if(typeof(thisObj.setStops)==='undefined') {//guarantees one time prototyping 
					GmapDirections.prototype.setStops = function (directionWaypoints) {
						thisObj.waypoints = directionWaypoints;
					}
				}

				if(typeof(thisObj.addStop)==='undefined') {//guarantees one time prototyping 
					GmapDirections.prototype.addStop = function(lat, lng, title, isStart, isEnd, stopOverFlag) {
						var latlngString = "" + lat + "," + lng;
						var gHash = Fgh.encode(lat, lng, thisObj.geoHashBitLen); //aim with 3rd parameter, bitlen, is to geohash to close-by markers to a one or two character difference for unique comparison and find matches between marker directions and waypoints.
						console.log(latlngString + " translates to geoHash: " + gHash);
						thisObj.startWaypoint = (!isStart) ? thisObj.startWaypoint : gHash;
						thisObj.endWaypoint = (!isEnd) ? thisObj.endWaypoint : gHash;
						var stopOver = stopOverFlag || true; //default is true, to add waypoint to route as a marker
						thisObj.waypoints[gHash] = {
						name: title,
						location: latlngString,
						stopover: stopOver
						};
						thisObj.waypointsLength += 1;
					};
  				}

  				if(typeof(thisObj.removeStop)==='undefined') { //guarantees one time prototyping 
  					GmapDirections.prototype.removeStop = function(geoHashorLat, lng) {
						var gHash = "";
						if (!lng) {
							//second parameter was missing so assume indexOrLat is an index
							//parameter was an index "3" or "-1" for the last one
							gHash = geoHashorLat;
						}
						else {
							//parameters were a lat and lng
							gHash = Fgh.encode(geoHashorLat, lng, thisObj.geoHashBitLen);
						}
						try {
							//if item being removed is currently a start waypoint or end waypoint, then reset those. Caller or removed this may need to figure out that one of these has been reset and figure out what the new value should be 
							thisObj.startWaypoint = (thisObj.startWaypoint == gHash) ? thisObj.startWaypoint = "" : thisObj.startWaypoint;
							thisObj.endWaypoint = (thisObj.endWaypoint == gHash) ? thisObj.endWaypoint = "" : thisObj.endWaypoint; 
							delete thisObj.waypoints[gHash];
							thisObj.waypointsLength -= 1;
						}
						catch (e) {
							console.log(gHash + " could not be deleted. Call was to .removeStop(" + geoHashorLat + ", " + lng + ") and error is: " + e);
						}
						return gHash;
	  				};
  				}
			} // end of GmapDirections 'class'

			directions = new GmapDirections('simplemap'); //creates new GmapDirections object to allow user to get directions between different markers (a.k.a. stops or waypoints)

			function clearInfoWindows() {
				if (infowindowsArray) {
					for (var i=0;i<infowindowsArray.length;i++) {
						infowindowsArray[i].close();
					}
				}
			}

			function clearOverlays() {
				if (markersArray) {
					for (var i=0;i<markersArray.length;i++) {
						markersArray[i].setMap(null);
					}
				}
			}

			function insertAfter(referenceNode, newNode) {
				referenceNode.parentNode.insertBefore(newNode, referenceNode.nextSibling);
			}

			//function load_simplemap( lat, lng, aspid, ascid, asma ) {
			function load_simplemap( lat, lng, aspid, ascid, asma, shortcode_zoom_level, map_type, shortcode_autoload ) {

				zoom_level = shortcode_zoom_level;
                autoload = shortcode_autoload;
				<?php 
				/*
				if ( '' == $options['api_key'] ) {
					?>
					jQuery( "#simplemap" ).html( "<p style='padding:10px;'><?php printf( __( 'You must enter an API Key in <a href=\"%s\">General Settings</a> before your maps will work.', 'SimpleMap' ), admin_url( 'admin.php?page=simplemap' ) ); ?></p>" );
				<?php
				}
				*/

				do_action( 'sm-load-simplemap-js-top' );
				?>
  
				if ( lat == 0 ) {
					lat = '<?php echo esc_js( $options['default_lat'] ); ?>';
				}

				if ( lng == 0 ) {
					lng = '<?php echo esc_js( $options['default_lng'] ); ?>';
				}

				var latlng = new google.maps.LatLng( lat, lng );
				var myOptions = {
					zoom: parseInt(zoom_level),
					center: latlng,
					mapTypeId: google.maps.MapTypeId[map_type] 
				};


				map = new google.maps.Map( document.getElementById( "simplemap" ), myOptions );
				directions.setDisplay(map); //prepare for Google Directions API calls by using GmapDirections object

				// Adsense for Google Maps
				<?php 
				if ( '' != $options['adsense_pub_id'] && $options['adsense_for_maps'] ) {

					$default_adsense_publisher_id = isset( $options['adsense_pub_id'] ) ? $options['adsense_pub_id'] : '';
					$default_adsense_channel_id = isset( $options['adsense_channel_id'] ) ? $options['adsense_channel_id'] : '';
					$default_adsense_max_ads = isset( $options['adsense_max_ads'] ) ? $options['adsense_max_ads'] : 2;

					?>

					// Adsense ID. If no shortcode, check for options. If not options, use blank string.
					if ( aspid == 0 )
						aspid = '<?php echo esc_js( $default_adsense_publisher_id ); ?>';

					// Channel ID. If no shortcode, check for options. If not options, use blank string.
					if ( ascid == 0 )
						ascid = '<?php echo esc_js( $default_adsense_channel_id ); ?>';

					// Max ads per map. If no shortcode, check for options. If no options, use 2.
					if ( asma == 0 )
						asma = '<?php echo esc_js( $default_adsense_max_ads ); ?>';

					var publisher_id = aspid;

					var adUnitDiv = document.createElement('div');
					var adUnitOptions = {
						channelNumber: ascid,
						format: google.maps.adsense.AdFormat.HALF_BANNER,
						position: google.maps.ControlPosition.TOP,
						map: map,
						visible: true,
						publisherId: publisher_id
					};
					adUnit = new google.maps.adsense.AdUnit(adUnitDiv, adUnitOptions);
				<?php
				}
				
				do_action( 'sm-load-simplemap-js-bottom' );				
				?>
			}

			function codeAddress() {
				// if this is modified, modify mirror function in general-options-js php function 
				var d_address = document.getElementById("default_address").value;

				geocoder = new google.maps.Geocoder();
				geocoder.geocode( { 'address': d_address }, function( results, status ) {
					if ( status == google.maps.GeocoderStatus.OK ) {
						var latlng = results[0].geometry.location;
						document.getElementById("default_lat").value = latlng.lat();
						document.getElementById("default_lng").value = latlng.lng();
					} else {
						alert("Geocode was not successful for the following reason: " + status);
					}
				});
			}

			function codeNewAddress() {
				if (document.getElementById("store_lat").value != '' && document.getElementById("store_lng").value != '') {
					document.new_location_form.submit();
				}
				else {
					var address = '';
					var street = document.getElementById("store_address").value;
					var city = document.getElementById("store_city").value;
					var state = document.getElementById("store_state").value;
					var country = document.getElementById("store_country").value;

					if (street) { address += street + ', '; }
					if (city) { address += city + ', '; }
					if (state) { address += state + ', '; }
					address += country;

					geocoder = new google.maps.Geocoder();
					geocoder.geocode( { 'address': address }, function( results, status ) {
						if ( status == google.maps.GeocoderStatus.OK ) {
							var latlng = results[0].geometry.location;
							document.getElementById("store_lat").value = latlng.lat();
							document.getElementById("store_lng").value = latlng.lng();
							document.new_location_form.submit();
						} else {
							alert("Geocode was not successful for the following reason: " + status);
						}
					});
				}
			}

			function codeChangedAddress() {
				var address = '';
				var street = document.getElementById("store_address").value;
				var city = document.getElementById("store_city").value;
				var state = document.getElementById("store_state").value;
				var country = document.getElementById("store_country").value;

				if (street) { address += street + ', '; }
				if (city) { address += city + ', '; }
				if (state) { address += state + ', '; }
				address += country;

				geocoder = new google.maps.Geocoder();
				geocoder.geocode( { 'address': address }, function( results, status ) {
					if ( status == google.maps.GeocoderStatus.OK ) {
						var latlng = results[0].geometry.location;
						document.getElementById("store_lat").value = latlng.lat();
						document.getElementById("store_lng").value = latlng.lng();
					} else {
						alert("Geocode was not successful for the following reason: " + status);
					}
				});
			}

			function searchLocations( is_search ) {
				// Init searchData
				var searchData = {};
				searchData.taxes = {};

				// Set defaults for search form fields
				searchData.address	= '';
				searchData.city 	= '';
				searchData.state	= '';
				searchData.zip		= '';
				searchData.country	= '';

				if ( null != document.getElementById('location_search_address_field') ) {
					searchData.address = document.getElementById('location_search_address_field').value;
				}

				if ( null != document.getElementById('location_search_city_field') ) {
					searchData.city = document.getElementById('location_search_city_field').value;
				}

				if ( null != document.getElementById('location_search_country_field') ) {
					searchData.country = document.getElementById('location_search_country_field').value;;
				}

				if ( null != document.getElementById('location_search_state_field') ) {
					searchData.state = document.getElementById('location_search_state_field').value;
				}

				if ( null != document.getElementById('location_search_zip_field') ) {
					searchData.zip = document.getElementById('location_search_zip_field').value;
				}

				if ( null != document.getElementById('location_search_distance_field') ) {
					searchData.radius = document.getElementById('location_search_distance_field').value;
				}

				searchData.lat			= document.getElementById('location_search_default_lat').value;
				searchData.lng			= document.getElementById('location_search_default_lng').value;
				searchData.limit		= document.getElementById('location_search_limit').value; 
				searchData.searching	= document.getElementById('location_is_search_results').value;

				// Do SimpleMap Taxonomies
				<?php 
				if ( $taxnames = get_object_taxonomies( 'sm-location' ) ) {

					foreach ( $taxnames as $name ) {
						$php_name = str_replace( '-', '_', $name );
						?>

						// Do taxnonomy for checkboxes
						searchData.taxes.<?php echo $php_name; ?> = '';
						var checks_found = false;
						jQuery( 'input[rel=location_search_<?php echo $php_name; ?>_field]' ).each( function() {
							checks_found = true;
							if ( jQuery( this ).attr( 'checked' ) && jQuery( this ).attr( 'value' ) != null ) {
								<?php echo 'searchData.taxes.' . $php_name; ?> += jQuery( this ).attr( 'value' ) + ',';
							}
						});

						// Do taxnonomy for select box if checks weren't found
						if ( false == checks_found ) {	
							jQuery( 'option[rel=location_search_<?php echo $php_name; ?>_select_val]' ).each( function() {
								if ( jQuery( this ).attr( 'selected' ) && jQuery( this ).attr( 'value' ) != null ) {
									<?php echo 'searchData.taxes.' . $php_name; ?> += jQuery( this ).attr( 'value' ) + ',';
								}
							});
						}

						<?php
					}
				}
				?>

				var query = '';
				var start = 0;
 
				if ( searchData.address && searchData.address != '' ) {
					query += searchData.address + ', ';
				}

				if ( searchData.city && searchData.city != '' ) {
					query += searchData.city + ', ';
				}

				if ( searchData.state && searchData.state != '' ) {
					query += searchData.state + ', ';
				}

				if ( searchData.zip && searchData.zip != '' ) {
					query += searchData.zip + ', ';
				}

				if ( searchData.country && searchData.country != '' ) {
					query += searchData.country + ', ';
				}

				// Query
				if ( query != null ) {
					query = query.slice(0, -2);
				}

				if ( searchData.limit == '' || searchData.limit == null ) {
					searchData.limit = 0;
				}

				if ( searchData.radius == '' || searchData.radius == null ) {
					searchData.radius = 0;
				}

				// Taxonomies
				<?php 
				if ( $taxnames = get_object_taxonomies( 'sm-location' ) ) {

					foreach ( $taxnames as $name ) {
						$php_name = str_replace( '-', '_', $name );
						?>

						if ( <?php echo 'searchData.taxes.' . $php_name; ?> != null ) {
							var _<?php echo $php_name; ?> = <?php echo 'searchData.taxes.' . $php_name; ?>.slice(0, -1);
						} else {
							var _<?php echo $php_name; ?> = '';
						}

						// Append available taxes logic if no taxes are selected but limited taxes were passed through shortcode as available
						if ( '' != document.getElementById('avail_<?php echo $php_name; ?>').value && '' == _<?php echo $php_name; ?> ) {
							_<?php echo $php_name; ?> = 'OR,' + document.getElementById('avail_<?php echo $php_name; ?>').value;
						}

						searchData.taxes.<?php echo $php_name; ?> = _<?php echo $php_name; ?>;

						<?php
					}
				}
				?>

				// Load default location if query is empty
				if ( query == '' || query == null ) {

					if ( searchData.lat != 0 && searchData.lng != 0 )
						query = searchData.lat + ', ' + searchData.lng;
					else
						query = '<?php echo esc_js( $options['default_lat'] ); ?>, <?php echo esc_js( $options['default_lng'] ); ?>';

				}

				// Searching
				if ( 1 == searchData.searching || 1 == is_search ) {
					is_search = 1;
					searchData.source = 'search';
				} else {
					is_search = 0;
					searchData.source = 'initial_load';
				}

				geocoder.geocode( { 'address': query }, function( results, status ) {
					if ( status == google.maps.GeocoderStatus.OK ) {
						searchData.center = results[0].geometry.location;
						if ( 'none' != autoload || is_search ) {
							if ( 'all' == autoload && is_search != 1 ) {
								searchData.radius = 0;
								searchData.limit = 0;
							}

							if (! searchData.center) {
								searchData.center = new GLatLng( 44.9799654, -93.2638361 );
							}
							searchData.query_type = 'all';
							searchData.mapLock = 'unlock';
							searchData.homeAddress = query;

							searchLocationsNear(searchData); 
						}
					}
				});
			}

			function searchLocationsNear(searchData) {
				// Radius
				if ( searchData.radius != null && searchData.radius != '' ) {
					searchData.radius = parseInt( searchData.radius );

					if ( units == 'km' ) {
						searchData.radius = parseInt( searchData.radius ) / 1.609344;
					}
				} else if ( autoload == 'all' ) {
					searchData.radius = 0;
				} else {
					if ( units == 'mi' ) {
						searchData.radius = parseInt( default_radius );
					} else if ( units == 'km' ) {
						searchData.radius = parseInt( default_radius ) / 1.609344;
					}
				}

				// Build search URL
				<?php 
				if ( $taxonomies = $this->get_sm_taxonomies( 'array', '', true ) ) {
					$js_tax_string = '';
					foreach( $taxonomies as $taxonomy ) {
						$js_tax_string .= "'&$taxonomy=' + searchData.taxes.$taxonomy + ";
					}
				}
				?>

				var searchUrl = siteurl + '/?sm-xml-search=1&lat=' + searchData.center.lat() + '&lng=' + searchData.center.lng() + '&radius=' + searchData.radius + '&namequery=' + searchData.homeAddress + '&query_type=' + searchData.query_type  + '&limit=' + searchData.limit + <?php echo $js_tax_string; ?>'&address=' + searchData.address + '&city=' + searchData.city + '&state=' + searchData.state + '&zip=' + searchData.zip + '&pid=<?php echo esc_js( absint( $_GET['smpid'] ) ); ?>';

				<?php if ( apply_filters( 'sm-use-updating-image', true ) ) : ?>
				// Display Updating Message and hide search results
				if ( jQuery( "#simplemap" ).is(":visible") ) {
					jQuery( "#simplemap" ).hide();
					jQuery( "#simplemap-updating" ).show();
				}
				<?php endif; ?>
				jQuery( "#results" ).html( '' );

				jQuery.get( searchUrl, {}, function(data) {
					<?php if ( apply_filters( 'sm-use-updating-image', true ) ) : ?>
					// Hide Updating Message
					if ( jQuery( "#simplemap-updating" ).is(":visible") ) {
						jQuery( "#simplemap-updating" ).hide();
						jQuery( "#simplemap" ).show();
					}
					<?php endif; ?>

					clearOverlays();

					var results = document.getElementById('results');
					results.innerHTML = '';

					var markers = jQuery( eval( data ) );
					if (markers.length == 0) {
						results.innerHTML = '<h3>' + noresults_text + '</h3>';
						map.setCenter( searchData.center );
						return;
					}

					var bounds = new google.maps.LatLngBounds();
					markers.each( function () {
						var locationData = this;
						locationData.distance = parseFloat(locationData.distance);
						locationData.point = new google.maps.LatLng(parseFloat(locationData.lat), parseFloat(locationData.lng));
						locationData.homeAddress = searchData.homeAddress;

						var marker = createMarker(locationData);
						var sidebarEntry = createSidebarEntry(marker, locationData, searchData);
						results.appendChild(sidebarEntry);
						bounds.extend(locationData.point);
					});

					// Make centeral marker on search
					if ( 'search' == searchData.source && <?php echo apply_filters( 'sm-show-search-marker-image', 'true' ); ?>) {
						var searchMarkerOptions = {};
						searchMarkerOptions.map = map;
						searchMarkerOptions.position = searchData.center;

						searchMarkerOptions.icon = new google.maps.MarkerImage( 
							'<?php echo esc_js( apply_filters( 'sm-search-marker-image-url', SIMPLEMAP_URL . "/inc/images/blue-dot.png" ) );?>',
							new google.maps.Size(20, 32),
							new google.maps.Point(0,0),
							new google.maps.Point(0,32)
						);

						var searchMarkerTitle = '';
						if ( '' != searchData.address ) {
								searchMarkerTitle += searchData.address + ' ';
						}
						if ( '' != searchData.city ) {
								searchMarkerTitle += searchData.city + ' ';
						}
						if ( '' != searchData.state ) {
								searchMarkerTitle += searchData.state + ' ';
						}
						if ( '' != searchData.zip ) {
								searchMarkerTitle += searchData.zip + ' ';
						}

						var searchMarker = new google.maps.Marker( searchMarkerOptions );
						searchMarker.title = searchMarkerTitle;
						markersArray.push(searchMarker);
						bounds.extend(searchMarkerOptions.position);
					}

					// If the search button was clicked, limit to a 15px zoom
					if ( 'search' == searchData.source ) {
						map.fitBounds( bounds );
						if ( map.getZoom() > 15 ) {
							map.setZoom( 15 );
						}
					} else {
						// If initial load of map, zoom to default settings
						map.setZoom(parseInt(zoom_level));
					}

				});
			}

			function stringFilter(s) {
				filteredValues = "emnpxt%";     // Characters stripped out
				var i;
				var returnString = "";
				for (i = 0; i < s.length; i++) {  // Search through string and append to unfiltered values to returnString.
					var c = s.charAt(i);
					if (filteredValues.indexOf(c) == -1) returnString += c;
				}
				return returnString;
			}

			
			function createMarker( locationData ) {

				// Init tax heights
				locationData.taxonomyheights = [];

				// Allow plugin users to define Maker Options (including custom images)
				var markerOptions = {};
				if ( 'function' == typeof window.simplemapCustomMarkers ) {
					markerOptions = simplemapCustomMarkers( locationData );
				}

				// Allow developers to turn of description in bubble. (Return true to hide)
				<?php if ( true === apply_filters( 'sm-hide-bubble-description', false ) ) : ?>
				locationData.description = '';
				<?php endif; ?>

				markerOptions.map = map;
				markerOptions.position = locationData.point;
				var marker = new google.maps.Marker( markerOptions );
				marker.title = locationData.name;
				markersArray.push(marker);

				var mapwidth = Number(stringFilter(map_width));
				if (map_width.indexOf("%") >= 0) {
					mapwidth = jQuery("#simplemap").width();
				}
				var mapheight = Number(stringFilter(map_height));

				var maxbubblewidth = Math.round(mapwidth / 1.5);
				var maxbubbleheight = Math.round(mapheight / 2.2);

				var fontsize = 12;
				var lineheight = 12;

				if (locationData.taxes.sm_category && locationData.taxes.sm_category != '' ) {
					var titleheight = 3 + Math.floor((locationData.name.length + locationData.taxes.sm_category.length) * fontsize / (maxbubblewidth * 1.5));
				} else {
					var titleheight = 3 + Math.floor((locationData.name.length) * fontsize / (maxbubblewidth * 1.5));
				}

				var addressheight = 2;
				if (locationData.address2 != '') {
					addressheight += 1;
				}
				if (locationData.phone != '' || locationData.fax != '') {
					addressheight += 1;
					if (locationData.phone != '') {
						addressheight += 1;
					}
					if (locationData.fax != '') {
						addressheight += 1;
					}
				}

				for (jstax in locationData.taxes) {
					if ( locationData.taxes[jstax] !== '' ) {
						locationData.taxonomyheights[jstax] = 3 + Math.floor((locationData.taxes[jstax][length]) * fontsize / (maxbubblewidth * 1.5));
					}
				}
				var linksheight = 2;

				var totalheight = titleheight + addressheight;
				for (jstax in locationData.taxes) {
					if ( 'sm_category' != jstax ) {
						totalheight += locationData.taxonomyheights[jstax];
					}
				}
				totalheight = (totalheight + 1) * fontsize;

				if (totalheight > maxbubbleheight) {
					totalheight = maxbubbleheight;
				}

				var html = '<div class="markertext" style="height: ' + totalheight + 'px; overflow-y: auto; overflow-x: hidden;">';
				html += '<h3 style="margin-top: 0; padding-top: 0; border-top: none;">';

				if ( '' != locationData.permalink ) {
					html += '<a href="' + locationData.permalink + '">';
				}
				html += locationData.name;

				if ( '' != locationData.permalink ) {
					html += '</a>';
				}

				if (locationData.taxes.sm_category && locationData.taxes.sm_category != null && locationData.taxes.sm_category != '' ) {
					html += '<br /><span class="bubble_category">' + locationData.taxes.sm_category + '</span>';
				}

				html += '</h3>';

				html += '<p class="buble_address">' + locationData.address;
				if (locationData.address2 != '') {
					html += '<br />' + locationData.address2;
				}
				
				// Address Data
				if (address_format == 'town, province postalcode') {
					html += '<br />' + locationData.city + ', ' + locationData.state + ' ' + locationData.zip + '</p>';
				} else if (address_format == 'town province postalcode') {
					html += '<br />' + locationData.city + ' ' + locationData.state + ' ' + locationData.zip + '</p>';
				} else if (address_format == 'town-province postalcode') {
					html += '<br />' + locationData.city + '-' + locationData.state + ' ' + locationData.zip + '</p>';
				} else if (address_format == 'postalcode town-province') {
					html += '<br />' + locationData.zip + ' ' + locationData.city + '-' + locationData.state + '</p>';
				} else if (address_format == 'postalcode town, province') {
					html += '<br />' + locationData.zip + ' ' + locationData.city + ', ' + locationData.state + '</p>';
				} else if (address_format == 'postalcode town') {
					html += '<br />' + locationData.zip + ' ' + locationData.city + '</p>';
				} else if (address_format == 'town postalcode') {
					html += '<br />' + locationData.city + ' ' + locationData.zip + '</p>';
				}

				// Phone and Fax Data
				if (locationData.phone != null && locationData.phone != '') {
					html += '<p class="bubble_contact"><span class="bubble_phone">' + phone_text + ': ' + locationData.phone + '</span>';
					if (locationData.email != null && locationData.email != '') {
						html += '<br />' + email_text + ': <a class="bubble_email" href="mailto:' + locationData.email + '">' + locationData.email + '</a>';
					}
					if (locationData.fax != null && locationData.fax != '') {
						html += '<br /><span class="bubble_fax">' + fax_text + ': ' + locationData.fax + '</span>';
					}
					html += '</p>';
				} else if (locationData.fax != null && locationData.fax != '') {
					html += '<p>' + fax_text + ': ' + locationData.fax + '</p>';
				}
								
				html += '<p class="bubble_tags">';
				
				for (jstax in locationData.taxes) {
					if ( 'sm_category' == jstax ) {
						continue;
					}
					if ( locationData.taxes[jstax] != null && locationData.taxes[jstax] != '' ) {
						html += taxonomy_text[jstax] + ': ' + locationData.taxes[jstax] + '<br />';
					}
				}
				html += '</p>';

					var dir_address = locationData.point.toUrlValue(10);
					var dir_address2 = '';
					if (locationData.address) { dir_address2 += locationData.address; }
					if (locationData.city) { if ( '' != dir_address2 ) { dir_address2 += ' '; } dir_address2 += locationData.city; };
					if (locationData.state) { if ( '' != dir_address2 ) { dir_address2 += ' '; } dir_address2 += locationData.state; };
					if (locationData.zip) { if ( '' != dir_address2 ) { dir_address2 += ' '; } dir_address2 += locationData.zip; };
					if (locationData.country) { if ( '' != dir_address2 ) { dir_address2 += ' '; } dir_address2 += locationData.country; };

					if ( '' != dir_address2 ) { dir_address = locationData.point.toUrlValue(10) + '(' + escape( dir_address2 ) + ')'; };
								
				html += '		<p class="bubble_links"><a class="bubble_directions" href="http://google' + default_domain + '/maps?saddr=' + locationData.homeAddress + '&daddr=' + dir_address + '" target="_blank">' + get_directions_text + '</a>';
								if (locationData.url != '') {
				html += '			<span class="bubble_website">&nbsp;|&nbsp;<a href="' + locationData.url + '" title="' + locationData.name + '" target="_blank">' + visit_website_text + '</a></span>';
								}
				html += '		</p>';

				if (locationData.description != '' && locationData.description != null) {
					var numlines = Math.ceil(locationData.description.length / 40);
					var newlines = locationData.description.split('<br />').length - 1;
					var totalheight2 = 0;

					if ( locationData.description.indexOf('<img') == -1) {
						totalheight2 = (numlines + newlines + 1) * fontsize;
					}
					else {
						var numberindex = locationData.description.indexOf('height=') + 8;
						var numberend = locationData.description.indexOf('"', numberindex);
						var imageheight = Number(locationData.description.substring(numberindex, numberend));

						totalheight2 = ((numlines + newlines - 2) * fontsize) + imageheight;
					}

					if (totalheight2 > maxbubbleheight) {
						totalheight2 = maxbubbleheight;
					}

					//marker.openInfoWindowTabsHtml([new GInfoWindowTab(location_tab_text, html), new GInfoWindowTab(description_tab_text, html2)], {maxWidth: maxbubblewidth});
					// tabs aren't possible with the Google Maps api v3
					html += '<hr /><p>' + locationData.description + '</p>';
				}

				html += '	</div>';

				google.maps.event.addListener(marker, 'click', function(e) {
					clearInfoWindows();
					var infowindow = new google.maps.InfoWindow({
						maxWidth: maxbubblewidth,
						content: directions.wrapInfowindow(html, locationData.name) /*inserts logic from GmapDirections object so user can ask for directions with multiple routes */
					});
					infowindow.open(map, marker);
					infowindowsArray.push(infowindow);
					window.location = '#map_top';	//Need this when results div below map are clicked so user can focus on window that just opened. But, GmapDirections author is considering removing because behavior is not likely to be wanted by end user				

					directions.setDirections(this, infowindow); //'this' refers to marker which has position information and this call sets event listener in GmapDirections object so user can interact with direction buttons inside infowindow
				});

				return marker;
			}


			function createSidebarEntry(marker, locationData, searchData) {
				var div = document.createElement('div');

				// Beginning of result
				var html = '<div id="location_' + locationData.postid + '" class="result">';

				// Flagged special
				if (locationData.special == 1 && special_text != '') {
					html += '<div class="special">' + special_text + '</div>';
				}

				// Name & distance
				html += '<div class="result_name">';
				html += '<h3 style="margin-top: 0; padding-top: 0; border-top: none;">';
				if (locationData.permalink != null && locationData.permalink != '') {
					html += '<a href="' + locationData.permalink + '">';
				}
				html += locationData.name;
				if (locationData.permalink != null && locationData.permalink != '') {
					html += '</a>';
				}

				if (locationData.distance.toFixed(1) != 'NaN') {
					if (units == 'mi') {
						html+= ' <small class="result_distance">' + locationData.distance.toFixed(1) + ' miles</small>';
					}
					else if (units == 'km') {
						html+= ' <small class="result_distance">' + (locationData.distance * 1.609344).toFixed(1) + ' km</small>';
					}
				}
				html += '</h3></div>';

				// Address
				html += '<div class="result_address"><address>' + locationData.address;
				if (locationData.address2 != '') {
					html += '<br />' + locationData.address2;
				}

				if (address_format == 'town, province postalcode') {
					html += '<br />' + locationData.city + ', ' + locationData.state + ' ' + locationData.zip + '</address></div>';
				}
				else if (address_format == 'town province postalcode') {
					html += '<br />' + locationData.city + ' ' + locationData.state + ' ' + locationData.zip + '</address></div>';
				}
				else if (address_format == 'town-province postalcode') {
					html += '<br />' + locationData.city + '-' + locationData.state + ' ' + locationData.zip + '</address></div>';
				}
				else if (address_format == 'postalcode town-province') {
					html += '<br />' + locationData.zip + ' ' + locationData.city + '-' + locationData.state + '</address></div>';
				}
				else if (address_format == 'postalcode town, province') {
					html += '<br />' + locationData.zip + ' ' + locationData.city + ', ' + locationData.state + '</address></div>';
				}
				else if (address_format == 'postalcode town') {
					html += '<br />' + locationData.zip + ' ' + locationData.city + '</address></div>';
				}
				else if (address_format == 'town postalcode') {
					html += '<br />' + locationData.city + ' ' + locationData.zip + '</address></div>';
				}

				// Phone, email, and fax numbers
				html += '<div class="result_phone">';
				if (locationData.phone != null && locationData.phone != '') {
					html += phone_text + ': ' + locationData.phone;
				}
				if (locationData.email != null && locationData.email != '') {
					html += '<span class="result_email"><br />' + email_text + ': <a href="mailto:' + locationData.email + '">' + locationData.email + '</a></span>';
				}
				if (locationData.fax != null && locationData.fax != '') {
					html += '<span class="result_fax"><br />' + fax_text + ': ' + locationData.fax + '</span>';
				}
				html += '</div>';

				// Links section
				html += '<div class="result_links">';

				// Visit Website link
				html += '<div class="result_website">';
				if (locationData.url != null && locationData.url != 'http://' && locationData.url != '') {
					html += '<a href="' + locationData.url + '" title="' + locationData.name + '" target="_blank">' + visit_website_text + '</a>';
				}
				html += '</div>';

				// Get Directions link
				if (locationData.distance.toFixed(1) != 'NaN') {
					var dir_address = locationData.point.toUrlValue(10);
					var dir_address2 = '';
					if (locationData.address) { dir_address2 += locationData.address; }
					if (locationData.city) { if ( '' != dir_address2 ) { dir_address2 += ' '; } dir_address2 += locationData.city };
					if (locationData.state) { if ( '' != dir_address2 ) { dir_address2 += ' '; } dir_address2 += locationData.state };
					if (locationData.zip) { if ( '' != dir_address2 ) { dir_address2 += ' '; } dir_address2 += locationData.zip };
					if (locationData.country) { if ( '' != dir_address2 ) { dir_address2 += ' '; } dir_address2 += locationData.country };
					if ( '' != dir_address2 ) { dir_address += '(' + escape( dir_address2 ) + ')' };

					//html += '<a class="result_directions" href="http://google' + default_domain + '/maps?saddr=' + searchData.homeAddress + '&daddr=' + dir_address + '" target="_blank">' + get_directions_text + '</a>';
					html += '<a class="result_directions" style="display: none" href="#map_top" onclick="directions.computeDirections(null, dir_address, \'address\'); return false;">' + 'Add to Trip' + '</a>'; //TODO: Currently hidden on page. Custom injection for GmapDirections so instead of new page, user can add the site as a stop / waypoint on the map. TODO: Add as waypoint before calling computeDirections. Figure out if allow drop functionality. 
				}
				html += '</div>';
				html += '<div style="clear: both;"></div>';

				<?php if ( apply_filters( 'sm-show-results-description', false ) ) : ?>
				html += '<div class="sm-results-description">';
				html += locationData.description;
				html += '</div>';
				html += '<div style="clear:both;"></div>';
				<?php endif; ?>

				// Taxonomy lists
				for (jstax in locationData.taxes) {
					if ( locationData.taxes[jstax] != null && locationData.taxes[jstax] != '' ) {
						html += '<div class="' + jstax + '_list"><small><strong>' + taxonomy_text[jstax] + ':</strong> ' + locationData.taxes[jstax] + '</small></div>';
					}
				}

				// End of result
				html += '</div>';

				div.innerHTML = html;
				div.style.cursor = 'pointer'; 
				div.style.margin = 0;
				google.maps.event.addDomListener(div, 'click', function() {
					google.maps.event.trigger(marker, 'click');
				});
				return div;
			}
			<?php
			die();
		}

		// This function geocodes a location
		function geocode_location( $address='', $city='', $state='', $zip='', $country='', $key='' ) {
			$options = $this->get_options();
			// Create URL encoded comma separated list of address elements that != ''
			$to_geocode = urlencode( implode( ', ', array_filter( compact( 'address', 'city', 'state', 'zip', 'country' ) ) ) );

			// Base URL
			$base_url = SIMPLEMAP_MAPS_WS_API . 'geocode/json?sensor=false&region=' . substr( $options['default_domain'], strrpos( $options['default_domain'], '.' ) + 1 );

			// Add query
			$request_url = $base_url . "&address=" . $to_geocode;

			$response = wp_remote_get( $request_url );

			// TODO: Handle this situation better
			if ( ! is_wp_error( $response ) ) {
				$body = json_decode( $response['body'] );
				$status = $body->status;

				if ( $status == 'OK' ) {
					// Successful geocode
					//echo "<pre>";print_r( $body );die();
					$location = $body->results[0]->geometry->location;

					// Format: Longitude, Latitude, Altitude
					$lat = $location->lat;
					$lng = $location->lng;
				}

				return compact( 'body', 'status', 'lat', 'lng' );
			} else {
				return false;
			}
		}

		// Returns list of SimpleMap Taxonomies
		function get_sm_taxonomies( $format='array', $prefix='', $php_safe=false, $output='names' ) {

			$taxes = array();

			if ( $taxes = get_object_taxonomies( 'sm-location', $output ) ) {

				foreach( $taxes as $key => $tax ) {

					// Convert to PHP safe and add prefix
					if ( $php_safe && 'names' == $output )
						$taxes[$key] = str_replace( '-', '_', $prefix.$tax );
					elseif ( $php_safe )
						$taxes[$key]->name = str_replace( '-', '_', $prefix.$tax->name );

				}

			}

			// Convert to string if needed
			if ( 'string' == $format )
				$taxes = implode( ', ', $taxes );

			return $taxes;

		}

		function get_taxonomy_settings( $taxonomy = null ) {
			$standard_taxonomies = array(
				'sm-category' => array( 'singular' => 'Category', 'plural' => 'Categories', 'hierarchical' => true, 'field' => 'category' ),
				'sm-tag' => array( 'singular' => 'Tag', 'plural' => 'Tags', 'field' => 'tags' ),
				'sm-day' => array( 'singular' => 'Day', 'plural' => 'Days', 'field' => 'days', 'description' => 'day of week' ),
				'sm-time' => array( 'singular' => 'Time', 'plural' => 'Times', 'field' => 'times', 'description' => 'time of day' ),
			);

			if ( empty( $taxonomy ) ) {
				return $standard_taxonomies;
			}

			if ( isset( $standard_taxonomies[$taxonomy] ) ) {
				return $standard_taxonomies[$taxonomy];
			}
			else {
				$singular = ucwords( substr( $taxonomy, strpos( $taxonomy, '-' ) + 1 ) );
				return array( 'singular' => $singular, 'plural' => $singular . 's' );
			}
		}

		// This function returns the default SimpleMap options		
		function get_options() {
			$options = array();
			$saved = get_option( 'SimpleMap_options' );

			if ( !empty( $saved ) ) {
				$options = $saved;
			}

			static $default = null;
			if ( empty( $default ) ) {
				$default = array(
					'map_width' => '100%',
					'map_height' => '350px',
					'default_lat' => '44.968684',
					'default_lng' => '-93.215561',
					'zoom_level' => '10',
					'default_radius' => '10',
					'map_type' => 'ROADMAP',
					'special_text' => '',
					'default_state' => '',
					'default_country' => 'US',
					'default_language' => 'en',
					'default_domain' => '.com',
					'map_stylesheet' => 'inc/styles/light.css',
					'units' => 'mi',
					'autoload' => 'all',
					'lock_default_location' => false,
					'results_limit' => '20',
					'address_format' => 'town, province postalcode',
					'powered_by' => 0,
					'enable_permalinks' => 0,
					'permalink_slug' => 'location',
					'display_search' => 'show',
					'map_pages' => '0',
					'adsense_for_maps' => 0,
					'adsense_pub_id' => '',
					'adsense_channel_id' => '',
					'adsense_max_ads' => 2,
					//'api_key' => '',
					'auto_locate' => '',
					'taxonomies' => array(
						'sm-category' => $this->get_taxonomy_settings( 'sm-category' ),
						'sm-tag' => $this->get_taxonomy_settings( 'sm-tag' ),
					),
				);

				$valid_map_type_map = array(
					'ROADMAP' => 'ROADMAP',
					'SATELLITE' => 'SATELLITE',
					'HYBRID' => 'HYBRID',
					'TERRAIN' => 'TERRAIN',
					'G_NORMAL_MAP' => 'ROADMAP',
					'G_SATELLITE_MAP' => 'SATELLITE',
					'G_HYBRID_MAP' => 'HYBRID',
					'G_PHYSICAL_MAP' => 'TERRAIN',
				);

				if ( empty( $valid_map_type_map[$options['map_type']] ) ) {
					$options['map_type'] = $default['map_type'];
				}
				else {
					$options['map_type'] = $valid_map_type_map[$options['map_type']];
				}
			}

			$options += $default;

			if ( isset( $options['days_taxonomy'] ) ) {
				if ( ! empty( $options['days_taxonomy'] ) ) {
					$options['taxonomies']['sm-day'] = $this->get_taxonomy_settings( 'sm-day' );
				}
				unset( $options['days_taxonomy'] );
			}

			if ( isset( $options['time_taxonomy'] ) ) {
				if ( ! empty( $options['time_taxonomy'] ) ) {
					$options['taxonomies']['sm-time'] = $this->get_taxonomy_settings( 'sm-time' );
				}
				unset( $options['time_taxonomy'] );
			}

			if ( $saved != $options ) {
				update_option( 'SimpleMap_options', $options );
			}
			return $options;
		}
		
		// Google Domains
		function get_domain_options() {
			$domains_list = array(
				'United States' => '.com',
				'Austria' => '.at',
				'Australia' => '.com.au',
				'Bosnia and Herzegovina' => '.com.ba',
				'Belgium' => '.be',
				'Brazil' => '.com.br',
				'Canada' => '.ca',
				'Switzerland' => '.ch',
				'Czech Republic' => '.cz',
				'Germany' => '.de',
				'Denmark' => '.dk',
				'Spain' => '.es',
				'Finland' => '.fi',
				'France' => '.fr',
				'Italy' => '.it',
				'Japan' => '.jp',
				'Netherlands' => '.nl',
				'Norway' => '.no',
				'New Zealand' => '.co.nz',
				'Poland' => '.pl',
				'Russia' => '.ru',
				'Sweden' => '.se',
				'Taiwan' => '.tw',
				'United Kingdom' => '.co.uk',
				'South Africa' => '.co.za'
			);
			
			return apply_filters( 'sm-domain-list', $domains_list );
		}

		// Region list from http://code.google.com/apis/adwords/docs/appendix/provincecodes.html
		// Used for Maps v3 localization: http://code.google.com/apis/maps/documentation/javascript/basics.html#Localization
		function get_region_options() {
			$region_list = array(
				'US' => 'United States',
				'AR' => 'Argentina',
				'AU' => 'Australia',
				'AT' => 'Austria',
				'BE' => 'Belgium',
				'BR' => 'Brazil',
				'CA' => 'Canada',
				'CL' => 'Chile',
				'CN' => 'China',
				'CO' => 'Colombia',
				'HR' => 'Croatia',
				'CZ' => 'Czech Republic',
				'DK' => 'Denmark',
				'EG' => 'Egypt',
				'FI' => 'Finland',
				'FR' => 'France',
				'DE' => 'Germany',
				'HU' => 'Hungary',
				'IN' => 'India',
				'IE' => 'Ireland',
				'IL' => 'Israel',
				'IT' => 'Italy',
				'JP' => 'Japan',
				'MY' => 'Malaysia',
				'MX' => 'Mexico',
				'MA' => 'Morocco',
				'NL' => 'Netherlands',
				'NZ' => 'New Zealand',
				'NG' => 'Nigeria',
				'NO' => 'Norway',
				'PL' => 'Poland',
				'PT' => 'Portugal',
				'RU' => 'Russian Federation',
				'SA' => 'Saudi Arabia',
				'ZA' => 'South Africa',
				'KR' => 'South Korea',
				'ES' => 'Spain',
				'SE' => 'Sweden',
				'CH' => 'Switzerland',
				'TH' => 'Thailand',
				'TR' => 'Turkey',
				'UA' => 'Ukraine',
				'GB' => 'United Kingdom',
			);

			return apply_filters( 'sm-region-list', $region_list );
		}

		// Country list
		function get_country_options() {
			$country_list = array(
				'US' => 'United States',
				'AF' => 'Afghanistan',
				'AL' => 'Albania',
				'DZ' => 'Algeria',
				'AS' => 'American Samoa',
				'AD' => 'Andorra',
				'AO' => 'Angola',
				'AI' => 'Anguilla',
				'AQ' => 'Antarctica',
				'AG' => 'Antigua and Barbuda',
				'AR' => 'Argentina',
				'AM' => 'Armenia',
				'AW' => 'Aruba',
				'AU' => 'Australia',
				'AT' => 'Austria',
				'AZ' => 'Azerbaijan',
				'BS' => 'Bahamas',
				'BH' => 'Bahrain',
				'BD' => 'Bangladesh',
				'BB' => 'Barbados',
				'BY' => 'Belarus',
				'BE' => 'Belgium',
				'BZ' => 'Belize',
				'BJ' => 'Benin',
				'BM' => 'Bermuda',
				'BT' => 'Bhutan',
				'BO' => 'Bolivia',
				'BA' => 'Bosnia and Herzegowina',
				'BW' => 'Botswana',
				'BV' => 'Bouvet Island',
				'BR' => 'Brazil',
				'IO' => 'British Indian Ocean Territory',
				'BN' => 'Brunei Darussalam',
				'BG' => 'Bulgaria',
				'BF' => 'Burkina Faso',
				'BI' => 'Burundi',
				'KH' => 'Cambodia',
				'CM' => 'Cameroon',
				'CA' => 'Canada',
				'CV' => 'Cape Verde',
				'KY' => 'Cayman Islands',
				'CF' => 'Central African Republic',
				'TD' => 'Chad',
				'CL' => 'Chile',
				'CN' => 'China',
				'CX' => 'Christmas Island',
				'CC' => 'Cocos (Keeling) Islands',
				'CO' => 'Colombia',
				'KM' => 'Comoros',
				'CG' => 'Congo',
				'CD' => 'Congo, The Democratic Republic of the',
				'CK' => 'Cook Islands',
				'CR' => 'Costa Rica',
				'CI' => 'Cote D\'Ivoire',
				'HR' => 'Croatia (Local Name: Hrvatska)',
				'CU' => 'Cuba',
				'CY' => 'Cyprus',
				'CZ' => 'Czech Republic',
				'DK' => 'Denmark',
				'DJ' => 'Djibouti',
				'DM' => 'Dominica',
				'DO' => 'Dominican Republic',
				'TP' => 'East Timor',
				'EC' => 'Ecuador',
				'EG' => 'Egypt',
				'SV' => 'El Salvador',
				'GQ' => 'Equatorial Guinea',
				'ER' => 'Eritrea',
				'EE' => 'Estonia',
				'ET' => 'Ethiopia',
				'FK' => 'Falkland Islands (Malvinas)',
				'FO' => 'Faroe Islands',
				'FJ' => 'Fiji',
				'FI' => 'Finland',
				'FR' => 'France',
				'FX' => 'France, Metropolitan',
				'GF' => 'French Guiana',
				'PF' => 'French Polynesia',
				'TF' => 'French Southern Territories',
				'GA' => 'Gabon',
				'GM' => 'Gambia',
				'GE' => 'Georgia',
				'DE' => 'Germany',
				'GH' => 'Ghana',
				'GI' => 'Gibraltar',
				'GR' => 'Greece',
				'GL' => 'Greenland',
				'GD' => 'Grenada',
				'GP' => 'Guadeloupe',
				'GU' => 'Guam',
				'GT' => 'Guatemala',
				'GN' => 'Guinea',
				'GW' => 'Guinea-Bissau',
				'GY' => 'Guyana',
				'HT' => 'Haiti',
				'HM' => 'Heard and Mc Donald Islands',
				'VA' => 'Holy See (Vatican City State)',
				'HN' => 'Honduras',
				'HK' => 'Hong Kong',
				'HU' => 'Hungary',
				'IS' => 'Iceland',
				'IN' => 'India',
				'ID' => 'Indonesia',
				'IR' => 'Iran (Islamic Republic of)',
				'IQ' => 'Iraq',
				'IE' => 'Ireland',
				'IL' => 'Israel',
				'IT' => 'Italy',
				'JM' => 'Jamaica',
				'JP' => 'Japan',
				'JO' => 'Jordan',
				'KZ' => 'Kazakhstan',
				'KE' => 'Kenya',
				'KI' => 'Kiribati',
				'KP' => 'Korea, Democratic People\'s Republic of',
				'KR' => 'Korea, Republic of',
				'KW' => 'Kuwait',
				'KG' => 'Kyrgyzstan',
				'LA' => 'Lao People\'s Democratic Republic',
				'LV' => 'Latvia',
				'LB' => 'Lebanon',
				'LS' => 'Lesotho',
				'LR' => 'Liberia',
				'LY' => 'Libyan Arab Jamahiriya',
				'LI' => 'Liechtenstein',
				'LT' => 'Lithuania',
				'LU' => 'Luxembourg',
				'MO' => 'Macau',
				'MK' => 'Macedonia, Former Yugoslav Republic of',
				'MG' => 'Madagascar',
				'MW' => 'Malawi',
				'MY' => 'Malaysia',
				'MV' => 'Maldives',
				'ML' => 'Mali',
				'MT' => 'Malta',
				'MH' => 'Marshall Islands',
				'MQ' => 'Martinique',
				'MR' => 'Mauritania',
				'MU' => 'Mauritius',
				'YT' => 'Mayotte',
				'MX' => 'Mexico',
				'FM' => 'Micronesia, Federated States of',
				'MD' => 'Moldova, Republic of',
				'MC' => 'Monaco',
				'MN' => 'Mongolia',
				'MS' => 'Montserrat',
				'MA' => 'Morocco',
				'MZ' => 'Mozambique',
				'MM' => 'Myanmar',
				'NA' => 'Namibia',
				'NR' => 'Nauru',
				'NP' => 'Nepal',
				'NL' => 'Netherlands',
				'AN' => 'Netherlands Antilles',
				'NC' => 'New Caledonia',
				'NZ' => 'New Zealand',
				'NI' => 'Nicaragua',
				'NE' => 'Niger',
				'NG' => 'Nigeria',
				'NU' => 'Niue',
				'NF' => 'Norfolk Island',
				'MP' => 'Northern Mariana Islands',
				'NO' => 'Norway',
				'OM' => 'Oman',
				'PK' => 'Pakistan',
				'PW' => 'Palau',
				'PA' => 'Panama',
				'PG' => 'Papua New Guinea',
				'PY' => 'Paraguay',
				'PE' => 'Peru',
				'PH' => 'Philippines',
				'PN' => 'Pitcairn',
				'PL' => 'Poland',
				'PT' => 'Portugal',
				'PR' => 'Puerto Rico',
				'QA' => 'Qatar',
				'RE' => 'Reunion',
				'RO' => 'Romania',
				'RU' => 'Russian Federation',
				'RW' => 'Rwanda',
				'KN' => 'Saint Kitts and Nevis',
				'LC' => 'Saint Lucia',
				'VC' => 'Saint Vincent and The Grenadines',
				'WS' => 'Samoa',
				'SM' => 'San Marino',
				'ST' => 'Sao Tome And Principe',
				'SA' => 'Saudi Arabia',
				'SN' => 'Senegal',
				'SC' => 'Seychelles',
				'SL' => 'Sierra Leone',
				'SG' => 'Singapore',
				'SK' => 'Slovakia (Slovak Republic)',
				'SI' => 'Slovenia',
				'SB' => 'Solomon Islands',
				'SO' => 'Somalia',
				'ZA' => 'South Africa',
				'GS' => 'South Georgia, South Sandwich Islands',
				'ES' => 'Spain',
				'LK' => 'Sri Lanka',
				'SH' => 'St. Helena',
				'PM' => 'St. Pierre and Miquelon',
				'SD' => 'Sudan',
				'SR' => 'Suriname',
				'SJ' => 'Svalbard and Jan Mayen Islands',
				'SZ' => 'Swaziland',
				'SE' => 'Sweden',
				'CH' => 'Switzerland',
				'SY' => 'Syrian Arab Republic',
				'TW' => 'Taiwan',
				'TJ' => 'Tajikistan',
				'TZ' => 'Tanzania, United Republic of',
				'TH' => 'Thailand',
				'TG' => 'Togo',
				'TK' => 'Tokelau',
				'TO' => 'Tonga',
				'TT' => 'Trinidad and Tobago',
				'TN' => 'Tunisia',
				'TR' => 'Turkey',
				'TM' => 'Turkmenistan',
				'TC' => 'Turks and Caicos Islands',
				'TV' => 'Tuvalu',
				'UG' => 'Uganda',
				'UA' => 'Ukraine',
				'AE' => 'United Arab Emirates',
				'GB' => 'United Kingdom',
				'UM' => 'United States Minor Outlying Islands',
				'UY' => 'Uruguay',
				'UZ' => 'Uzbekistan',
				'VU' => 'Vanuatu',
				'VE' => 'Venezuela',
				'VN' => 'Vietnam',
				'VG' => 'Virgin Islands (British)',
				'VI' => 'Virgin Islands (U.S.)',
				'WF' => 'Wallis and Futuna Islands',
				'EH' => 'Western Sahara',
				'YE' => 'Yemen',
				'YU' => 'Yugoslavia',
				'ZM' => 'Zambia',
				'ZW' => 'Zimbabwe'
			);

			return apply_filters( 'sm-country-list', $country_list );
		}

		// Region list from http://code.google.com/apis/maps/faq.html#languagesupport
		// Used for Maps v3 localization: http://code.google.com/apis/maps/documentation/javascript/basics.html#Localization
		function get_language_options() {
			$language_list = array(
				'ar' => 'Arabic',
				'eu' => 'Basque',
				'bg' => 'Bulgarian',
				'bn' => 'Bengali',
				'ca' => 'Catalan',
				'cs' => 'Czech',
				'da' => 'Danish',
				'de' => 'German',
				'el' => 'Greek',
				'en' => 'English',
				'en-AU' => 'English (Australian)',
				'en-GB' => 'English (Great Britain)',
				'es' => 'Spanish',
				'eu' => 'Basque',
				'fa' => 'Farsi',
				'fi' => 'Finnish',
				'fil' => 'Filipino',
				'fr' => 'French',
				'gl' => 'Galician',
				'gu' => 'Gujarati',
				'hi' => 'Hindi',
				'hr' => 'Croatian',
				'hu' => 'Hungarian',
				'id' => 'Indonesian',
				'it' => 'Italian',
				'iw' => 'Hebrew',
				'ja' => 'Japanese',
				'kn' => 'Kannada',
				'ko' => 'Korean',
				'lt' => 'Lithuanian',
				'lv' => 'Latvian',
				'ml' => 'Malayalam',
				'mr' => 'Marathi',
				'nl' => 'Dutch',
				'no' => 'Norwegian',
				'pl' => 'Polish',
				'pt' => 'Portuguese',
				'pt-BR' => 'Portuguese (Brazil)',
				'pt-PT' => 'Portuguese (Portugal)',
				'ro' => 'Romanian',
				'ru' => 'Russian',
				'sk' => 'Slovak',
				'sl' => 'Slovenian',
				'sr' => 'Serbian',
				'sv' => 'Swedish',
				'tl' => 'Tagalog',
				'ta' => 'Tamil',
				'te' => 'Telugu',
				'th' => 'Thai',
				'tr' => 'Turkish',
				'uk' => 'Ukrainian',
				'vi' => 'Vietnamese',
				'zh-CN' => 'Chinese (Simplified)',
				'zh-TW' => 'Chinese (Traditional)',
			);

			return apply_filters( 'sm-language-list', $language_list );
		}

		function get_auto_locate_options() {
			$auto_locate_list = array(
				'' => 'No Automatic Location',
				'ip' => 'Use IP Address',
				'html5' => 'Use HTML5',
			);

			return apply_filters( 'sm-auto-locte-list', $auto_locate_list );
		}

		// Echo the toolbar
		function show_toolbar( $title = '' ) {
			global $simple_map;
			$options = $simple_map->get_options();
			if ( '' == $title )
				$title = 'SimpleMap';
			?>
			<table class="sm-toolbar" cellspacing="0" cellpadding="0" border="0">
				<tr>
					<td class="sm-page-title">
						<h2><?php _e( $title, 'SimpleMap' ); ?></h2>
					</td>
					<td class="sm-toolbar-item">
						<a href="http://simplemap-plugin.com" target="_blank" title="<?php _e( 'Go to the SimpleMap Home Page', 'SimpleMap' ); ?>"><?php _e( 'SimpleMap Home Page', 'SimpleMap' ); ?></a>
					</td>
					<td class="sm-toolbar-item">
						<a href="<?php echo admin_url( 'admin.php?page=simplemap-help' ); ?>" title="<?php _e( 'Premium Support', 'SimpleMap' ); ?>"><?php _e( 'Premium Support', 'SimpleMap' ); ?></a>
					</td>
					<td class="sm-toolbar-item">
						<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
						<input type="hidden" name="cmd" value="_s-xclick" />
						<input type="hidden" name="hosted_button_id" value="DTJBYXGQFSW64" />
						<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" style="border: none;" name="submit" alt="PayPal - The safer, easier way to pay online!" />
						<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1" />
						</form>
					</td>
				</tr>
			</table>

			<?php
			/*
			if ( !isset( $options['api_key'] ) || $options['api_key'] == '' )
				echo '<div class="error"><p>' . __( 'You must enter an API key for your domain.', 'SimpleMap' ).' <a href="' . admin_url( 'admin.php?page=simplemap' ) . '">' . __( 'Enter a key on the General Options page.', 'SimpleMap' ) . '</a></p></div>';
			*/
		}

		// Return the available search_radii
		function get_search_radii(){
			$search_radii = array( 1, 5, 10, 25, 50, 100, 500, 1000 );
			return apply_filters( 'sm-search-radii', $search_radii );
		}

		// What link are we using for google's API
		function get_api_link() {
			$lo = str_replace('_', '-', get_locale());
			$l = substr($lo, 0, 2);
			switch($l) {
				case 'es':
				case 'de':
				case 'ja':
				case 'ko':
				case 'ru':
					$api_link = "http://code.google.com/intl/$l/apis/maps/signup.html";
					break;
				case 'pt':
				case 'zh':
					$api_link = "http://code.google.com/intl/$lo/apis/maps/signup.html";
					break;
				case 'en':
				default:
					$api_link = "http://code.google.com/apis/maps/signup.html";
					break;
			}
			return $api_link;
		}

		// Returns true if legacy tables exist in the DB
		function legacy_tables_exist() {
			global $wpdb;

			$sql = "SHOW TABLES LIKE '" . $wpdb->prefix . "simple_map'";
			if ( $tables = $wpdb->get_results( $sql ) ) {
				return true;
			}

			return false;
		}

		// Search form / widget query vars
		function register_query_vars( $vars ) {

			$vars[] = 'location_search_address';
			$vars[] = 'location_search_city';
			$vars[]	= 'location_search_state';
			$vars[] = 'location_search_zip';
			$vars[] = 'location_search_distance';
			$vars[] = 'location_search_limit';
			$vars[] = 'location_is_search_results';

			return $vars;
		}

		/**
		 * Parses the shortcode attributes with the default options and returns array
		 *
		 * @since 2.3
		 */
		function parse_shortcode_atts( $shortcode_atts ) {
			$options			= $this->get_options();
			$default_atts		= $this->get_default_shortcode_atts();
			$atts			 	= shortcode_atts( $default_atts, $shortcode_atts );

			// If deprecated shortcodes were used, replace with current ones
			if ( isset( $atts['show_categories_filter'] ) )
				$atts['show_sm_category_filter'] = $atts['show_categories_filter'];
			if ( isset( $atts['show_tags_filter'] ) )
				$atts['show_sm_tag_filter'] = $atts['show_tags_filter'];
			if ( isset( $atts['show_days_filter'] ) )
				$atts['show_sm_day_filter'] = $atts['show_days_filter'];
			if ( isset( $atts['show_times_filter'] ) )
				$atts['show_sm_time_filter'] = $atts['show_times_filter'];
			if ( isset( $atts['categories'] ) )
				$atts['sm_category'] = $atts['categories'];			
			if ( isset( $atts['tags'] ) )
				$atts['sm_tag'] = $atts['tags'];			
			if ( isset( $atts['days'] ) )
				$atts['sm_day'] = $atts['days'];			
			if ( isset( $atts['times'] ) )
				$atts['sm_time'] = $atts['times'];			

			// Determine if we need to hide the search form or not
			if ( '' == $atts['hide_search'] ) {
				// Use default value
				if ( 'show' == $options['display_search'] )
					$atts['hide_search'] = 0;
				else
					$atts['hide_search'] = 1;
			} 

			// Set categories and tags to available equivelants 
			$atts['avail_sm_category'] 	= $atts['sm_category'];
			$atts['avail_sm_tag'] 		= $atts['sm_tag'];
			$atts['avail_sm_day'] 		= $atts['sm_day'];
			$atts['avail_sm_time'] 		= $atts['sm_time'];

			// Default lat / lng from shortcode?
			if ( ! $atts['default_lat'] ) 
				$atts['default_lat'] = $options['default_lat'];
			if ( ! $atts['default_lng'] )
				$atts['default_lng'] = $options['default_lng'];

			// Doing powered by?
			if ( '' == $atts['powered_by'] ) {

				// Use default value
				$atts['powered_by'] = $options['powered_by'];

			} else {

				// Use shortcode
				if ( 0 == $atts['powered_by'] )
					$atts['powered_by'] = 0;
				else
					$atts['powered_by'] = 1;

			}

			// Default units or shortcode units?
			if ( 'km' != $atts['units'] && 'mi' != $atts['units'] )
				$atts['units'] = $options['units'];

			// Default radius or shortcode radius?
			if ( '' != $atts['radius'] && in_array( $atts['radius'], $this->get_search_radii() ) )
				$atts['radius'] = absint( $atts['radius'] );
			else
				$atts['radius'] = $options['default_radius'];

			//Make sure we have limit
			if ( '' == $atts['limit'] )
				$atts['limit'] = $options['results_limit'];

			// Clean search_field_cols
			if ( 0 === absint( $atts['search_form_cols'] ) )
				$atts['search_form_cols'] = $default_atts['search_form_cols'];

			// Which type of map are we using?
			if ( '' == $atts['map_type'] )
				$atts['map_type'] = $options['map_type'];

			// Height of the map?
			if ( '' == $atts['map_height'] )
				$atts['map_height'] = $options['map_height'];

			// Width of the map?
			if ( '' == $atts['map_width'] )
				$atts['map_width'] = $options['map_width'];

			// Which zoom level are we using?
			if ( '' == $atts['zoom_level'] )
				$atts['zoom_level'] = $options['zoom_level'];

			// Which autoload option are we using?
			if ( '' == $atts['autoload'] )
				$atts['autoload'] = $options['autoload'];

			// Return final array
			return $atts;
		}
		
		/**
		 * Returns default shortcode attributes
		 *
		 * @since 2.3
		 */
		function get_default_shortcode_atts() {
			$options = $this->get_options();

			$tax_atts = array();
			$tax_search_fields = array();
			foreach ( $options['taxonomies'] as $taxonomy => $taxonomy_info ) {
				$tax_search_fields[] = "||labeltd_$taxonomy||empty";

				$safe_tax = str_replace('-', '_', $taxonomy);
				$tax_atts[$safe_tax] = '';
				$tax_atts['show_' . $safe_tax . '_filter'] = 1;

				// The following are deprecated. Don't use them.
				$tax_atts[strtolower($taxonomy_info['plural'])] = null;
				$tax_atts['show_' . strtolower($taxonomy_info['plural']) . '_filter'] = null;
			}

			$atts = $tax_atts + array(
				'search_title'				=> __( 'Find Locations Near:', 'SimpleMap' ), 
				'search_form_type'			=> 'table', 
				'search_form_cols'			=> 3, 
				'search_fields'				=> 'labelbr_street||labelbr_city||labelbr_state||labelbr_zip||empty||empty||labeltd_distance||empty' . implode('', $tax_search_fields) . '||submit||empty||empty', 
				'taxonomy_field_type'		=> 'checkboxes',
				'hide_search'				=> '', 
				'hide_map'					=> 0, 
				'hide_list'					=> 0, 
				'default_lat'				=> 0, 
				'default_lng'				=> 0, 
				'adsense_publisher_id'		=> 0, 
				'adsense_channel_id'		=> 0, 
				'adsense_max_ads'			=> 0,
				'map_width'					=> '', 
				'map_height'				=> '', 
				'units'						=> '',
				'radius'					=> '',
				'limit'						=> '',
				'autoload'					=> '',
				'zoom_level'				=> '',
				'map_type'					=> '',
				'powered_by'				=> '', 
				'sm_day'					=> '',
				'sm_time'					=> ''
			);

			return apply_filters( 'sm-default-shortcode-atts', $atts );
		}

		// This function filters category text labels
		function backwards_compat_categories_text( $text ) {
			return __( 'Categories', 'SimpleMap' );
		}

		// This function filters category text labels
		function backwards_compat_tags_text( $text ) {
			return __( 'Tags', 'SimpleMap' );
		}

		// This function filters category text labels
		function backwards_compat_days_text( $text ) {
			return __( 'Days', 'SimpleMap' );
		}

		// This function filters category text labels
		function backwards_compat_times_text( $text ) {
			return __( 'Times', 'SimpleMap' );
		}

	}	
}
