<?php
function my_theme_enqueue_styles() {

    $parent_style = 'wp-bootstrap-starter-style'; // This is 'twentyfifteen-style' for the Twenty Fifteen theme.

    wp_enqueue_style( $parent_style, get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 'child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array( $parent_style ),
        wp_get_theme()->get('Version')
    );
    wp_localize_script( 'ajax-pagination', 'ajaxpagination', array(
    	'ajaxurl' => admin_url( 'admin-ajax.php' )
    ));

}
add_action( 'wp_enqueue_scripts', 'my_theme_enqueue_styles' );


// SERVER SIDE DATATABLES TESTING
// Add Post title to custom meta

add_action( 'transition_post_status', 'duplicate_title', 10, 3 );

function duplicate_title( $new, $old, $post ) {
    if ( $post->post_type == 'case' ) {
        update_post_meta( $post->ID, 'd_title', $post->post_title );
    }
}

function case_datatables_scripts() {
    wp_enqueue_script( 'case_datatables', get_stylesheet_directory_uri(). '/js/casetable.js', array(), '1.0', true );
    wp_localize_script( 'case_datatables', 'ajax_url', admin_url('admin-ajax.php?action=case_datatables') );
}

function case_datatables() {

    case_datatables_scripts();

    ob_start(); ?>

    <table id="case-list" class="table case-table">
        <thead>
            <tr>
                <th>Case Name</th>
                <th>Case Number</th>
                <th>Date Filed</th>
                <th>Last Docket Entry</th>
                <th>Status</th>
            </tr>
        </thead>
    </table>

    <?php
    return ob_get_clean();
}

add_shortcode ('case_datatables', 'case_datatables');

add_action('wp_ajax_case_datatables', 'datatables_server_side_callback');
add_action('wp_ajax_nopriv_case_datatables', 'datatables_server_side_callback');

function datatables_server_side_callback() {

    header("Content-Type: application/json");

    $request= $_GET;

    $time_set = strtotime('meta_value_num');
    $columns = array(
        0 => 'post_title',
        1 => 'case_number',
        2 => 'date_filed',
        3 => 'last_docket_entry',
        4 => 'status'
    );

    $args = array(
        'post_type' => 'case',
        'post_status' => 'publish',
        'posts_per_page' => $request['length'],
        'offset' => $request['start'],
        'order' => $request['order'][0]['dir'],
    );

    if ($request['order'][0]['column'] == 0) {

        $args['orderby'] = $columns[$request['order'][0]['column']];

    } elseif ($request['order'][0]['column'] == 1 ){

      $args['orderby'] = 'meta_value';
      $args['meta_key'] = $columns[$request['order'][0]['column']];

    } elseif ( $request['order'][0]['column'] == 2 || $request['order'][0]['column'] == 3 ) {

        $args['orderby'] = 'meta_value';
        $args['meta_key'] = $columns[$request['order'][0]['column']];
        $args['meta_type'] = 'DATE';

    };


    //$request['search']['value'] <= Value from search

    if( $request['search']['value'] === 'opinion' ) {
      $args['meta_query'] = array(
        //'relation' => 'AND',
        array(
          'key' => 'case_number_for_opinion',
          'value' => '', //The value of the field.
          'compare' => '!=', //Conditional statement used on the value.
        )
      );
    } elseif( $request['search']['value'] === 'pending argument' ) {
      $args['meta_query'] = array(
        //'relation' => 'AND',
        array(
          'key' => 'argument_date',
          'value' => '', //The value of the field.
          'compare' => '!=', //Conditional statement used on the value.
        )
      );
    } elseif( !empty($request['search']['value']) ) { // When datatables search is used

        $args['meta_query'] = array(
            'relation' => 'OR',
            array(
                'key' => 'd_title',
                'value' => sanitize_text_field($request['search']['value']),
                'compare' => 'LIKE'
            ),
            array(
                'key' => 'case_number',
                'value' => sanitize_text_field($request['search']['value']),
                'compare' => 'LIKE'
            ),
            //array(
            //    'key' => 'case_number_for_opinion',
                //'value' => sanitize_text_field($request['search']['value']),
            //    'compare' => 'EXISTS'
            //),
            array(
                'key' => 'status',
                'value' => sanitize_text_field($request['search']['value']),
                'compare' => 'LIKE'
            )
        );
    }

    $cases_query = new WP_Query($args);
    $totalData = $cases_query->found_posts;

    if ( $cases_query->have_posts() ) {
        while ( $cases_query->have_posts() ) {
            $cases_query->the_post();

            // Get status field for badge
            $status = get_field('status');
            $arg_scheduled = get_field('argument_date');



            // Return arguments badge HTML if argurment date is in future
            //function arg_date_in_future() {
            //  Get arg date for badge

            $arg_date = strtotime( $arg_scheduled );
            $now_date = new DateTime();
            $today = $now_date->getTimestamp(); // Get today's date for comparison

            if ( $arg_date >= $today ) {
              $arg_badge = "<span class='arguments-badge'>pending argument</span>";
            } else {
              $arg_badge = "";
            };

            if (get_field('case_number_for_opinion', $post->ID)) {
              $opinion_badge = "<span class='opinion-badge'>opinion</span>";
            } else {
              $opinion_badge = "";
            }
              //if ( $arg_scheduled ) {


              //  if ($arg_date >= $today) {
              //    $arg_badge = "<span class='arguments-badge'>pending argument</span>";
              //  }
              //  else {
              //    $arg_badge = "";
              //  };
              //} else {
              //  $arg_badge = "";
              //};


            //}

            $nestedData = array();
            $nestedData[] = '<a href="'.get_the_permalink().'">'.get_the_title().'</a>';
            $nestedData[] = get_field('case_number');
            $nestedData[] = date('m/d/Y', strtotime(get_field('date_filed', false, false)));
            $nestedData[] = date('m/d/Y', strtotime(get_field('last_docket_entry', false, false)));
            $nestedData[] = '<span class="'.$status.'">'.$status.'</span>'.$arg_badge.$opinion_badge;

            $data[] = $nestedData;
        }
        wp_reset_query();

        $json_data = array(
            "draw" => intval($request['draw']),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => intval($totalData),
            "data" => $data
        );

        echo json_encode($json_data);

    } else {

        $json_data = array(
            "data" => array()
        );

        echo json_encode($json_data);
    }
    wp_die();
}

