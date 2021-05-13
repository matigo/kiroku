/** ************************************************************************* *
 *  Startup
 ** ************************************************************************* */
jQuery(function($) {
    window.KEY_DOWNARROW = 40;
    window.KEY_ESCAPE = 27;
    window.KEY_ENTER = 13;

    window.addEventListener('offline', function(e) { showNetworkStatus(); });
    window.addEventListener('online', function(e) { showNetworkStatus(); });

    $('.side-nav').click(function() { toggleNavOption(this); });
});
document.onreadystatechange = function () {
    if (document.readyState == "interactive") {
        if ( isBrowserCompatible() ) {
            /* Add the various Event Listeners that make the site come alive */
            document.addEventListener('keydown', function(e) { handleDocumentKeyPress(e); });
            document.addEventListener('click', function(e) { handleDocumentClick(e); });

            let classWatcher = new ClassWatcher(document.body, handleBodyClassChange);

            var els = document.getElementsByTagName('LI');
            for ( var i = 0; i < els.length; i++ ) {
                els[i].addEventListener('touchend', function(e) {
                    e.preventDefault();
                    handleNavListAction(e);
                });
                els[i].addEventListener('click', function(e) {
                    e.preventDefault();
                    handleNavListAction(e);
                });
            }

            var els = document.getElementsByClassName('navmenu-popover');
            for ( var i = 0; i < els.length; i++ ) {
                els[i].addEventListener('touchend', function(e) {
                    e.preventDefault();
                    handlePopover(e);
                });
                els[i].addEventListener('click', function(e) {
                    e.preventDefault();
                    handlePopover(e);
                });
            }

            var els = document.getElementsByClassName('btn-pronoun');
            for ( var i = 0; i < els.length; i++ ) {
                els[i].addEventListener('touchend', function(e) { setPronoun(e); });
                els[i].addEventListener('click', function(e) { setPronoun(e); });
            }
            var els = document.getElementsByClassName('btn-displang');
            for ( var i = 0; i < els.length; i++ ) {
                els[i].addEventListener('touchend', function(e) { setDisplayLang(e); });
                els[i].addEventListener('click', function(e) { setDisplayLang(e); });
            }

            /* Populate the Screen */
            checkTimezone();
            getAvatarList();
            prepButtons();

        } else {
            var els = document.getElementsByClassName('compat-msg');
            for ( var i = 0; i < els.length; i++ ) {
                var _msg = NoNull(els[i].getAttribute('data-msg'));
                if ( _msg === undefined || _msg === false || _msg === null ) { _msg = ''; }

                els[i].innerHTML = _msg.replaceAll('{browser}', navigator.browserSpecs.name).replaceAll('{version}', navigator.browserSpecs.version);
            }
            hideByClass('form-content');
            showByClass('compat');
        }

        /* Dark Mode Handling */
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            toggleDarkMode(true);
        }
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if ( e.matches ) { toggleDarkMode(true) } else { toggleDarkMode(false); }
        });
    }
}
function handleBodyClassChange() {
    console.log("The body class has changed ...");
}
function handleDocumentKeyPress(e) {
    enableSave();
}

/** ************************************************************************* *
 *  UI Interaction Functions
 ** ************************************************************************* */
function enableSave() {
    var els = document.getElementsByClassName('btn-account-save');
    for ( var e = 0; e < els.length; e++ ) {
        if ( els[e].classList.contains('btn-primary') === false ) { els[e].classList.add('btn-primary'); }
        els[e].disabled = false;
    }
}
function disableSave() {
    var els = document.getElementsByClassName('btn-account-save');
    for ( var e = 0; e < els.length; e++ ) {
        if ( els[e].classList.contains('btn-primary') ) { els[e].classList.remove('btn-primary'); }
        els[e].disabled = true;
    }
}
function prepButtons() {
    var items = ['pronoun', 'displang', 'showlabels', 'fontfamily', 'fontsize', 'theme'];

    for ( idx in items ) {
        var els = document.getElementsByClassName('btn-' + items[idx]);
        var _vv = NoNull(window.settings[items[idx]]);

        for ( var e = 0; e < els.length; e++ ) {
            if ( els[e].classList.contains('btn-primary') ) { els[e].classList.remove('btn-primary'); }
            var _val = NoNull(els[e].getAttribute('data-value'));
            if ( _val == _vv ) { els[e].classList.add('btn-primary'); }
        }
    }
}

