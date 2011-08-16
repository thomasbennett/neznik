jQuery(document).ready( function($) {
	var bgx = ( isRtl ? 'left' : 'right' );

	// help tab
	$('.toggle-contextual-help').click(function () {
		if ( ! $('#contextual-help-wrap').hasClass('contextual-help-open') )
			$('#screen-options-link-wrap').css('visibility', 'hidden');

		$('#contextual-help-wrap').slideToggle('fast', function() {
			if ( $(this).hasClass('contextual-help-open') ) {
				$('#contextual-help-link').css({'backgroundPosition':'top '+bgx});
				$('#screen-options-link-wrap').css('visibility', '');
				$(this).removeClass('contextual-help-open');
			} else {
				$('#contextual-help-link').css({'backgroundPosition':'bottom '+bgx});
				$(this).addClass('contextual-help-open');
			}
		});
		return false;
	});
});
