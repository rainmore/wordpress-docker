<?php 
add_action( 'wp_enqueue_scripts', 'mywiki_theme_setup' );
function mywiki_theme_setup(){

 wp_enqueue_style( 'google-fonts-open-sans','//fonts.googleapis.com/css?family=Open+Sans', array(), false,null );
 wp_enqueue_style( 'google-fonts-lato', '//fonts.googleapis.com/css?family=Lato', array(), false,null );
 wp_enqueue_style( 'google-fonts-cabin', '//fonts.googleapis.com/css?family=Cabin', array(), false,null );

  wp_enqueue_style( 'bootstrap', get_template_directory_uri() . '/css/bootstrap.css', array(), false,null );

  wp_enqueue_style( 'font-awesome', get_template_directory_uri() . '/css/font-awesome.css', array(), false, 'all' );
  wp_enqueue_style( 'mywiki-style', get_stylesheet_uri());
  wp_enqueue_script( 'bootstrap',  get_template_directory_uri() . '/js/bootstrap.js', array('jquery'), '3.0.1');
  wp_enqueue_script( 'mywiki-ajaxsearch',  get_template_directory_uri() . '/js/ajaxsearch.js', array(), '1.0.0');
  wp_enqueue_script( 'mywiki-general',  get_template_directory_uri() . '/js/general.js');
  wp_localize_script( 'mywiki-general', 'my_ajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
  if ( is_singular() ): wp_enqueue_script( 'comment-reply' ); endif;
}

/* mywiki theme starts */
if ( ! function_exists( 'mywiki_setup' ) ) :
  function mywiki_setup() {
    	/* content width */
    	global $content_width;
    	if ( ! isset( $content_width ) ) {
    		$content_width = 900;
    	}
    	/*
    	 * Make mywiki theme available for translation.
    	 *
    	 */
    	load_theme_textdomain( 'mywiki', get_template_directory() . '/languages' );

      register_nav_menus(
        array(
          'primary' => __( 'The Main Menu', 'mywiki' ),  // main nav in header
          'footer-links' => __( 'Footer Links', 'mywiki' ) // secondary nav in footer
        )
      );
    	// This theme styles the visual editor to resemble the theme style.
    	add_editor_style( 'css/editor-style.css' );
    	// Add RSS feed links to <head> for posts and comments.
    	add_theme_support( 'automatic-feed-links' );
      add_theme_support( 'title-tag' );
      add_theme_support( 'custom-logo', array(
                'height'      => 160,
                'width'       => 45,
                'flex-height' => true,
                'flex-width'  => true,
                'priority'    => 11,
                'header-text' => array( 'site-title', 'site-description' ), 
            ) );
    	/*
    	 * Enable support for Post Formats.
    	 */
    	// This theme allows users to set a custom background.
    	add_theme_support( 'custom-background', apply_filters( 'mywiki_custom_background_args', array(
    		'default-color' => '048eb0',
    	) ) );
    	// Add support for featured content.
    	add_theme_support( 'featured-content', array(
    		'featured_content_filter' => 'mywiki_get_featured_posts',
    		'max_posts' => 6,
    	) );
    	// This theme uses its own gallery styles.
    	add_filter( 'use_default_gallery_style', '__return_false' );


      add_theme_support( 'post-thumbnails' );
      set_post_thumbnail_size( 150, 150 ); // default Post Thumbnail dimensions
     
      
      add_image_size( 'category-thumb', 300, 9999 ); //300 pixels wide (and unlimited height)
      add_image_size( 'homepage-thumb', 220, 180, true ); //(cropped)
      
      add_theme_support( 'html5', array( 'comment-list', 'comment-form', 'search-form' ) );
  }
endif; // mywiki_setup
add_action( 'after_setup_theme', 'mywiki_setup' );

add_filter('get_custom_logo','mywiki_change_logo_class');
function mywiki_change_logo_class($html)
{
  //$html = str_replace('class="custom-logo"', 'class="img-responsive logo-fixed"', $html);
  $html = str_replace('width=', 'original-width=', $html);
  $html = str_replace('height=', 'original-height=', $html);
  $html = str_replace('class="custom-logo-link"', 'class="navbar-brand logo"', $html);
  return $html;
}
/*Title*/
function mywiki_wp_title( $title, $sep ) {
  global $paged, $page;
  if ( is_feed() ) { return $title; } // end if
  // Add the site name.
  $title .= get_bloginfo( 'name' );
  // Add the site description for the home/front page.
  $site_description = get_bloginfo( 'description', 'display' );
  if ( $site_description && ( is_home() || is_front_page() ) ) {
    $title = "$title $sep $site_description";
  } // end if
  // Add a page number if necessary.
  if ( $paged >= 2 || $page >= 2 ) {  
    $title = sprintf(/* translators: 1 is title */ __( 'Page %s', 'mywiki' ), max( $paged, $page ) ) . " $sep $title";
  } // end if
  return $title;
} // end mywiki_wp_title
add_filter( 'wp_title', 'mywiki_wp_title', 10, 2 );
if ( ! function_exists( 'mywiki_entry_meta' ) ) :
/**
 * Set up post entry meta.
 *
 * Meta information for current post: categories, tags, permalink, author, and date.
 **/
function mywiki_entry_meta() {
	$mywiki_category_list = get_the_category_list(', '); 
  $mywiki_tags_list = get_the_tags(', ');  ?>
  <i class="fa fa-calendar-check-o"></i>&nbsp;&nbsp;
  <a href="<?php echo esc_url(get_permalink()); ?>" title="<?php echo esc_attr(get_the_time()); ?>" ><time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date()); ?></time></a>
  &nbsp;  
  <?php if ( $mywiki_category_list ) { ?>
   <i class="fa fa-folder-open"></i>
  <?php echo wp_kses_post(get_the_category_list(', '));    }  
 }
