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
        var clmax = ["1hspeed", "1hvmg", "lrspeed", "lrvmg", "lrdistance", "24hspeed", "24hvmg", "24hdistance", "total_distance", "oas", "dtl_diff"];
        for(var i=0;i<clmax.length;i++) {
            getMax(clmax[i])
        }
        $("#report td.dtl_diff").each(function(rowIndex) {
            console.log($(this).text());
            var c = 'icon-resize-horizontal';
            var b = 'label-info';
            if($(this).text() < 0) {
                c = 'icon-arrow-up';
                b = 'label-success';
            } else if($(this).text() > 0) {
                c = 'icon-arrow-down';
                b = 'label-important';
            }
            $(this).find("i").addClass(c);
            $(this).find("span").addClass(b);
        });
    })
}(window.jQuery)