/** ************************************************************************* *
 *  Account-Level Functions
 ** ************************************************************************* */
function setAccountData() {
    var params = { 'language_code': getSelectedDisplayLanguage() };

    /* Note that there are no required text input fields */
    var els = document.getElementsByName('pdata');
    for ( var e = 0; e < els.length; e++ ) {
        var _name = NoNull(els[e].getAttribute('data-name'));
        params[_name] = getElementValue(els[e]);
    }
    setTimeout(function () { doJSONQuery('account/set', 'POST', params, parsePreference); }, 150);
    disableSave();
}

/** ************************************************************************* *
 *  Display Language Functions
 ** ************************************************************************* */
function getSelectedDisplayLanguage() {
    var els = document.getElementsByClassName('btn-displang');
    for ( var e = 0; e < els.length; e++ ) {
        if ( els[e].classList.contains('btn-primary') ) {
            var _val = NoNull(els[e].getAttribute('data-value'));
            if ( _val != '' ) { return _val; }
        }
    }
    return '';
}

function setDisplayLang(el) {
    if ( el === undefined || el === false || el === null ) { return; }
    if ( el.target !== undefined ) { el = el.target; }
    if ( el.classList !== undefined && el.classList.contains('btn-primary') ) { return; }
    if ( splitSecondCheck(el) === false ) { return; }
    var _val = NoNull(el.getAttribute('data-value'));

    var els = document.getElementsByClassName('btn-displang');
    for ( var e = 0; e < els.length; e++ ) {
        if ( els[e].classList.contains('btn-primary') ) { els[e].classList.remove('btn-primary'); }
    }

    /* Ensure the button is properly lit */
    el.classList.add('btn-primary');

    /* Save the data */
    setAccountData();

    /* Reload the Page after a short delay */
    setTimeout(function () { location.reload(); }, 500);
}

/** ************************************************************************* *
 *  Pronoun Functions
 ** ************************************************************************* */
function setPronoun(el) {
    if ( el === undefined || el === false || el === null ) { return; }
    if ( el.target !== undefined ) { el = el.target; }
    if ( el.classList !== undefined && el.classList.contains('btn-primary') ) { return; }
    if ( splitSecondCheck(el) === false ) { return; }
    var _val = NoNull(el.getAttribute('data-value'));

    var els = document.getElementsByClassName('btn-pronoun');
    for ( var e = 0; e < els.length; e++ ) {
        if ( els[e].classList.contains('btn-primary') ) { els[e].classList.remove('btn-primary'); }
    }

    /* Ensure the button is properly lit */
    el.classList.add('btn-primary');

    /* Update the Account Profile */
    var params = { 'key': 'pronoun',
                   'value': NoNull(_val, 'T')
                  };
    setTimeout(function () { doJSONQuery('account/profile', 'POST', params, parsePreference); }, 150);
    enableSave();
}

/** ************************************************************************* *
 *  Avatar Functions
 ** ************************************************************************* */
