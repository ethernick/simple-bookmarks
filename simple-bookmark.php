<?php
/**
 * Plugin Name: Simple Bookmark
 * Plugin URI: TBD
 * Description: My Simple bookmark plugin. Use the first link in a post as the title and link.
 * Version: 1.1.0
 * Author: Nick Kempinski
 * Author URI: https://whoisnick.com/
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Text Domain: simplebookmark
 */

namespace SimpleBookmark;

add_action('save_post', 'SimpleBookmark\\simplebookmark_save_data', 8, 3);
function simplebookmark_save_data( $post_id, $post, $update) {    
    $link = get_url_in_content($post->post_content);
	$bookmark = get_post_meta($post_id,'bookmark',true);
    if($link && $bookmark!==$link) {
        update_post_meta( $post_id, 'bookmark', sanitize_url( $link ) );
    }
}

add_filter( 'post_link', 'SimpleBookmark\\simplebookmark_post_link', 8, 3 );
function simplebookmark_post_link( $permalink, $post, $leavename ) { 
	// unly use bookmark link when presented on the web.
    if(preg_grep("/Content-Type:(.*)html/i",headers_list())) {
		$id = $post->ID;
		$bookmark = get_post_meta($id,'bookmark',true);

		if(!empty($bookmark)  && is_bookmark_category($id)) {
			return $bookmark;
		}
    }
    
    return $permalink; 
}; 

add_filter('the_title', 'SimpleBookmark\\simplebookmark_post_title', 10, 2);
function simplebookmark_post_title($title, $id) { 
    $bookmark = get_post_meta($id,'bookmark',true);
    if(!empty($bookmark) && is_bookmark_category($id)) {
        $icon = get_option( 'simplebookmark_title_icon' );
        return $title." <sup>".$icon."</sup>";
    }
    return $title;
}; 

function is_bookmark_category($post_id) { 
	//convert v1.0.1 string value to array   
    $bookmark_categories = is_string(get_option( 'simplebookmark_category_ids' ))?
		array_map(function($item){
			return (int)trim($item);
		}, explode(',', get_option( 'simplebookmark_category_ids' )))
		: get_option( 'simplebookmark_category_ids' );

    if(!$bookmark_categories) {
        return true; 
    } else {
        $categories = array_map(function($item){
            return $item;
        },wp_get_post_categories($post_id, array( 'fields' => 'ids' )));


        return (sizeof(array_intersect($categories,$bookmark_categories)) > 0);
    }
}

//Simple Admin
add_action( 'admin_init', 'SimpleBookmark\\simplebookmark_register_settings' );
function simplebookmark_register_settings() {
    
    add_settings_section(
		'simplebookmark_section_settings',
		__( 'Settings', 'simplebookmark' ), 'SimpleBookmark\\simplebookmark_section_settings_cb',
		'simplebookmark'
	);

    register_setting( 'simplebookmark', 'simplebookmark_title_icon' );
    add_settings_field(
		'simplebookmark_title_icon', __( 'Bookmark Indicator', 'simplebookmark' ),
		'SimpleBookmark\\simplebookmark_title_icon_cb',
		'simplebookmark',
		'simplebookmark_section_settings',
		array(
			'label_for'         => 'simplebookmark_title_icon',
			'class'             => 'simplebookmark_row',
			'simplebookmark_custom_data' => 'custom',
		)
	);

	register_setting( 'simplebookmark', 'simplebookmark_category_ids' );
    add_settings_field(
		'simplebookmark_category_ids', __( 'Categories', 'simplebookmark' ),
		'SimpleBookmark\\simplebookmark_category_ids_cb',
		'simplebookmark',
		'simplebookmark_section_settings',
		array(
			'label_for'         => 'simplebookmark_category_ids',
			'class'             => 'simplebookmark_row',
			'simplebookmark_custom_data' => 'custom',
		)
	);
}
function simplebookmark_section_settings_cb( $args ) {
	?>
	<p id="<?php echo esc_attr( $args['id'] ); ?>"></p>
	<?php
}

function simplebookmark_category_ids_cb( $args ) {
	?>
	<p><b>All Categories</b>: Unselect all checkboxes</p>
	<br />
	<?php

	//convert v1.0.1 string value to array
	$option = is_string(get_option( 'simplebookmark_category_ids' ))?
		array_map('trim',explode(",",$obj))
		: get_option( 'simplebookmark_category_ids' );

	$categories = get_categories();
	foreach($categories as $category) {
		$checked_html = "";
	
		if(in_array($category->cat_ID,$option)) {
			$checked_html = 'checked="checked"';
		} ?>
		<label><input type="checkbox" name="simplebookmark_category_ids[]" value="<?php echo $category->cat_ID; ?>" <?php echo $checked_html;?>><?php echo $category->cat_name; ?></label><br />
	<?php }
}

function simplebookmark_title_icon_cb( $args ) {
	$option = get_option( 'simplebookmark_title_icon' );
    ?>
    <input type="text" id="simplebookmark_title_icon" name="simplebookmark_title_icon" value="<?php echo $option; ?>" /><br />
	<small><i>Sample:</i></small> <b>Title<sup><?php echo $option; ?></sup>
    <?php
}

add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'SimpleBookmark\\simplebookmark_add_plugin_page_settings_link');
function simplebookmark_add_plugin_page_settings_link( $links ) {
	$links[] = '<a href="' .
		admin_url( 'options-general.php?page=simplebookmark' ) .
		'">' . __('Settings') . '</a>';
	return $links;
}

add_action('admin_menu', 'SimpleBookmark\\simplebookmark_register_options_page');
function simplebookmark_register_options_page() {
    add_options_page('Simple Bookmark', 'Simple Bookmark', 'manage_options', 'simplebookmark', 'SimpleBookmark\\simplebookmark_options_page');
}

function simplebookmark_options_page()
{
    // check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// add error/update messages

	if ( isset( $_GET['settings-updated'] ) ) {
		// add settings saved message with the class of "updated"
		add_settings_error( 'simplebookmark_messages', 'simplebookmark_message', __( 'Settings Saved', 'simplebookmark' ), 'updated' );
	}

	// show error/update messages
	settings_errors( 'simplebookmark_messages' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'simplebookmark' );
			do_settings_sections( 'simplebookmark' );
			submit_button( 'Save Settings' );
			?>
		</form>
	</div>
<?php
} 
?>