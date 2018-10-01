<?php  if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handle operations on knowledge base such as adding, deleting and updating KB
 *
 * @copyright   Copyright (C) 2018, Echo Plugins
 */
class EPKB_KB_Handler {

	// name of KB shortcode
	const KB_MAIN_PAGE_SHORTCODE_NAME = 'epkb-knowledge-base'; // changing this requires db update

	// Prefix for custom post type name associated with given KB; this will never change
	const KB_POST_TYPE_PREFIX = 'epkb_post_type_';  // changing this requires db update
	const KB_CATEGORY_TAXONOMY_SUFFIX = '_category';  // changing this requires db update; do not translate
	const KB_TAG_TAXONOMY_SUFFIX = '_tag'; // changing this requires db update; do not translate

	/**
	 * Get KB slug based on default KB name and ID. Default KB has slug without ID.
	 *
	 * @param $kb_id
	 *
	 * @return string
	 */
	public static function get_default_slug( $kb_id ) {
		/* translators: do NOT change this translation again. It will break links !!! */
		return sanitize_title_with_dashes( _x( 'Knowledge Base', 'slug', 'echo-knowledge-base' ) . ( $kb_id == EPKB_KB_Config_DB::DEFAULT_KB_ID ? '' : '-' . $kb_id ) );
	}

	/**
	 * Create a new Knowledge Base using default configuration when:
	 *  a) plugin is installed and activated
	 *  b) user clicks on 'Add Knowledge Base' button (requires Multiple KBs add-on)
	 *
	 * First default knowledge base has name 'Knowledge Base' with ID 1
	 * Add New KB will create KB with pre-set name 'Knowledge Base 2' with ID 2 and so on.
	 *
	 * @param int $new_kb_id - ID of the new KB
	 * @param $new_kb_main_page_title
	 * @param string $new_kb_main_page_slug
	 * @return array|WP_Error - the new KB configuration or WP_Error
	 */
	public static function add_new_knowledge_base( $new_kb_id, $new_kb_main_page_title, $new_kb_main_page_slug='' ) {

		// use default KB configuration for a new KB
		$update_kb_config = true;

		// use default KB configuration ONLY if none exists
		$kb_config = epkb_get_instance()->kb_config_obj->get_kb_config( $new_kb_id );
		if ( is_wp_error( $kb_config ) || ! is_array($kb_config) ) {
			$kb_config = EPKB_KB_Config_Specs::get_default_kb_config( $new_kb_id );
		} else {
			$update_kb_config = false;
		}

		// 1. register custom post type for this knowledge base
		$error = EPKB_Articles_CPT_Setup::register_custom_post_type( $kb_config, $new_kb_id );
		if ( is_wp_error( $error ) ) {
			EPKB_Logging::add_log("Could not register post type when adding a new KB", $new_kb_id, $error);
			// ignore error and try to continue
		}

		// 2. Add a sample category and sub-category with article each if no category exists
		$all_kb_terms = EPKB_Utilities::get_kb_categories( $new_kb_id );
		if ( empty($all_kb_terms) ) {
			self::create_sample_categories( $new_kb_id );
		}

		// 3. Add KB Main Page
		$kb_main_pages = $kb_config['kb_main_pages'];
		if ( empty($kb_main_pages) ) {

			// we add new KB Page here so remove hook
			remove_filter('save_post', 'epkb_save_any_page', 10 );
			// do not process KB shortcode during KB creation
			remove_shortcode( EPKB_KB_Handler::KB_MAIN_PAGE_SHORTCODE_NAME );

			$my_post = array(
				'post_title'    => $new_kb_main_page_title,
				'post_name'     => $new_kb_main_page_slug,
				'post_type'     => 'page',
				'post_content'  => '[' . self::KB_MAIN_PAGE_SHORTCODE_NAME . ' id=' . $new_kb_id . ']',
				'post_status'   => 'publish',
				'comment_status' => 'closed'
				// current user or 'post_author'   => 1,
			);
			$post_id = wp_insert_post( $my_post );
			if ( is_wp_error( $post_id ) || empty($post_id) ) {
				EPKB_Logging::add_log("Could not insert new post", $new_kb_id, $post_id);
			} else {
				$post = WP_Post::get_instance( $post_id );
				$kb_config['kb_name'] = $post->post_title;
				$kb_main_pages[ $post_id ] = $post->post_title;
				$kb_config['kb_main_pages'] = $kb_main_pages;
				$kb_config['kb_articles_common_path'] = $post->post_name;
				$update_kb_config = true;
			}
		}

		// 5. save new/updated KB configuration
		if ( $update_kb_config ) {
			$result = epkb_get_instance()->kb_config_obj->update_kb_configuration( $new_kb_id, $kb_config );
			if ( is_wp_error( $result ) ) {
				EPKB_Logging::add_log( "Could not save configuration in the new KB", $new_kb_id, $result );
				return $result;
			}
		}

		// let add-ons know we have a new KB; does not apply to default KB because at that time add-on is not even active
		if ( $new_kb_id !== EPKB_KB_Config_DB::DEFAULT_KB_ID ) {
			do_action( 'eckb_new_knowledge_base_added', $new_kb_id );
		}

		return $kb_config;
	}

