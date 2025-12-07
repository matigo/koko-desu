String.prototype.replaceAll = function(str1, str2, ignore) {
   return this.replace(new RegExp(str1.replace(/([\,\!\\\^\$\{\}\[\]\(\)\.\*\+\?\|\<\>\-\&])/g, function(c){return "\\" + c;}), "g"+(ignore?"i":"")), str2);
};
String.prototype.hashCode = function() {
  var hash = 0, i, chr, len;
  if (this.length === 0) return hash;
  for (i = 0, len = this.length; i < len; i++) {
    chr   = this.charCodeAt(i);
    hash  = ((hash << 5) - hash) + chr;
    hash |= 0; // Convert to 32bit integer
  }
  return hash;
};
function NoNull( txt, alt ) {
    if ( alt === undefined || alt === null || alt === false ) { alt = ''; }
    if ( txt === undefined || txt === null || txt === false || txt == '' ) { txt = alt; }
    if ( txt == '' ) { return ''; }

    return txt.toString().replace(/^\s+|\s+$/gm, '');
}
function nullInt( val, alt ) {
    if ( alt === undefined || alt === false || alt === null || NoNull(alt) == '' ) { alt = 0; }
    if ( val === undefined || val === false || val === null || NoNull(val) == '' ) { val = alt; }
    if ( isNaN(val) && val.indexOf(',') >= 0 ) { val = val.replaceAll(',', ''); }
    var oVal = parseFloat(val);
    if ( isNaN(oVal) ) { return 0; }
    return oVal;
}
function numberWithCommas(x) {
    if ( x === undefined || x === false || x === null ) { return ''; }
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}
function numberWithDecimals(value, precision = 0) {
    if ( isNaN(precision) || precision < 0 ) { precision = 0; }
    var rounded = Math.pow(10, precision || 0);
    return (Math.round(value * rounded) / rounded).toFixed(precision);
}
function hasNumber(txt) { return /\d/.test(txt); }
function easyFileSize(bytes) {
    if ( isNaN(bytes) || bytes <= 0 ) { return 0; }
    var i = Math.floor( Math.log(bytes) / Math.log(1024) );
    return ( bytes / Math.pow(1024, i) ).toFixed(2) * 1 + ' ' + ['B', 'KB', 'MB', 'GB', 'TB'][i];
}
function validateDate( _value ) {
    var _format = /^\d{4}[\/\-](0?[1-9]|[12][0-9]|3[01])[\/\-](0?[1-9]|1[012])$/;
    if ( NoNull(_value).length > 10 ) { _value = NoNull(_value).substring(0, 10); }

    if(_value.match(_format)) {
        var opera1 = _value.split('/');
        var opera2 = _value.split('-');
        lopera1 = opera1.length;
        lopera2 = opera2.length;

        /* Extract the string into year, month, and day */
        if ( lopera1 > 1 ) {
            var pdate = _value.split('/');
        } else if ( lopera2 > 1 ) {
            var pdate = _value.split('-');
        }
        var yy = parseInt(pdate[0]);
        var mm  = parseInt(pdate[1]);
        var dd = parseInt(pdate[2]);

        /* Create list of days for a month (February is a placeholder) */
        var ListofDays = [31,28,31,30,31,30,31,31,30,31,30,31];
        if ( mm == 1 || mm > 2 ) {
            if (dd > ListofDays[mm - 1] ) { return false; }
        }

        /* Handle February */
        if ( mm == 2 ) {
            var _leap = false;
            if ( ( !(yy % 4) && yy % 100) || !(yy % 400) ) { _leap = true; }
            if ( (_leap === false) && (dd >= 29) ) { return false; }
            if ( (_leap === true) && (dd > 29) ) { return false; }
        }

        /* If we're here, we can assume it's good. Return a complete YYYY-MM-DD rendering */
        return yy.toString() + '-' + mm.toString() + '-' + dd.toString();

    } else {
        return false;
    }
}
function getPageLanguage() {
    var els = document.getElementsByTagName('html');
    if ( els.length !== undefined && els.length !== null && els.length > 0 ) {
        for ( let e = 0; e < els.length; e++ ) {
            var _val = els[e].getAttribute('lang');
            if ( _val.length >= 2 ) { return _val; }
        }
    }
    return NoNull(window.strings['lang.culture'], 'en-US');
}
function dateToLocalString( timestamp ) {
    var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    if ( timestamp === undefined || timestamp === null || timestamp === false || isNaN(timestamp) ) { return ''; }
    var date = new Date(timestamp * 1000);
    return date.toLocaleDateString(getPageLanguage(), options);
}
function dateToYYYYMMDD( timestamp ) {
    if ( timestamp === undefined || timestamp === null || timestamp === false || isNaN(timestamp) ) { return ''; }
    var date = new Date(timestamp * 1000);
    var yyyy = date.getFullYear(),
        mm = ('0' + (date.getMonth() + 1)).slice(-2),
        dd = ('0' + date.getDate()).slice(-2);

    return yyyy + '-' + mm + '-' + dd;
}
function dateToHHMMSS( timestamp ) {
    if ( timestamp === undefined || timestamp === null || timestamp === false || isNaN(timestamp) ) { return ''; }
    var date = new Date(timestamp * 1000);
    var hours = date.getHours();
    var minutes = "0" + date.getMinutes();
    var seconds = "0" + date.getSeconds();

    return hours + ':' + minutes.substr(-2) + ':' + seconds.substr(-2);
}
function dateToHHMM( timestamp ) {
    if ( timestamp === undefined || timestamp === null || timestamp === false || isNaN(timestamp) ) { return ''; }
    var date = new Date(timestamp * 1000);
    var hours = "0" + date.getHours();
    var minutes = "0" + date.getMinutes();

    return hours.substr(-2) + ':' + minutes.substr(-2);
}
function hmsToSeconds(str) {
    var p = str.split(':'),
        s = 0, m = 1;

    /* Ensure there are enough segments */
    while (p.length < 3) {
        p.push(0);
    }

    /* If it's valid, let's build something */
    if ( hasNumber(str) ) {
        while (p.length > 0) {
            var ii = nullInt(p.pop());
            s += m * parseInt(ii, 10);
            m *= 60;
        }
    }

    return s;
}
function secondsToHHMMSS( secs ) {
    var sec_num = parseInt(secs, 10);
    var hours   = Math.floor(sec_num / 3600);
    var minutes = Math.floor(sec_num / 60) % 60;
    var seconds = sec_num % 60;

    if ( isNaN(hours) ) { hours = 0; }
    if ( isNaN(minutes) ) { minutes = 0; }
    if ( isNaN(seconds) ) { seconds = 0; }

    return ((sec_num > 3600) ? ('00' + hours).substr(-2) + ':' : '') + ('00' + minutes).substr(-2) + ':' + ('00' + seconds).substr(-2);
}
function getRelativeTime( timestamp ) {
    if ( timestamp === undefined || timestamp === null || timestamp === false || isNaN(timestamp) ) { return ''; }
    var secs = timestamp - Math.floor(Date.now() / 1000);
    return secondsToRelativeTime(secs);
}
function secondsToRelativeTime( secs ) {
    var locales = [ 'en', 'ja'];
    var options = { localeMatcher: 'lookup',
                    numeric: ((secs == 0) ? 'auto' : 'always'),
                    style: 'long',
                   };
    var rtf = new Intl.RelativeTimeFormat(locales, options);

    /* Ensure there is a time offset */
    var offset = (9 * 60 * 60);
    secs += offset;
    if ( secs == 0 ) { secs = -1; }

    /* Return the Most Appropriate String */
    if ( secs > -60 && secs < 60 ) { return rtf.format(Math.round(secs), "second"); }
    secs = secs / 60;
    if ( secs > -60 && secs < 60 ) { return rtf.format(Math.round(secs), "minute"); }
    secs = secs / 60;
    if ( secs > -60 && secs < 60 ) { return rtf.format(Math.round(secs), "hour"); }
    secs = secs / 24;
    return rtf.format(Math.round(secs), "day");
}
function strip_tags(html, allowed_tags) {
    if ( allowed_tags === undefined || allowed_tags === false || allowed_tags === null ) { allowed_tags = ''; }
    if ( html === undefined || html === false || html === null || html.length <= 0 ) { return ''; }
    allowed_tags = allowed_tags.trim()
    if (allowed_tags) {
        allowed_tags = allowed_tags.split(/\s+/).map(function(tag){ return "/?" + tag });
        allowed_tags = "(?!" + allowed_tags.join("|") + ")";
    }
    return html.replace(new RegExp("(<" + allowed_tags + ".*?>)", "gi"), "");
}
function checkIfOverflow( el ) {
    if ( el === undefined || el === false || el === null ) { return false; }

    var curOverflow = el.style.overflow;
    if ( !curOverflow || curOverflow === "visible" ) { el.style.overflow = "hidden"; }
    var isOverflowing = el.clientWidth < el.scrollWidth || el.clientHeight < el.scrollHeight;
    el.style.overflow = curOverflow;

    return isOverflowing;
}
function randName(len) {
    if ( len === undefined || len === false || len === null ) { len = 8; }
    if ( parseInt(len) <= 0 ) { len = 8; }
    if ( parseInt(len) > 64 ) { len = 64; }

    var txt = '';
    var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";

    for ( var i = 0; i < len; i++ ) {
        txt += possible.charAt(Math.floor(Math.random() * possible.length));
    }
    return txt;
}
function splitSecondCheck(el) {
    if ( el === undefined || el === false || el === null ) { return; }
    var touch_ts = nullInt(Math.floor(Date.now()));
    var last_ts = nullInt(el.getAttribute('data-lasttouch'));

    if ( (touch_ts - last_ts) <= 333 ) { return false; }
    el.setAttribute('data-lasttouch', touch_ts);
    return true;
}
function shuffleArray(array) {
    for (var i = array.length - 1; i > 0; i--) {
        var j = Math.floor(Math.random() * (i + 1));
        var temp = array[i];
        array[i] = array[j];
        array[j] = temp;
    }
    return array;
}
function getGuidFromUrl(id = 0) {
    var _segs = window.location.pathname.split("/");
    if ( _segs === undefined || _segs === null || _segs === false ) { return ''; }
    if ( _segs.length === undefined || _segs.length <= 0 ) { return ''; }
    if ( id === undefined || id === null || isNaN(id) ) { id = 0; }

    var _guids = [];

    for ( var i = 0; i < _segs.length; i++ ) {
        var _vv = NoNull(_segs[i]);
        if ( _vv.length == 36 ) { _guids.push(_vv); }
    }

    /* Return an appropriate Guid, or an emopty string */
    if ( _guids.length > 0 ) {
        if ( id >= 0 && _guids.length > id ) { return NoNull(_guids[id]); }
        if ( id == -1 ) { return NoNull(_guids[(_guids.length - 1)]); }
    }
    return '';
}
function getLastUrlSegment() {
    var _segs = window.location.pathname.split("/");
    if ( _segs === undefined || _segs === null || _segs === false ) { return ''; }
    if ( _segs.length === undefined || _segs.length <= 0 ) { return ''; }

    for ( var i = (_segs.length - 1); i >= 0; i-- ) {
        if ( NoNull(_segs[i]) != '' ) { return NoNull(_segs[i]); }
    }
    return '';
}
function setHeadMeta( _name, _value ) {
    if ( _name === undefined || _name === false || _name === null || _name.length <= 3 ) { return; }
    if ( _value === undefined || _value === false || _value === null ) { _value = ''; }

    var metas = document.getElementsByTagName('meta');
    for ( var i = 0; i < metas.length; i++ ) {
        var _attrib = NoNull(metas[i].getAttribute("name"));
        if ( _attrib == _name ) {
            if ( metas[i].getAttribute("content") != _value ) { metas[i].setAttribute('content', _value); }
        }
    }
}
function showByClass( _cls ) {
    if ( _cls === undefined || _cls === null || _cls === false || NoNull(_cls).length <= 0 ) { return; }
    var els = document.getElementsByClassName(_cls);
    for ( let e = 0; e < els.length; e++ ) {
        if ( els[e].classList.contains('hidden') ) { els[e].classList.remove('hidden'); }
    }
}
function hideByClass( _cls ) {
    if ( _cls === undefined || _cls === null || _cls === false || NoNull(_cls).length <= 0 ) { return; }
    var els = document.getElementsByClassName(_cls);
    for ( let e = 0; e < els.length; e++ ) {
        if ( els[e].classList.contains('hidden') === false ) { els[e].classList.add('hidden'); }
    }
}
function removeByClass( _cls ) {
    if ( _cls === undefined || _cls === null || _cls === false || NoNull(_cls).length <= 0 ) { return; }
    var els = document.getElementsByClassName(_cls);
    if ( els.length > 0 ) {
        for ( let e = els.length - 1; e >= 0; e-- ) {
            els[e].parentElement.removeChild(els[e]);
        }
    }
}
function toggleByClass( _cls ) {
    if ( _cls === undefined || _cls === null || _cls === false || NoNull(_cls).length <= 0 ) { return; }
    var els = document.getElementsByClassName(_cls);
    for ( let e = 0; e < els.length; e++ ) {
        if ( els[e].classList.contains('hidden') ) {
            els[e].classList.remove('hidden');
        } else {
            els[e].classList.add('hidden');
        }
    }
}
function chkNumberValue( el ) {
    if ( el === undefined || el === null || el === false ) { return; }
    if ( NoNull(el.value) != "" ) {
        var _val = nullInt(el.value.replace(/[^0-9]/g,'')),
            _min = nullInt(el.min),
            _max = nullInt(el.max);
        if ( _max <= _min ) { _max = _min + 1; }

        if ( _val < _min ) { _val = _min; }
        if ( _val > _max ) { _val = _max; }

        /* Set the Value */
        el.value = _val;
    }
}
function getElementValue( el ) {
    if ( el === undefined || el === null || el === false ) { return ''; }
    var _tagName = NoNull(el.tagName).toLowerCase();
    var _val = '';

    /* If we're working with an <input>, set the _tagName to the Input.type */
    if ( _tagName == 'input' ) {
        if ( el.type !== undefined && el.type !== null && NoNull(el.type).length > 0 ) {
            _tagName = NoNull(el.type).toLowerCase();
        }
    }

    /* Determine the value based on the _tagName */
    switch ( _tagName ) {
        case 'checkbox':
            if ( el.checked !== undefined && el.checked !== null && el.checked === true ) {
                _val = NoNull(el.getAttribute('data-value'), el.value);
            }
            break;

        case 'select':
            if ( el.selectedIndex !== undefined && el.selectedIndex >= 0 ) {
                _val = NoNull(el.options[el.selectedIndex].getAttribute('data-value'), el.value);
            } else {
                _val = NoNull(el.value);
            }
            break;

        case 'button':
            _val = NoNull(el.getAttribute('data-value'), el.value);
            break;

        case 'textarea':
            _val = el.value;
            break;

        case 'span':
        case 'pre':
            _val = el.innerHTML;
            break;

        default:
            _val = NoNull(el.value);
    }
    return _val;
}
function setElementValue( el, _value ) {
    if ( _value === undefined || _value === null || _value === false ) { _value = ''; }
    if ( el === undefined || el === null || el === false ) { return ''; }
    var _tagName = NoNull(el.tagName).toLowerCase();

    /* If we're working with an <input>, set the _tagName to the Input.type */
    if ( _tagName == 'input' ) {
        if ( el.type !== undefined && el.type !== null && NoNull(el.type).length > 0 ) {
            _tagName = NoNull(el.type).toLowerCase();
        }
    }

    /* Determine the value based on the _tagName */
    switch ( _tagName ) {
        case 'checkbox':
            var _vv = NoNull(el.getAttribute('data-value'), el.value);
            if ( NoNull(_value).length > 0 && _vv == _value ) {
                el.checked = true;
            } else {
                el.checked = false;
            }
            break;

        case 'button':
            el.innerHTML = NoNull(_value);
            el.value = NoNull(_value);
            break;

        case 'textarea':
            el.value = _value;
            break;

        case 'span':
        case 'div':
        case 'pre':
        case 'h1':
        case 'h2':
        case 'h3':
        case 'h4':
        case 'h5':
        case 'h6':
        case 'p':
            el.innerHTML = _value.toString().replaceAll('\\n', "\n");
            break;

        default:
            el.value = NoNull(_value);
    }
}
function toggleVisibility( _cls ) {
    if ( _cls === undefined || _cls === false || _cls === null ) { return; }
    var els = document.getElementsByClassName(_cls);
    for ( var i = 0; i < els.length; i++ ) {
        if ( els[i].classList.contains('hidden') ) {
            showByClass(_cls);
        } else {
            hideByClass(_cls);
        }
        return;
    }
}
function toggleElementVisibility( el ) {
    if ( el === undefined || el === false || el === null ) { return; }
    if ( el.tagName === undefined || el.tagName === null ) { return; }

    /* Ensure the Touch Time is Decent to Prevent Double-Actions */
    var last_touch = nullInt(el.getAttribute('data-lasttouch'));
    var touch_ts = Math.floor(Date.now());

    if ( (touch_ts - last_touch) <= 500 ) { return; }
    el.setAttribute('data-lasttouch', touch_ts);

    /* Toggle the Visibility */
    if ( el.classList.contains('hidden') ) {
        el.classList.remove('hidden');
    } else {
        el.classList.add('hidden');
    }
}
function toggleButton(el) {
    if ( el === undefined || el === null || el === false ) { return; }
    if ( el.tagName === undefined || el.tagName === null ) { el = el.currentTarget; }
    if ( el.tagName.toLowerCase() != 'button' ) { return; }
    if ( splitSecondCheck(el) === false ) { return; }

    if ( el.classList.contains('btn-primary') ) {
        el.classList.remove('btn-primary');
    } else {
        var els = el.parentElement.getElementsByTagName('BUTTON');
        for ( var e = 0; e < els.length; e++ ) {
            if ( els[e].classList.contains('btn-primary') ) {
                els[e].classList.remove('btn-primary');
            }
        }
        el.classList.add('btn-primary');
    }
}
function spinButtons(cls, reset = false) {
    if ( cls === undefined || cls === false || cls === null || cls == '') { return; }
    var btns = document.getElementsByClassName(cls);
    for ( var e = 0; e < btns.length; e++ ) {
        spinButton(btns[e], reset);
    }
}
function spinButton(btn, reset = false) {
    if ( btn === undefined || btn === false || btn === null ) { return; }
    if ( btn.tagName.toLowerCase() != 'button' ) { return; }
    var _txt = NoNull(btn.innerHTML);
    var _lbl = NoNull(btn.getAttribute('data-label'));
    if ( _lbl == '' ) {
        _lbl = NoNull(btn.innerHTML);
        if ( _lbl.indexOf('fa-spin') < 0 ) {
            btn.setAttribute('data-label', _lbl);
        }
    }

    /* Set the button "spinning", or return the original label text */
    if ( reset || _txt == '<i class="fas fa-spin fa-spinner"></i>' ) {
        btn.setAttribute('data-spinning', 'N');
        restoreButtonColor(btn);
        btn.innerHTML = _lbl;
        btn.disabled = false;

    } else {
        removeButtonColor(btn);
        btn.innerHTML = '<i class="fas fa-spin fa-spinner"></i>';
        btn.setAttribute('data-spinning', 'Y');
        btn.disabled = true;
    }
}
function disableButtons(cls, disable = true) {
    if ( disable === undefined || disable === null || disable !== false ) { disable = true; }
    if ( cls === undefined || cls === false || cls === null || cls == '') { return; }

    var btns = document.getElementsByClassName(cls);
    for ( let e = 0; e < btns.length; e++ ) {
        if ( NoNull(btns[e].getAttribute('data-spinning'), 'N') == 'N' ) {
            if ( disable ) {
                if ( btns[e].disabled === false ) { btns[e].disabled = true; }
            } else {
                if ( btns[e].disabled ) { btns[e].disabled = false; }
            }
        }
    }
}
function removeButtonColor(btn) {
    if ( btn === undefined || btn === null || btn === false ) { return; }
    var _classes = ['btn-primary', 'btn-selected', 'btn-warning', 'primary', 'selected', 'warning', 'yellow', 'orange', 'black', 'green', 'blue', 'red'];
    for ( let idx in _classes ) {
        if ( btn.classList.contains(_classes[idx]) ) {
            var _key = NoNull(_classes[idx]).replaceAll('btn-', '').toLowerCase();
            btn.setAttribute('data-' + _key, 'Y');
            btn.classList.remove(_classes[idx]);
        }
    }
}
function restoreButtonColor(btn) {
    if ( btn === undefined || btn === null || btn === false ) { return; }
    var _classes = ['btn-primary', 'btn-selected', 'btn-warning', 'primary', 'selected', 'warning', 'yellow', 'orange', 'black', 'green', 'blue', 'red'];
    for ( let idx in _classes ) {
        var _key = NoNull(_classes[idx]).replaceAll('btn-', '').toLowerCase();
        if ( NoNull(btn.getAttribute('data-' + _key)) == 'Y' ) {
            btn.setAttribute('data-' + _key, 'N');
            btn.classList.add('btn-' + _key);
            btn.classList.add(_key);
        }
    }
}

