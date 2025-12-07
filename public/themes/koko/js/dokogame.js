/** ************************************************************************* *
 *  Startup
 ** ************************************************************************* */
window.KEY_DOWNARROW = 40;
window.KEY_ESCAPE = 27;
window.KEY_ENTER = 13;

var xComplete = false;
var xLocation = false;
var xStart = 0;
var xSecs = 0;

document.onreadystatechange = function () {
    if (document.readyState == "interactive") {
        window.addEventListener('offline', function(e) { showNetworkStatus(); });
        window.addEventListener('online', function(e) { showNetworkStatus(true); });

        /* Bring the Page to Life */
        preparePage();
    }
}

/** ************************************************************************* *
 *  Population Functions
 ** ************************************************************************* */
function preparePage() {
    var els = document.getElementsByClassName('btn-action');
    for ( let i = 0; i < els.length; i++ ) {
        els[i].addEventListener('touchend', function(e) { handleButtonAction(e); });
        els[i].addEventListener('click', function(e) { handleButtonAction(e); });
    }

    /* Ensure inputs are not annoying */
    var _attribs_false = ['spellcheck'];
    var _attribs_off = ['autocomplete', 'autocorrect', 'autocapitalize'];
    var els = document.getElementsByTagName('input');
    for ( let e = 0; e < els.length; e++ ) {
        if ( els[e].type !== undefined && els[e].type !== null ) {
            switch ( NoNull(els[e].type).toLowerCase() ) {
                case 'text':
                    for ( let a in _attribs_false ) {
                        els[e].setAttribute(_attribs_false[a], 'false');
                        els[e].setAttribute(_attribs_false[a], 'false');
                    }
                    for ( let a in _attribs_off ) {
                        els[e].setAttribute(_attribs_off[a], 'off');
                        els[e].setAttribute(_attribs_off[a], 'off');
                    }
                    break;

                default:
                    /* Do Nothing */
            }
        }
    }

    /* Collect the Location Data */
    setTimeout(function () { getGameData(); }, 150);
}

function getGameData() {
    var _key = NoNull(window.location.pathname).replaceAll('/', '').toLowerCase();
    var _path = 'random';

    switch ( _key ) {
        case 'today':
            _path = _key;
            break;

        default:
            /* No Change Required */
    }

    /* Collect the Game data */
    setTimeout(function () { doJSONQuery('location/' + _path, 'GET', {}, parseGameData); }, 75);
}
function parseGameData(data) {
    if ( data.meta !== undefined && data.meta.code == 200 ) {
        var ds = data.data;

        if ( NoNull(ds.guid).length == 36 ) {
            xLocation = ds;

            /* Populate the Form */
            var els = document.getElementsByName('fdata');
            for ( let e = 0; e < els.length; e++ ) {
                var _name = NoNull(els[e].getAttribute('data-name')).toLowerCase();
                switch ( _name ) {
                    case 'location_key':
                        setElementValue(els[e], ds.key);
                        break;

                    default:
                        setElementValue(els[e], '');
                }
            }

            /* Set the Image */
            setImageFile(ds.src);

            /* Ensure the correct elements are visible */
            showByClass('onload');

            /* Start the Watchers */
            setTimeout(function () { watchGameTimer(); }, 150);
            setTimeout(function () { watchInputs(); }, 250);
        }
    }
}

function setImageFile( _src ) {
    if ( _src === undefined || _src === null || NoNull(_src).length <= 10 ) { return; }

    var els = document.getElementsByClassName('photowrap');
    for ( let e = 0; e < els.length; e++ ) {
        els[e].innerHTML = '';

        var _obj = buildElement({ 'tag': 'img',
                                  'classes': ['game-image'],
                                  'attribs': [{'key':'src','value':NoNull(_src)},
                                              {'key':'alt','value':''}]
                                 });
        els[e].appendChild(_obj);
    }
}

/** ************************************************************************* *
 *  Handler Functions
 ** ************************************************************************* */
function handleButtonAction(el) {
    if ( el === undefined || el === null || el === false ) { return; }
    if ( el.currentTarget !== undefined && el.currentTarget !== null ) { el = el.currentTarget; }
    if ( NoNull(el.tagName).toLowerCase() !== 'button' ) { return; }
    if ( splitSecondCheck(el) ) {
        var _action = NoNull(el.getAttribute('data-action')).toLowerCase();

        switch ( _action ) {
            case 'submit':
                checkMapPoint();
                break;

            default:
                console.log("Not sure how to handle: " + _action);
        }
    }
}

/** ************************************************************************* *
 *  Game Functions
 ** ************************************************************************* */
function watchGameTimer() {
    if ( xStart < 1000 ) { xStart = Math.floor(Date.now() / 1000); }
    if ( xComplete === false ) {
        var _secs = getSecondsSinceStart();
        if ( _secs != xSecs ) {
            xSecs = _secs;

            var els = document.getElementsByClassName('timer');
            for ( let e = 0; e < els.length; e++ ) {
                setElementValue(els[e], secondsToHHMMSS(_secs));
            }
        }

        setTimeout(function () { watchGameTimer(); }, 150);
    }
}

function watchInputs() {
    if ( xComplete === false ) {
        var sOK = validateData();
        disableButtons('btn-submit', !sOK);

        setTimeout(function () { watchInputs(); }, 250);
    }
}

