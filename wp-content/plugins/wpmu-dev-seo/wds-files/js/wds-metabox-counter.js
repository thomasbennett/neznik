(function ($) {
$(function () {
	
var __WDS_TITLE_COUNT = 70;
var __WDS_META_COUNT = 160;

function checkTitleLength () {
	var res = $('#wds_title').val().length;
	if (res > __WDS_TITLE_COUNT) {
		$('#wds_title').val( $('#wds_title').val().substr(0, __WDS_TITLE_COUNT) );
		return false;
	}
	$('#wds_title_counter_result').text( (__WDS_TITLE_COUNT - res) + ' characters left');
}
function checkMetaLength () {
	var res = $('#wds_metadesc').val().length;
	if (res > __WDS_META_COUNT) {
		$('#wds_metadesc').val( $('#wds_metadesc').val().substr(0, __WDS_META_COUNT) );
		return false;
	}
	$('#wds_meta_counter_result').text( (__WDS_META_COUNT - res) + ' characters left');
}

function setUpCounters () {
	var $title = $('#wds_title');
	if (!$title.length) return false;
	$title.parents('td').append('<p id="wds_title_counter_result">' + __WDS_TITLE_COUNT + ' characters left</p>');
	$title.keyup(checkTitleLength);
	$title.change(checkTitleLength);
	checkTitleLength();

	var $meta = $('#wds_metadesc');
	if (!$meta.length) return false;
	$meta.parents('td').append('<p id="wds_meta_counter_result">' + __WDS_META_COUNT + ' characters left</p>');
	$meta.keyup(checkMetaLength);
	$meta.change(checkMetaLength);
	checkMetaLength();
}

setUpCounters();

// Set overflow for SEO metabox
$("#wds-wds-meta-box .inside").css('overflow-x', 'scroll');

});
})(jQuery);