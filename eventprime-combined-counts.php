<?php
/**
 * Plugin Name:       EventPrime Combined Counted
 * Plugin URI:        https://web321.co/plugins/eventprime-counts
 * Description:       An EventPrime extension that tracks all of the tickets sold under an event.
 * Version:           3.0.1
 * Requires at least: 6.0
 * Tested up to:      6.2.2
 * Author:            dewolfe001
 * Author URI:        https://web321.co/
 * Donate:		      https://www.paypal.com/paypalme/web321co/10/
 * Text Domain:       eventprime-combined-counts
 * Domain Path:       /languages
 */

 https://www.paypal.com/paypalme/web321co/10/

defined( 'ABSPATH' ) || exit;
define( 'EPCC_NAME', plugin_basename( __FILE__ ));

if (epcc_plugin_active('event-prime.php', false)) {

    include_once(ABSPATH . "wp-includes/class-wp-rewrite.php");    
    if (!function_exists('wp_get_current_user')) {
        include_once(ABSPATH . "wp-includes/pluggable.php"); 
    }
    
	// front end functionality and checking
	new EventM_Combined_Count_FrontEnd();

	// backend meta boxes
	if (current_user_can('edit_posts') || current_user_can('edit_em_events')) {
		new EventM_Combined_Count_Admin_Meta_Boxes();
	}
}

add_filter('plugin_row_meta', 'epcc_row_meta', 10, 2);

// donation link
function epcc_row_meta( $links, $file ) {    
    if (EPCC_NAME == $file ) {
        $row_meta = array(
          'donate'    => '<a href="' . esc_url( 'https://www.paypal.com/paypalme/web321co/10/' ) . '" target="_blank" aria-label="' . esc_attr__( 'Donate', 'eventprime-counts' ) . '" >' . esc_html__( 'Donate', 'eventprime-counts' ) . '</a>'
        );
        return array_merge( $links, $row_meta );
    }
    return (array) $links;
}

function epcc_plugin_active($plugin_file, $bool_return = false) {
    $plugins = (array) get_option('active_plugins', array());
    foreach ($plugins as $plugin) {
        if (strpos($plugin, $plugin_file)) {
            if ($bool_return) {
                return explode('/', $plugin);    
            }
            else {
                return true;
            }
        }
    }
    return false;
}

// look for the tickets associated with an event

function epcc_ticket_count( $event_id ) {    
	if (intval($event_id) < 1) {
		return [];
	}
	global $wpdb;
	$ticket_table_name = $wpdb->prefix.'em_price_options';
	$ticket_data = $wpdb->get_results($wpdb->prepare( "SELECT * FROM $ticket_table_name WHERE event_id = %d", $event_id ));
	return $ticket_data;
}

/* 

replacement code for the function at
- /eventprime-event-calendar-management/includes/core/ep-utility-functions.php
- the original code has the ep_get_available_tickets renamed to "xep_get_available_tickets"

*/

function ep_get_available_tickets($event, $ticket){
    $all_event_bookings = EventM_Factory_Service::get_event_booking_by_event_id( $event->em_id, true );
    // look at event_id

    $em_combined_count = get_post_meta($ticket->event_id, 'em_combined_count');
    $per_cap = false;
	if (array_key_exists('ep-policy-default', $em_combined_count[0])) {
        if ($em_combined_count[0]['ep-policy-default'] == 'per-cap') {
            $per_cap = true;
        }
	}

    if ($per_cap) {
        $remaining_caps = $em_combined_count[0]['ep-policy-cap'];

        $booked_tickets_data = $all_event_bookings['tickets'];
        if( ! empty( $booked_tickets_data ) ) {
            $booked_ticket_qty = 0;
            foreach ($all_event_bookings['tickets'] as $t_id => $qty) {
                $booked_ticket_qty += absint( $qty );
            }
            if( $booked_ticket_qty > 0 ) {
                $remaining_caps -= $booked_ticket_qty;
                if( $remaining_caps < 1 ) {
                    $remaining_caps = 0;
                }
            }
        }
    }
    else {
        // use the default
        $remaining_caps = $ticket->capacity;
        
        $booked_tickets_data = $all_event_bookings['tickets'];
        if( ! empty( $booked_tickets_data ) ) {
            if( isset( $booked_tickets_data[$ticket->id] ) && ! empty( $booked_tickets_data[$ticket->id] ) ) {
                $booked_ticket_qty = absint( $booked_tickets_data[$ticket->id] );
                if( $booked_ticket_qty > 0 ) {
                    $remaining_caps = $ticket->capacity - $booked_ticket_qty;
                    if( $remaining_caps < 1 ) {
                        $remaining_caps = 0;
                    }
                }
            }
        }
    }
    
    return $remaining_caps;
}


