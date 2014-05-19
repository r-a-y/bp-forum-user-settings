<?php

/** UTILITY ********************************************************/

/**
 * Utility function to fetch a specifc bbPress forum setting for a user.
 *
 * @param string $setting The individual setting
 * @return mixed
 */
function bp_fus_get_setting( $setting = '' ) {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	if ( isset( buddypress()->fus->settings[$setting] ) ) {
		$option = buddypress()->fus->settings[$setting];

		// if user thread display option is threaded, but threading is disabled in
		// bbPress, default to oldest linear display
		if ( 'thread_display' === $setting && 'threaded' === $option && ! bbp_thread_replies() ) {
			$option = 'oldest';
		}

		return $option;
	} else {
		return false;
	}
}

/** HOOKS **********************************************************/

/**
 * Show bbPress threads either by oldest or newest first.
 *
 * By default, bbPress threads are displayed by oldest first.  When a user has
 * set thread display to newest first, we need to change the order to DESC.
 *
 * @param array $args bbPress display args
 * @return array
 */
function bp_fus_thread_display_linear( $args ) {
	$order = bp_fus_get_setting( 'thread_display' );

	if ( false === $order ) {
		return $args;
	}

	// newest first
	if ( 'newest' === $order ) {
		$args['order'] = 'DESC';
	}

	return $args;
}
add_filter( 'bbp_after_has_replies_parse_args', 'bp_fus_thread_display_linear' );

/**
 * Set custom number of posts per page for bbPress threads.
 *
 * @param int $retval bbPress default posts per page
 * @return int
 */
function bp_fus_replies_per_page( $retval ) {
	$per_page = bp_fus_get_setting( 'per_page' );

	if ( empty( $per_page ) ) {
		return $retval;
	}

	return (int) $per_page;
}
add_filter( 'bbp_get_replies_per_page', 'bp_fus_replies_per_page' );

/**
 * Modify the threaded replies setting when a user has set mode to linear.
 *
 * If a user has explicitly set the thread display mode to linear (oldest or
 * newest first) we force bbPress to set the threaded replies option to false.
 *
 * @param bool $retval The current threaded replies setting
 * @return bool
 */
function bp_fus_thread_replies( $retval ) {
	$order = bp_fus_get_setting( 'thread_display' );

	if ( 'threaded' !== $order ) {
		$retval = false;
	}

	return $retval;
}
add_filter( 'bbp_thread_replies', 'bp_fus_thread_replies' );

/**
 * Modify last reply ID when user has set thread sort order to newest.
 *
 * When a user has set thread display to newest first, we need to change the
 * last reply ID to the topic ID.  This is so the topic freshness permalink
 * and the topic last author will use the first post.
 *
 * @param int $retval The last reply ID
 * @return int
 */
function bp_fus_get_topic_last_reply_id( $retval ) {
	$order = bp_fus_get_setting( 'thread_display' );

	if ( false === $order ) {
		return $retval;
	}

	// newest first
	if ( 'newest' === $order ) {
		// setting the post ID to 0 will use the topic ID
		$retval = 0;
	}

	return $retval;
}
add_filter( 'bbp_get_topic_last_reply_id', 'bp_fus_get_topic_last_reply_id' );

/**
 * Modify the reply position when user has set thread sort order to newest.
 *
 * When a user has set thread display to newest first, we need to change the
 * reply position for the current post to use the inverse position.  This is
 * done so the reply URL is generated properly in {@link bbp_get_reply_url()}.
 *
 * @param int $retval The last reply ID
 * @return int
 */
function bp_fus_get_reply_position_inverse( $retval, $reply_id, $topic_id ) {
	$order = bp_fus_get_setting( 'thread_display' );

	if ( false === $order ) {
		return $retval;
	}

	// newest first
	// need to reverse the reply position to calculate the proper permalink
	if ( 'newest' === $order ) {
		// recalculate position to use the inverse position
		$retval = ( bbp_get_topic_reply_count( $topic_id ) + 1 ) - $retval;

		if ( ! bbp_show_lead_topic() ) {
			$retval++;
		}
	}

	return $retval;
}
add_filter( 'bbp_get_reply_position', 'bp_fus_get_reply_position_inverse', 10, 3 );