// END DATATABLES TESTING


if ( ! function_exists( 'wp_bootstrap_starter_child_posted_on' ) ) :
/**
 * Prints HTML with meta information for the current post-date/time and author.
 */
function wp_bootstrap_starter_child_posted_on() {
	$time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
	if ( get_the_time( 'U' ) !== get_the_modified_time( 'U' ) ) {
        $time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time>';
	}

	$time_string = sprintf( $time_string,
		esc_attr( get_the_date( 'c' ) ),
		esc_html( get_the_date() )
	);

	$posted_on = sprintf(
		esc_html_x( '%s', 'post date', 'wp-bootstrap-starter' ),
		$time_string
	);

	//$byline = sprintf(
	//	esc_html_x( 'by %s', 'post author', 'wp-bootstrap-starter' ),
	//	'<span class="author vcard"><a class="url fn n" href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . esc_html( get_the_author() ) . '</a></span>'
	//);

  //$coauthor = get_author_posts_url(get_the_author_meta( get_field('co_author') ) );
  //$coauthor = ' and <a class="url fn n" href="'. esc_url( get_author_posts_url( get_field('co_author')[ID] ) ).'">'.esc_html( get_field('co_author')[user_firstname] ).' '. esc_html( get_field('co_author')[user_lastname] ) .'</a>';



  //echo get_avatar(get_the_author_meta('ID'),'thumbnail');
	echo '<p class="byline-wrap"><span class="byline"> ' . coauthors_posts_links(null, null, null, null, false) .'</span> | <span class="posted-on">' . $posted_on .'</span></p>';

    if ( ! is_single() && ! post_password_required() && ( comments_open() || get_comments_number() ) ) {
        echo ' | <span class="comments-link"><i class="fa fa-comments" aria-hidden="true"></i> ';
        /* translators: %s: post title */
        comments_popup_link( sprintf( wp_kses( __( 'Leave a Comment<span class="screen-reader-text"> on %s</span>', 'wp-bootstrap-starter' ), array( 'span' => array( 'class' => array() ) ) ), get_the_title() ) );
        echo '</span>';
    }

}
endif;

if ( ! function_exists( 'wp_bootstrap_starter_entry_footer' ) ) :
/**
 * Prints HTML with meta information for the categories, tags and comments.
 */