/*
 *	Two classes: 
 *	- meta box for editing rules for each event
 *	- frontend for checking if the rules are violated
 */


/**
 * Class for admin Booking meta boxes
 */
class EventM_Combined_Count_Admin_Meta_Boxes {

	/**
	 * Constructor
	 */
	 
	public $extensions = []; 
	 
	public function __construct() {

        $this->define_constants();
        $this->load_textdomain();
        $this->define_hooks();
        $this->extensions[] = 'combined_counts';
    }

    public function define_constants(){
        if (!defined( 'EP_EPCC_DIR' )) {
		    define( 'EP_EPCC_DIR', plugin_dir_path( __FILE__ ));
        }
        if (!defined( 'EP_EPCC_URL' )) {            
		    define( 'EP_EPCC_URL', plugin_dir_url( __FILE__ ));
        }
        if (!defined( 'EPPC_VER' )) {
		    define ('EPPC_VER','3.0.1');
        }
    }

    public function load_textdomain() {
        load_plugin_textdomain( 'eventprime-combined-counts', false, EP_EPCC_DIR . '/languages/' );
    }

    public function define_hooks() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_epcc_meta_box_scripts' ] );
		add_action( 'ep_event_tab_content', [ $this, 'ep_epcc_setting_box' ], 20 );
        add_action( 'save_post', [$this, 'ep_epcc_save_meta_box_data'] );
		
