<?php
$mywiki_options = get_option( 'faster_theme_options' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="pingback" href="<?php bloginfo('pingback_url'); ?>">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<div id="wrap">
<header role="banner">
  <div id="inner-header" class="clearfix">
    <div class="navbar navbar-default top-bg">
      <div class="container" id="navbarcont">
        <div class="row">
        <div class="nav-container col-md-9">
          <nav role="navigation">
            <div class="navbar-header">
              <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse"> <span class="icon-bar"></span> <span class="icon-bar"></span> <span class="icon-bar"></span> </button>
              <?php if(has_custom_logo()){  
                     the_custom_logo();
                } 
               if (display_header_text()==true){ ?>
              <a class="navbar-brand logo" id="logo" title="<?php echo esc_html(get_bloginfo('description')); ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                <p><span class="header-text"><?php echo esc_html(bloginfo("name"));?></span></p>
                <h4><span class="header-description-text"><?php echo esc_html(get_bloginfo('description')); ?></span></h4>
              </a>
              <?php } ?>
            </div>
            <!-- end .navbar-header -->
          </nav>
        </div>
        <div class="navbar-collapse collapse top-menu">
          <?php
			$mywiki_defaults = array(
					'theme_location'  => 'primary',
					'container'       => 'div',					
					'echo'            => true,
					'fallback_cb'     => 'wp_page_menu',					
					'items_wrap'      => '<ul id="menu" class="nav navbar-nav navbar-right mywiki-header-menu">%3$s</ul>',
					'depth'           => 0,
					'walker'          => ''
					);
			wp_nav_menu( $mywiki_defaults ); ?>
        </div>
        <!-- end .nav-container -->
        </div>  
      </div>
      <!-- end #navcont -->
    </div>
    <!-- end .navbar --> 
  </div>
  <!-- end #inner-header --> 
</header>
<!-- end header -->
<div class="searchwrap ">
  <div class="container" id="search-main">
    <div class="row">
      <form class="asholder search-main col-md-12 col-sm-12 col-xs-12" role="search" method="get" id="searchformtop" action="<?php echo esc_url(site_url()); ?>">        
          <div class="input-group" id="suggest">
            <input name="s" id="s" type="text" onKeyUp="suggest(this.value);" onBlur="fill();" class="search-query form-control pull-right" autocomplete="off" placeholder="<?php esc_attr_e('Have a Question? Write here and press enter','mywiki') ?>" data-provide="typeahead" data-items="4" data-source=''>
            <div class="suggestionsbox" id="suggestions" style="display: none;"> <img src="<?php echo esc_url(get_template_directory_uri()).'/img/arrow1.png'; ?>" height="18" width="27" class="upArrow" alt="upArrow" />
              <div class="suggestionlist" id="suggestionslist"></div>
            </div>        
        </div>
      </form>
    </div>
  </div>
</div>
<div class="container " id="maincnot">