function wp_bootstrap_starter_entry_footer() {
	// Hide category and tag text for pages.
	if ( 'post' === get_post_type() ) {
		/* translators: used between list items, there is a space after the comma */
		$categories_list = get_the_category_list( esc_html__( ', ', 'wp-bootstrap-starter' ) );
		if ( $categories_list && wp_bootstrap_starter_categorized_blog() ) {
			printf( '<span class="cat-links">' . esc_html__( '%1$s', 'wp-bootstrap-starter' ) . '</span>', $categories_list ); // WPCS: XSS OK.
		}

		/* translators: used between list items, there is a space after the comma */
		$tags_list = get_the_tag_list( '', esc_html__( '', 'wp-bootstrap-starter' ) );
		if ( $tags_list ) {
			printf( '<span class="tags-links h-100">' . esc_html__( '%1$s', 'wp-bootstrap-starter' ) . '</span>', $tags_list ); // WPCS: XSS OK.
		}
	}


	//edit_post_link(
	//	sprintf(
	//		/* translators: %s: Name of current post */
	//		esc_html__( 'Edit %s', 'wp-bootstrap-starter' ),
	//		the_title( '<span class="screen-reader-text">"', '"</span>', false )
	//	),
	//	' <br><span class="edit-link">',
	//	'</span>'
	//);
}
endif;

function bidirectional_acf_update_value( $value, $post_id, $field  ) {

	// vars
	$field_name = $field['name'];
	$field_key = $field['key'];
	$global_name = 'is_updating_' . $field_name;


	// bail early if this filter was triggered from the update_field() function called within the loop below
	// - this prevents an inifinte loop
	if( !empty($GLOBALS[ $global_name ]) ) return $value;


	// set global variable to avoid inifite loop
	// - could also remove_filter() then add_filter() again, but this is simpler
	$GLOBALS[ $global_name ] = 1;


	// loop over selected posts and add this $post_id
	if( is_array($value) ) {

		foreach( $value as $post_id2 ) {

			// load existing related posts
			$value2 = get_field($field_name, $post_id2, false);


			// allow for selected posts to not contain a value
			if( empty($value2) ) {

				$value2 = array();

			}


			// bail early if the current $post_id is already found in selected post's $value2
			if( in_array($post_id, $value2) ) continue;


			// append the current $post_id to the selected post's 'related_posts' value
			$value2[] = $post_id;


			// update the selected post's value (use field's key for performance)
			update_field($field_key, $value2, $post_id2);

		}

	}


	// find posts which have been removed
	$old_value = get_field($field_name, $post_id, false);

	if( is_array($old_value) ) {

		foreach( $old_value as $post_id2 ) {

			// bail early if this value has not been removed
			if( is_array($value) && in_array($post_id2, $value) ) continue;


			// load existing related posts
			$value2 = get_field($field_name, $post_id2, false);


			// bail early if no value
			if( empty($value2) ) continue;


			// find the position of $post_id within $value2 so we can remove it
			$pos = array_search($post_id, $value2);


			// remove
			unset( $value2[ $pos] );


			// update the un-selected post's value (use field's key for performance)
			update_field($field_key, $value2, $post_id2);

		}

	}


	// reset global varibale to allow this filter to function as per normal
	$GLOBALS[ $global_name ] = 0;


	// return
    return $value;

}

add_filter('acf/update_value/name=case_number_for_opinion', 'bidirectional_acf_update_value', 10, 3);
add_filter('acf/update_value/name=opinion_name_for_judges', 'bidirectional_acf_update_value', 10, 3);
add_filter('acf/update_value/name=concurring_judge_opinion', 'bidirectional_acf_update_value', 10, 3);
add_filter('acf/update_value/name=dissenting_judge_opinion', 'bidirectional_acf_update_value', 10, 3);

