
	  function setCookie(name, value) {
		var date = new Date();
		date.setDate(date.getDate() + 180);
		document.cookie = name + "=" + value + "; path=/; expires=" + date.toGMTString();
	  }
	  function getCookie(name) {
		var r = document.cookie.match("(^|;) ?" + name + "=([^;]*)(;|$)");
		if (r) return r[2];
		else return "";
	  }
	  if (getCookie("ubid")=="")
	  {
		  setCookie("ubid", "ubid_"+Math.random().toString(36).substr(2, 17)+Math.random().toString(36).substr(2, 17));
	  }

	  // scrollToTop
		$(function(){
		  $.fn.scrollToTop=function(){
			$(this).hide().removeAttr("href");
			if($(window).scrollTop()!="0"){
				$(this).fadeIn("slow")
		  }
		  var scrollDiv=$(this);
		  $(window).scroll(function(){
			if($(window).scrollTop()=="0"){
			$(scrollDiv).fadeOut("slow")
			}else{
			$(scrollDiv).fadeIn("slow")
		  }});
			$(this).click(function(){
			  $("html, body").animate({scrollTop:0},"slow")})
		  }});
	$(function() {$("#toTop").scrollToTop();});