endif;
/**
 * Add default menu style if menu is not set from the backend.
 */
function mywiki_add_menuclass ($page_markup) {
  preg_match('/^<div class=\"([a-z0-9-_]+)\">/i', $page_markup, $mywiki_matches);
  $mywiki_toreplace = array('<div class="navbar-collapse collapse top-gutter">', '</div>');
  $mywiki_replace = array('<div class="navbar-collapse collapse top-gutter">', '</div>');
  $mywiki_new_markup = str_replace($mywiki_toreplace,$mywiki_replace, $page_markup);
  $mywiki_new_markup= preg_replace('/<ul/', '<ul class="nav navbar-nav navbar-right mywiki-header-menu"', $mywiki_new_markup);
  return $mywiki_new_markup; 
} //}
add_filter('wp_page_menu', 'mywiki_add_menuclass');

/**
 * Wiki search
 */
function mywiki_search() {
	global $wpdb;
	$mywiki_title=(isset($_POST['queryString']))?trim(sanitize_text_field(wp_unslash($_POST['queryString']))):'';
  if(strpos($mywiki_title,"#")>-1):
    $tags = strtolower(str_replace(array(' ','#'),array( '-',''),$mywiki_title));
    $mywiki_args = array('posts_per_page' => -1, 'order'=> 'ASC', "orderby"=> "title", "post_type" => "post",'post_status'=>'publish',"tag" => $tags);
  else:
    $mywiki_args = array('posts_per_page' => -1, 'order'=> 'ASC', "orderby"=> "title", "post_type" => "post",'post_status'=>'publish', "s" => $mywiki_title);
  endif;	
  $mywiki_posts = get_posts( $mywiki_args );
	$mywiki_output='';
	if($mywiki_posts):
		 $mywiki_h=0; ?>
		 <ul id="search-result">
  		 <?php foreach ( $mywiki_posts as $mywiki_post ) { setup_postdata( $mywiki_post );?>
  			 <li class="que-icn">
            <a href="<?php echo esc_url(get_the_permalink($mywiki_post->ID))?>"> <i class="fa fa-angle-right"></i><?php echo esc_html($mywiki_posts[$mywiki_h]->post_title);?> </a>
          </li>  			 
  		 <?php $mywiki_h++; } ?>
  	 </ul>
	<?php  wp_reset_postdata();	
  else:
      esc_html_e('No','mywiki');
	endif;
	die();
}
add_action('wp_ajax_mywiki_search', 'mywiki_search');
add_action('wp_ajax_nopriv_mywiki_search', 'mywiki_search' );