	private static function create_sample_categories( $new_kb_id ) {

		$kb_category_taxonomy_name = self::get_category_taxonomy_name( $new_kb_id );

		// first create category with an article
		$smaple_category_name = __( 'Articles', 'echo-knowledge-base' );
		$sample_article_title = __( 'Knowledge Base Article', 'echo-knowledge-base' );
		$sample_cat = self::create_sample_category_and_article( $new_kb_id, $kb_category_taxonomy_name, $smaple_category_name, $sample_article_title, null );
		if ( empty($sample_cat) ) {
			return;
		}

		// second create sub-category with an article
		$smaple_category_name = __( 'Other', 'echo-knowledge-base' );
		$sample_sub_cat = self::create_sample_category_and_article( $new_kb_id, $kb_category_taxonomy_name, $smaple_category_name, $sample_article_title . ' #2', $sample_cat['category_id'] );

		// save category sequence data
		$cat_seq_meta = empty($sample_sub_cat) ? array( $sample_cat['category_id'] => '' )
			                                   : array( $sample_cat['category_id'] => array( $sample_sub_cat['category_id'] => '' ) );
		EPKB_Utilities::save_kb_option( $new_kb_id, EPKB_Categories_Admin::KB_CATEGORIES_SEQ_META, $cat_seq_meta, true );

		// save articles sequence data
		$articles_array = $sample_cat['articles_array'];
		if ( is_array($sample_sub_cat) ) {
			$articles_array += $sample_sub_cat['articles_array'];
		}
		EPKB_Utilities::save_kb_option( $new_kb_id, EPKB_Articles_Admin::KB_ARTICLES_SEQ_META, $articles_array, true );

	}

	private static function create_sample_category_and_article( $new_kb_id, $kb_category_taxonomy_name, $category_name, $post_title, $parent_id ) {

		$args = empty($parent_id) ? array() : array( 'parent' => $parent_id );

		// insert category
		$term_id_array = wp_insert_term( $category_name, $kb_category_taxonomy_name, $args );
		if ( is_wp_error($term_id_array) ) {
			EPKB_Logging::add_log( 'Failed to insert category for new KB. cat name: ' . $category_name . ', taxonomy: ' . $kb_category_taxonomy_name . ' kb id: ' . $new_kb_id, $args, $term_id_array );
			return null;
		}
		if ( ! isset($term_id_array['term_id']) ) {
			EPKB_Logging::add_log( 'Failed to insert category for new KB. cat name: ' . $category_name . ', taxonomy: ' . $kb_category_taxonomy_name . ' kb id: ' . $new_kb_id, $args );
			return null;
		}

		$kb_term_id = $term_id_array['term_id'];

		$my_post = array(
			'post_title'    => $post_title,
			'post_type'     => self::get_post_type( $new_kb_id ),
			'post_content'  => __( 'Coming Soon.', 'echo-knowledge-base' ),
			'post_status'   => 'publish',
			// current user or 'post_author'   => 1,
		);

		// create article under category
		$post_id = wp_insert_post( $my_post );
		if ( is_wp_error( $post_id ) || empty($post_id) ) {
			$wp_error = is_wp_error( $post_id ) ? $post_id : new WP_Error(124, "post_id is emtpy");
			EPKB_Logging::add_log( 'Cound not insert post for new KB', $new_kb_id, $wp_error );
			return null;
		}

		$result = wp_set_object_terms( $post_id, $kb_term_id, $kb_category_taxonomy_name );
		if ( is_wp_error($result) ) {
			EPKB_Logging::add_log( 'Cound not insert default category for new KB. post id: ' . $post_id . ' term id: ' . $kb_term_id . ', taxonomy: ' . $kb_category_taxonomy_name, $new_kb_id, $result );
			return null;
		}

		$articles_array = array( $kb_term_id => array( '0' => $category_name, '1' => '', $post_id => $post_title ) );

		return array( 'category_id' => $kb_term_id, 'articles_array' => $articles_array );
	}

