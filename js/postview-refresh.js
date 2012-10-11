jQuery(document).ready(function($) {

	var data = {
		action: 'refresh_post_views'
	};
	
	var loading = jQuery('<span class="loading">').html('Loading...');
	
	jQuery('#analytics-settings-page-google_analytics_page_views-ga_manual_refresh').click(
		function(){
			jQuery(this).replaceWith(loading);
			$.post(ajaxurl, data, function(response) {
				jQuery(loading).removeClass('loading').html(response);
			});
			return false;
		}
	);

});