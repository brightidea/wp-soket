<?php
/*
Plugin Name: Soket Events Import
Plugin URI: http://brightideainteractive.com/
Description: Import Soket Events as events for "The Event Calendar" wordpress plugin
Author: Blake Moore
Author URI: http://brightideainteractive.com 
Version: 0.1
Text Domain: soket_events
License: GPL2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

define( 'SOKETEVENTS_TITLE', 'Soket Events Importer'); 
define( 'SOKETEVENTS_VERSION', '0.1' );
define( 'SOKETEVENTS_SLUG', 'soket_events'); //use _
define( 'SOKETEVENTS_PATH', dirname( __FILE__ ) );
define( 'SOKETEVENTS_DIR', basename( SOKETEVENTS_PATH ));
define( 'SOKETEVENTS_URL', plugins_url() . '/' . SOKETEVENTS_DIR );
define( 'SOKETEVENTS_FILE', plugin_basename( __FILE__ ) );

//* Register asl location post type
include (SOKETEVENTS_PATH.'/api/connect-to-soket.php');

/*
* Main class in assigning schedule and posting the events as post types
*/
class Soket_Event_Import extends Soket_API{
	
	function __construct(){	
		//* 2 lines below can be removed
		//add_shortcode("DISPLAY_EVENTS",array(&$this,"displayEvents"));		
		//add_action('wp_head',array(&$this,'process_event_post_type'));              
		//process schedule		
		register_activation_hook(SOKETEVENTS_PATH . '/atlanticstation-events-plugin.php', array(&$this,'soketevents_activation'));
		//add_filter('cron_schedules', array(&$this,'custom_cron_schedules'));
		add_action('soketimport', array(&$this,'process_event_post_type'));  //PRIMARY
		//* add the settings page
		add_action( 'admin_menu', array(&$this,'soket_menu') );
	}	
	
	//* TEMPORARY function
	function displayEvents(){
		$events = parent::get_all_events();
	    parent::renderEvents($events);		
	}
			
	//* Process adding event post type
	function process_event_post_type(){           
		 $events = parent::get_all_events(); //retrieve all events under -30 days from the current date  
		 echo count($events)."ASD";
		 if($events){
			 $this->deleteEventsandMedia(); // delete events upon adding
			 $this->prepareEvents($events);
		 }
	}
	
	//* Render events
	function prepareEvents($listEvent)
	{
		if(isset($listEvent))
		{		
			foreach ($listEvent as $value) {
				//echo 'Result: ' . $value['title'] . "<br />";	
				 $this->add_event_post_type($value);
			}
		}	
	}
	
	/**
	 * A function used to programmatically create a post in WordPress. The slug, author ID, and title
	 * are defined within the context of the function.
	 *
	 * @returns -1 if the post was never created, -2 if a post with the same title exists, or the ID
	 *          of the post if successful.
	 */
	function add_event_post_type($event) {
		//if event doesnt exists
		//if($this->is_event_exists($event) == false){		
			// Initialize the page ID to -1. This indicates no action has been taken.
			$post_id = -1;
			
			// Set the post ID so that we know the post was created successfully
			$post_id = wp_insert_post(
				array(
					'comment_status'	=>	'closed',
					'ping_status'		=>	'closed',
					'post_author'		=>	1, //admin
					'post_title'		=>	$event['title'],
					'post_content'      => $event['description'],
					'post_status'		=>	'publish',
					'post_type'		=>	'tribe_events',
					'tags_input'     => $event['key_word']
				)
			);
			
			update_post_meta($post_id,'_EventID',$event['id']);
			update_post_meta($post_id,'_deletionStatus','yes');
			update_post_meta($post_id,'_EventStartDate',$this->changeDateformat($event['start_date_string']." ".$event['start_time_string']));
			update_post_meta($post_id,'_EventEndDate',$this->changeDateformat($event['end_date_string']." ".$event['end_time_string']));		
			update_post_meta($post_id,'_EventVenueID',$this->addVenuePostType($event['location']['name']));
			update_post_meta($post_id,'_EventFeaturedImage',$event['event_image']);
			
			//assign event category
			$this->assignEventCategory($post_id, $event['event_type']);
			
			//set event featured image if image is available
			if($event['event_image']) $this->setEventFeaturedImage($post_id, $event['event_image']);
		//}
		
	} // end programmatically_create_post

	//* check if event exists
	function is_event_exists($event){
		global $post;
		$args = array('numberposts' => -1,'post_type' => 'tribe_events', 'meta_key' => '_EventID', 'meta_value' => "".$event['id'] );
		$exists = get_posts( $args );
		if($exists) return true;
		else return false;
	}

