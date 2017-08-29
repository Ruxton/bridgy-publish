<?php
// The Bridgy_Micropub class handles Micropub endpoint publishing
class Bridgy_Micropub {

	public static function do_micropub($input,$args) {
		if(WP_DEBUG) { error_log("BRIDGY MICROPUB: ".print_r($input,true)."\n\n ARGS: ".print_r($args,true)); }
		if(isset($input['properties']['mp-syndicate-to'])) {
			$targets = $input['properties']['mp-syndicate-to'];
			error_log("POST_ID: ".$post_id);
		}
	}
}
?>
