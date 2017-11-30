<!DOCTYPE html>
<html>
<head>
    <title>StLight Map</title>
    <link rel='stylesheet' href='assets/css/style.css' />
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
	<!-- PNotify -->
    <link href='assets/vendors/pnotify/dist/pnotify.css' rel="stylesheet">
    <link href='assets/vendors/pnotify/dist/pnotify.buttons.css' rel="stylesheet">
    <link href='assets/vendors/pnotify/dist/pnotify.nonblock.css' rel="stylesheet">
</head>
<body>
<!-- start of map -->
<div id="map-details">
	<div class="detail-title">
		<h3> <b>Street Light Monitoring </b></h3>
		<p style="text-align:right"><small> Operated on TMRnD Container Platform </small></p>
	</div>
	<ul id="server-details" class="list-group">
	</ul>
</div>
<div class="clearfix"></div>
<div id="map-canvas"></div>
<!-- snip -->
<div id="dom-target" style="display: none;">
    <?php 
        $output = $_SERVER['SERVER_ADDR']; //gethostname();
        echo htmlspecialchars($output); 
    ?>
</div>
<?php $markerFile=json_decode(file_get_contents("config/ss.js"), true);?>
<?php $styleFile=json_decode(file_get_contents("config/styles.js"), true);?>
<script src="assets/js/jquery/jquery-3.1.1.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
<!-- PNotify -->
<script src="assets/vendors/pnotify/dist/pnotify.js"></script>
<script src="assets/vendors/pnotify/dist/pnotify.buttons.js"></script>
<script src="assets/vendors/pnotify/dist/pnotify.nonblock.js"></script>
<!-- PNotify -->
<script>
  $(document).ready(function() {
	var div = document.getElementById("dom-target");
    var hostname = div.textContent;
    
	new PNotify({
	  title: "Street Light Monitoring Map",
	  type: "info",
	  text: "This application run on host: <b> "+hostname+"</b>",
	  addclass: 'dark',
	  styling: 'bootstrap3',
	  hide: false,
	  before_close: function(PNotify) {
		PNotify.update({
		  title: PNotify.options.title + " - powered by Container",
		  before_close: null
		});

		PNotify.queueRemove();

		return false;
	  }
	});

  });
