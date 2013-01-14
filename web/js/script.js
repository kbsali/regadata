function getMax(_class) {
    if(null === document.getElementById("report")) {
        return;
    }
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

function showmap() {
    if(null === document.getElementById("map")) {
        return;
    }
    var myOptions = {
        center: new google.maps.LatLng(0, 0),
        zoom: 3,
        mapTypeId: google.maps.MapTypeId.SATELLITE
    };
    map = new google.maps.Map(document.getElementById("map"), myOptions);
    kml = new google.maps.KmlLayer('http://vg2012.saliou.name/json/trace_FULL.kmz',{ preserveViewport : true });
    kml.setMap(map);
    $(document.getElementById("main")).css("padding", 0);
    $(document.getElementById("map")).height(document.documentElement.clientHeight-115);
}

!function ($) {
    $(function() {
        $(".tooltips").tooltip({
            selector: "a[rel=tooltip]"
        });
        var clmax = ["1hspeed", "1hvmg", "lrspeed", "lrvmg", "lrdistance", "24hspeed", "24hvmg", "24hdistance", "total_distance", "oas", "dtl_diff", "dtp"];
        for(var i=0;i<clmax.length;i++) {
            getMax(clmax[i])
        }
        showmap();
    })
}(window.jQuery)