/** ************************************************************************* *
 *  Form and Field Generics
 ** ************************************************************************* */
function voidEnter(e){
    e = e || event;
    var txtArea = /textarea/i.test((e.target || e.srcElement).tagName);
    return txtArea || (e.keyCode || e.which || e.charCode || 0) !== 13;
}

/** ************************************************************************* *
 *  Browser Elements
 ** ************************************************************************* */
navigator.browserSpecs = (function(){
    var ua = navigator.userAgent, tem,
        M = ua.match(/(opera|chrome|safari|firefox|msie|trident(?=\/))\/?\s*(\d+)/i) || [];
    if(/trident/i.test(M[1])){
        tem = /\brv[ :]+(\d+)/g.exec(ua) || [];
        return {name:'IE',version:(tem[1] || '')};
    }
    if(M[1]=== 'Chrome'){
        tem = ua.match(/\b(OPR|Edge)\/(\d+)/);
        if(tem != null) return {name:tem[1].replace('OPR', 'Opera'),version:tem[2]};
    }
    M = M[2]? [M[1], M[2]]: [navigator.appName, navigator.appVersion, '-?'];
    if((tem = ua.match(/version\/(\d+)/i))!= null)
        M.splice(1, 1, tem[1]);
    return {name:M[0], version:M[1]};
})();