</script>
<!-- /PNotify -->
<script>var isIE = false;</script><!--[if IE]><script>isIE = true;</script><![endif]-->
<script>
	var serverz = <?php echo json_encode($markerFile);?>;
	var mapstyle= <?php echo json_encode($styleFile);?>;
	var _timeout_cb,
		_time_to_timeout=100*45;
    var map;
    var smallerZoom = 6, largerZoom = 17;
	var markers_on=[],markers_off=[];
    var geocoder;
    var sl_light_on = "assets/images/svg/street-light.svg#st-light",
		sl_light_off = "assets/images/svg/street-light-off.svg#st-light-off";
	var $leftList=$('#server-details');

    /* wait for library to be loaded first to initialize */
    var mapLibsReady = 0;
    var mapLibReadyHandler = function() {
        if (++ mapLibsReady < 1) return;

        initMap();
        createSDNMarkers();
		startingClockUp();
    }

	var mapOptions={};
    var initMap = function() {
        geocoder = new google.maps.Geocoder();
        var lat = 4.5278171, lng = 109.1658123;
        mapOptions = {
			scrollwheel: false,
			navigationControl: false,
			mapTypeControl: false,
			scaleControl: false,
            center: new google.maps.LatLng(lat,lng),
            zoom: smallerZoom,
            mapTypeId: google.maps.MapTypeId.ROADMAP,
            gestureHandling: 'cooperative',
			styles:mapstyle.dark
        };

        map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);
        var acOptions = {
            types: ['establishment']
        };
		onMapZoomChanged();
    }
	
	function getQueryString() {
	  var result = {}, queryString = location.search.slice(1),
		  re = /([^&=]+)=([^&]*)/g, m;
	  while (m = re.exec(queryString)) {
		result[decodeURIComponent(m[1])] = decodeURIComponent(m[2]);
	  }
	  return result;
	}

	var markerDataReady = 0;
	var markerSvrLim=0;
    function createSDNMarkers() {
        /* example set marker */
		//var id = getQueryString()["id"];
		var id="SL";
		var svrArr = [];
		serverz.some(function(val) {
		  if (!("id" in val)) return false;
		  if (val.id == id) {
			svrArr=svrArr.concat(val.servers);
			return true;
		  }
		});
        if (0==svrArr.length) return;
		
		markerSvrLim=svrArr.length;
        var handleObj={
			arr:[]
		};
		var polyCoords=[];
		$.map(svrArr,function(val,i){
			var location=val.location,
				latLng="latLng" in location?location.latLng:"";
			
			$leftList.append('<li id="pol_list_'+i+'" class="list-group-item list-group-item-action"><b>'+val.title.replace('Light','Pole')+' </b>&nbsp; ('+latLng+') <span id="msl_'+i+'" style="float:right;" class="label label-success label-pill">on</span></li>');
			
			if ("latLng" in location) {
				var latLng=location.latLng.split(',');
				processHandleObj(handleObj,latLng[0], latLng[1], val);
				prepareMarkerData(handleObj);
				polyCoords.push({lat:parseFloat(latLng[0]),lng:parseFloat(latLng[1])});			
				
				return;
			}
		});
		setArealCompund(polyCoords);
    }
	
	function processHandleObj(obj,lat,lng,val){
		var marker=setMarker(lat, lng, val, sl_light_on);
		marker.setMap(map);
		markers_on.push(marker);
		
		obj.arr.push(marker);
		// sl light off
		var mark_2=setMarker(lat, lng, val, sl_light_off);
		markers_off.push(mark_2);
	}
	
	function startingClockUp() {
		endClockOut();
		_timeout_cb=setTimeout(alternate_marker,_time_to_timeout);
	}
	function endClockOut() {
		if (_timeout_cb!==null) clearTimeout(_timeout_cb);
	}
	
	function alternate_marker() {
		markers_on.forEach(function(val,i){
			var m_on_vis=val.getMap();//map.getBounds().contains(val.getPosition());
			var $msl_status=$('#msl_'+i);
			
			if (m_on_vis) {
				mapOptions.styles=mapstyle.light;
				markers_off[i].setMap(map);
				val.setMap(null);
				$msl_status.html('off').removeClass('label-success').addClass('label-default');
			} else {
				mapOptions.styles=mapstyle.dark;
				val.setMap(map);
				markers_off[i].setMap(null);
				$msl_status.html('on').removeClass('label-default').addClass('label-success');
			}
			mapOptions.zoom=map.getZoom();
			mapOptions.center=map.getCenter();
			map.setOptions(mapOptions);
		});
		startingClockUp();
	}
	
	var prepareMarkerData=function(obj) {
		if (markerSvrLim==0) return;
		//console.log('prepareMarkerData: ',markerDataReady);
		if (++markerDataReady < markerSvrLim) return;
		
		fitBoundsMarkers(obj.arr);
	}
	
	function setArealCompund(arr) {
		var polygonStructure = new google.maps.Polygon({
			paths: arr,
			strokeColor: '#b39c00',
			strokeOpacity: 0.8,
			strokeWeight: 2,
			fillColor: '#ffdf00',
			fillOpacity: 0.35
		});
		polygonStructure.setMap(map);
	}
	
    var setMarker = function(lat, lng, obj, path) {
		
        var m = new google.maps.Marker({
            position: new google.maps.LatLng(lat,lng),
            zIndex : -20,
            title: obj.title,
            //map: map,
            optimized: ! isIE  // makes SVG icons work in IE
        });

        if (typeof path != 'undefined' && path.length) {
            m.setIcon(getMarkerImage(path));
        }

        var content = '<div id="iw-container">' +
                    '<div class="iw-title">'+obj.title+'<button class="btn btn-round btn-primary btn-md">Schedule</button></div>' +
                    '<div class="iw-content">' +
                    '<div class="iw-subTitle">Profile</div>' +
                    '<b>Location</b>: '+obj.latLng+'</p>' +

            '</div>' +
            '<div class="iw-bottom-gradient"></div>' +
            '</div>';
            createInfoWindow(m, content);
        return m;
    }
	
	function getMarkerImage(path) {
		var point_x=22,point_y=40;
		var scale_y=45;
		
		if (path==sl_light_off) {
			point_x=23;
			point_y=44;
			scale_y=44;
		}
		return new google.maps.MarkerImage(
			path,
			new google.maps.Size(45,45), //size
			null, //origin
			new google.maps.Point(point_x,point_y), //anchor
			new google.maps.Size(45,scale_y) //scale
		);
	}
	
	function onMapZoomChanged() {
		//when the map zoom changes, resize the icon based on the zoom level so the marker covers the same geographic area
		google.maps.event.addListener(map, 'zoom_changed', function() {
			var pixelSizeAtZoom0 = 8; //the size of the icon at zoom level 0
			var maxPixelSize = 45; //restricts the maximum size of the icon, otherwise the browser will choke at higher zoom levels trying to scale an image to millions of pixels

			var zoom = map.getZoom();
			var relativePixelSize = Math.round(pixelSizeAtZoom0*Math.pow(1.1,zoom)); // use 2 to the power of current zoom to calculate relative pixel size.  Base of exponent is 2 because relative size should double every time you zoom in
			
			//console.log('zoom_ch: %s\nrelativePixelSize: %s',zoom,relativePixelSize);

			if(relativePixelSize > maxPixelSize) //restrict the maximum size of the icon
				relativePixelSize = maxPixelSize;
			
			markers_on.forEach(function(val){
				val.setIcon(
					new google.maps.MarkerImage(
						val.getIcon().url, //marker's same icon graphic
						null,//size
						null,//origin
						null, //anchor
						new google.maps.Size(relativePixelSize, relativePixelSize) //changes the scale
					)
				);
			});
		});
	}

    function createInfoWindow(marker, content) {
        var infoWindowOptions = {
            content: content,
            maxWidth: 370
        };
        var infoWindow = new google.maps.InfoWindow(infoWindowOptions);

        google.maps.event.addListener(marker,'click',function(e){
            if (!isInfoWindowOpen(infoWindow)) infoWindow.open(map, marker);
            else infoWindow.close();
        });
        google.maps.event.addListener(marker,'mouseover',function(e){
            if (map.getZoom > smallerZoom) {
                if (!isInfoWindowOpen(infoWindow)) infoWindow.open(map, marker);
            }
        });
        google.maps.event.addListener(marker,'mouseout',function(e){
//          if (isInfoWindowOpen(infoWindow)) infoWindow.close();
        });

        google.maps.event.addListener(infoWindow, 'domready', function() {
            // Reference to the DIV that wraps the bottom of infowindow
            var iwOuter = $('.gm-style-iw');

            /* Since this div is in a position prior to .gm-div style-iw.
             * We use jQuery and create a iwBackground variable,
             * and took advantage of the existing reference .gm-style-iw for the previous div with .prev().
             */
            var iwBackground = iwOuter.prev();

            // Removes background shadow DIV
            iwBackground.children(':nth-child(2)').css({'display' : 'none'});

            // Removes white background DIV
            iwBackground.children(':nth-child(4)').css({'display' : 'none'});

            // Moves the infowindow 115px to the right.
            iwOuter.parent().parent().css({left: '75px'});

            // Moves the shadow of the arrow 76px to the left margin.
            iwBackground.children(':nth-child(1)').attr('style', function(i,s){ return s + 'left: 76px !important;'});

            // Moves the arrow 76px to the left margin.
            iwBackground.children(':nth-child(3)').attr('style', function(i,s){ return s + 'left: 76px !important;'});

            // Changes the desired tail shadow color.
            iwBackground.children(':nth-child(3)').find('div').children().css({'box-shadow': 'rgba(72, 181, 233, 0.6) 0px 1px 6px', 'z-index' : '1'});

            // Reference to the div that groups the close button elements.
            var iwCloseBtn = iwOuter.next();

            // Apply the desired effect to the close button
            iwCloseBtn.css({opacity: '1', right: '40px', top: '4px', width:'22px',height:'22px', border: '4px solid #E96702', 'border-radius': '10px', 'box-shadow': '0 0 5px #3990B9'});

            // If the content of infowindow not exceed the set maximum height, then the gradient is removed.
            if($('.iw-content').height() < 140){
                $('.iw-bottom-gradient').css({display: 'none'});
            }

            // The API automatically applies 0.7 opacity to the button after the mouseout event. This function reverses this event to the desired value.
            iwCloseBtn.mouseout(function(){
                $(this).css({opacity: '1'});
            });
        });
    }

    function isInfoWindowOpen(infoWindow){
        var map = infoWindow.getMap();
        return (map !== null && typeof map !== "undefined");
    }

    function connectMarker(parent, child) {
        var polyline;
        if (child.constructor === Array) {
            child.forEach(function(v){
                polyline = createPolyline(parent, v);
                polyline.setMap(map);
            })
        } else {
            polyline = createPolyline(parent, child);
            polyline.setMap(map);
        }
    }

    function createPolyline(parent, child) {
        var path = [];
        path.push(parent.getPosition());
        path.push(child.getPosition());

        return new google.maps.Polyline({
            strokeColor:"#000000",
            strokeOpacity: 0.8,
            strokeWeight: 1,
            path:path
        });
    }

    $(function() {

    })

    function findLocationByAddress(addr, cb) {
        GMaps.geocode({
            address: addr,
            callback: function(results, status) {
                if ('OK'==status) {
                    var latlng = results[0].geometry.location;
                    cb({
                        lat: latlng.lat(),lng: latlng.lng(),
                        location: latlng,
                        bounds: results[0].geometry.bounds,
                        viewport: results[0].geometry.viewport
                    });
                } else cb(); /* return undefined */
            }
        });
    }

    function fitBoundsMarkers(markers) {
        var bounds = new google.maps.LatLngBounds();
        for (var i=0;i<markers.length;i++){
            if(markers[i].getVisible()) {
                bounds.extend(markers[i].getPosition());
            }
        }
        map.fitBounds(bounds);
    }
	
	function getZoom() {
		return map.getZoom();
	}
</script>

<!-- gmaps.js -->
<script src="assets/js/maps/gmaps.js" type="text/javascript"></script>
<!-- marker clusterer -->
<script src="assets/js/maps/markerclusterer.js" type="text/javascript"></script>
<!-- Google Map -->
<script src="https://maps.googleapis.com/maps/api/js?v=3&callback=mapLibReadyHandler&key=AIzaSyBWUMinfvGZQYP3Ow_h71CnCqYaEOrvJj0&libraries=places"></script>
</body>
</html>