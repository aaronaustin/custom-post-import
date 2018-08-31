<?php
/*
Plugin Name: Custom Post Import
Plugin URI: http://royduineveld.nl
Description: Post Import with ACF Fields
Version: 1.0
Author: Aaron Austin - Modified from Roy Duineveld
Author URI: http://royduineveld.nl
*/

add_action('admin_menu', 'custom_import_plugin_setup_menu');
 
function custom_import_plugin_setup_menu(){
        add_menu_page( 'Custom Import', 'Custom Import', 'manage_options', 'custom-import-plugin', 'custom_import_init' );
}
 
function custom_import_init(){
	echo '<h1>Custom Import</h1>
		<form method="post" action="">
			<textarea style="width: calc(100% - 20px)" rows="20" id="custom_import_file_upload" name="custom_import_file_upload" type="file"></textarea>
			<button type="submit" class="button" style="width: calc(100% - 20px); margin-top: 10px;" onclick="form.submit()">Upload</button>
		</form>';			
}

add_action('admin_init', 'importIt');

function importIt() {
	set_time_limit(0);
	require_once(ABSPATH . 'wp-admin/includes/media.php');
	require_once(ABSPATH . 'wp-admin/includes/file.php');
	require_once(ABSPATH . 'wp-admin/includes/image.php');

	if (isset($_POST['custom_import_file_upload'])) {
		$json = $_POST['custom_import_file_upload'];
		$clean_data = cleanUpJson($json);

		if($clean_data !== FALSE){
			foreach($clean_data as $item){
				$categoryID = getCurrentCategory($item->post_category);
				//TODO: CHECK WORDPRESS AUTHOR IDS
				// switch($item->author) {
				// 	case 'central': $authorID = 2; break;
				// 	case 'aaron': $authorID = 1; break;
				// 	case 'mark': $authorID = 3; break;
				// 	case 'charles': $authorID = 4; break;
				// 	case 'crystal': $authorID = 5; break;
				// }

				$new_post = array(
					'post_title' => $item->post_title,
					'post_content' => $item->post_content,
					'post_status' => 'publish',
					'post_date' => $item->post_date,
					'post_author' => 1,
					'post_type' => 'post',
					'post_category' => array($categoryID)
				);


				$postInsertId = wp_insert_post( $new_post );
				//Use ACF methods to insert
				https://www.advancedcustomfields.com/resources/update_field/
				$acf_meta_fields = getACFMeta($item->post_category, $item);
				foreach($acf_meta_fields as $meta) {
					update_field($meta['field_key'], $meta['value'], $postInsertId);
				}

				//TODO: Check if file is already present before uploading.  I think it's working.  Check and then apply
				// I think it's working.  Check and then apply to acf uploads below.
				$prev_image = get_attachment_url_by_title($item->image);
				if($prev_image) {
					set_post_thumbnail($postInsertId, $prev_image);
				}
				else {
					add_action('add_attachment','featuredImageTrick');
					media_sideload_image($item->image, $postInsertId, $item->title);
					remove_action('add_attachment','featuredImageTrick');
				}

				if($item->post_category == 'audio') {
					uploadACFFile($item->audio_file, 'field_5aee493cbf97c', $postInsertId, $item->title);
					if($item->audio_category == 'podcast') {
						$prev_acf_image = get_attachment_url_by_title($item->podcast_image);
						if($prev_acf_image) {
							update_field('field_5aee4965bf97d', $prev_acf_image, $postInsertId);
						}
						else {
							uploadACFFile($item->podcast_image, 'field_5aee4965bf97d', $postInsertId, $item->title);
						}
					}
				}
			}
		}
	}
}
	
// A little hack to "catch" and save the image id with the post
function featuredImageTrick($att_id){
    $p = get_post($att_id);
    update_post_meta($p->post_parent,'_thumbnail_id',$att_id);
}

function cleanUpJson($data) {
	$temp_data = str_replace("\\", "", $data);
	return json_decode($temp_data);
}

function getAllCategories() {
	$args = array('hide_empty' => FALSE);
	return get_categories($args);
}

function getCurrentCategory($current_cat) {
	$my_categories = getAllCategories();
	foreach($my_categories as $cat) {
		if ($current_cat == $cat->slug) {
			return $cat->term_id;
			break;
		}
	}
}

function uploadACFFile($file_url, $acf_field_id, $postId, $title) {
	$tmp = download_url( $file_url );
	$file_array = array(
		'name' => basename( $file_url ),
		'tmp_name' => $tmp
	);
	if ( is_wp_error( $tmp ) ) {
		@unlink( $file_array[ 'tmp_name' ] );
		return $tmp;
	}
	$media_id = media_handle_sideload($file_array, $postId, $title);
	update_field($acf_field_id, $media_id, $postId);

	if ( is_wp_error( $id ) ) {
		@unlink( $file_array['tmp_name'] );
		return $id;
	}
}

function getACFMeta($category, $data) {
	$acf_post_meta = array(
		'blog'=> array(
			'subtitle' => array (
				'field_key' => 'field_5aee44c09e72f',
				'value' => $data->subtitle
			),
			'author_override' => array(
				'field_key' => 'field_5afc47daacd85', 
				'value' => $data->author_override
			),
		),
		'event'=> array(
			'subtitle' => array (
				'field_key' => 'field_5aee44c09e72f',
				'value' => $data->subtitle
			),
			'start_date' => array(
				'field_key' => 'field_5aee49b3c61ce', 
				'value' => $data->start_date
			),
			'end_date' => array(
				'field_key' => 'field_5aee4c442eba5', 
				'value' => $data->end_date
			),
		),
		'audio'=> array(
			'subtitle' => array (
				'field_key' => 'field_5aee44c09e72f',
				'value' => $data->subtitle
			),
			'audio_category' => array(
				'field_key' => 'field_5aee48909af59', 
				'value' => $data->audio_category
			),
		)
	);
	return $acf_post_meta[$category];
}

function get_attachment_url_by_title( $url ) {
  $url_array = explode("/", $url);
  $file_name = substr($url_array[count($url_array) - 1], 0, -4);

  $title = sanitize_title($file_name);
  $args = array(
    'post_type' => 'attachment',
    'name' => $title,
	'posts_per_page' => '1',
	'post_status' => 'inherit'
  );
  $_header = get_posts( $args );
  $result = $_header[0]->post_name == $title ? $_header[0]->ID : false ;
  
  $_fuzzy_title = substr($title, 0, -2);
  $fuzzy_title = preg_replace("/\d+$/","",$_fuzzy_title).'2x';
  $fuzzy_args = array(
    'post_type' => 'attachment',
    'name' => $fuzzy_title,
	'posts_per_page' => '1',
	'post_status' => 'inherit'
  );
  $fuzzy_results = get_posts( $fuzzy_args );
  echo '<pre>';
  var_dump($fuzzy_results);
  echo '<br>'.$fuzzy_title;
  echo '<br>'.$fuzzy_results[0]->post_name;
  $dupe = $fuzzy_results[0]->post_name == $fuzzy_title ? $fuzzy_results[0]->ID: false;
//   var_dump($fuzzy_results); echo $dupe; $dupe ? die() : "";
//   echo (count($url_array) - 1).'<br><br><pre>'; var_dump($args); var_dump($_header); echo $_header[0]->post_name;die();
  

  $header = $dupe ? $dupe : $result;
  return $header;
}