<?php

/**
 * Setup hookds for KB Articles
 */
class EPKB_Articles_Admin {

	const KB_ARTICLES_SEQ_META =  'epkb_articles_sequence';

	public function __construct() {

		add_action( 'deleted_post', array( $this, 'update_articles_sequence' ) );

		$kb_post_type = EPKB_KB_Handler::get_post_type2();
		if ( empty($kb_post_type) ) {
			return;
		}

		// handle KB article sequence
		add_action( 'save_post_' . $kb_post_type, array( $this, 'update_articles_sequence' ) );

		// refresh article order when on All Articles page
		add_action( 'restrict_manage_posts', array( $this, 'update_articles_sequence' ) );
		// only published posts are queried so ignore: add_action( 'wp_trash_post', array( $this, 'update_articles_sequence' ) );
	}

	/**
	 * After a post is saved, updated or deleted, update articles sequence.
	 *
	 * @param $post_id  (unused)
	 * @return false on error
	 */
	public function update_articles_sequence( $post_id ) {

		// ignore autosave/revision which is not article submission; same with ajax and bulk edit
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || ( defined( 'DOING_AJAX') && DOING_AJAX ) || isset( $_REQUEST['bulk_edit'] ) ) {
			return true;
		}

		$kb_id = EPKB_KB_Handler::get_current_kb_id();
		if ( empty($kb_id) ) {
			EPKB_Logging::add_log("Found empty kb id" );
			return false;
		}

		// 1. get stored sequence of articles
		$article_order_method = epkb_get_instance()->kb_config_obj->get_value( 'articles_display_sequence', $kb_id );

		// 2. get all term ids  ( do not use WP function get_terms() to avoid recursions or unhook actions )
		$all_kb_terms = EPKB_Utilities::get_kb_categories( $kb_id );
		if ( $all_kb_terms === null ) {
			return false;
		}

		// 3. FOR EACH CATEGORY:
		$new_stored_ids = array();
		$db = new EPKB_Articles_DB();
		foreach( $all_kb_terms as $term ) {

			// 3. setup sequence of articles within this category
			if ( $article_order_method == 'created-date' ) {
				$articles = $db->get_published_articles_by_sub_or_category( $kb_id, $term->term_id );
			} else {  // for 'user-sequenced' use default for now otherwise default is 'alphabetical-title'
				$articles = $db->get_published_articles_by_sub_or_category( $kb_id, $term->term_id, 'title' );  
			}

			// 4. add article sequence to the configuration
			$new_article_sequence = EPKB_Articles_Array::retrieve_article_sequence( $articles );
			$new_stored_ids[$term->term_id] = array( '0' => $term->name, '1' => $term->description);
			foreach( $new_article_sequence as $article_id => $article_title ) {
				$new_stored_ids[$term->term_id] += array( $article_id => $article_title );
			}
		}

		// 4. stored new configuration
		$new_article_ids_obj = new EPKB_Articles_Array( $new_stored_ids ); // normalizes and sanitizes the array as well

		// for custom sequenced update the custom sequence with changes
		if ( $article_order_method == 'user-sequenced' ) {
			$orig_sequence = $this->get_orig_custom_sequence( $kb_id );
			if ( $orig_sequence === false ) {
				return false;
			}
			$config = new EPKB_KB_Config_Sequence();
			$new_article_ids_obj = $config->update_articles_order( $kb_id, $orig_sequence, $new_article_ids_obj );
			if ( $new_article_ids_obj === false ) {
				return false;
			}
		}

		EPKB_Utilities::save_kb_option( $kb_id, self::KB_ARTICLES_SEQ_META, $new_article_ids_obj->ids_array, true );

		return true;
	}

	/**
	 * Update custom order with changed articles/categories
	 *
	 * @param $kb_id
	 * @return array|false on error
	 */
	public function get_orig_custom_sequence( $kb_id ) {

		$stored_articles_ids = EPKB_Utilities::get_kb_option( $kb_id, self::KB_ARTICLES_SEQ_META, null, true );
		if ( $stored_articles_ids === null ) {
			return false;
		}

		$custom_sequence = array();
		foreach( $stored_articles_ids as $category_id => $articles_array ) {

			$custom_sequence[] = array( $category_id, 'category' );

			$ix = 0;
			foreach( $articles_array as $article_id => $article_title ) {
				if ( $ix ++ < 2 ) {
					continue;
				}
				$custom_sequence[] = array( $article_id, 'article' );
			}
		}

		return $custom_sequence;
	}

	/**
	 * Retrieve non-custom sequence of articles e.g. based on date or title
	 *
	 * @param $kb_id
	 * @param $article_order_method
	 * @return array|false on error
	 */
	public function get_articles_sequence_non_custom( $kb_id, $article_order_method ) {

		// 1. get all term ids  ( do not use WP function get_terms() to avoid recursions or unhook actions )
		$all_kb_terms = EPKB_Utilities::get_kb_categories( $kb_id );
		if ( $all_kb_terms === null ) {
			return false;
		}

		// 3. FOR EACH CATEGORY:
		$new_stored_ids = array();
		$db = new EPKB_Articles_DB();
		foreach( $all_kb_terms as $term ) {

			// 3. setup sequence of articles within this category
			if ( $article_order_method == 'created-date' ) {
				$articles = $db->get_published_articles_by_sub_or_category( $kb_id, $term->term_id );
			} else {  // for 'user-sequenced' use default for now otherwise default is 'alphabetical-title'
				$articles = $db->get_published_articles_by_sub_or_category( $kb_id, $term->term_id, 'title' );
			}

			// 4. add article sequence to the configuration
			$new_article_sequence = EPKB_Articles_Array::retrieve_article_sequence( $articles );
			$new_stored_ids[$term->term_id] = array( '0' => $term->name, '1' => $term->description);
			foreach( $new_article_sequence as $article_id => $article_title ) {
				$new_stored_ids[$term->term_id] += array( $article_id => $article_title );
			}
		}

		// 5. stored new configuration
		$new_article_ids_obj = new EPKB_Articles_Array( $new_stored_ids ); // normalizes and sanitizes the array as well

		return $new_article_ids_obj->ids_array;
	}
}
