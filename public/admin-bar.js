/*!
 * Sulu Admin Bar
 *
 * Fetches the authenticated admin bar endpoint and, when a Sulu admin
 * session exists, injects a toolbar on top of the page. Without a session
 * marker cookie the endpoint is not called and nothing is rendered.
 */
(function () {
    'use strict';

    var script = document.currentScript || document.querySelector('script[data-sulu-admin-bar]');
    if (!script) {
        return;
    }

    var endpoint = script.dataset.endpoint;
    var cssHref = script.dataset.css;
    if (!endpoint) {
        return;
    }

    // Marker cookie maintained by the admin (AdminSessionCookieListener).
    // Without it there is no admin session, so the endpoint is not called
    // at all and anonymous visitors never see a 401 in their console.
    var COOKIE_NAME = 'sulu_admin_bar';

    function hasMarkerCookie() {
        return -1 !== ('; ' + document.cookie).indexOf('; ' + COOKIE_NAME + '=');
    }

    function clearMarkerCookie() {
        document.cookie = COOKIE_NAME + '=; Max-Age=0; path=/';
    }

    var params = new URLSearchParams();
    ['webspace', 'locale', 'uuid', 'id', 'resourceKey', 'route'].forEach(function (key) {
        if (script.dataset[key]) {
            params.set(key, script.dataset[key]);
        }
    });

    // Unresolved entity page (route fallback): also send the URL path so
    // the endpoint can match its segments against the entity resource keys.
    if (script.dataset.route) {
        params.set('path', window.location.pathname);
    }

    // Generic labels: the edit/add links may point to a page or to a
    // custom entity form depending on the current context. Overridable
    // via the "admin_bar.labels" configuration (data-label-* attributes).
    var LABELS = {
        edit: script.dataset.labelEdit || 'Edit',
        add: script.dataset.labelAdd || 'Add new',
        logout: script.dataset.labelLogout || 'Logout'
    };

    var LOGO_SVG =
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" width="20" height="20" aria-hidden="true" focusable="false">' +
        '<rect width="20" height="20" rx="4" fill="#52b6ca"/>' +
        '<text x="10" y="14.5" text-anchor="middle" font-family="Arial, sans-serif" font-size="12" font-weight="bold" fill="#fff">S</text>' +
        '</svg>';

    function createLink(href, text, className) {
        var link = document.createElement('a');
        link.className = 'sab-item ' + (className || '');
        link.href = href;
        link.textContent = text;

        return link;
    }

    function render(data) {
        if (cssHref && !document.querySelector('link[data-sulu-admin-bar-css]')) {
            var style = document.createElement('link');
            style.rel = 'stylesheet';
            style.href = cssHref;
            style.setAttribute('data-sulu-admin-bar-css', '');
            document.head.appendChild(style);
        }

        var bar = document.createElement('div');
        bar.id = 'sulu-admin-bar';
        bar.setAttribute('role', 'navigation');
        bar.setAttribute('aria-label', 'Sulu admin bar');

        var left = document.createElement('div');
        left.className = 'sab-section sab-left';

        var logo = document.createElement('a');
        logo.className = 'sab-item sab-logo';
        logo.href = data.urls.admin;
        logo.innerHTML = LOGO_SVG;

        var logoText = document.createElement('span');
        logoText.textContent = 'Sulu';
        logo.appendChild(logoText);
        left.appendChild(logo);

        var right = document.createElement('div');
        right.className = 'sab-section sab-right';

        var userName = document.createElement('span');
        userName.className = 'sab-item sab-user';
        userName.textContent = data.user.name;
        right.appendChild(userName);

        if (data.urls.edit) {
            right.appendChild(createLink(data.urls.edit, LABELS.edit, 'sab-edit'));
        }
        if (data.urls.add) {
            right.appendChild(createLink(data.urls.add, LABELS.add, 'sab-add'));
        }
        right.appendChild(createLink(data.urls.logout, LABELS.logout, 'sab-logout'));

        bar.appendChild(left);
        bar.appendChild(right);

        document.body.insertBefore(bar, document.body.firstChild);
        document.documentElement.classList.add('sulu-admin-bar-active');
    }

    function init() {
        if (!hasMarkerCookie()) {
            return;
        }

        fetch(endpoint + '?' + params.toString(), {
            credentials: 'same-origin',
            headers: {Accept: 'application/json'}
        })
            .then(function (response) {
                if (!response.ok) {
                    // Stale marker (expired session): drop it so the
                    // endpoint is not called again on the next pages.
                    clearMarkerCookie();

                    return null;
                }

                return response.json();
            })
            .then(function (data) {
                if (data && data.authenticated) {
                    render(data);
                }
            })
            .catch(function () {
                // Not authenticated or endpoint unavailable: no admin bar.
            });
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