//Insert ads after second paragraph of single post content.
/*add_filter( 'the_content', 'prefix_insert_post_ads' );
function prefix_insert_post_ads( $content ) {
	if ( get_field('related_cases') ) {

            $ad_code = get_related_cases();

          } else {
            $ad_code = '';
          };
	if ( is_single() && ! is_admin() ) {
		return prefix_insert_after_paragraph( $ad_code, 1, $content );
	}
return $content;
}

// Parent Function that makes the magic happen
function prefix_insert_after_paragraph( $insertion, $paragraph_id, $content ) {
	$closing_p = '</p>';
	$paragraphs = explode( $closing_p, $content );
	foreach ($paragraphs as $index => $paragraph) {
		if ( trim( $paragraph ) ) {
			$paragraphs[$index] .= $closing_p;
		}
		if ( $paragraph_id == $index + 1 ) {
			$paragraphs[$index] .= $insertion;
		}
	}

	return implode( '', $paragraphs );
}

function get_related_cases() {
  return '<div class="left-side-inset">Hello</div>';
}*/




// USE BELOW FOR SERVER-SIDE CASES SEARCH

//function update_my_metadata(){
//    $args = array(
//        'post_type' => 'case', // Only get the posts
//        'post_status' => 'publish', // Only the posts that are published
//        'posts_per_page'   => -1 // Get every post
//    );
//    $posts = get_posts($args);
//    foreach ( $posts as $post ) {
//        // Run a loop and update every meta data
//        update_post_meta( $post->ID, 'd_title', $post->post_title );
//    }
//}
// Hook into init action and run our function
//add_action('init','update_my_metadata');



//USE BELOW TO UPDATE POST_CONTENT WITH CASE_NUMBER

//function add_case_number_to_content(){
//    $args = array(
//        'post_type' => 'case', // Only get the posts
//        'post_status' => 'publish', // Only the posts that are published
//        'posts_per_page'   => -1 // Get every post
//    );
//    $posts = get_posts($args);
//    foreach ( $posts as $post ) {
        // Run a loop and update every meta data
//        update_post_meta( $post->ID, 'post_content', get_field('case_number') );
//    }
//}
// Hook into init action and run our function
//add_action('init','add_case_number_to_content');


// USE BELOW TO REFORMAT LAST DOCKET ENTRY AS YYYY-MM-DD

function reformat_docket_date(){

    $args = array(
        'post_type' => 'case', // Only get the posts
        'post_status' => 'publish', // Only the posts that are published
        'posts_per_page'   => -1 // Get every post
    );
    $posts = get_posts($args);
    foreach ( $posts as $post ) {
        // Run a loop and update every meta data
        $date = get_field('last_docket_entry');
        $formatted_date = strtotime($date);
        $final_date = date('Y-m-d', strtotime(get_field('last_docket_entry', $post->ID)) );
        update_post_meta( $post->ID, 'date_testing_field', $final_date );
    }
}

add_filter( 'get_the_archive_title', function ( $title ) {



        $title = get_avatar(get_the_author_meta('ID'),'thumbnail').'<span class="archive-name">Stories by <br>'.get_the_author().'</span>';




    return $title;

});

//add_action('init','reformat_docket_date');


 //ADD RELEVANSSI SEARCH TO RELATIONSHIP FIELD FOR OPINION RELATIONSHIP
//add_filter('relevanssi_content_to_index', 'rlv_relationship_content', 10, 2);
//function rlv_relationship_content($content, $post) {
//	 Fetching the post data by the relationship field
//	$relationships = get_post_meta($post->ID, 'case_number_for_opinion', true);
//	if (!is_array($relationships)) $relationships = array($relationships);
//	foreach ($relationships as $related_post) {
//		$content .= " " . get_post_meta($related_post->ID, 'case_number', true);
//	}



//	return $content;
//}


//add_action( 'pre_get_posts', 'wpsx_185734_acf_search_relationship' );

//function wpsx_185734_acf_search_relationship( $q ) {

//    $screen = get_current_screen();
//    $s = $q->get('s');
//    $post_type = $q->get('post_type');

    // Be very selective when running code at the pre_get_posts hook
//    if ( ! is_admin() || empty( $s ) || ( isset( $screen->post_type ) && 'opinion' != $screen->post_type ) || 'opinion' != $post_type ) {
//        return;
//    }

    // get all artists that match the search parameter $s
//    $found_artists = get_posts( array('post_type' => 'case', 'nopaging' => true, 's' => $s, 'fields' => 'ids') );

    // build a meta query to include all posts that contain the matching artist IDs in their custom fields
