<?php

class Popular_Posts_Widget extends WP_Widget {

	function __construct() {
		parent::__construct(
			'post_views_widget', 'Popular Posts', array( 'description' => __( 'Displays most viewed posts basesd on Google Analytics information.', 'vocewiki' ), ) // Args
		);
	}

	function sort_posts( $array, $sortby, $direction = 'asc' ) {

		$sortedArr = array( );
		$tmp_Array = array( );

		foreach ( $array as $k => $v ) {
			$tmp_Array[ ] = $v[ $sortby ];
		}

		if ( $direction === 'asc' ) {
			asort( $tmp_Array );
		} else {
			arsort( $tmp_Array );
		}

		foreach ( $tmp_Array as $k => $tmp ) {
			$sortedArr[ ] = $array[ $k ];
		}

		return $sortedArr;
	}

	function widget( $args, $instance ) {

		extract( $args );

		$title = apply_filters( 'widget_title', $instance[ 'title' ] );
		$count = $instance[ 'post_count' ];
		$views = $instance[ 'views_type' ];

		echo $before_widget;
		if ( !empty( $title ) )
			echo $before_title . $title . $after_title;

		$views_type = ($views === 'unique_views' ) ? 'uniqueViews' : 'views';
		$top_posts = get_option( 'google-analytics-post-views', false );

		if ( $top_posts ) {

			$ordered_posts = self::sort_posts( $top_posts, $views_type, 'dsc' );
			$posts = array_slice( $ordered_posts, 0, $count );

			$html = '<ul class="left-col-list">';

			foreach ( $posts as $p ) {

				global $post;

				$pid = $p[ 'id' ];
				$post = get_post( $pid );
				setup_postdata( $post );

				$html .= sprintf( '<li><a href="%s">%s</a><p>%s&nbsp;&nbsp;<i class="icon-eye-open"></i> %d</p></li>', get_permalink(), get_the_title(), get_the_time( 'F jS, Y' ), $p[ $views_type ] );
			}

			wp_reset_postdata();

			$html .= '</ul>';
			echo $html;
		} else {
			echo '<p>No posts to display right now.</p>';
		}
		echo $after_widget;
	}

	function dashboard_widget() {

		$top_posts = get_option( 'google-analytics-post-views', false );

		if ( $top_posts ) {

			$ordered_posts = self::sort_posts( $top_posts, 'views', 'dsc' );
			$posts = array_slice( $ordered_posts, 0, 5 );

			$html = '<table style="width: 100%;"><thead><tr><td><strong>Post</strong></td><td align="center"><strong>Views<strong></td></tr></thead><tbody>';

			foreach ( $posts as $p ) {

				global $post;

				$pid = $p[ 'id' ];
				$post = get_post( $pid );
				setup_postdata( $post );

				$html .= sprintf( '<tr><td class="t last"><a href="%s">%s</a></td><td align="center">%d</td></tr>', get_permalink(), get_the_title(), $p[ 'views' ] );
			}

			wp_reset_postdata();

			$html .= '</tbody></table>';
			echo $html;
		} else {
			echo '<p>No posts to display right now.</p>';
		}
	}

	function update( $new_instance, $old_instance ) {

		$instance = array( );
		$instance[ 'title' ] = strip_tags( $new_instance[ 'title' ] );
		$instance[ 'views_type' ] = $new_instance[ 'views_type' ];
		$count = intval( strip_tags( $new_instance[ 'post_count' ] ) );
		$instance[ 'post_count' ] = ($count) ? $count : 10;

		return $instance;
	}

	function form( $instance ) {

		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		} else {
			$title = __( 'New title', 'vocewiki' );
		}

		$count = $instance[ 'post_count' ];
		$selected = $instance[ 'views_type' ];
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'views_type' ); ?>"><?php _e( 'Views Type:' ); ?></label>
			<select class="widefat" id="<?php echo $this->get_field_id( 'views_type' ); ?>" name="<?php echo $this->get_field_name( 'views_type' ); ?>" >
				<option value="all_views" <?php echo ($selected === 'all_views') ? 'selected=true' : ''; ?>>All Views</option>
				<option value="unique_views" <?php echo ($selected === 'unique_views') ? 'selected=true' : ''; ?>>Unique Views</option>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'post_count' ); ?>"><?php _e( 'Post Limit:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'post_count' ); ?>" name="<?php echo $this->get_field_name( 'post_count' ); ?>" type="text" value="<?php echo (!empty( $count ) ) ? esc_attr( intval( $instance[ 'post_count' ] ) ) : '10'; ?>" />
		</p>
		<?php
	}

}

if ( Voce_GA_Post_Views::check_enabled() ) {
	add_action( 'widgets_init', function() {
			return register_widget( 'Popular_Posts_Widget' );
		} );
	add_action( 'wp_dashboard_setup', function() {
			wp_add_dashboard_widget( 'ga_pageviews_dashboard', 'Top Page Views', array( 'Popular_Posts_Widget', 'dashboard_widget' ) );
		} );
}