	/**
	 * Update KB if plugin is reactivated
	 *
	 * First default knowledge base has name 'Knowledge Base' with ID 1
	 * If missing add:
	 * - first category
	 * - first article
	 * - first KB main page
	 *
	 * @param int $new_kb_id - ID of the new KB
	 * @param $new_kb_main_page_title
	 * @param string $new_kb_main_page_slug
	 * @return array|WP_Error - the new KB configuration or WP_Error
	 */
	public static function update_existing_knowledge_base( $new_kb_id, $new_kb_main_page_title, $new_kb_main_page_slug='' ) {

		$update_kb_config = false;
		$kb_config = epkb_get_instance()->kb_config_obj->get_kb_config( $new_kb_id );
		if ( is_wp_error( $kb_config ) ) {
			EPKB_Logging::add_log("Could not retrieve KB configuration (12)", $new_kb_id, $kb_config);
			return $kb_config;
		}

		// 1. register custom post type for this knowledge base
		$error = EPKB_Articles_CPT_Setup::register_custom_post_type( $kb_config, $new_kb_id );
		if ( is_wp_error( $error ) ) {
			EPKB_Logging::add_log("Could not register post type when adding a new KB", $new_kb_id, $error);
			// ignore error and try to continue
		}

		// 2. Add first KB Main page if none exists; first KB is just called Knowledge Base
		$kb_main_pages = $kb_config['kb_main_pages'];
		if ( empty($kb_main_pages) ) {

			// handle adding Main page here
			remove_filter('save_post', 'epkb_save_any_page', 10 );

			// we don't want to process KB shortcode during KB creation
			remove_shortcode( EPKB_KB_Handler::KB_MAIN_PAGE_SHORTCODE_NAME );

			$new_kb_main_page_slug = empty($new_kb_main_page_slug) ? $new_kb_main_page_title : '';
			$my_post = array(
				'post_title'    => $new_kb_main_page_title,
				'post_name'     => $new_kb_main_page_slug,
				'post_type'     => 'page',
				'post_content'  => '[' . self::KB_MAIN_PAGE_SHORTCODE_NAME . ' id=' . $new_kb_id . ']',
				'post_status'   => 'publish',
				'comment_status' => 'closed'
				// current user or 'post_author'   => 1,
			);
			$post_id = wp_insert_post( $my_post );
			if ( is_wp_error( $post_id ) || empty($post_id) ) {
				EPKB_Logging::add_log("Could not insert new post", $new_kb_id, $post_id);
			} else {
				$post = WP_Post::get_instance( $post_id );
				$kb_main_pages[ $post_id ] = $post->post_title;
				$kb_config['kb_main_pages'] = $kb_main_pages;
				$kb_config['kb_articles_common_path'] = $post->post_name;
				$update_kb_config = true;
			}
		}

		// 3. save new/updated KB configuration
		if ( $update_kb_config ) {
			$result = epkb_get_instance()->kb_config_obj->update_kb_configuration( $new_kb_id, $kb_config );
			if ( is_wp_error( $result ) ) {
				EPKB_Logging::add_log( "Could not save configuration in the new KB", $new_kb_id, $result );
				return $result;
			}
		}

		return $kb_config;
	}