//    $meta_query = array();
//    foreach ( $found_artists as $artist_id ) {
//        $meta_query[] = array(
//            'key' => 'case_number_for_opinion', // name of custom field
//            'value' => '"' . intval($artist_id) . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
//            'compare' => 'OR'
//        );
//    }
//    $q->set( 'meta_query', $meta_query );
//    $q->set( 's', '' ); // unset the original query parameter to avoid errors
//}


// ADD LOGO ICON BEFORE CLOSING TAG OF LAST PARAGRAPH IN ARTICLES
add_filter('the_content', 'ata_the_content_filter', 10, 1);

function ata_the_content_filter($content)
{
  if ('post' === get_post_type() && !in_category('Video') ) {
    $parags = explode('</p>', $content);
    $parags[count($parags)-1] .= '<img class="endcap-icon" src="https://dccircuitbreaker.org/wp-content/uploads/2018/06/dc-gavel-icon.png">';// add whatever you want after first paragraph

    $content_new = '';

    foreach ($parags as $parag) {
        $content_new .= $parag;
    }

    return $content_new;
  } else {
    return $content;
  }
}

// REWRITE RULE FOR JUDGES' DECISIONS PAGE
add_action( 'init', 'wpse26388_rewrites_init' );
function wpse26388_rewrites_init(){
    add_rewrite_rule(
        'opinions/judge/([0-9]+)/?$',
        'index.php?pagename=judge&judge_id=$matches[1]',
        'top' );
}

add_filter( 'query_vars', 'wpse26388_query_vars' );
function wpse26388_query_vars( $query_vars ){
    $query_vars[] = 'judge_id';
    return $query_vars;
}

// Redirect Kavanaugh page
add_action('init', 'redirect_kav_page');
function redirect_kav_page(){
  add_rewrite_rule(
    'kavanaugh-opinions',
    'index.php?pagename=judge&judge_id=1654',
    'top'
  );
}

// Make names possessive
function properize($string) {
	return $string.'’'.($string[strlen($string) - 1] != 's' ? 's' : '');
}

//Add Open Graph Meta Info from the actual article data, or customize as necessary
	function facebook_open_graph() {
	    global $post;
	    if ( !is_singular()) //if it is not a post or a page
	        return;
		if($excerpt = $post->post_excerpt)
	        {
 			$excerpt = strip_tags($post->post_excerpt);
			$excerpt = str_replace("", "'", $excerpt);
        	}
        	else
        	{
            		$excerpt = get_bloginfo('description');
		}

	        //You'll need to find you Facebook profile Id and add it as the admin
	        //echo '<meta property="fb:admins" content="XXXXXXXXX-fb-admin-id"/>';
          $url_id = get_query_var('judge_id');
          $judge_name = get_the_title($url_id);

          if ( get_the_title() == 'Judge' ) {
            $fixed_title = properize($judge_name).' Opinions';
            echo '<meta property="og:title" content="' . $fixed_title . '"/>';
          } else {
            $fixed_title = wp_strip_all_tags( get_the_title() );
            echo '<meta property="og:title" content="' . $fixed_title . '"/>';

          }

		echo '<meta property="og:description" content="' . $excerpt . '"/>';
	        echo '<meta property="og:type" content="article"/>';
	        echo '<meta property="og:url" content="' . get_permalink() . '"/>';
	        //Let's also add some Twitter related meta data
	        echo '<meta name="twitter:card" content="summary" />';
	        //This is the site Twitter @username to be used at the footer of the card
		echo '<meta name="twitter:site" content="@dccircuitbreak" />';
		//This the Twitter @username which is the creator / author of the article
		echo '<meta name="twitter:creator" content="@dccircuitbreak" />';

	        // Customize the below with the name of your site
	        echo '<meta property="og:site_name" content="Circuit Breaker"/>';
	        if(!has_post_thumbnail( $post->ID )) { //the post does not have featured image, use a default image
	        //Create a default image on your server or an image in your media library, and insert it's URL here
	        $default_image="https://dccircuitbreaker.org/wp-content/uploads/2018/04/circuit-breaker-logo-3-e1524238354533.png";
	        echo '<meta property="og:image" content="' . $default_image . '"/>';
	    }
	    else{
	        $thumbnail_src = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'medium' );
	        echo '<meta property="og:image" content="' . esc_attr( $thumbnail_src[0] ) . '"/>';
	    }

	    echo "
	";
	}
add_action( 'wp_head', 'facebook_open_graph', 5 );

?>
