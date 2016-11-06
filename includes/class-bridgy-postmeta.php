<?php
// Adds Post Meta Box for Bridgy Publish

// The Bridgy_Postmeta class sets up a post meta box to publish using Bridgy
class Bridgy_Postmeta {
	public static function init() {
		// Add meta box to new post/post pages only
		add_action( 'load-post.php', array( 'Bridgy_Postmeta', 'bridgybox_setup' ) );
		add_action( 'load-post-new.php', array( 'Bridgy_Postmeta', 'bridgybox_setup' ) );
		add_action( 'save_post', array( 'Bridgy_Postmeta', 'save_post' ), 8, 2 );
		add_action( 'transition_post_status', array( 'Bridgy_Postmeta', 'transition_post_status' ) ,12,5 );
		add_filter( 'the_content', array( 'Bridgy_Postmeta', 'the_content' ) );
		add_filter( 'syn_add_links', array( 'Bridgy_Postmeta', 'syn_add_links' ) );
	}

	/* Meta box setup function. */
	public static function bridgybox_setup() {
		/* Add meta boxes on the 'add_meta_boxes' hook. */
		add_action( 'add_meta_boxes', array( 'Bridgy_Postmeta', 'add_postmeta_boxes' ) );
	}

	/* Create one or more meta boxes to be displayed on the post editor screen. */
	public static function add_postmeta_boxes() {
		add_meta_box(
			'bridgybox-meta',      // Unique ID
			esc_html__( 'Bridgy Publish To', 'Bridgy Publish' ),    // Title
			array( 'Bridgy_Postmeta', 'metabox' ),   // Callback function
			'post',         // Admin page (or post type)
			'side',         // Context
			'default'         // Priority
		);
	}

	public static function bridgy_checkboxes() {
		$options = get_option( 'bridgy_options' );
		$bridgy_checkboxes = array(
						'twitter' => _x( 'Twitter', 'Bridgy Publish' ),
						'facebook' => _x( 'Facebook', 'Bridgy Publish' ),
						'flickr' => _x( 'Flickr', 'Bridgy Publish' ),
						);
		if ( $options ) {
			foreach ( $options as $key => $value ) {
				if ( 0 == $options[ $key ] ) {
					unset( $bridgy_checkboxes[ $key ] );
				}
			}
		}
		return $bridgy_checkboxes;
	}

	public static function metabox( $object, $box ) {
		wp_nonce_field( 'bridgy_metabox', 'bridgy_metabox_nonce' );
		$bridgy_checkboxes = self::bridgy_checkboxes();
		$bridgy = get_post_meta( get_the_ID(), '_bridgy_options', true );
		echo '<ul>';
		foreach ( $bridgy_checkboxes as $key => $value ) {
			echo '<li>';
			echo '<input type="checkbox" name="bridgy_' . $key . '"';
			if ( isset( $bridgy[ $key ] ) ) {
				echo ' value="yes" ' . checked( $bridgy[ $key ], 'yes' ) . '"';
			}
			echo ' />';
			echo '<label for="bridgy_' . $key . '">' . $value . '</label>';
			echo '</li>';
		}
		echo '</ul>';
	}

	/* Save the meta box's post metadata. */
	public static function save_post( $post_id, $post ) {
		/*
		 * We need to verify this came from our screen and with proper authorization,
		 * because the save_post action can be triggered at other times.
		 */
		// Check if our nonce is set.
		if ( ! isset( $_POST['bridgy_metabox_nonce'] ) ) {
			return;
		}

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['bridgy_metabox_nonce'], 'bridgy_metabox' ) ) {
			return;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check the user's permissions.
		if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}
		$bridgy_checkboxes = self::bridgy_checkboxes();
		$bridgy = array();
		/* OK, its safe for us to save the data now. */
		foreach ( $bridgy_checkboxes as $key => $value ) {
			if ( isset( $_POST[ 'bridgy_' . $key ] ) ) {
				$bridgy[ $key ] = 'yes';
			}
		}
		if ( ! empty( $bridgy ) ) {
			update_post_meta( $post_id,'_bridgy_options', $bridgy );
		}
	}

	public static function transition_post_status($new, $old, $post) {
		if ( 'publish' == $new && 'publish' != $old ) {
			self::save_post( $post->ID,$post );
		}
		$bridgy = get_post_meta( $post->ID, '_bridgy_options', true );
		$syn = get_post_meta( $post->ID, 'bridgy_syndication', true );
		if ( ! is_array( $syn ) ) {
			$syn = explode( "\n", $syn );
		}
		$options = get_option( 'bridgy_options' );
		if ( 1 == $options['shortlinks'] ) {
			$url = wp_get_shortlink( $post->ID );
		} else {
			$url = get_permalink( $post->ID );
		}
		if ( ! $syn ) { $syn = array(); }
		if ( ! empty( $bridgy ) ) {
			foreach ( $bridgy as $key => $value ) {
				$response = send_webmention( $url, 'https://www.brid.gy/publish/' . $key );
				  $response_code = wp_remote_retrieve_response_code( $response );
				$json = json_decode( $response['body'] );
				if ( 201 == $response_code ) {
					 	$syn[] = $json->url;
				}
				if ( (400 == $response_code)||(500 == $response_code) ) {
						error_log( 'Bridgy Publish Error: ' . $json->error );
				} else {
					if ( WP_DEBUG ) {
						error_log( 'Bridgy Published: ' . $response_code . ' ' . get_permalink( $post ) . ' => '
						. serialize( $json ) );
					}
				}
			}
			$syn = array_unique( array_filter( $syn ) );
			if ( ! empty( $syn ) ) {
				update_post_meta( $post->ID, 'bridgy_syndication', $syn );
			} else {
				delete_post_meta( $post->ID, 'bridgy_syndication' );
			}
		}
	}

	public static function the_content($content) {
		$bridgy = get_post_meta( get_the_ID(), '_bridgy_options', true );
		if ( empty( $bridgy ) ) { return $content; }
		$publish = '';
		$options = get_option( 'bridgy_options' );
		$classes = array();
		if ( 1 == $options['omitlink'] ) {
			$classes[] = 'u-bridgy-omit-link';
		}
		if ( 1 == $options['ignoreformatting'] ) {
			$classes[] = 'u-bridgy-ignore-formatting';
		}
		$class = implode( ' ', $classes );
		foreach ( $bridgy as $key => $value ) {
			$publish .= '<a class="' . $class . '" href="https://www.brid.gy/publish/' . $key . '"></a>';
		}
		return $content . $publish;
		;
	}

	public static function syn_add_links($urls) {
		$bridgy = get_post_meta( get_the_ID(), 'bridgy_syndication', true );
		if ( is_array( $bridgy ) ) {
			return array_merge( $urls, syn_meta::clean_urls( $bridgy ) );
		} else {
			return $urls;
		}
	}

} // End Class

?>
