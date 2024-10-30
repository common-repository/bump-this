<?php
/**
 * @package Bump_this
 * @version 1.1
 */
 
/**
 * Plugin Name: Bump This
 * Plugin URI: http://wordpress.org/plugins/bump-this/
 * Description: "Bump this" is a simple plugin that adds a "bump this" button to all your posts.  Users can 'bump' their favorite posts.
 * Author: Kishan Shiyaliya, Dmitry Alexander 
 * Author URI: http://www.bumpposts.com/
 * Version: 1.1
 */

global $bt_db_version;
$bt_db_version = "1.0";
define('BUMP_THIS_PLUGIN_URL', plugin_dir_url( __FILE__ ));

function bt_install() {
    global $wpdb;
    global $bt_db_version;
    $bumps_needed = 10;
  
    $table_name = $wpdb->prefix . "bump_this";
      
    $sql = "CREATE TABLE $table_name (
				  id mediumint(9) NOT NULL AUTO_INCREMENT,
				  time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				  post_id mediumint(9) NOT NULL,
				  post_name tinytext NOT NULL,
				  user_id mediumint(9) NOT NULL,
				  user_name tinytext NOT NULL,
				  user_email tinytext NOT NULL,
				  UNIQUE KEY id (id)
					);";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
 
    add_option( "bt_db_version", $bt_db_version );
   
    add_option( "bumps_needed", $bumps_needed );
	
	$post_ids = $wpdb->get_col( $wpdb->prepare( 
					"
					SELECT ID FROM $wpdb->posts 
						WHERE       
						post_status = %s 
						AND post_type = %s
					",
					"publish", 
					"post" 
				) ); 
	
	if($post_ids)
	{
		foreach( $post_ids as $post_id)
		{
			add_post_meta( $post_id, 'bump_relist_id', $post_id );
			add_post_meta( $post_id, 'bump_relist', 0 , true);
		}
	}
	
}

register_activation_hook( __FILE__, 'bt_install' );

function bt_uninstall(){
	global $wpdb;
	
	$table_name = $wpdb->prefix . "bump_this";
	
	$wpdb->query("delete from ".$wpdb->prefix."postmeta where meta_key = 'bump_relist_id' or meta_key = 'bump_relist' or meta_key = 'bump_counts'");
	
	$wpdb->query("DROP TABLE IF EXISTS $table_name");
	 
}

register_deactivation_hook( __FILE__, 'bt_uninstall' );


add_action('admin_menu', 'bump_this_menu');


function bump_this_menu() {

	add_menu_page( 'Bump This', 'Bump This', 'manage_options', 'bump_this', 'bt_admin', BUMP_THIS_PLUGIN_URL."/images/bumpthis.png");
	
}

function bt_admin() {
	global $wpdb;
	$bt_options = '';
	
	$saved_ok        = false;
	$bt_errors = array();
	
	if ( isset( $_POST['submit'] ) ) {
		if( empty( $_POST['bumps_needed'] ) )
		{
			$bt_errors[] = 'bumps_needed_empty';
		}
		else if( ! is_numeric( $_POST['bumps_needed'] ))
		{
			$bt_errors[] = 'bumps_needed_invalid';
		}
		else 
		{
			update_option( 'bumps_needed', $_POST['bumps_needed'] );
			$saved_ok = true;
		}
	}
	
	$messages = array(
		'bumps_needed_empty'       => array( 'class' => 'error fade', 'text' => __('Please enter number of bumps needed.' ) ),
		'bumps_needed_invalid'       => array( 'class' => 'error fade', 'text' => __('Invalid entry for bumps needed. Only numbers are allowed.' ) ),
	);

	$bumps_needed = get_option('bumps_needed');
	
?>
	<div class="wrap">
	
		<h2>Bump This</h2>
		<?php if ( !empty($_POST['submit'] ) && $saved_ok ) : ?>
		<div id="message" class="updated fade"><p><strong><?php _e('Settings saved.') ?></strong></p></div>
		<?php endif; ?>
		<?php foreach( $bt_errors as $bt_error ) : ?>
		<div class="<?php echo $messages[$bt_error]['class']; ?>"><p><strong><?php echo $messages[$bt_error]['text']; ?></strong></p></div>
		<?php endforeach; ?>
		<form method="post" action="" id="bump-this-form">
			<table class="form-table">
				<tr>
					<th scope="row">Bumps Needed</th>
					<td>
						<input type='text' name='bumps_needed' id='bumps_needed' value='<?php _e($bumps_needed);?>' size='10'>
						<p>Enter how many bumps needed to relist post to top of the page</p>
					</td>
				</tr>
			</table>
			<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>
		</form>
		
		<hr/>
		 <h2>Do you like this plugin?</h2>
    
			<p>The best way to show your support is by <a href="http://wordpress.org/extend/plugins/bump-this/"><strong>voting it up on Wordpress.org</strong></a>.</p>
			
			<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
				<input type="hidden" name="cmd" value="_s-xclick">
				<input type="hidden" name="hosted_button_id" value="Q9LPXAXAGYVVE">
				<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
				<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
			</form>

	</div>
	
<?php
}



// Register Style
function bump_this_js_and_css() {

	wp_register_style( 'bump-this', BUMP_THIS_PLUGIN_URL.'bump-this.css', false, '1.0' );
	wp_enqueue_style( 'bump-this' );
	
	wp_enqueue_script("jquery");

}

