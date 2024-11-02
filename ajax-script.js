jQuery(document).ready(function($) {
    $('#sf_shortcode_form').on('submit', function(e) {
        e.preventDefault(); // Prevent default form submission

        var shortcode = $('#sf_shortcode').val(); // Get selected shortcode
        var posttype = $('#sf_post_type').val(); // Get selected post type
        var poststatus = $('#sf_post_status').val(); // Get selected post status
        // Show loading spinner
        $('#sf_loading').show();
        $('#sf_results').html(''); // Clear previous results

        $.ajax({
            type: 'POST',
            url: sf_ajax_object.ajax_url,
            data: {
                action: 'sf_get_shortcode_usage',
                shortcode: shortcode,
                posttype: posttype, // Send the selected post type
                poststatus: poststatus // Send the selected post status
            },
            success: function(response) {
                $('#sf_loading').hide(); // Hide loading spinner

                if (response.success) {
                    $('#sf_results').html(response.data); // Display results
                } else {
                    $('#sf_results').html('<p>' + response.data + '</p>'); // Display error message
                }
            },
            error: function() {
                $('#sf_loading').hide();
                $('#sf_results').html('<p>An error occurred. Please try again.</p>');
            }
        });
    });
});
