<?php
/**
 * Plugin Name:       CI HUB Connector
 * Plugin URI:        https://ci-hub.com/wordpress/
 * Description:       This plugin connects you to all of your pictures, images and text assets and synchronizes your library with all your DAM/PIM/Stock easy/fast/simple.
 * Version:           1.2.104
 * Requires at least: 4.1
 * Requires PHP:      5.6
 * Author:            CI HUB GmbH
 * Author URI:        https://ci-hub.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// Check php version compatibility.
if (version_compare(phpversion(), '5.6', '<=')) add_action('admin_notices', 'com_ci_hub_php_version_error');

/**
 * Admin notice for incompatible versions of PHP.
 * @return string
 */
function com_ci_hub_php_version_error() {
  printf('<div class="notice notice-error"><p>%s</p></div>', esc_html(com_ci_hub_php_version_text()));
}

/**
 * String describing the minimum PHP version.
 * @return string
 */
function com_ci_hub_php_version_text() {
  return esc_html('CI HUB Connector: Your version of PHP is too old to run this plugin. You must be running PHP 5.6 or higher.');
}

/**
 * Enqueue and localize scripts.
 */
function com_ci_hub_scripts() {
	try {
		wp_enqueue_media();
		wp_enqueue_script('com_ci_hub_script', plugins_url('/main.js', __FILE__), array(), '1.0.0', true);
		wp_localize_script('com_ci_hub_script', 'ajax_var', array('url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('com-ci-hub')));
	} catch (\Throwable $th) {
		wp_die('com_ci_hub_scripts failed: ' . $th->getMessage(), 400);
	} catch (\Exception $e) {
		wp_die('com_ci_hub_scripts failed: ' . $e->getMessage(), 400);
	}
}

// Enqueue scripts on admin pages.
add_action('admin_enqueue_scripts', 'com_ci_hub_scripts');

// Elementor is special: Enqueue scripts on elementor editor page.
add_action('elementor/editor/after_enqueue_styles', 'com_ci_hub_scripts');

// Beaver builder is special: Enqueue scripts on beaver builder editor page.
if ((isset($_GET['fl_builder']))) add_action('wp_footer', 'com_ci_hub_scripts');

// Add button to upload UI.
add_action('pre-plupload-upload-ui', function() {
	echo '<button id="com-ci-hub-upload-from-ci-hub-button" class="button button-hero" type="button" style="margin-bottom: 1em;">CI HUB</button>';
});

$cihub_metadata_key_prefix = 'com_ci_hub_';
$cihub_translated_metadata_key = '_' . $cihub_metadata_key_prefix . 'metadata_translations';
$cihub_linkdatastring_key = '_' . $cihub_metadata_key_prefix . 'linkdatastring';
$cihub_file_hash_key = $cihub_metadata_key_prefix . 'file_hash';

$cihub_hash_algo = 'sha256';
$cihub_scaled_file_suffix = '-scaled.';

// Display metadata information on attachment view.
add_filter('attachment_fields_to_edit', function($form_fields, $post) {
	global $cihub_translated_metadata_key;
	global $cihub_metadata_key_prefix;

	$translated_metadata = get_post_meta($post->ID, $cihub_translated_metadata_key, true);
	if (is_array($translated_metadata)) {
		foreach ($translated_metadata as $entry) {
			if (property_exists($entry, 'key') && property_exists($entry, 'name')) {
				$prefixed_key = $cihub_metadata_key_prefix . $entry->key;
				$form_fields[$prefixed_key] = array(
					'label' => $entry->name,
					'input' => 'html',
					'html'	=> '<input disabled="true" id="attachments-' . $post->ID . '-' . $prefixed_key . '" class="text" type="text" name="attachments[' . $post->ID . '][' . $prefixed_key . ']" value="' . esc_html(get_post_meta($post->ID, $prefixed_key, true)) . '"' . '/>',
					'helps' => 'Custom field name "' . $prefixed_key . '"'
				);
			}
		}
	}
	return $form_fields;
}, 10, 2);

/**
 * Check the nonce.
 */
function com_ci_hub_check_nonce ($nonce) {
	if (!isset($nonce) || !wp_verify_nonce($nonce, 'com-ci-hub')) throw new Exception('com_ci_hub_check_nonce failed: nonce is missing or invalid');
}

/**
 * Check permission to upload files in the media library.
 */
function com_ci_hub_check_upload_permissions () {
	if (!current_user_can( 'upload_files' )) throw new Exception('com_ci_hub_check_upload_permissions failed: user missing capability "upload_files"');
}

function com_ci_hub_get_original_file_path ($attachment_id) {
	global $cihub_scaled_file_suffix;
	if (function_exists('wp_get_original_image_path')) $file_path = wp_get_original_image_path($attachment_id);
	if (!isset($file_path) || $file_path === false || strlen($file_path) === 0) $file_path = get_attached_file($attachment_id);
	$file_path = str_replace($cihub_scaled_file_suffix, '.', $file_path);
	return $file_path;
}

function com_ci_hub_remove_scaled_image ($file_path) {
	global $cihub_scaled_file_suffix;
	$file_extension = array_pop(explode('.', $file_path));
	$file_path_split = explode('.', $file_path);
	array_pop($file_path_split);
	$scaled_image_file_path = implode('.', $file_path_split) . $cihub_scaled_file_suffix . $file_extension;
	if (file_exists($scaled_image_file_path) && !is_dir($scaled_image_file_path)) unlink($scaled_image_file_path);
}

function com_ci_hub_remove_metadata ($attachment_id) {
	$allMetadata = get_post_meta($attachment_id);
	if (is_array($allMetadata)) {
		foreach($allMetadata as $meta_key=>$meta_value) {
			if (strpos($meta_key, 'com_ci_hub_') !== false) delete_post_meta($attachment_id, $meta_key);
		}
	}
}

// Host: com_ci_hub_upload_asset
add_action('wp_ajax_com_ci_hub_upload_asset', function() {
	try {
		global $cihub_metadata_key_prefix;
		global $cihub_translated_metadata_key;
		global $cihub_linkdatastring_key;
		global $cihub_file_hash_key;
		global $cihub_hash_algo;
		
		if (!isset($_POST['nonce'])) throw new Exception('nonce is missing');
		com_ci_hub_check_nonce($_POST['nonce']);
		com_ci_hub_check_upload_permissions();

		$post_data = array('post_status' => 'inherit');

		if (isset($_POST['description']) && !empty($_POST['description'])) {
			$description = sanitize_text_field($_POST['description']);
			$post_data['post_excerpt'] = $description;
		}

		if (isset($_POST['title']) && !empty($_POST['title'])) {
			$title = sanitize_title($_POST['title']);
			$post_data['post_title'] = $title;
		}

		$attachment_id = media_handle_upload('data', 0, $post_data);
		if (is_wp_error($attachment_id)) throw new Exception($attachment_id->get_error_message());

		$file_path = com_ci_hub_get_original_file_path($attachment_id);
		if (!file_exists($file_path)) throw new Exception('original file does not exist: ' . $file_path);
		if (isset($_POST['modified']) && !empty($_POST['modified'])) touch($file_path, intval(sanitize_text_field($_POST['modified'])));

		if (isset($_POST['linkdatastring']) && !empty($_POST['linkdatastring'])) {
			$linkdatastring = sanitize_text_field($_POST['linkdatastring']);
			add_post_meta($attachment_id, $cihub_linkdatastring_key, $linkdatastring, true);
		}

		add_post_meta($attachment_id, $cihub_file_hash_key, hash_file($cihub_hash_algo, $file_path), true);

		if (isset($_POST['metadata']) && !empty($_POST['metadata'])) {
			$metadata = json_decode(stripslashes(wp_check_invalid_utf8($_POST['metadata'], true)));
			if (is_array($metadata)) {
				add_post_meta($attachment_id, $cihub_translated_metadata_key, $metadata, true);
				foreach ($metadata as $entry) {
					if (property_exists($entry, 'key') && !empty($entry->key) && property_exists($entry, 'value') && !empty($entry->value)) {
						if (isset($_POST['alttextmappingenabled']) && !empty($_POST['alttextmappingenabled']) && isset($_POST['alttextmappingvalue']) && !empty($_POST['alttextmappingvalue'])) {
							$alttextmappingvalue = sanitize_text_field($_POST['alttextmappingvalue']);
							if ($entry->key === $alttextmappingvalue) add_post_meta($attachment_id, '_wp_attachment_image_alt', $entry->value);
						}
						add_post_meta($attachment_id, $cihub_metadata_key_prefix . sanitize_text_field($entry->key), $entry->value, true);
					}
				}
			}
		}

		do_action('com_ci_hub_upload_asset', $attachment_id);
		wp_die($attachment_id);
	} catch (\Throwable $th) {
		wp_die('wp_ajax_com_ci_hub_upload_asset failed: ' . $th->getMessage(), 400);
	} catch (\Exception $e) {
		wp_die('wp_ajax_com_ci_hub_upload_asset failed: ' . $e->getMessage(), 400);
	}
});

// Host: com_ci_hub_relink_items
add_action('wp_ajax_com_ci_hub_relink_items', function() {
	try {
		global $cihub_metadata_key_prefix;
		global $cihub_translated_metadata_key;
		global $cihub_linkdatastring_key;
		global $cihub_file_hash_key;
		global $cihub_hash_algo;

		if (!isset($_POST['nonce'])) throw new Exception('nonce is missing');
		com_ci_hub_check_nonce($_POST['nonce']);
		com_ci_hub_check_upload_permissions();

		if (!$_FILES['data']['size'] || $_FILES['data']['size'] > wp_max_upload_size()) throw new Exception('max upload size exceeded');

		if (!isset($_POST['navigationitems']) || empty($_POST['navigationitems'])) throw new Exception('navigationitems not valid');
		$navigation_items = json_decode(stripslashes(sanitize_text_field($_POST['navigationitems'])));
		if (!is_array($navigation_items)) throw new Exception('navigationitems not array');

		$base_dir = wp_upload_dir()['basedir'];

		foreach($navigation_items as $navigation_item) {
			if (!property_exists($navigation_item, 'id')) throw new Exception('navigationitem is missing id');
			$attachment_id = $navigation_item->id;

			$file_path = com_ci_hub_get_original_file_path($attachment_id);
			if (!file_exists($file_path)) throw new Exception('original file does not exist: ' . $file_path);
			$content = file_get_contents($_FILES['data']['tmp_name']);
			if (!$content) throw new Exception('request file data missing or corrupted');
			
			file_put_contents($file_path, $content);
			if (!file_exists($file_path)) throw new Exception('could not save file');

			if (isset($_POST['modified']) && !empty($_POST['modified'])) touch($file_path, intval(sanitize_text_field($_POST['modified'])));
			$image_size_names = get_intermediate_image_sizes();
			if ($image_size_names && is_array($image_size_names)) {
				foreach($image_size_names as $image_size_name) {
					$image_size_info = image_get_intermediate_size($attachment_id, $image_size_name);
					if ($image_size_info && $image_size_info['path']) {
						$image_size_path = $image_size_info['path'];
						$image_size_full_path = $base_dir . '/' . $image_size_path;
						if (file_exists($image_size_full_path) && !is_dir($image_size_full_path)) unlink($image_size_full_path);
					}
				}
			}
			com_ci_hub_remove_scaled_image($file_path);

			wp_generate_attachment_metadata($attachment_id, $file_path);

			com_ci_hub_remove_metadata($attachment_id);

			if (isset($_POST['linkdatastring']) && !empty($_POST['linkdatastring'])) {
				$linkdatastring = sanitize_text_field($_POST['linkdatastring']);
				if (!add_post_meta($attachment_id, $cihub_linkdatastring_key, $linkdatastring, true)) { 
					update_post_meta($attachment_id, $cihub_linkdatastring_key, $linkdatastring);
				}
			}

			if (!add_post_meta($attachment_id, $cihub_file_hash_key, hash_file($cihub_hash_algo, $file_path), true)) { 
				update_post_meta($attachment_id, $cihub_file_hash_key, hash_file($cihub_hash_algo, $file_path));
			}

			if (isset($_POST['metadata']) && !empty($_POST['metadata'])) {
				$metadata = json_decode(stripslashes(wp_check_invalid_utf8($_POST['metadata'], true)));
				if (is_array($metadata)) {
					if (!add_post_meta($attachment_id, $cihub_translated_metadata_key, $metadata, true)) { 
						update_post_meta($attachment_id, $cihub_translated_metadata_key, $metadata);
					}
					foreach ($metadata as $value) {
						if (property_exists($value, 'key') && !empty($value->key) && property_exists($value, 'value') && !empty($value->value)) {
							$k = sanitize_text_field($value->key);
							$v = $value->value;
							if (isset($_POST['alttextmappingenabled']) && !empty($_POST['alttextmappingenabled']) && isset($_POST['alttextmappingvalue']) && !empty($_POST['alttextmappingvalue'])) {
								$alttextmappingvalue = sanitize_text_field($_POST['alttextmappingvalue']);
								if ($k === $alttextmappingvalue) {
									if (!add_post_meta($attachment_id, '_wp_attachment_image_alt', $v, true)) { 
										update_post_meta($attachment_id, '_wp_attachment_image_alt', $v);
									}
								}
							}
							if (!add_post_meta($attachment_id, $cihub_metadata_key_prefix . $k, $v, true)) { 
								update_post_meta($attachment_id, $cihub_metadata_key_prefix . $k, $v);
							}
						}
					}
					do_action('com_ci_hub_relink_item', $attachment_id);
				}
			}
		}

		wp_die($file_path);
	} catch (\Throwable $th) {
		wp_die('wp_ajax_com_ci_hub_relink_items failed: ' . $th->getMessage(), 400);
	} catch (\Exception $e) {
		wp_die('wp_ajax_com_ci_hub_relink_items failed: ' . $e->getMessage(), 400);
	}
});

// Host: com_ci_hub_replace_linkdata
add_action('wp_ajax_com_ci_hub_replace_linkdata', function() {
	try {
		global $cihub_metadata_key_prefix;
		global $cihub_translated_metadata_key;
		global $cihub_linkdatastring_key;
		global $cihub_file_hash_key;
		global $cihub_hash_algo;

		if (!isset($_POST['nonce'])) throw new Exception('nonce is missing');
		com_ci_hub_check_nonce($_POST['nonce']);
		com_ci_hub_check_upload_permissions();

		if (!isset($_POST['navigationitems']) || empty($_POST['navigationitems'])) throw new Exception('navigationitems not valid');
		$navigation_items = json_decode(stripslashes(sanitize_text_field($_POST['navigationitems'])));
		if (!is_array($navigation_items)) throw new Exception('navigationitems not array');

		foreach($navigation_items as $navigation_item) {
			if (!property_exists($navigation_item, 'id')) throw new Exception('navigationitem is missing id');
			$attachment_id = $navigation_item->id;

			$file_path = com_ci_hub_get_original_file_path($attachment_id);
			if (!file_exists($file_path)) throw new Exception('original file does not exist: ' . $file_path);
			
			com_ci_hub_remove_metadata($attachment_id);

			if (isset($_POST['linkdatastring']) && !empty($_POST['linkdatastring'])) {
				$linkdatastring = sanitize_text_field($_POST['linkdatastring']);
				if (!add_post_meta($attachment_id, $cihub_linkdatastring_key, $linkdatastring, true)) { 
					update_post_meta($attachment_id, $cihub_linkdatastring_key, $linkdatastring);
				}
			}

			if (!add_post_meta($attachment_id, $cihub_file_hash_key, hash_file($cihub_hash_algo, $file_path), true)) { 
				update_post_meta($attachment_id, $cihub_file_hash_key, hash_file($cihub_hash_algo, $file_path));
			}

			if (isset($_POST['metadata']) && !empty($_POST['metadata'])) {
				$metadata = json_decode(stripslashes(wp_check_invalid_utf8($_POST['metadata'], true)));
				if (is_array($metadata)) {
					if (!add_post_meta($attachment_id, $cihub_translated_metadata_key, $metadata, true)) { 
						update_post_meta($attachment_id, $cihub_translated_metadata_key, $metadata);
					}
					foreach ($metadata as $value) {
						if (property_exists($value, 'key') && !empty($value->key) && property_exists($value, 'value') && !empty($value->value)) {
							$k = sanitize_text_field($value->key);
							$v = $value->value;
							if (!add_post_meta($attachment_id, $cihub_metadata_key_prefix . $k, $v, true)) { 
								update_post_meta($attachment_id, $cihub_metadata_key_prefix . $k, $v);
							}
						}
					}
					do_action('com_ci_hub_relink_item', $attachment_id);
				}
			}
		}

		wp_die($file_path);
	} catch (\Throwable $th) {
		wp_die('wp_ajax_com_ci_hub_replace_linkdata failed: ' . $th->getMessage(), 400);
	} catch (\Exception $e) {
		wp_die('wp_ajax_com_ci_hub_replace_linkdata failed: ' . $e->getMessage(), 400);
	}
});

// Host: com_ci_hub_check_asset_availability
add_action('wp_ajax_com_ci_hub_check_asset_availability', function() {
	try {
		global $cihub_linkdatastring_key;
		if (!isset($_POST['nonce'])) throw new Exception('nonce is missing');
		com_ci_hub_check_nonce($_POST['nonce']);
		if (!isset($_POST['linkdatastring']) || empty($_POST['linkdatastring'])) wp_die(json_encode(false)); 
		$attachments = get_posts(array('post_type'  => 'attachment', 'meta_query' => array(array('key' => $cihub_linkdatastring_key, 'value' => sanitize_text_field($_POST['linkdatastring'])))));
		if (empty($attachments)) wp_die(json_encode(false));
		else wp_die($attachments[0]->ID);
	} catch (\Throwable $th) {
		wp_die('wp_ajax_com_ci_hub_check_asset_availability failed: ' . $th->getMessage(), 400);
	} catch (\Exception $e) {
		wp_die('wp_ajax_com_ci_hub_check_asset_availability failed: ' . $e->getMessage(), 400);
	}
});

// Host: com_ci_hub_get_max_upload_size
add_action('wp_ajax_com_ci_hub_get_max_upload_size', function() {
	try {
		if (!isset($_POST['nonce'])) throw new Exception('nonce is missing');
		com_ci_hub_check_nonce($_POST['nonce']);
		wp_die(wp_max_upload_size());
	} catch (\Throwable $th) {
		wp_die('wp_ajax_com_ci_hub_get_max_upload_size failed: ' . $th->getMessage(), 400);
	} catch (\Exception $e) {
		wp_die('wp_ajax_com_ci_hub_get_max_upload_size failed: ' . $e->getMessage(), 400);
	}
});

// Host: com_ci_hub_retrieve_media_items
add_action('wp_ajax_com_ci_hub_retrieve_media_items', function() {
	try {
		global $cihub_linkdatastring_key;
		global $cihub_file_hash_key;
		global $cihub_hash_algo;
		if (!isset($_POST['nonce'])) throw new Exception('nonce is missing');
		com_ci_hub_check_nonce($_POST['nonce']);

		if (isset($_POST['postIds'])) $postIds = sanitize_text_field($_POST['postIds']);

		if (!isset($_POST['limit'])) throw new Exception('limit is missing');
		$limit = intval(sanitize_text_field($_POST['limit']));

		if (isset($_POST['offset'])) $offset = intval(sanitize_text_field($_POST['offset']));
		else $offset = 0;

		$total_attachments = intval(wp_count_posts('attachment')->inherit);
		$total_pages = ceil($total_attachments / $limit);

		if (isset($_POST['postIds']) && !empty($postIds)) { $args = array('post_type' => 'attachment', 'numberposts' => $limit, 'include' => $postIds); }
		else { $args = array('post_type' => 'attachment', 'numberposts' => $limit, 'offset' => $offset); }
		$attachments = get_posts($args);
		foreach ($attachments as $attachment) {
			$file_path = com_ci_hub_get_original_file_path($attachment->ID);
			$attachment->hash = hash_file($cihub_hash_algo, $file_path);
			$attachment->oldHash = get_post_meta($attachment->ID, $cihub_file_hash_key, true);
			$attachment->filesize = filesize($file_path);
			$attachment->filemtime = filemtime($file_path);
			$attachment->linkdatastring = get_post_meta($attachment->ID, $cihub_linkdatastring_key, true);
		}
		wp_die(json_encode(array($attachments, $total_pages)));
	} catch (\Throwable $th) {
		wp_die('wp_ajax_com_ci_hub_retrieve_media_items failed: ' . $th->getMessage(), 400);
	} catch (\Exception $e) {
		wp_die('wp_ajax_com_ci_hub_retrieve_media_items failed: ' . $e->getMessage(), 400);
	}
});

// Host: com_ci_hub_get_attachment_thumbnail_as_data_uri
add_action('wp_ajax_com_ci_hub_get_attachment_thumbnail_as_data_uri', function() {
	function attachment_url_to_path($url) {
		$parsed_url = parse_url($url);
		if(empty($parsed_url['path'])) return false;
		$file = ABSPATH . ltrim( $parsed_url['path'], '/');
		if (file_exists( $file)) return $file;
		return false;
	}
	try {
		if (!isset($_POST['nonce'])) throw new Exception('nonce is missing');
		com_ci_hub_check_nonce($_POST['nonce']);
		if (!isset($_POST['id']) || empty($_POST['id'])) wp_die('');
		$id = sanitize_text_field($_POST['id']);
		if (!wp_attachment_is_image($id)) wp_die('');
		$thumbnail_source = wp_get_attachment_image_src($id, 'thumbnail');
		if (!is_array($thumbnail_source)) wp_die('');
		$thumbnail_url = $thumbnail_source[0];
		$file_path = attachment_url_to_path($thumbnail_url);
		if (!file_exists($file_path)) wp_die('');
		$file_size_valid = filesize($file_path) <= 100 * 1024 * 1024;
		if (!$file_size_valid) wp_die('');
		$content = file_get_contents($file_path);
		wp_die('data:' . mime_content_type($file_path) . ';base64,' . base64_encode($content));
	} catch (\Throwable $th) {
		wp_die('wp_ajax_com_ci_hub_get_attachment_thumbnail_as_data_uri failed: ' . $th->getMessage(), 400);
	} catch (\Exception $e) {
		wp_die('wp_ajax_com_ci_hub_get_attachment_thumbnail_as_data_uri failed: ' . $e->getMessage(), 400);
	}
});

// Host: com_ci_hub_get_admin_url
add_action('wp_ajax_com_ci_hub_get_admin_url', function() {
	try {
		if (!isset($_POST['nonce'])) throw new Exception('nonce is missing');
		com_ci_hub_check_nonce($_POST['nonce']);
		wp_die(get_admin_url());
	} catch (\Throwable $th) {
		wp_die('wp_ajax_com_ci_hub_get_admin_url failed: ' . $th->getMessage(), 400);
	} catch (\Exception $e) {
		wp_die('wp_ajax_com_ci_hub_get_admin_url failed: ' . $e->getMessage(), 400);
	}
});

// Host: com_ci_hub_get_attachment_original_file
add_action('wp_ajax_com_ci_hub_get_attachment_original_file', function() {
	try {
		if (!isset($_POST['nonce'])) throw new Exception('nonce is missing');
		com_ci_hub_check_nonce($_POST['nonce']);
		if (!isset($_POST['id'])) throw new Exception('id is missing');
		$id = sanitize_text_field($_POST['id']);
		$file_path = com_ci_hub_get_original_file_path($id);
		if (!file_exists($file_path)) throw new Exception('original file does not exist: ' . $file_path);
		$content = file_get_contents($file_path);
		header("Content-Type: application/octet-stream");
		wp_die($content);
	} catch (\Throwable $th) {
		wp_die('wp_ajax_com_ci_hub_get_attachment_original_file failed: ' . $th->getMessage(), 400);
	} catch (\Exception $e) {
		wp_die('wp_ajax_com_ci_hub_get_attachment_original_file failed: ' . $e->getMessage(), 400);
	}
});

// Host: com_ci_hub_attachment_usage
add_action('wp_ajax_com_ci_hub_attachment_usage', function() {
	try {
		if (!isset($_POST['nonce'])) throw new Exception('nonce is missing');
		com_ci_hub_check_nonce($_POST['nonce']);
		if (!isset($_POST['id'])) wp_die(json_encode(array()));
		$attachment_id = sanitize_text_field($_POST['id']);

		$thumbnail_occurrences = array();
		if (wp_attachment_is_image($attachment_id)) {
			$thumbnail_query = new WP_Query(array(
				'meta_key'       => '_thumbnail_id',
				'meta_value'     => $attachment_id,
				'post_type'      => 'any',	
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'posts_per_page' => -1,
			));
			$thumbnail_occurrences = $thumbnail_query->posts;
		}

		$attachment_urls = array(wp_get_attachment_url($attachment_id));
		if (wp_attachment_is_image($attachment_id)) {
			foreach (get_intermediate_image_sizes() as $size) {
				$intermediate = image_get_intermediate_size($attachment_id, $size);
				if ($intermediate) { $attachment_urls[] = $intermediate['url']; }
			}
		}

		$content_occurrences = array();
		foreach ($attachment_urls as $attachment_url) {
			$content_query = new WP_Query(array(
				's'              => $attachment_url,
				'post_type'      => 'any',
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'posts_per_page' => -1,
			));
			$content_occurrences = array_merge($content_occurrences, $content_query->posts);
		}

		$occurrences = array_unique(array_merge($thumbnail_occurrences, $content_occurrences));

		$occurrences_with_info = array();
		foreach ($occurrences as $occurrence) {
			$post = get_post($occurrence);
			array_push($occurrences_with_info, array(
				'ID' => $post->ID,
				'title' => $post->post_title,
				'type' => $post->post_type
			));
		}

		wp_die(json_encode($occurrences_with_info));
	} catch (\Throwable $th) {
		wp_die('wp_ajax_com_ci_hub_attachment_usage failed: ' . $th->getMessage(), 400);
	} catch (\Exception $e) {
		wp_die('wp_ajax_com_ci_hub_attachment_usage failed: ' . $e->getMessage(), 400);
	}
});

// Host: com_ci_hub_post_attachments
add_action('wp_ajax_com_ci_hub_post_attachments', function() {
	try {
		if (!isset($_POST['nonce'])) throw new Exception('nonce is missing');
		com_ci_hub_check_nonce($_POST['nonce']);

		if (!isset($_POST['id'])) throw new Exception('id is missing');
		$post_id = sanitize_text_field($_POST['id']);
		$attachment_ids = array();

		$thumbnail_id = get_post_thumbnail_id($post_id);
		if ($thumbnail_id) array_push($attachment_ids, $thumbnail_id);

		$upload_dir = wp_upload_dir();
		$search_string = $upload_dir['baseurl'];

		$content_query = new WP_Query(array(
			's'              => $search_string,
			'p'							 => $post_id,
			'post_type'      => 'any',
			'fields'         => 'all',
			'no_found_rows'  => true,
			'posts_per_page' => -1
		));
		
		$content = $content_query->post->post_content;
		if ($content) {
			$regExString = '/"' . str_replace('/', '\/', $search_string) . '[^ "]+/';
			preg_match_all($regExString, $content, $matches);
			foreach ($matches[0] as $attachment_url) {
				$url = str_replace('"', '', $attachment_url);
				$post_id = attachment_url_to_postid($url);
				if ($post_id) array_push($attachment_ids, $post_id);
				else {
					$url = preg_replace('/^(.+)(-\d+x\d+)(\.[^.]*)$/', '$1$3', $url);
					$post_id = attachment_url_to_postid($url);
					if ($post_id) array_push($attachment_ids, $post_id);
					else {
						$url = preg_replace('/^(.+)(\.[^.]*)$/', '$1-scaled$2', $url);
						$post_id = attachment_url_to_postid($url);
						if ($post_id) array_push($attachment_ids, $post_id);
					}
				}
			}
			$attachment_ids = array_unique($attachment_ids);
		}
		
		wp_die(json_encode($attachment_ids));
	} catch (\Throwable $th) {
		wp_die('wp_ajax_com_ci_hub_post_attachments failed: ' . $th->getMessage(), 400);
	} catch (\Exception $e) {
		wp_die('wp_ajax_com_ci_hub_post_attachments failed: ' . $e->getMessage(), 400);
	}
});

function com_ci_hub_wrap_error($content) {
	return '<div style="background: red; border-radius: 3px; padding: 3px; color: white;">'.$content.'</div>';
}

function com_ci_hub_extract_metadata_return_value($entry, $atts) {
	if ($atts['mode'] === 'name') {
		$return_value = $entry->name;
	} else if ($atts['mode'] === 'entry') {
		$return_value = $entry->name . ': ' . $entry->value;
	} else {
		$return_value = $entry->value;
	}
	return $return_value;
}

function com_ci_hub_remove_key_prefix($key) {
	global $cihub_metadata_key_prefix;
	if (strpos($key, $cihub_metadata_key_prefix) === 0) {
		return str_replace($cihub_metadata_key_prefix, '', $key);
	} else {
		return $key;
	}
}

// Shortcode: metadata
add_shortcode('cihub_metadata', function ($atts, $content) {
	global $cihub_translated_metadata_key;
	global $cihub_metadata_key_prefix;

	$a = shortcode_atts(array(
		'id' => '',
		'key' => '',
		'mode' => 'value',
		'wrapper' => 'div',
		'content' => 'true',
		'htmlattributes' => '',
		'throwerror' => 'true',
		'fallback' => ''
	), $atts);

	if (empty($a['key'])) { return com_ci_hub_wrap_error('Attribute "key" is required.'); }
	$key = com_ci_hub_remove_key_prefix($a['key']);

	$post_id = empty($a['id']) ? get_the_ID() : $a['id'];
	
	$attribute_string = '';
	$raw_attribute_string = empty($a['htmlattributes']) ? '' : $a['htmlattributes'];
	if (!empty($raw_attribute_string)) {
		$attribute_entries = explode(',', $a['htmlattributes']);
		foreach ($attribute_entries as $entry) {
			$attribute_key = explode('=', $entry)[0];
			$attribute_value = explode('=', $entry)[1];
			$attribute_string .= $attribute_key . '="' . $attribute_value . '" ';
		}
	}
	
	$wrapper_start = empty($a['wrapper']) || $a['wrapper'] === 'false' ? '' : '<'.$a['wrapper']. ' ' . $attribute_string . '>';
	$wrapper_end = empty($a['wrapper']) || $a['wrapper'] === 'false' ? '' : '</'.$a['wrapper'].'>';

	$key_not_found_message = 'Key not found';
	
	$translated_metadata = get_post_meta($post_id, $cihub_translated_metadata_key, true);
	if (!is_array($translated_metadata)) { return com_ci_hub_wrap_error('Metadata for attachment with ID "'.$post_id.'" not found.'); }

	foreach ($translated_metadata as $entry) {
		if (property_exists($entry, 'key')) {
			$prefixed_key = $cihub_metadata_key_prefix . $entry->key;
			if ($prefixed_key === $cihub_metadata_key_prefix . $key) {
				$return_value = com_ci_hub_extract_metadata_return_value($entry, $a);
				$html_element_string = $wrapper_start . (empty($a['content']) || $a['content'] === 'false' ? '' : $return_value) . ' ' . $content . '' . $wrapper_end;

				$combined_metadata_search_string = '{metadata.';
				while (strpos($html_element_string, $combined_metadata_search_string) !== false) {
					$metadata_position = strpos($html_element_string, $combined_metadata_search_string);
					$metadata_closing_bracket_position = strpos($html_element_string, '}', $metadata_position);
					$metadata_key = substr($html_element_string, $metadata_position + 10, $metadata_closing_bracket_position - $metadata_position - 10);
					$metadata_key = com_ci_hub_remove_key_prefix($metadata_key);
					$metadata_placeholder_string = $combined_metadata_search_string . $metadata_key . '}';

					$found = false;
					foreach ($translated_metadata as $nested_entry) {
						if (property_exists($nested_entry, 'key')) {
							$nested_prefixed_key = $cihub_metadata_key_prefix . $nested_entry->key;
							if ($cihub_metadata_key_prefix . $metadata_key === $nested_prefixed_key) {
								$found = true;
								$html_element_string = str_replace($metadata_placeholder_string, com_ci_hub_extract_metadata_return_value($nested_entry, $a), $html_element_string);
							}
						}
					}
					if (!$found && $a['throwerror'] === 'true') return com_ci_hub_wrap_error($key_not_found_message);
					else $html_element_string = str_replace($metadata_placeholder_string, $a['fallback'] ? $a['fallback'] : $key_not_found_message, $html_element_string);
				}
				return str_replace('{metadata}', $return_value, $html_element_string);
			}
		}
	}

	if ($a['throwerror'] === 'true') return com_ci_hub_wrap_error($key_not_found_message);
	$return_value = $a['fallback'] ? $a['fallback'] : $key_not_found_message;
	return str_replace('{metadata}', $return_value, $wrapper_start . (empty($a['content']) || $a['content'] === 'false' ? '' : $return_value) . ' ' . $content . '' . $wrapper_end);
});