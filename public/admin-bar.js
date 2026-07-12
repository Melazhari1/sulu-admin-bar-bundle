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

    // Official Sulu logo mark (the "su-sulu" glyph shipped with the Sulu admin).
    var LOGO_SVG =
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="32 160 960 704" width="25" height="18" fill="currentColor" aria-hidden="true" focusable="false">' +
        '<path d="M987.014 560.957c9.583-14.184 4.792-30.732-9.583-40.188l-225.212-153.661c-14.375-9.456-26.355-28.368-28.75-44.916l-16.771-141.841c-2.396-16.548-16.771-23.64-31.146-18.912l-622.927 193.85c-16.771 4.728-23.959 21.276-19.167 35.46l119.794 326.235c4.792 14.184 23.959 30.732 38.334 33.096l565.426 113.473c16.771 2.364 35.938-4.728 45.522-18.912l184.482-283.682zM474.298 416.752c-9.583 11.82-31.146 21.276-47.917 16.548l-263.546-52.008c-16.771-2.364-16.771-9.456 0-14.184l440.84-137.113c16.771-4.728 21.563 2.364 11.979 14.184l-141.356 172.573zM653.988 255.999c9.583-11.82 19.167-9.456 21.563 4.728l52.709 482.26c2.396 16.548-4.792 18.912-14.375 4.728l-203.649-262.406c-9.583-11.82-9.583-33.096 0-44.916l143.752-184.394zM114.917 409.66l309.067 63.829c16.771 2.364 38.334 16.548 47.917 28.368l222.816 286.046c9.583 11.82 4.792 21.276-11.979 16.548l-457.611-89.833c-16.771-2.364-33.542-18.912-38.334-33.096l-91.043-250.586c-4.792-14.184 2.396-23.64 19.167-21.276zM737.843 433.3c-2.396-16.548 7.188-21.276 21.563-11.82l162.919 111.109c14.375 9.456 16.771 28.368 9.583 40.188l-136.565 208.034c-9.583 14.184-16.771 11.82-19.167-4.728l-38.334-342.783z"/>' +
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
        logo.setAttribute('aria-label', 'Sulu admin');
        logo.innerHTML = LOGO_SVG;
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