function getAvatarList() {
    var els = document.getElementsByClassName('avatar-list');
    if ( els.length > 0 ) {
        setTimeout(function () { doJSONQuery('account/avatars', 'GET', {}, parseAvatarList); }, 150);
        for ( var e = 0; e < els.length; e++ ) {
            els[e].innerHTML = '<p class="api-message"><i class="fas fa-spin fa-spinner"></i> Reading Items ...</p>';
        }
    }
}
function parseAvatarList( data ) {
    if ( data.meta !== undefined && data.meta.code == 200 ) {
        var ds = data.data;
        if ( ds.length !== undefined && ds.length > 0 ) {
            var els = document.getElementsByClassName('avatar-list');
            for ( var e = 0; e < els.length; e++ ) {
                els[e].innerHTML = '';
            }

            for ( var i = 0; i < ds.length; i++ ) {
                var el = document.createElement("span");
                    el.classList.add('avatar-item');
                    el.setAttribute('data-value', NoNull(ds[i].name));
                    el.style.backgroundImage = 'url(' + NoNull(ds[i].url) + ')';
                    el.innerHTML = '&nbsp;';

                if ( ds[i].selected ) { el.classList.add('selected'); }

                el.addEventListener('touchend', function(e) { setAvatar(e.target); });
                el.addEventListener('click', function(e) { setAvatar(e.target); });

                /* Add the Element to the List */
                for ( var e = 0; e < els.length; e++ ) {
                    els[e].appendChild(el);
                }
            }
        }
    }
}
function setAvatar( el ) {
    if ( el === undefined || el === false || el === null ) { return; }
    if ( el.classList !== undefined && el.classList.contains('selected') ) { return; }
    if ( splitSecondCheck(el) === false ) { return; }
    var _val = NoNull(el.getAttribute('data-value'));

    /* Remove any selected borders that might be in place */
    var els = document.getElementsByClassName('avatar-item');
    for ( var e = 0; e < els.length; e++ ) {
        if ( els[e].classList.contains('selected') ) { els[e].classList.remove('selected'); }
    }

    /* Ensure the selected Item is marked accordingly */
    el.classList.add('selected');

    /* Set the Top Avatar image to the same */
    var els = document.getElementsByClassName('avatar');
    for ( var e = 0; e < els.length; e++ ) {
        els[e].style.backgroundImage = el.style.backgroundImage;
    }

    /* Update the Account Profile */
    var params = { 'key': 'avatar',
                   'value': NoNull(_val, 'default.png')
                  };
    setTimeout(function () { doJSONQuery('account/profile', 'POST', params, parsePreference); }, 150);
    enableSave();
}

/** ************************************************************************* *
 *  Timezone Functions
 ** ************************************************************************* */
function getSelectedTimezone() {
    var els = document.getElementsByClassName('tz-select');
    for ( var e = 0; e < els.length; e++ ) {
        var _val = NoNull(els[e].options[els[e].selectedIndex].getAttribute('data-value'));
        if ( _val != '' ) { return _val; }
    }
    return '';
}
function checkTimezone() {
    var els = document.getElementsByClassName('timezone');
    var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;

    for ( var e = 0; e < els.length; e++ ) {
        var _lbl = NoNull(els[e].getAttribute('data-label')).replaceAll('{timezone}', tz);
        els[e].innerHTML = _lbl;
    }
    getTimezoneList();
}
function getTimezoneList() {
    setTimeout(function () { doJSONQuery('account/timezones', 'GET', {}, parseTimezoneList); }, 150);
}
function parseTimezoneList( data ) {
    if ( data.meta !== undefined && data.meta.code == 200 ) {
        var tz = NoNull(window.settings.timezone, Intl.DateTimeFormat().resolvedOptions().timeZone);
        var ds = data.data;

        if ( ds.length !== undefined && ds.length > 0 ) {
            var els = document.getElementsByClassName('tz-select');

            for ( var i = 0; i < ds.length; i++ ) {
                var _utc = NoNull(ds[i].utc[0]);
                if ( ds[i].utc.indexOf(tz) >= 0 ) { _utc = tz; }

                var el = document.createElement("option");
                    el.setAttribute('data-value', NoNull(_utc, ds[i].value));
                    el.innerHTML = NoNull(ds[i].name);

                if ( _utc == tz ) { el.selected = true; }

                /* Add the Element to the List */
                for ( var e = 0; e < els.length; e++ ) {
                    els[e].appendChild(el);
                }
            }

            /* Set the Event Listener */
            for ( var i = 0; i < els.length; i++ ) {
                els[i].addEventListener('change', function(e) { setTimezone(e.target); });
            }
        }
    }
}
function setTimezone( el ) {
    if ( el === undefined || el === false || el === null ) { return; }
    if ( splitSecondCheck(el) === false ) { return; }
    var _val = NoNull(el.options[el.selectedIndex].getAttribute('data-value'));
    if ( _val != '' ) { setAccountData(); }
    enableSave();
}