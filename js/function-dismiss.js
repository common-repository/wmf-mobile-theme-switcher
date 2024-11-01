(function($) {
  "use strict";

  $('body').on('click','#wmfdismiss button',function(){
		$.ajax({
			url: wmfthemeswitcherjscmg.ajaxurl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'wmf_nagsystem',
				nname: 'wmf_notice',
				security: wmfthemeswitcherjscmg.nonce
			},
			success:function(data){
				$('#wmfdismiss').hide();
			}
		});
	});

})(jQuery);