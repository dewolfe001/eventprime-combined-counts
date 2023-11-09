jQuery(document).ready(function($) {
    // Event listener for the ticket_minus button
    $(".ticket_minus").on('click', function() {
        var parent_id = $(this).data('parent_id'); // Corrected to use jQuery data method correctly

        // Find all input elements of type number and class 'ep-select-event-ticket-number'
        // except the one with the specific parent_id
        $(".ep-select-event-ticket-number input[type='number']").not("#ep_event_ticket_qty_" + parent_id).each(function() {
            var $input = $(this);
            var maxAllowed = $input.data('epcc'); // Assuming data-epcc is an attribute in the input tag
            var currentMax = parseInt($input.attr('max'), 10) || 0;

            // Add 1 to max but do not exceed the value in data-epcc
            if (currentMax < maxAllowed) {
                $input.attr('max', currentMax + 1);
            }
        });
    });

    // Event listener for the ticket_plus button
    $(".ticket_plus").on('click', function() {
        var parent_id = $(this).data('parent_id'); // Corrected to use jQuery data method correctly

        // Find all input elements of type number and class 'ep-select-event-ticket-number'
        // except the one with the specific parent_id
        $(".ep-select-event-ticket-number input[type='number']").not("#ep_event_ticket_qty_" + parent_id).each(function() {
            var $input = $(this);
            var currentMax = parseInt($input.attr('max'), 10) || 0;

            // Subtract 1 from max but do not go below zero
            if (currentMax > 0) {
                $input.attr('max', currentMax - 1);
            }
        });
    });
});















/*
jQuery(document).ready(function($) {
	// Add an event listener to the radio buttons
	$(".ticket_minus").on('click' ,function() {
        // find and ignore the current capacity.  Change the rest by incrementing them.
        alert("Add one elsewhere");
        var parent_id = data('parent_id');
        
	});
	$(".ticket_plus").on('click' ,function() {
        // find and ignore the current capacity. Change the rest by decrementing them. 
        alert("Diminish one elsewhere");        
        var parent_id = data('parent_id');
        
	});	

});
*/