	/**
	 * Retrieve current KB ID based on post_type value in URL based on user request etc.
	 *
	 * @return String | <empty> if not found
	 */
	public static function get_current_kb_id() {
		global $current_screen;

		// 1. retrieve current post being used and if user selected a tab for specific KB
		$kb_id = new WP_Error('unknown KB ID.');
		$kb_post_type = empty($_REQUEST['post_type']) ? '' : preg_replace('/[^A-Za-z0-9 \-_]/', '', $_REQUEST['post_type']); // sanitize_text_field( $_REQUEST['post_type'] );
		if ( ! empty($kb_post_type) && $kb_post_type != 'page' ) {
			$kb_id = self::get_kb_id_from_post_type( $kb_post_type );
		}

		$epkb_taxonomy = empty($_REQUEST['taxonomy']) ? '' : preg_replace('/[^A-Za-z0-9 \-_]/', '', $_REQUEST['taxonomy']);

		if ( is_wp_error( $kb_id ) && ! empty($epkb_taxonomy) && ! in_array($epkb_taxonomy, array('category', 'tag', 'post_tag')) ) {
			$kb_id = self::get_kb_id_from_category_taxonomy_name( $epkb_taxonomy );
		}

		if ( is_wp_error( $kb_id ) && ! empty($epkb_taxonomy) && ! in_array($epkb_taxonomy, array('category', 'tag', 'post_tag')) ) {
			$kb_id = self::get_kb_id_from_tag_taxonomy_name( $epkb_taxonomy );
		}

		if ( is_wp_error( $kb_id ) && isset($current_screen->post_type) && ! empty($current_screen->post_type) && ! in_array($current_screen->post_type, array('page', 'attachment', 'post') ) ) {
			$kb_id = self::get_kb_id_from_post_type( $current_screen->post_type );
		}

		$epkb_action = empty($_REQUEST['action']) ? '' : preg_replace('/[^A-Za-z0-9 \-_]/', '', $_REQUEST['action']);

		// e.g. when adding category within KB article
		if ( is_wp_error( $kb_id ) && ! empty($epkb_action) && strpos( $epkb_action, self::KB_POST_TYPE_PREFIX ) !== false ) {
			$found_kb_id = str_replace('add-', '', $epkb_action);
			$found_kb_id = EPKB_KB_Handler::get_kb_id_from_category_taxonomy_name( $found_kb_id );
			if ( ! empty($found_kb_id) && ! is_wp_error($found_kb_id) && EPKB_Utilities::is_positive_int( $found_kb_id ) ) {
				$kb_id = $found_kb_id;
			}
		}

		$epkb_kb_id = empty($_REQUEST['epkb_kb_id']) ? '' : preg_replace('/\D/', '', $_REQUEST['epkb_kb_id']);
		if ( is_wp_error( $kb_id ) && ! empty($epkb_kb_id) && EPKB_Utilities::is_positive_int( $epkb_kb_id )) {
			$kb_id = $epkb_kb_id;
		}

		$epkb_post = empty($_REQUEST['post']) ? '' : preg_replace('/\D/', '', $_REQUEST['post']);

		// when editing article
		if ( is_wp_error( $kb_id ) && ! empty($epkb_action) && $epkb_action == 'edit' && ! empty($epkb_post) && EPKB_Utilities::is_positive_int( $epkb_post )) {
			$post = EPKB_Utilities::get_kb_post_secure( $epkb_post );
			if ( ! empty($post) ) {
				$kb_id = self::get_kb_id_from_post_type( $post->post_type );
			}
		}

		if ( empty($kb_id) || is_wp_error( $kb_id ) || ! EPKB_Utilities::is_positive_int( $kb_id ) ) {
			return '';
		}

		// 2. check if the "current id" belongs to one of the existing KBs
		if ( $kb_id != EPKB_KB_Config_DB::DEFAULT_KB_ID ) {
			$db_kb_config = new EPKB_KB_Config_DB();
			$kb_ids = $db_kb_config->get_kb_ids();
			if ( ! in_array( $kb_id, $kb_ids ) ) {
				EPKB_Logging::add_log("Found current KB ID to be unknown", $kb_id);
				return '';
			}
		}

		return $kb_id;
	}

