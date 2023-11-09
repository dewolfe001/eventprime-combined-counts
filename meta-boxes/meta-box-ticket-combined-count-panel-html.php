<?php
/**
 * Event tickets panel html
 */
defined( 'ABSPATH' ) || exit;
global $post;


if (!$post) {
    $show_meta = false;    
    if (array_key_exists('action', $_GET)) {
        $show_meta = true;
    }
    if (array_key_exists('post_type', $_GET)) {
        $show_meta = true;
    }
}
else {
    $show_meta = true;    
}

$event_controller = EventM_Factory_Service::ep_get_instance( 'EventM_Event_Controller_List' );
if ($show_meta) {
    $em_combined_count = get_post_meta( $post->ID, 'em_combined_count', true );
    $single_event_data = $event_controller->get_single_event( $post->ID );
}
else {
    $em_combined_count = [];
    $single_event_data = [];
}

$extensions = $this->extensions;
$is_event_expired = check_event_has_expired( $single_event_data );

?>
<div id="ep_epcc_data" class="panel ep_event_options_panel">
    <div class="ep-box-wrap">
		<div class="ep-box-row ep-p-1">
			<div class="ep-box-row ep-p-3 ep-border ep-bg-light ep-rounded ep-m-3">
				<strong>What are your policies on ticket combinations?</strong>
				<div class="ep-form-combined-count-policy ep-mb-3">
					<input class="ep-form-check-input" type="radio" name="ep-policy-default" id="ep-policy-default" value="default"<?php print epcc_form_policy('default', $em_combined_count) ?>>
					<label class="ep-form-combined-count-label" for="ep-policy-default">
						Enforce Per Type Policy
						<div class="ep-text-muted ep-text-small">
							The established tickets are limited per category
						</div>
					</label>
				</div>
				<div class="ep-form-combined-count-policy ep-mb-3">
					<input class="ep-form-check-input" type="radio" name="ep-policy-default" id="ep-policy-default" value="per-cap"<?php print epcc_form_policy('per-cap', $em_combined_count) ?>>
					<label class="ep-form-combined-count-label" for="ep-policy-default">
						Combined Limit
						<div class="ep-text-muted ep-text-small">
							You may sell up to the capacity limit for the event, irrespective of ticket type.
						</div>
					</label>
				</div>
				<div class="show-cap">
					<strong>What is your ticket capacity limit?</strong>
					<div class="ep-form-combined-count-cap ep-mb-3">
						<input class="ep-form-number-input" type="number" name="ep-policy-cap" id="ep-policy-cap" value="<?php print epcc_form_cap($em_combined_count); ?>">
						<label class="ep-form-combined-count-label" for="ep-policy-cap">
							What is your ticket sale limit?
							<div class="ep-text-muted ep-text-small">
								By default, this is a combination of your ticket counts for the other categories.
							</div>
						</label>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<?

function epcc_form_policy($field, $em_combined_count) {
    if (is_null($em_combined_count)) {
		if ($field == 'default') {
			return ' checked';	
		}	
		else {
			return '';
		}
	} 
		
	if ((!array_key_exists('ep-policy-default', $em_combined_count)) && ($field == 'default')) {
        return ' checked';	
	}
	if ($em_combined_count['ep-policy-default'] == $field) {
        return ' checked';	
	}
	return '';
}

function epcc_form_cap($em_combined_count) {
	global $post;
	$cap = 0;
	if ($em_combined_count) {
    	$cap = $em_combined_count['ep-policy-cap'];	    
	}
	
	if ($cap == 0) {
		// combine counts
		$data = epcc_ticket_count($post->ID);
		$cap = 0;
		foreach ($data as $datum) {
			if ($datum->status == 1) {
				$cap += $datum->capacity;	
			}
		}	
	}
	return intval($cap);
}

?>