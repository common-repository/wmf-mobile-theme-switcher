(function($) {
  "use strict";

  $("#wmf_thwsw_ex_options").multiSelect();

  new ClipboardJS(".wmfcopylink").on("success", function(e) {
  	$(e.trigger).append("<span class='copiedtext' style='color:red'>"+wmfthemeswitcherjscm.copiedtext+"</span>");
  	setTimeout(function(){
  		$(e.trigger).find(".copiedtext").remove();
  	},2000);
  	e.clearSelection();
  });

})(jQuery);