        add_filter( 'ep_event_meta_tabs', [ $this, 'ep_event_meta_tabs' ] );
    }

    /**
     * Cloning is forbidden.
     *
     * @since 3.0
     */
    public function __clone() {
        _doing_it_wrong(__FUNCTION__, __('Cloning is forbidden.', 'eventprime-event-calendar-management'), $this->version);
    }

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 3.0
     */
    public function __wakeup() {
        _doing_it_wrong(__FUNCTION__, __('Unserializing instances of this class is forbidden.', 'eventprime-event-calendar-management'), $this->version);
    }			

	/**
	 * Enqueue meta box scripts
	*/

    
    public function ep_event_meta_tabs($tabs) {
        $args = func_get_args();
        // Log all the arguments passed to this filter
        // error_log('Tabs passed to custom_filter_ep_event_meta_tabs: ' . print_r($tabs, true));
        // error_log('Arguments passed to custom_filter_ep_event_meta_tabs: ' . print_r($args, true));
    
        $tabs['combined_counts'] =
            [
                'label' => 'Combined Counts',
                'target' => 'ep_epcc_data',
                'class' => [0 => 'ep_epcc_setting_box'],
                'priority' => 25
            ];
    
        return $tabs;
    }

	/**
	 * Register meta box for event
	 */
	public function ep_epcc_register_meta_boxes() {
		add_meta_box(
			'ep_epcc_setting_box',
			esc_html__( 'Combined Counts', 'eventprime-event-calendar-management' ),
			array( $this, 'ep_epcc_setting_box' ),
			'em_epcc', 'normal', 'high'
		);

		// do anyways
		do_action( 'ep_bookings_register_meta_boxes_addon');
	}

	public function enqueue_admin_epcc_meta_box_scripts() {
		$current_screen = get_current_screen();
		if( $current_screen ->post_type === "em_event" ) {
			wp_enqueue_style('em-admin-jquery-ui');
			wp_enqueue_style(
				'em-epcc-css',
				EP_EPCC_URL . 'assets/css/ep-epcc-admin.css',
				false, EVENTPRIME_VERSION
			);

			wp_enqueue_script(
				'em-epcc-js',
				EP_EPCC_URL . 'assets/js/ep-epcc-admin.js',
				array( 'jquery' ), EVENTPRIME_VERSION
			);
		}
	}

	public function ep_epcc_setting_box(){
		// Meta Boxes
		$ep = epcc_plugin_active('event-prime.php', true);		
        include_once plugin_dir_path(__FILE__) . '../' . $ep[0] . '/' . $ep[1];
        EP();

        ob_start(); 
		include_once EP_EPCC_DIR . '/meta-boxes/meta-box-ticket-combined-count-panel-html.php';
        $ep_epcc_setting_box = ob_get_clean();

		print $ep_epcc_setting_box;
	}
 
    public function ep_epcc_save_meta_box_data( $post_id ) {
        
        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
    
        // Check the user's permissions.
        if ( isset( $_POST['post_type'] ) && 'em_event' !== $_POST['post_type'] ) {
            return;
        }
        if (!current_user_can('edit_posts') && !current_user_can('edit_em_events')) {
            return;
        }
    
        // Make sure that it is set.
        if ( ! isset( $_POST['ep-policy-default'] ) || ! isset( $_POST['ep-policy-cap'] ) ) {
            return;
        }
    
        // Sanitize user input and update the meta field in the database.
        $ep_policy_default = sanitize_text_field( $_POST['ep-policy-default'] );
        $ep_policy_cap = intval( $_POST['ep-policy-cap'] );

        update_post_meta( $post_id, 'em_combined_count', array(
            'ep-policy-default' => $ep_policy_default,
            'ep-policy-cap' => $ep_policy_cap,
        ) );
    }
}

/*

extend 
get_event_all_tickets

$tickets = $event_controller->get_event_all_tickets($event);
change $ticket->capacity is each ticket IF 

$em_combined_count = get_post_meta( $post->ID, 'em_combined_count', true );

if ($em_combined_count['ep-policy-default'] == 'per-cap')
$cap = $em_combined_count['ep-policy-cap'];

*/

class EventM_Combined_Count_FrontEnd {

	/**
	 * Constructor
	 */
	public $extensions = []; 
	
	public function __construct() {

        $this->define_constants();
        $this->load_textdomain();
        $this->define_hooks();
		$this->extensions[] = 'combined_counts';
	
    }

    public function define_constants(){
        if (!defined( 'EP_EPCC_DIR' )) {
		    define( 'EP_EPCC_DIR', plugin_dir_path( __FILE__ ));
        }
        if (!defined( 'EP_EPCC_URL' )) {            
		    define( 'EP_EPCC_URL', plugin_dir_url( __FILE__ ));
        }
        if (!defined( 'EPPC_VER' )) {
		    define ('EPPC_VER','3.0.1');
        }
    }

    // standing up similar but different functions for capacity review and changes
    
    public function ticket_data($elements) {
        foreach ($element as $id => $details) {
            if ($details->em_combined_count['ep-policy-default'] == 'per-cap') {
                $per_cap = $details->em_combined_count['ep-policy-cap'];
                foreach ($details->all_tickets_data as $index => $obj) {
                    $elements->posts[$id]->all_tickets_data[$index]->capacity = $per_cap;
                }
            }
        }
        return $elements;
    }

    public static function ticket_template_data($elements) {
        foreach ($elements as $index => $details) {
            $em_combined_count = get_post_meta($details->event_id, 'em_combined_count', true);
            if ($em_combined_count['ep-policy-default'] == 'per-cap') {
                $elements[$index]->orig_capacity = $elements[$index]->capacity;   
                $elements[$index]->epcc_capacity = $em_combined_count['ep-policy-cap'];                   
                $elements[$index]->capacity = $em_combined_count['ep-policy-cap'];               
            }
        }
        return $elements;
    }

