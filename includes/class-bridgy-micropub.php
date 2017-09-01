<?php
// The Bridgy_Micropub class handles Micropub endpoint publishing
class Bridgy_Micropub {

	public static function do_micropub($input,$args) {
		if(WP_DEBUG) { error_log("BRIDGY MICROPUB: ".print_r($input,true)."\n\n ARGS: ".print_r($args,true)); }
		if(isset($input['properties']['mp-syndicate-to'])) {
			$targets = $input['properties']['mp-syndicate-to'];
			if(isset($args['ID'])) {
				$post_id = $args['ID'];
				update_post_meta( $post_id, 'mf2_syndicate-to', array_map( 'sanitize_text_field', $targets ) );
				Bridgy_Postmeta::send_bridgy($post_id)
			}
		}
	}
}
?>