	/**
	 * Is this KB post type?
	 *
	 * @param $post_type
	 * @return bool
	 */
	public static function is_kb_post_type( $post_type ) {
		if ( empty($post_type) || ! is_string($post_type)) {
			return false;
		}
		// we are only interested in KB articles
		return strncmp($post_type, self::KB_POST_TYPE_PREFIX, strlen(self::KB_POST_TYPE_PREFIX)) == 0;
	}

	/**
	 * Is this KB taxonomy?
	 *
	 * @param $taxonomy
	 * @return bool
	 */
	public static function is_kb_taxonomy( $taxonomy ) {
		if ( empty($taxonomy) || ! is_string($taxonomy) ) {
			return false;
		}
		// we are only interested in KB articles
		return strncmp($taxonomy, self::KB_POST_TYPE_PREFIX, strlen(self::KB_POST_TYPE_PREFIX)) == 0;
	}

	/**
	 * Does request have KB taxonomy or post type ?
	 *
	 * @return bool
	 */
	public static function is_kb_request() {

		$kb_post_type = empty($_REQUEST['post_type']) ? '' : preg_replace('/[^A-Za-z0-9 \-_]/', '', $_REQUEST['post_type']);
		$is_kb_post_type = empty($kb_post_type) ? false : self::is_kb_post_type( $kb_post_type );
		if ( $is_kb_post_type ) {
			return true;
		}

		$kb_taxonomy = empty($_REQUEST['taxonomy']) ? '' : preg_replace('/[^A-Za-z0-9 \-_]/', '', $_REQUEST['taxonomy']);
		$is_kb_taxonomy = empty($kb_taxonomy) ? false : self::is_kb_taxonomy( $kb_taxonomy );

		return $is_kb_taxonomy;
	}

	/**
	 * Retrieve current KB post type based on post_type value in URL based on user request etc.
	 *
	 * @return String | <empty> if valid post type not found
	 */
	public static function get_current_kb_post_type() {
		$kb_id = self::get_current_kb_id();
		if ( empty( $kb_id ) ) {
			return '';
		}
		return self::get_post_type( $kb_id );
	}

	/**
	 * Retrieve KB post type name e.g. ep kb_post_type_1
	 *
	 * @param $kb_id - assumed valid id
	 *
	 * @return string
	 */
	public static function get_post_type( $kb_id ) {
		$kb_id = EPKB_Utilities::sanitize_int($kb_id, EPKB_KB_Config_DB::DEFAULT_KB_ID );
		return self::KB_POST_TYPE_PREFIX . $kb_id;
	}

	/**
	 * Retrieve KB post type name e.g. <post type>_1
	 *
	 * @return string | <empty> when kb id cannot be determined
	 */
	public static function get_post_type2() {
		$kb_id = self::get_current_kb_id();
		if ( empty( $kb_id ) ) {
			return '';
		}
		return self::KB_POST_TYPE_PREFIX . $kb_id;
	}

	/**
	 * Return category name e.g. ep kb_post_type_1_category
	 *
	 * @param $kb_id - assumed valid id
	 *
	 * @return string
	 */
	public static function get_category_taxonomy_name( $kb_id ) {
		return self::get_post_type( $kb_id ) . self::KB_CATEGORY_TAXONOMY_SUFFIX;
	}

	/**
	 * Return category name e.g. <post type>_1_category
	 *
	 * @return string | <empty> when kb id cannot be determined
	 */
	public static function get_category_taxonomy_name2() {
		$kb_id = self::get_current_kb_id();
		if ( empty( $kb_id ) ) {
			return '';
		}
		return self::get_post_type( $kb_id ) . self::KB_CATEGORY_TAXONOMY_SUFFIX;
	}

