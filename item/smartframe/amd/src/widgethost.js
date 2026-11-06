// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The lesson page's side of the Poodll widget message protocol (v1).
 *
 * One message listener serves every widget on the page. A message is only acted on when it comes from a
 * mounted widget's own frame, in the protocol's format, and (after the hello) with that widget's current
 * instance id. Widgets run untrusted code, so nothing in a message is ever run or put into the page as HTML:
 * the host only resizes the frame and hands on a checked score.
 *
 * Widgets are served with a sandbox policy that gives them an opaque origin, so their messages arrive with
 * the origin "null". "null" proves nothing on its own (any sandboxed frame has it), so the sender is known by
 * its window: it must be the contentWindow of a frame this module mounted, which no other frame can be.
 * Messages to a widget go to "*", as an opaque origin can not be named. That is fine because nothing sent to
 * a widget is secret.
 *
 * @module     minilessonitem_smartframe/widgethost
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import log from 'core/log';

const PROTOCOL = 'poodll-widget';
const VERSION = 1;
const MIN_HEIGHT = 120;
const MAX_HEIGHT = 5000;
const INITIAL_HEIGHT = 360;
// A widget that does not report its height gets this much room.
const NORESIZE_HEIGHT = 640;
const MAX_POSSIBLE = 100000;

/** @type {Set<Widget>} The mounted widgets. */
const widgets = new Set();
let listening = false;
let resizeTimer = null;

/**
 * Make an instance id: random, and new each time a widget (re)loads, so messages from an old load are
 * ignored. It identifies the load; it is not a secret.
 *
 * @returns {string}
 */
const newInstanceId = () => {
    const bytes = new Uint8Array(12);
    window.crypto.getRandomValues(bytes);
    return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
};

/**
 * Is this a plain object (not null, not an array)?
 *
 * @param {*} value
 * @returns {boolean}
 */
const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);

/**
 * One widget frame on the page.
 */
class Widget {
    /**
     * @param {HTMLIFrameElement} iframe the frame, with no src yet
     * @param {Object} options see mount()
     */
    constructor(iframe, options) {
        this.iframe = iframe;
        this.url = options.url;
        this.parentOrigin = options.parentOrigin;
        this.uiLanguage = options.uiLanguage;
        this.gradeReporting = !!options.gradeReporting;
        this.onReady = options.onReady || (() => null);
        this.onGrade = options.onGrade || (() => null);
        this.instanceId = null;
        this.ready = false;
        this.height = options.resizes === false ? NORESIZE_HEIGHT : INITIAL_HEIGHT;
        this.iframe.style.height = this.height + 'px';
    }

    /**
     * Send a message to the widget's frame.
     *
     * @param {string} type message type, without the protocol prefix
     * @param {Object} payload
     */
    send(type, payload) {
        const win = this.iframe.contentWindow;
        if (!win || !this.instanceId) {
            return;
        }
        win.postMessage({
            protocol: PROTOCOL,
            version: VERSION,
            type: PROTOCOL + ':' + type,
            instanceId: this.instanceId,
            payload: payload,
        }, '*');
    }

    /**
     * Act on a message that has passed the checks common to all messages.
     *
     * @param {string} type message type, without the protocol prefix
     * @param {Object} message the whole message
     */
    handle(type, message) {
        const payload = isObject(message.payload) ? message.payload : {};

        // A hello starts (or restarts, if the widget reloaded) a session with a new instance id.
        if (type === 'hello') {
            this.instanceId = newInstanceId();
            this.ready = false;
            this.send('init', {
                uiLanguage: this.uiLanguage,
                parentOrigin: this.parentOrigin,
                gradeReportingEnabled: this.gradeReporting,
            });
            return;
        }

        if (!this.instanceId || message.instanceId !== this.instanceId) {
            return;
        }

        switch (type) {
            case 'ready': {
                this.ready = true;
                // The protocol names it uiLanguage; an early draft called it effectiveUiLanguage.
                let lang = payload.uiLanguage ?? payload.effectiveUiLanguage;
                lang = typeof lang === 'string' && lang.length <= 35 ? lang : null;
                this.onReady({uiLanguage: lang});
                break;
            }
            case 'resize': {
                const height = payload.height;
                if (typeof height !== 'number' || !Number.isFinite(height)) {
                    return;
                }
                const bounded = Math.min(MAX_HEIGHT, Math.max(MIN_HEIGHT, Math.ceil(height)));
                if (Math.abs(bounded - this.height) >= 2) {
                    this.height = bounded;
                    this.iframe.style.height = bounded + 'px';
                }
                break;
            }
            case 'grade': {
                if (!this.gradeReporting) {
                    return;
                }
                const {earned, possible} = payload;
                if (payload.final !== true || !Number.isInteger(earned) || !Number.isInteger(possible)
                        || possible <= 0 || possible > MAX_POSSIBLE || earned < 0 || earned > possible) {
                    log.debug('Widget grade ignored: not a valid final score');
                    return;
                }
                this.onGrade({earned: earned, possible: possible});
                break;
            }
            default:
                // Unknown types are ignored, so later protocol additions do not break this host.
                break;
        }
    }

