$(function(){
    var isMobile = false;
    if( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ) {
        isMobile = true;
    }
    if(isMobile){

    }

    $('.toggle').eq(0).click(function(){
        if($('.left').is(':hidden') === true)
        {
            $('.left').show();
            $('.right').css('margin-left','200px');
        }else{
            $('.left').hide();
            $('.right').css('margin-left','0');
        }
    });

});