function isBrowserCompatible() {
    switch ( navigator.browserSpecs.name.toLowerCase() ) {
        case 'applewebkit':
        case 'safari':
            _ver = parseInt(navigator.browserSpecs.version);
            if ( _ver === undefined || _ver === false || _ver === null || isNaN(_ver) ) { _ver = 0; }
            if ( _ver > 0 && _ver < 10 ) { return false; }
            break;
    }

    return true;
}

function debounce(func){
    var timer;
    return function ( event ) {
        if ( timer ) { clearTimeout(timer); }
        timer = setTimeout(func, 100, event);
    };
}

/** ************************************************************************* *
 *  Local Storage
 ** ************************************************************************* */
function saveStorage( key, value, useStore ) {
    if ( value === undefined || value === null ) { return false; }
    if ( !key ) { return false; }
    if ( hasStorage()  && !useStore) { localStorage.setItem( key, value ); } else { window.store[key] = value; }
}
function readStorage( key, useStore ) {
    if ( !key ) { return false; }
    if ( hasStorage() && !useStore ) {
        return localStorage.getItem(key) || false;
    } else {
        if ( window.store.hasOwnProperty(key) ) { return window.store[key]; } else { return false; }
    }
}
function deleteStorage( key ) {
    if ( !key ) { return false; }
    if ( hasStorage() ) { localStorage.removeItem(key); } else { window.store[key] = false; }
}
function hasStorage() {
    try {
        return 'localStorage' in window && window['localStorage'] !== null;
    } catch (e) {
        return false;
    }
}