    /**
     * Ask the widget to report its height again (after the page width changes).
     */
    measure() {
        if (this.ready) {
            this.send('measure', {});
        }
    }

    /**
     * Stop listening to this widget.
     */
    destroy() {
        widgets.delete(this);
        this.instanceId = null;
        if (!widgets.size) {
            stopListening();
        }
    }
}

/**
 * Forget widgets whose frame has left the page.
 */
const prune = () => {
    widgets.forEach((widget) => {
        if (!widget.iframe.isConnected) {
            widget.destroy();
        }
    });
};

/**
 * The one message listener for all widgets.
 *
 * @param {MessageEvent} event
 */
const onMessage = (event) => {
    const message = event.data;
    if (!isObject(message) || message.protocol !== PROTOCOL || message.version !== VERSION
            || typeof message.type !== 'string' || message.type.indexOf(PROTOCOL + ':') !== 0) {
        return;
    }
    prune();
    for (const widget of widgets) {
        // The sender must be this widget's own frame, running sandboxed as the widget store serves it.
        if (event.source === widget.iframe.contentWindow && event.origin === 'null') {
            widget.handle(message.type.substring(PROTOCOL.length + 1), message);
            return;
        }
    }
};

/**
 * Ask every widget to measure itself once the page has stopped resizing.
 */
const onWindowResize = () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        prune();
        widgets.forEach((widget) => widget.measure());
    }, 200);
};

/**
 * Start listening, if not already.
 */
const startListening = () => {
    if (!listening) {
        window.addEventListener('message', onMessage);
        window.addEventListener('resize', onWindowResize);
        listening = true;
    }
};

/**
 * Stop listening.
 */
const stopListening = () => {
    if (listening) {
        window.removeEventListener('message', onMessage);
        window.removeEventListener('resize', onWindowResize);
        clearTimeout(resizeTimer);
        listening = false;
    }
};

/**
 * Mount a widget: start listening for it, then load it into its frame.
 *
 * @param {HTMLIFrameElement} iframe the frame, with no src
 * @param {Object} options
 * @param {string} options.url the widget's entry URL, built by the server
 * @param {string} options.base the URL of the widget's own folder, which the URL must be in
 * @param {string} options.parentOrigin this page's origin, as given to the widget in its URL
 * @param {string} options.uiLanguage the interface language for the widget
 * @param {boolean} options.gradeReporting whether the widget should report a score
 * @param {boolean} [options.resizes] false if the widget says it does not report its height
 * @param {Function} [options.onReady] called with {uiLanguage} when the widget is ready
 * @param {Function} [options.onGrade] called with {earned, possible} for each valid final score
 * @returns {Widget|null} the widget, or null if the URL is not in the widget's folder
 */
export const mount = (iframe, options) => {
    let url;
    try {
        url = new URL(options.url);
    } catch (e) {
        return null;
    }
    // Never load anything into the frame but the widget's own page.
    if (!iframe || typeof options.base !== 'string' || !options.base || url.href.indexOf(options.base) !== 0) {
        log.debug('Widget not mounted: its URL is not in the widget folder');
        return null;
    }
    const widget = new Widget(iframe, options);
    widgets.add(widget);
    startListening();
    iframe.src = url.href;
    return widget;
};