// load required css and js files to run this plugin
add_action( 'wp_enqueue_scripts', 'bump_this_js_and_css' );

class Bump_this {
	
	static function bump_this_button( $content ) {

		$bump_this_button = '';
		
		$bump_this_button .= $content;
		
		if( is_single() &&  'post' == get_post_type() )
		{
			global $post , $wpdb;
			
			$post_id = $post->ID;
			
			$bump_counts = intval(get_post_meta( $post_id, 'bump_counts' , true  ));	
			
			$user_ID = get_current_user_id(); 
			
			$entry_id = $wpdb->get_var( $wpdb->prepare( 
									"
										select id from ".$wpdb->prefix."bump_this where user_id = %s and post_id= %s
									", 
								
									$user_ID,
									$post_id
												
						) );
		
			$bump_this_input = "";
			$bump_this_value = "";
			
			
			if( ! empty( $entry_id ))
			{
				$bump_this_input = 'disabled="disabled"';
				$bump_this_value = "Bumped";
			}
			else
			{
				$bump_this_value = "Bump This";
			}
			
			$bump_this_button .= '<div id="bump-this">
									<input type="submit" name="bump_this" id="bump_this" value="'.$bump_this_value.'" class="bump-this-button" '.$bump_this_input.'>
									<input type="hidden" name="bump_counts" id="bump_counts" value="'.$bump_counts.'" class="bump-this-button">
									<div class="bump_counter">
										<div class="bump-this-arrow-left"></div>
										<div class="bump-this-count">'.$bump_counts.'</div>
										<div class="bump-this-count-right"></div>
									</div>	
								  </div>
								  <div id="bump-this-error">Please <a href="'.wp_login_url( get_permalink() ).'" title="Login">Log In</a> to bump this post.</div>
								  ';
		}						
		return $bump_this_button;						
	}
}

add_filter( 'the_content', array('Bump_this', 'bump_this_button') , 20);


add_action( 'wp_ajax_bump_this', 'bump_this_callback' );

function bump_this_callback() {
	global $wpdb; // this is how you get access to the database
	
	$user = get_user_by( 'id', $_POST['user_id'] );
	$bump_counts = intval( $_POST['bump_counts'] );
	
	$bump_counts += 1;
	
	$bt_table_name = $wpdb->prefix . "bump_this";
	
	$current_date = 
	$bt_sql_results = $wpdb->query( $wpdb->prepare( 
							"
							insert into $bt_table_name set
								time = %s,
								post_id = %s,
								post_name = %s,
								user_id	=	%s,
								user_name	=	%s,
								user_email	=	%s;
							",
							array(
								current_time('mysql'),
								absint( $_POST['post_id'] ),
								get_the_title( absint( $_POST['post_id'] ) ),
								absint( $_POST['user_id'] ),
								$user->user_login,
								$user->user_email
							)
						) );
	
	update_post_meta( $_POST['post_id'], 'bump_counts', $bump_counts );

	$bump_relist_counts_new = intval(get_post_meta( $_POST['post_id'], 'bump_relist' , true  ));	
	
	if( $bump_counts >= $_POST['bumps_needed'] )
	{
		$bt_post_meta_table_name = $wpdb->prefix . "postmeta";
		
		$bt_ID_query = "";
	
		$bt_max_ID = $wpdb->get_var( $wpdb->prepare( 
							"
								select max(meta_value) value from $wpdb->postmeta where meta_key=%S
							" ,
							'bump_relist_id'
						) );
		
		$bt_max_ID_new = $bt_max_ID + 1;
		
		update_post_meta( $_POST['post_id'], 'bump_relist_id', $bt_max_ID_new );
		
		$bump_relist_counts = intval(get_post_meta( $_POST['post_id'], 'bump_relist' , true  ));	
		
		$bump_relist_counts+=1;
		
		update_post_meta( $_POST['post_id'], 'bump_relist', $bump_relist_counts );

	}
	if (ob_get_length()) ob_clean();
	
	echo $bump_counts;
	
	die(); // this is required to return a proper result
	
}

add_action( 'wp_footer', 'bump_this_javascript' );

function bump_this_javascript() {

	global $post;
	$post_id = $post->ID;
?>
<script type="text/javascript" >
jQuery(document).ready(function($) {
	
	$('#bump_this').click( function() {
		
		<?php if(is_user_logged_in()) { 
				
				$user_ID = get_current_user_id();
				
				$bumps_needed = get_option('bumps_needed');				
		?>
			var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
		
			var bumps = $( '#bump_counts' ).val();
			
			var data = {
							action: 'bump_this',
							bump_counts: bumps,
							post_id: <?php echo $post_id;?>,
							user_id: <?php echo $user_ID;?>,
							bumps_needed: <?php echo $bumps_needed;?>
						};
		
			// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			$.post(ajaxurl, data, function(response) {
	
				$('.bump-this-count').html(response);
				$('#bump_this').val('Bumped');
				$('#bump_this').attr('disabled', 'disabled');
				$('#bump_counts').val(response);
			
			});
		<?php } else {?>
				$("#bump-this-error").show();
		<?php } ?>	
	});
});
</script>
<?php
}

function bt_relist_posts($query) {
  if ( !is_admin() && $query->is_main_query() ) {
      $query->set('meta_key', 'bump_relist_id');
	  $query->set('orderby', 'meta_value_num');
	  
  }
}

add_action('pre_get_posts','bt_relist_posts');