/** ************************************************************************* *
 *  Preferences
 ** ************************************************************************* */
function togglePreference(btn) {
    if ( btn === undefined || btn === false || btn === null ) { return; }
    if ( btn.classList.contains('btn-primary') ) { return; }
    var _key = NoNull(btn.getAttribute('data-class')).toLowerCase();
    var _val = NoNull(btn.getAttribute('data-value')).toLowerCase();

    /* Ensure Other Buttons in the Set are Reset */
    var btns = btn.parentElement.getElementsByTagName('BUTTON');
    for ( var i = 0; i < btns.length; i++ ) {
        if ( btns[i].classList.contains('btn-primary') ) { btns[i].classList.remove('btn-primary'); }
    }

    switch ( _key ) {
        case 'fontfamily':
            applyFontFamily(_val);
            break;

        case 'fontsize':
            applyFontSize(_val);
            break;

        case 'theme':
            setColorScheme(_val);
            break;

        default:
            /* Do Nothing */
    }

    /* Record the Update to the API */
    var params = { 'key': _key,
                   'value': _val
                  };
    doJSONQuery('account/preference', 'POST', params, parsePreference);

    /* Ensure the Button is Properly Highlighted */
    btn.classList.add('btn-primary');
}
function parsePreference(data) {
    if ( data.meta !== undefined && data.meta.code == 200 ) {
        var ds = data.data;
        console.log(ds);
    } else {
        setResponseMessage("Could not save preference on the service.");
    }
}
function applyAvatar( _val ) {
    var els = document.getElementsByClassName('btn-avatar');
    for ( var e = 0; e < els.length; e++ ) {
        if ( els[e].classList.contains('selected') ) { els[e].classList.remove('selected'); }

        var vv = NoNull(els[e].getAttribute('data-value'));
        if ( vv == _val ) {
            els[e].classList.add('selected');
            var _url = NoNull(els[e].getAttribute('data-url'));
            if ( _url.length > 10 ) {
                var aes = document.getElementsByClassName('avatar');
                for ( var a = 0; a < aes.length; a++ ) {
                    aes[a].style.backgroundImage = 'url(' + _url + ')';
                }
            }
        }
    }
}
function setColorScheme( _scheme = 'light' ) {
    if ( _scheme === undefined || _scheme === null || NoNull(_scheme).length < 4 ) { _scheme = ''; }
    if ( document.body === undefined || document.body === null ) { return; }
    _scheme = NoNull(_scheme).toLowerCase();

    /* Ensure the theme is valid */
    var _valids = ['light', 'dark', 'solar'];
    if ( _valids.indexOf(_scheme) < 0 ) { _scheme = 'light'; }

    /* Clear out any color schemes that may be set */
    for ( let idx in _valids ) {
        if ( document.body.classList.contains(_valids[idx]) ) { document.body.classList.remove(_valids[idx]); }
    }

    /* Set the preferred scheme */
    document.body.classList.add(_scheme);
}
function applyShowLabels( _val ) {
    if ( document.body === undefined || document.body === null ) { return; }
    var _valids = ['auto', 'n'];
    _val = NoNull(_val, 'N').toLowerCase();

    switch ( _val ) {
        case 'n':
            if ( document.body.classList.contains('nolabels') === false ) { document.body.classList.add('nolabels'); }
            break;

        default:
            if ( document.body.classList.contains('nolabels') ) { document.body.classList.remove('nolabels'); }
    }
    if ( _valids.indexOf(_val) >= 0 ) { saveStorage('showlabels', _val); }
}
function applyFontFamily( _val ) {
    if ( document.body === undefined || document.body === null ) { return; }
    var _valids = ['default', 'sans', 'serif', 'mono'];
    _val = NoNull(_val, 'default').toLowerCase();

    if ( _valids.indexOf(_val) >= 0 ) {
        for ( var i = 0; i < _valids.length; i++ ) {
            _cls = 'font-' + _valids[i];
            if ( document.body.classList.contains(_cls) ) { document.body.classList.remove(_cls); }
        }
        document.body.classList.add('font-' + _val);
        saveStorage('fontfamily', _val);
    }
}
function applyFontSize( _val ) {
    if ( document.body === undefined || document.body === null ) { return; }
    var _valids = ['xs', 'sm', 'md', 'lg', 'xl'];
    if ( _valids.indexOf(_val) >= 0 ) {
        for ( var i = 0; i < _valids.length; i++ ) {
            _cls = 'size-' + _valids[i];
            if ( document.body.classList.contains(_cls) ) { document.body.classList.remove(_cls); }
        }
        document.body.classList.add('size-' + _val);
        saveStorage('fontsize', _val);
    }
}

