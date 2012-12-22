function getMax(_class) {
    var max = 0, tmp, idx;
    $("#report td."+_class).each(function(rowIndex) {
        tmp = parseFloat($(this).text());
        if(max < tmp) {
            max = tmp;
            $sel = $(this);
        }
    });
    $sel.css("font-weight", "bold");
}
!function ($) {
    $(function() {
        $(".tooltips").tooltip({
            selector: "a[rel=tooltip]"
        });
        var clmax = ["1hspeed", "1hvmg", "lrspeed", "lrvmg", "lrdistance", "24hspeed", "24hvmg", "24hdistance"];
        for(var i=0;i<clmax.length;i++) {
            getMax(clmax[i])
        }
    })
}(window.jQuery)