    public function capacity_filter($elements, $atts) {
        /*
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST']; // Contains the domain name
        $uri = $_SERVER['REQUEST_URI']; // Contains the path and query string if any
        
        $currentUrl = $protocol . '://' . $host . $uri;        
        error_log($currentUrl.' --- ');
        
        error_log('LINE 306 ------------');
        */
        foreach ($elements as $key => $element) {
            if ($key == 'posts') {   
                $elements = $this->ticket_data($elements);
            }
        }
        return $elements;
    }

    public function attr_filter($params, $atts) {
        /*
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST']; // Contains the domain name
        $uri = $_SERVER['REQUEST_URI']; // Contains the path and query string if any
        
        $currentUrl = $protocol . '://' . $host . $uri;        
        error_log($currentUrl.' --- ');
        error_log('LINE 318 ------------');
        */
        foreach ($params as $key => $post) {
            error_log("$key HOLDS ".print_r($post, TRUE));
        }
        // error_log("atts is ".print_r($atts, TRUE));

        // error_log('LINE 324 ------------');    
        // Always return the modified $posts
        /* */
        return $params;
    }    

    public function test_filter($params) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST']; // Contains the domain name
        $uri = $_SERVER['REQUEST_URI']; // Contains the path and query string if any
        
        $currentUrl = $protocol . '://' . $host . $uri;        
        error_log($currentUrl.' --- ');        

        error_log('LINE 370 ------------');
        foreach ($params as $key => $post) {
            error_log("$key TESTS ".print_r($post, TRUE));
        }
        error_log('LINE 374 ------------');    
        return $params;
    }

    public function eventprime_template($file, $slug, $name) {
        // error_log(" - $file AND $slug AND $name - ");
        $continue = true;
        if ($name) {
            $continue = false;            
            if (preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {            
                $continue = true;            
            }
        }
        
        if ($continue) {
            // Construct the template part name based on the slug and name
            $template_part = $slug . ($name ? "-{$name}" : '') . '.php';
            
            // Look for the file in the active theme's directory
            $theme_file = locate_template(['eventprime/' . $template_part]);
    
            // If a custom template exists in the theme, use it
            if (!empty($theme_file)) {
                $file = $theme_file;
            } else {
                // Otherwise, use the default template from the plugin
                if ('events/single-event/tickets' === $slug) {
                    $new_file = EP_EPCC_DIR . '/events/views/single-event/tickets.php';
                    if (file_exists($new_file)) {
                        $file = $new_file;
                    }
                }
            }
        }
    
        // Return the file path (it might be the original or the updated one)
        return $file;
    }

	public function enqueue_scripts() {
        if ( is_singular('em_event') ) {		    
		    
			wp_enqueue_style('em-admin-jquery-ui');
			wp_enqueue_style(
				'em-epcc-css',
				EP_EPCC_URL . 'assets/css/ep-epcc-front.css',
				false, EVENTPRIME_VERSION
			);

			wp_enqueue_script(
				'em-epcc-js',
				EP_EPCC_URL . 'assets/js/ep-epcc-front.js',
				array( 'jquery' ), EVENTPRIME_VERSION
			);
	    }
	}

    public function load_textdomain() {
        load_plugin_textdomain( 'eventprime-combined-counts', false, EP_EPCC_DIR . '/languages/' );
    }

    public function define_hooks() {
        add_filter('ep_filter_front_events', [$this, 'capacity_filter'], 10, 2);
        // add_filter('ep_event_views', [$this, 'test_filter'], 10);
        // add_filter('ep_booking_detail_add_booking_data', [$this, 'attr_filter'], 10, 2);
        // add_filter('ep_booking_edit_booking_data', [$this, 'attr_filter'], 10, 2);              
        // add_filter('ep_events_render_attribute_data', [$this, 'attr_filter'], 10, 2);      
     
		add_action('wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );        
        add_filter('ep_get_template_part', [$this, 'eventprime_template'], 10, 3);
    }
}