	//* add venue pos type
	function addVenuePostType($venuename){
		$existingVenue = get_page_by_title($venuename,OBJECT,'tribe_venue');
		if(null == $existingVenue){
			$post_id = wp_insert_post(
			array(
					'comment_status'	=>	'closed',
					'ping_status'		=>	'closed',
					'post_author'		=>	1, //admin
					'post_title'		=>	$venuename,
					'post_content'      => '',
					'post_status'		=>	'publish',
					'post_type'		=>	'tribe_venue'					
				)
			);
			return $post_id; //return new venue ID
		}
		else{
			return $existingVenue->ID; //return the venue ID if its already added
		}
	}

	//* check the date format 
	function changeDateformat($dte){
		return date('Y-m-d H:i:s',strtotime($dte));
	}

	//* assign event category
	function assignEventCategory($post_id, $term, $taxonomy = 'tribe_events_cat'){
		$parent_term = term_exists( $term, $taxonomy );	
		if($parent_term !== 0 && $parent_term !== null){
			wp_set_post_terms( $post_id, $parent_term['term_id'], $taxonomy );
		}
		else{
			$new_term = wp_insert_term($term,$taxonomy);
			wp_set_post_terms( $post_id, $new_term['term_id'], $taxonomy );
		}
	}

	//* set event featured image
	function setEventFeaturedImage($post_id, $image_url){
		$upload_dir = wp_upload_dir();
		$image_data = @file_get_contents($image_url);
		if($image_data){
			$filename = basename($image_url);
			if(wp_mkdir_p($upload_dir['path']))
				$file = $upload_dir['path'] . '/' . $filename;
			else
				$file = $upload_dir['basedir'] . '/' . $filename;
			file_put_contents($file, $image_data);
			
			$wp_filetype = wp_check_filetype($filename, null );
			$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_title' => sanitize_file_name($filename),
				'post_content' => '',
				'post_status' => 'inherit'
			);		
			$attach_id = wp_insert_attachment( $attachment, $file, $post_id );
			update_post_meta($attach_id,'_deletionStatus','yes'); //add special post meta
			require_once(ABSPATH . 'wp-admin/includes/image.php');
			$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
			wp_update_attachment_metadata( $attach_id, $attach_data );
			
			set_post_thumbnail( $post_id, $attach_id );
		}
	}

	//delete existing events  
	function deleteEventsandMedia(){
		global $wpdb;                
		$wpdb->query("DELETE {$wpdb->prefix}posts.* FROM {$wpdb->prefix}posts INNER JOIN {$wpdb->prefix}postmeta ON {$wpdb->prefix}postmeta.post_id = {$wpdb->prefix}posts.ID WHERE ({$wpdb->prefix}posts.post_type ='tribe_events' OR {$wpdb->prefix}posts.post_type ='attachment') AND ({$wpdb->prefix}postmeta.meta_key = '_deletionStatus' AND {$wpdb->prefix}postmeta.meta_value = 'yes')"); 		
	}
	
	//delete attachments = CURRENTLY UNUSED
	function delete_post_media( $post_id ) {
		$attachments = get_posts( array(
			'post_type'      => 'attachment',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'post_parent'    => $post_id
		) );
	
		foreach ( $attachments as $attachment ) {
			if ( false === wp_delete_attachment( $attachment->ID ) ) {
				// Log failure to delete attachment.
			}
		}
		 wp_reset_postdata();
	}
	
	//add a custom cron schedule three minutes
	function custom_cron_schedules($param) {
		 return array('five_minutes' => array(
			  'interval' => 300, // seconds
			  'display'  => __('Every 5 minutes') 
		 ));
	}

	//register wp_schedule_event
	function soketevents_activation() {		
		$timestamp = wp_next_scheduled( 'soketimport' );
		if( $timestamp == false ){
			wp_schedule_event( time(), 'twicedaily', 'soketimport');
		}
	}
	
	//* Soket Plugin Menu
	function soket_menu(){
		add_options_page( 'Soket Manual Import', 'Soket Event Import', 'manage_options', 'soket-event-import', array(&$this,'soketSettingsForm') );
	}
	
	//* Settings Form
	function soketSettingsForm(){
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		?>
        <div class="wrap">
        	<h1><?php _e('Manual Soket Events Import'); ?></h1>
         	
            <?php
				//* process importing
				if(isset($_POST['triggerSoket'])){ $this->process_event_post_type(); echo '<div id="message" class="updated below-h2"><p>Events were imported successfully!.</p></div>';	}
			?>
            
            <form method="post">
            	<ul>
                	<li>
                    	<input type="submit" name="triggerSoket" id="triggerSoket" class='button-primary' value="<?php _e('Import Events'); ?>"  />
                    </li>
                </ul>
            </form>
        </div>	
        <?php
	}
	
}

$sei = new Soket_Event_Import();