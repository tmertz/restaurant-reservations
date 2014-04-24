<?php
/**
 * Class to handle all custom post type definitions for Restaurant Reservations
 */

if ( !defined( 'ABSPATH' ) )
	exit;

if ( !class_exists( 'rtbCustomPostTypes' ) ) {
class rtbCustomPostTypes {

	// Array of valid post statuses
	// @sa set_booking_statuses()
	public $booking_statuses = array();

	// Cached select fields for booking stasuses
	public $status_select_html = array();

	public function __construct() {

		// Call when plugin is initialized on every page load
		add_action( 'init', array( $this, 'load_cpts' ) );

		// Set up $booking_statuses array and register new post statuses
		add_action( 'init', array( $this, 'set_booking_statuses' ) );

	}

	/**
	 * Initialize custom post types
	 * @since 0.1
	 */
	public function load_cpts() {

		// Define the booking custom post type
		$args = array(
			'labels' => array(
				'name'               => __( 'Bookings',                   RTB_TEXTDOMAIN ),
				'singular_name'      => __( 'Booking',                    RTB_TEXTDOMAIN ),
				'menu_name'          => __( 'Bookings',                   RTB_TEXTDOMAIN ),
				'name_admin_bar'     => __( 'Bookings',                   RTB_TEXTDOMAIN ),
				'add_new'            => __( 'Add New',                 	  RTB_TEXTDOMAIN ),
				'add_new_item'       => __( 'Add New Booking',            RTB_TEXTDOMAIN ),
				'edit_item'          => __( 'Edit Booking',               RTB_TEXTDOMAIN ),
				'new_item'           => __( 'New Booking',                RTB_TEXTDOMAIN ),
				'view_item'          => __( 'View Booking',               RTB_TEXTDOMAIN ),
				'search_items'       => __( 'Search Bookings',            RTB_TEXTDOMAIN ),
				'not_found'          => __( 'No bookings found',          RTB_TEXTDOMAIN ),
				'not_found_in_trash' => __( 'No bookings found in trash', RTB_TEXTDOMAIN ),
				'all_items'          => __( 'All Bookings',               RTB_TEXTDOMAIN ),
			),
			'menu_icon' => 'dashicons-calendar',
			'public' => false,
			'supports' => array(
				'title',
				'revisions'
			)
		);

		// Create filter so addons can modify the arguments
		$args = apply_filters( 'rtb_booking_args', $args );

		// Add an action so addons can hook in before the post type is registered
		do_action( 'rtb_booking_pre_register' );

		// Register the post type
		register_post_type( RTB_BOOKING_POST_TYPE, $args );

		// Add an action so addons can hook in after the post type is registered
		do_action( 'rtb_booking_post_register' );
	}

	/**
	 * Set an array of valid booking statuses and register any custom statuses
	 * @since 0.0.1
	 */
	public function set_booking_statuses() {

		$this->booking_statuses['pending'] = array(
			'label'						=> _x( 'Pending', 'Booking status when it is pending review', RTB_TEXTDOMAIN ),
			'default'					=> true, // Whether or not this status is part of WP Core
			'user_selectable'			=> true, // Whether or not a user can set a booking to this status
		);

		$this->booking_statuses['confirmed'] = array (
			'label'                     => _x( 'Confirmed', 'Booking status for a confirmed booking', RTB_TEXTDOMAIN ),
			'default'					=> false, // Whether or not this status is part of WP Core
			'user_selectable'			=> true, // Whether or not a user can set a booking to this status
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Confirmed <span class="count">(%s)</span>', 'Confirmed <span class="count">(%s)</span>', RTB_TEXTDOMAIN ),
		);

		$this->booking_statuses['closed'] = array(
			'label'                     => _x( 'Closed', 'Booking status for a closed booking', RTB_TEXTDOMAIN ),
			'default'					=> false, // Whether or not this status is part of WP Core
			'user_selectable'			=> true, // Whether or not a user can set a booking to this status
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Closed <span class="count">(%s)</span>', 'Closed <span class="count">(%s)</span>', RTB_TEXTDOMAIN )
		);

		// Let addons hook in to add/edit/remove post statuses
		$this->booking_statuses = apply_filters( 'rtb_post_statuses_args', $this->booking_statuses );

		// Register the custom post statuses
		foreach ( $this->booking_statuses as $status => $args ) {
			if ( $args['default'] === false ) {
				register_post_status( $status, $args );
			}
		}

	}

	/**
	 * Print an HTML element to select a booking status
	 * @since 0.0.1
	 * @note This is no longer used in the bookings table, but it could be
	 *	useful in the future, so leave it in for now (0.0.1) until the plugin is
	 *	more fleshed out.
	 */
	public function print_booking_status_select( $current = false ) {

		if ( $current === false ) {
			$current = 'none';
		}

		// Output stored select field if available
		if ( !empty( $this->status_select_html[$current] ) ) {
			return $this->status_select_html[$current];
		}

		ob_start();
		?>

		<select name="rtb-select-status">
		<?php foreach ( $this->booking_statuses as $status => $args ) : ?>
			<?php if ( $args['user_selectable'] === true ) : ?>
			<option value="<?php echo esc_attr( $status ); ?>"<?php echo $status == $current ? ' selected="selected"' : ''; ?>><?php echo esc_attr( $args['label'] ); ?></option>
			<?php endif; ?>
		<?php endforeach; ?>
		</select>

		<?php
		$output = ob_get_clean();

		// Store output so we don't need to loop for every row
		$this->status_select_html[$current] = $output;

		return $output;

	}

	/**
	 * Delete a booking request (or send to trash)
	 *
	 * @since 0.0.1
	 * @todo roles/capabilities check
	 */
	public function delete_booking( $id ) {

		// If we're already looking at trashed posts, delete it for good.
		// Otherwise, just send it to trash.
		if ( !empty( $_GET['status'] ) && $_GET['status'] == 'trash' ) {
			$screen = get_current_screen();
			if ( $screen->base == 'toplevel_page_rtb-bookings' ) {
				$result = wp_delete_post( $id, true );
			}
		} else {
			$result = wp_trash_post( $id );
		}

		if ( $result === false ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Update a booking status.
	 * @since 0.0.1
	 * @todo roles/capabilities check
	 */
	function update_booking_status( $id, $status ) {

		if ( !$this->is_valid_booking_status( $status ) ) {
			return false;
		}

		$booking = get_post( $id );

		if ( is_wp_error( $booking ) || !is_object( $booking ) ) {
			return false;
		}

		if ( $booking->post_status === $status ) {
			return null;
		}

		$result = wp_update_post(
			array(
				'ID'			=> $id,
				'post_status'	=> $status,
				'edit_date'		=> current_time( 'mysql' ),
			)
		);

		return $result ? true : false;
	}

	/**
	 * Check if status is valid for bookings
	 * @since 0.0.1
	 */
	public function is_valid_booking_status( $status ) {
		return isset( $this->booking_statuses[$status] ) ? true : false;
	}

}
} // endif;