/**
 * For the bbPress reply URL, use the WP shortlink.
 *
 * The WP shortlink acts as the canonical link to the bbPress post.
 *
 * @see bp_fus_post_link() Where we set the reply permalink
 * @see bp_fus_redirect_canonical() Where we do the redirect
 */
function bp_fus_get_reply_url( $retval, $post_id ) {
	// pretty shortlink - example.com/forums/r/{POST_ID}
	// in the future... might implement this
	//return wp_get_shortlink( $post_id, 'bbp' );

	return add_query_arg( 'p', $post_id, home_url( '/' ) );
}
add_filter( 'bbp_get_reply_url', 'bp_fus_get_reply_url', 10, 2 );

/**
 * Correctly generate the full bbPress post link from the WP shortlink.
 *
 * Takes the WP shortlink and converts it to the full, paginated URL.  Handles
 * a user's custom forum settings as well.
 *
 * @param string $retval The current post link
 * @param WP_Post The WP Post object
 * @return string
 */
function bp_fus_post_link( $retval, $post ) {
	if ( get_query_var( 'p' ) !== $post->ID ) {
		return $retval;
	}

	// reply
	if ( bbp_get_reply_post_type() == $post->post_type ) {
		// save the post ID temporarily so we can add it back for the anchor
		buddypress()->fus->reply_id = $post->ID;

		remove_filter( 'bbp_get_reply_url', 'bp_fus_get_reply_url', 10, 2 );
		$retval = bbp_get_reply_url( $post->ID );
		add_filter( 'bbp_get_reply_url', 'bp_fus_get_reply_url', 10, 2 );

	// topic
	} elseif ( bbp_get_topic_post_type() == $post->post_type ) {
		// save the post ID temporarily so we can add it back for the anchor
		buddypress()->fus->topic_id = $post->ID;

		remove_filter( 'bbp_get_reply_url', 'bp_fus_get_reply_url', 10, 2 );
		$retval = bbp_get_reply_url( $post->ID, $retval );
		add_filter( 'bbp_get_reply_url', 'bp_fus_get_reply_url', 10, 2 );
	}

	return $retval;
}
add_filter( 'post_type_link', 'bp_fus_post_link', 20, 2 );

/**
 * During URL canonical generation, add the anchor to bbPress post links.
 *
 * {@link redirect_canonical()} strips the anchor from the full bbPress post
 * link generated in {@link bp_fus_post_link()}.  We add it back here.
 *
 * @param string $retval The canonical URL
 * @return string
 */
function bp_fus_redirect_canonical( $retval ) {

	if ( isset( buddypress()->fus->reply_id ) ) {
		$retval .= '#post-' . (int) buddypress()->fus->reply_id;
		unset( buddypress()->fus->reply_id );
	}

	if ( isset( buddypress()->fus->topic_id ) ) {
		$retval .= '#post-' . (int) buddypress()->fus->topic_id;
		unset( buddypress()->fus->topic_id );
	}

	return $retval;
}
add_filter( 'redirect_canonical', 'bp_fus_redirect_canonical' );

/**
 * Pretty shortlink - example.com/forums/r/{post_id}
 * need to add rewrite rule for this to work.
 *
 * Commented out for now.
 */
function bp_fus_get_bbp_shortlink( $retval, $id, $context, $allow_slugs ) {
	$use_bbp = false;

	if ( 'bbp' !== $context ) {
		$post = get_post( $id );

		if ( empty( $post->ID ) ) {
			return $retval;
		}

		$post_id = $post->ID;

		$post_type = get_post_type_object( $post->post_type );

		if ( bbp_get_topic_post_type() == $post->post_type || bbp_get_reply_post_type() == $post->post_type ) {
			$use_bbp = true;
		}

	} else {
		$use_bbp = true;
		$post_id = $id;
	}

	if ( true === $use_bbp ) {
		$retval = bbp_get_forums_url( "/r/{$post_id}/" );
	}

	return $retval;
}
//add_filter( 'pre_get_shortlink', 'bp_fus_get_bbp_shortlink', 10, 4 );
