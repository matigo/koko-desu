/** ************************************************************************* *
 *  Startup
 ** ************************************************************************* */
window.KEY_DOWNARROW = 40;
window.KEY_ESCAPE = 27;
window.KEY_ENTER = 13;

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
            case 'feature':
            case 'today':
                showTodayChallenge();
                break;

            case 'random':
                showRandomChallenge();
                break;

            default:
                console.log("Not sure how to handle: " + _action);
        }
    }
}

/** ************************************************************************* *
 *  Search / Filter Functions
 ** ************************************************************************* */
function showTodayChallenge() {
    redirectTo('/today');
}

function showRandomChallenge() {
    var _key = 'abc123';

    redirectTo('/' + _key);
}