	/**
	 * Return tag name e.g. ep kb_post_type_1_tag
	 *
	 * @param $kb_id - assumed valid id
	 *
	 * @return string
	 */
	public static function get_tag_taxonomy_name( $kb_id ) {
		return self::get_post_type( $kb_id ) . self::KB_TAG_TAXONOMY_SUFFIX;
	}

	/**
	 * Retrieve KB ID from category taxonomy name
	 *
	 * @param $category_name
	 *
	 * @return int | WP_Error
	 */
	public static function get_kb_id_from_category_taxonomy_name( $category_name ) {
		if ( empty($category_name) || in_array($category_name, array('category', 'tag', 'post_tag')) || ! is_string($category_name) ) {
			return new WP_Error('40', "kb_id not found");
		}

		$kb_id = str_replace( self::KB_POST_TYPE_PREFIX, '', $category_name );
		if ( empty($kb_id) ) {
			return new WP_Error('41', "kb_id not found");
		}

		$kb_id = str_replace( self::KB_CATEGORY_TAXONOMY_SUFFIX, '', $kb_id );
		if ( ! EPKB_Utilities::is_positive_int( $kb_id ) ) {
			return new WP_Error('42', "kb_id not valid");
		}

		return $kb_id;
	}

	/**
	 * Retrieve KB ID from tag taxonomy name
	 *
	 * @param $tag_name
	 *
	 * @return int | WP_Error
	 */
	public static function get_kb_id_from_tag_taxonomy_name( $tag_name ) {
		if ( empty($tag_name) || in_array($tag_name, array('category', 'tag', 'post_tag')) || ! is_string($tag_name) ) {
			return new WP_Error('50', "kb_id not found");
		}

		$kb_id = str_replace( self::KB_POST_TYPE_PREFIX, '', $tag_name );
		if ( empty($kb_id) ) {
			return new WP_Error('51', "kb_id not found");
		}

		$kb_id = str_replace( self::KB_TAG_TAXONOMY_SUFFIX, '', $kb_id );
		if ( ! EPKB_Utilities::is_positive_int( $kb_id ) ) {
			return new WP_Error('52', "kb_id not valid");
		}

		return $kb_id;
	}

	/**
	 * Retrieve KB ID from article type name
	 *
	 * @param String $post_type is post or post type
	 *
	 * @return int | WP_Error if no kb_id found
	 */
	public static function get_kb_id_from_post_type( $post_type ) {
		if ( empty($post_type) || in_array($post_type, array('page', 'attachment', 'post')) || ! is_string($post_type) ) {
			return new WP_Error('35', "kb_id not found");
		}

		$kb_id = str_replace( self::KB_POST_TYPE_PREFIX, '', $post_type );
		if ( ! EPKB_Utilities::is_positive_int( $kb_id ) ) {
			return new WP_Error('36', "kb_id not valid");
		}

		return $kb_id;
	}

	/**
	 * Determine if the current page is KB main page i.e. it contains KB shortcode and return its KB ID if any
	 * @param null $the_post - either pass post to the method or use current post
	 * @return int|null return KB ID if current page is KB main page otherwise null
	 */
	public static function get_kb_id_from_kb_main_shortcode( $the_post=null ) {
		/** @var $wpdb Wpdb */
		global $wpdb;

		// ensure WP knows about the shortcode
		add_shortcode( self::KB_MAIN_PAGE_SHORTCODE_NAME, array( 'EPKB_Layouts_Setup', 'output_kb_page_shortcode' ) );

		$global_post = empty($GLOBALS['post']) ? '' : $GLOBALS['post'];
		$apost = empty($the_post) ? $global_post : $the_post;
        if ( empty($apost) || ! $apost instanceof WP_Post ) {
            return null;
        }

		// determine whether this page contains this plugin shortcode
		$content = '';
		if ( has_shortcode( $apost->post_content, self::KB_MAIN_PAGE_SHORTCODE_NAME ) ) {
			$content = $apost->post_content;
		} else if ( isset($apost->ID) ) {
			$content = $wpdb->get_var( "SELECT meta_value FROM $wpdb->postmeta " .
			                           "WHERE post_id = {$apost->ID} and meta_value LIKE '%%" . self::KB_MAIN_PAGE_SHORTCODE_NAME . "%%'" );
		}

		return self::get_kb_id_from_shortcode( $content );
	}