/** ************************************************************************* *
 *  System Messages
 ** ************************************************************************* */
function showNetworkOffline() {
    var els = document.getElementsByTagName('BODY');
    for ( let e = 0; e < els.length; e++ ) {
        var _notice = buildElement({ 'tag': 'div', 'classes': ['network-message'] });
            _notice.appendChild(buildElement({ 'tag': 'h3', 'text': NoNull(window.strings['ttl.network-offline'], 'Offline') }));
            _notice.appendChild(buildElement({ 'tag': 'p', 'text': NoNull(window.strings['msg.network-offline'], 'Your computer is not connected to the internet') }));
        els[e].appendChild(_notice);
    }
}
function hideNetworkOffline() {
    removeByClass('network-message');
}

/** ************************************************************************* *
 *  Copy/Pasta
 ** ************************************************************************* */
function copyInnerHTMLToClipboard( obj ) {
    if ( obj === undefined || obj === false || obj === null ) { return; }
    if ( obj.tagName == 'INPUT' ) {
        selectAllInputText(obj);
    } else {
        var txt = obj.innerHTML;
        if ( txt === undefined || txt === false || txt === null || txt.trim() == '' ) { txt = ''; }
        txt = txt.replaceAll('&lt;', '<').replaceAll('&gt;', '>').trim();
        if ( txt != '' ) {
            var el = document.getElementById('clippy');
            el.focus();
            el.value = txt;
            el.focus();
            el.setSelectionRange(0, 9999);
            el.focus();
            el.select();
            var isGood = document.execCommand("copy");
            if ( isGood ) {
                $(obj).notify("Copied!", { position: "top", className: "success", autoHide: true, autoHideDelay: 5000 });
            } else {
                toggleElementType(obj);
            }
        }
    }
}
function toggleElementType( obj ) {
    if ( obj === undefined || obj === false || obj === null ) { return; }
    var txt = obj.innerHTML;
    if ( txt === undefined || txt === false || txt === null || txt.trim() == '' ) { txt = ''; }
    txt = txt.trim();

    var inpt = document.createElement('textarea');
    inpt.classList.add('copypasta');
    inpt.value = txt.replaceAll('&lt;', '<').replaceAll('&gt;', '>').trim();
    obj.parentNode.replaceChild(inpt, obj);

    inpt.focus();
    inpt.setSelectionRange(0, 9999);
    setTimeout(function() { inpt.setSelectionRange(0, 9999); }, 5);
}
function selectAllInputText( el ) {
    if ( el === undefined || el === false || el === null ) { return; }
    el.focus();
    el.setSelectionRange(0, 9999);
    setTimeout(function() { el.setSelectionRange(0, 9999); }, 5);
}

/** ************************************************************************* *
 *  Class Watcher
 ** ************************************************************************* */
class ClassWatcher {
    constructor(targetNode, classChangeCallback) {
        this.targetNode = targetNode;
        this.classChangeCallback = classChangeCallback;
        this.lastClassList = targetNode.classList;
        this.observer = null;

        this.init();
    }

    init() {
        this.observer = new MutationObserver(this.mutationCallback);
        this.observe();
    }

    observe() {
        this.observer.observe(this.targetNode, { attributes: true });
    }

    disconnect() {
        this.observer.disconnect();
    }

    mutationCallback = mutationsList => {
        for(let mutation of mutationsList) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                let currentClassList = NoNull(mutation.target.classList.value);
                if(this.lastClassList !== currentClassList) {
                    this.lastClassList = currentClassList;
                    this.classChangeCallback();
                }
            }
        }
    }
}