/** ************************************************************************* *
 *  Data Functions
 ** ************************************************************************* */
function getSecondsSinceStart() {
    if ( xStart < 1000 ) { xStart = Math.floor(Date.now() / 1000); }
    var _now = Math.floor(Date.now() / 1000);
    var _ss = _now - xStart;

    /* Ensure we are using a consistent number */
    if ( xComplete ) { _ss = xSecs; }
    if ( isNaN(_ss) || _ss < 0 ) { _ss = 0; }

    return _ss;
}
function getHintsCount() {
    var els = document.getElementsByClassName('hint-count');
    var cnt = 3;

    for ( let e = 0; e < els.length; e++ ) {
        var _remain = parseInt(NoNull(els[e].getAttribute('data-remain')));
        if ( isNaN(_remain) || _remain < 0 ) { _remain = 0; }
        return _remain - cnt;
    }
    return 0;
}

function validateData() {
    var els = document.getElementsByName('fdata');
    var cnt = 0;

    for ( let e = 0; e < els.length; e++ ) {
        var _req = NoNull(els[e].getAttribute('data-required')).toUpperCase();
        if ( _req != 'Y' ) { if ( els[e].classList.contains('required') ) { _req = 'Y'; } }

        if ( _req == 'Y' ) {
            var _min = parseInt(NoNull(els[e].getAttribute('data-min')));
            var _val = getElementValue(els[e]);

            if ( isNaN(_min) || _min <= 0 ) { _min = 1; }
            if ( _val.length < _min ) { cnt++; }
        }
    }

    return ((cnt <= 0) ? true : false);
}

function checkMapPoint() {
    if ( validateData() ) {
        var _params = { 'visitor_id': getMetaValue('visitor_id'),
                        'seconds': getSecondsSinceStart(),
                        'hints': getHintsCount()
                       };
        var els = document.getElementsByName('fdata');
        for ( let e = 0; e < els.length; e++ ) {
            var _name = NoNull(els[e].getAttribute('data-name')).toLowerCase();
            if ( _name.length > 0 ) { _params[_name] = getElementValue(els[e]); }
        }

        console.log(_params);

        /* Send the data to the API */
        setTimeout(function () { doJSONQuery('location/check', 'GET', _params, parseMapPointCheck); }, 75);
        spinButtons('btn-submit');
    }
}
function parseMapPointCheck(data) {
    spinButtons('btn-submit', true);

    if ( data.meta !== undefined && data.meta.code == 200 ) {
        /* Set the Results */


        /* Ensure the DOM is properly updated */
        removeByClass('validate');
        showByClass('result');
    }
}

function degreesToRadians(degrees) {
    return degrees * Math.PI / 180;
}

function distanceInKmBetweenEarthCoordinates(lat1, lon1, lat2, lon2) {
    var earthRadiusKm = 6371;

    var dLat = degreesToRadians(lat2-lat1);
    var dLon = degreesToRadians(lon2-lon1);

    lat1 = degreesToRadians(lat1);
    lat2 = degreesToRadians(lat2);

    var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.sin(dLon/2) * Math.sin(dLon/2) * Math.cos(lat1) * Math.cos(lat2);
    var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));

    return earthRadiusKm * c;
}

/** ************************************************************************* *
 *  Google Maps Functions
 ** ************************************************************************* */
let map;
let marker;

function initMap() {
    const initialPosition = { lat: 38.5, lng: 138.5 };
    map = new google.maps.Map(document.getElementById("map"), {
        zoom: 6,
        center: initialPosition,
    });

    marker = new google.maps.Marker({
        map: map,
        disableDefaultUI: true,
        draggable: true,
        mapTypeId: google.maps.MapTypeId.ROADMAP,
        streetViewControl: false,
        zoomControl: true
    });

    map.addListener("click", (mapsMouseEvent) => {
        const coords = mapsMouseEvent.latLng;
        marker.setPosition(coords);

        var _pos = marker.getPosition();
        if ( _pos ) { updateMarkerPosition(_pos.lat(), _pos.lng()); }
    });
    google.maps.event.addListener(marker, 'dragend', function() {
        var _pos = marker.getPosition();
        if ( _pos ) { updateMarkerPosition(_pos.lat(), _pos.lng()); }
    });
}

function updateMarkerPosition(_latitude, _longitude) {
    if ( _longitude === undefined || _longitude === null || _longitude === false ) { return; }
    if ( _latitude === undefined || _latitude === null || _latitude === false ) { return; }
    if ( typeof _longitude === 'object' || typeof _latitude === 'object' ) { return; }

    var els = document.getElementsByClassName('latlong');
    for ( let e = 0; e < els.length; e++ ) {
        var _key = NoNull(els[e].getAttribute('data-name')).toLowerCase();
        switch ( _key ) {
            case 'longitude':
                setElementValue(els[e], _longitude);
                break;

            case 'latitude':
                setElementValue(els[e], _latitude);
                break;

            case 'summary':
                setElementValue(els[e], numberWithDecimals(_latitude, 6) + ', ' + numberWithDecimals(_longitude, 6));
                break;

            default:
                /* Do Nothing */
        }
    }
}
