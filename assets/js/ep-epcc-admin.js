jQuery(document).ready(function($) {
	// Add an event listener to the radio buttons
	$("input[name='ep-policy-default']").change(function() {
		// Check which radio button is selected
		if ($(this).val() === 'per-cap') {
		// If "per-cap" is selected, remove the 'false' class from .show-cap
			$(".show-cap").removeClass('false');
		} else {
		// If "default" or another option is selected, add the 'false' class to .show-cap
			$(".show-cap").addClass('false');
		}
	});
	
    // Run the code once when the page first loads
    if ($("input[name='ep-policy-default']:checked").val() === 'per-cap') {
        $(".show-cap").removeClass('false');
    } else {
        $(".show-cap").addClass('false');
    }	
});