	/**
	 * Retrieve KB ID from post content - shortcode
	 *
	 * @param String $content should have the shortcode with KB ID
	 *
	 * @return int|null returns KB ID if found
	 */
	private static function get_kb_id_from_shortcode( $content ) {

		if ( empty($content) || ! is_string($content) ) {
			return null;
		}

		$start = strpos($content, self::KB_MAIN_PAGE_SHORTCODE_NAME);
		if ( empty($start) || $start < 0 ) {
			return null;
		}

		$end = strpos($content, ']', $start);
		if ( empty($start) || $start < 1 ) {
			return null;
		}

		$shortcode = substr($content, $start, $end);
		if ( empty($shortcode) || strlen($shortcode) < strlen(self::KB_MAIN_PAGE_SHORTCODE_NAME)) {
			return null;
		}

		preg_match_all('!\d+!', $shortcode, $number);
		$number = empty($number[0][0]) ? 0 : $number[0][0];
		if ( ! EPKB_Utilities::is_positive_int( $number ) ) {
			return null;
		}

		return (int)$number;
	}

    /**
     * Return all KB Main pages that we know about. Also remove old ones.
     *
     * @param $kb_config
     * @return array a list of KB Main Pages titles and links
     */
	public static function get_kb_main_pages( $kb_config) {

		$kb_main_pages = $kb_config['kb_main_pages'];
		$kb_main_pages_info = array();
		foreach ( $kb_main_pages as $post_id => $post_title ) {

			$post_status = get_post_status( $post_id );

			// remove previous page versions
			if ( empty( $post_status ) || $post_status == 'inherit' || $post_status == 'trash' ) {
				unset( $kb_main_pages[ $post_id ] );
				continue;
			}

			$post = get_post( $post_id );
			if ( empty( $post ) || is_array( $post ) || ! $post instanceof WP_Post ) {
				unset( $kb_main_pages[ $post_id ] );
				continue;
			}

			// remove page that does not contain KB shortcode any more
			$kb_id = self::get_kb_id_from_kb_main_shortcode( $post );
			if ( empty( $kb_id ) || $kb_id != $kb_config['id'] ) {
				unset( $kb_main_pages[ $post_id ] );
				continue;
			}

			$kb_post_slug = get_page_uri($post_id);
			if ( is_wp_error( $kb_post_slug ) || empty($kb_home_slug) || is_array($kb_post_slug) ) {
				$kb_home_slug = EPKB_KB_Handler::get_default_slug( $kb_id );
			}

			$kb_main_pages_info[$post_id] = array( 'post_title' => $post_title, 'post_status' => EPKB_Utilities::get_post_status_text( $post_status ), 'post_slug' => $kb_post_slug );
		}

		// we need to remove pages that are revisions
		if ( count( $kb_config['kb_main_pages'] ) != count($kb_main_pages) ) {
			$kb_config['kb_main_pages'] = $kb_main_pages;
			epkb_get_instance()->kb_config_obj->update_kb_configuration( $kb_config['id'], $kb_config );
		}

		return $kb_main_pages_info;
	}

    /**
     * Find KB Main Page that is not in trash and get its URL.
     *
     * @param $kb_config
     * @return string|<empty>
     */
	public static function get_first_kb_main_page_url( $kb_config ) {
		$first_page_id = '';
        $kb_main_pages = $kb_config['kb_main_pages'];
        foreach ( $kb_main_pages as $post_id => $post_title ) {
			$first_page_id = $post_id;
			break;
		}

        $first_page_url = empty($first_page_id) ? '' : get_permalink( $first_page_id );

		return is_wp_error( $first_page_url ) ? '' : $first_page_url;
	}
}