if ( ! function_exists( 'mywiki_comment' ) ) :
  /**
   * Template for comments and pingbacks.
   *
   * To override this walker in a child theme without modifying the comments template
   * simply create your own mywiki_comment(), and that function will be used instead.
   *
   * Used as a callback by wp_list_comments() for displaying the comments.
   *
   * @since Twenty Twelve 1.0
   */
  function mywiki_comment( $comment, $args, $depth ) {
  	//$GLOBALS['comment'] = $comment;
  		// Proceed with normal comments.
 		global $post; ?>
  		<li id="comment-<?php comment_ID(); ?>" <?php comment_class( empty( $args['has_children'] ) ? '' : 'parent' ); ?> >
      	<article class="div-comment-<?php comment_ID(); ?>" id="div-comment-1">
  				<footer class="comment-meta">
  					<div class="comment-author vcard">
  						<?php if ( 0 != $args['avatar_size'] ) echo get_avatar( $comment, $args['avatar_size'] ); ?>
  					<b class="fn">	<?php printf( /* translators: 1 is author link */ esc_html__( '%s says:','mywiki' ), get_comment_author_link()  ); ?></b>
  					</div><!-- .comment-author -->
  					<div class="comment-metadata">
  						<a href="<?php echo esc_url( get_comment_link( $comment->comment_ID ) ); ?>">
  							<time datetime="<?php comment_time( 'c' ); ?>">
  								<?php printf( /* translators: 1 is post date , 2 is post time */ esc_html__( '%1$s at %2$s', 'mywiki' ), get_comment_date(), get_comment_time() ); ?>
  							</time>
  						</a>
  						<?php edit_comment_link( __( 'Edit','mywiki' ), '<span class="edit-link">', '</span>' ); ?>
            </div><!-- .comment-metadata -->
  				</footer><!-- .comment-meta -->
  				<div class="comment-content">
  					<?php comment_text(); ?>
  				</div><!-- .comment-content -->
  				<div class="reply">
  					<?php comment_reply_link( array_merge( $args, array( 'add_below' => 'div-comment', 'depth' => $depth, 'max_depth' => $args['max_depth'] ) ) ); ?>
                 </div><!-- .reply -->
  			</article>
  	<?php
  }
endif;
add_action('wp_ajax_mywiki_header', 'mywiki_header_image_function');
add_action('wp_ajax_nopriv_mywiki_header', 'mywiki_header_image_function' );
function mywiki_header_image_function(){
	$mywiki_return['header'] = get_header_image();
	echo json_encode($mywiki_return);
	die;
}
 add_action( 'admin_menu', 'mywiki_admin_menu');
function mywiki_admin_menu( ) {
    add_theme_page( __('Pro Feature','mywiki'), __('MyWiki Pro','mywiki'), 'manage_options', 'mywiki-pro-buynow', 'mywiki_buy_now', 300 );   
}
function mywiki_buy_now(){ ?>
<div class="mywiki_pro_version">
  <a href="<?php echo esc_url('https://fasterthemes.com/wordpress-themes/mywikipro/'); ?>" target="_blank">
    
    <img src ="<?php echo esc_url(get_template_directory_uri()); ?>/img/mywiki_pro_features.png" width="100%" height="auto" />

  </a>
</div>
<?php
}

/*Customizer*/
require get_template_directory() . '/function/customizer.php';
/*theme-default-setup*/
require get_template_directory() . '/function/theme-default-setup.php';
// Implement Custom Header features.
require get_template_directory() . '/function/custom-header.php';