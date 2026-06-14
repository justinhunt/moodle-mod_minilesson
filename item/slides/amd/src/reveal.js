import log from 'core/log';

// reveal.js and its markdown plugin are >100kb MIT libraries vendored with mod_minilesson and
// loaded lazily as AMD modules (see amd/src/external/reveal*.js). Their CSS (core + themes) is
// shipped statically with the slides item (item/slides/css/). Nothing is pulled from a CDN, so this
// works on networks that cannot reach jsdelivr/cdnjs (e.g. China) and the libraries stay off the
// critical path. The previous region (awsregion) based CDN switching is therefore no longer needed.
const wwwroot = (window.M && window.M.cfg && window.M.cfg.wwwroot) || '';
const cssRoot = wwwroot + '/mod/minilesson/item/slides/css';
const coreCssURL = `${cssRoot}/reveal.min.css`;
const themeCssURL = theme => `${cssRoot}/theme/${theme}.min.css`;
const defaultTheme = 'beige';

let currenttheme = defaultTheme;
let themeLinkElement = null;
let revealcssLinkElement = document.querySelector('link#revealCssRoot');

log.debug('MiniLesson Reveal JS helper: initialising');

/**
 * Lazy-load a vendored AMD module by name and resolve with its export.
 *
 * @param {String} module the AMD module name.
 * @returns {Promise} resolves with the (default) export of the module.
 */
function loadModule(module)
{
    return new Promise(function(resolve) {
        require([module], function(lib) {
            resolve((lib && lib.default) || lib);
        });
    });
}

/**
 * @returns {Boolean} true if the reveal.js core stylesheet is already present on the page.
 */
function hasRevealCSS()
{
    return Array.from(document.styleSheets).some(sheet => {
        try {
            return sheet.href && sheet.href.toLowerCase().includes('reveal.min.css');
        } catch (e) {
            return false; // Ignore CORS-protected sheets
        }
    });
}

/**
 * @param {String} cssfilename a filename fragment to match against loaded stylesheet hrefs.
 * @returns {(HTMLLinkElement|null)} the matching link element, or null if none.
 */
function getCSSElement(cssfilename)
{
    return Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .find(link => link.href && link.href.toLowerCase().includes(cssfilename)) || null;
}

/**
 * Insert a stylesheet link into the document head, after an existing reference link where possible.
 *
 * @param {HTMLLinkElement} linkEl the link element to insert.
 * @param {(HTMLLinkElement|null)} appendLinkEl the element to insert after (defaults to the reveal core link).
 */
function appendLinkElement(linkEl, appendLinkEl = revealcssLinkElement)
{
    if (!appendLinkEl) {
        let lastLink = document.querySelectorAll('head > link');
        if (lastLink.length > 0) {
            appendLinkEl = lastLink.item(lastLink.length - 1);
        }
    }
    if (!appendLinkEl) {
        document.querySelector('head').append(linkEl);
    } else {
        appendLinkEl.insertAdjacentElement('afterend', linkEl);
    }
}

/**
 * Initialise a reveal.js presentation inside the given element.
 *
 * @param {HTMLElement} element the .reveal container element.
 * @param {String} [themename] the reveal theme to load (defaults to beige).
 * @returns {Promise<Object>} resolves with the reveal.js instance.
 */
export async function init(element, themename = undefined)
{
    // Load CSS (local). Region/CDN switching was removed now the assets are shipped with the plugin.
    if (!hasRevealCSS() && !revealcssLinkElement) {
        revealcssLinkElement = document.createElement('link');
        revealcssLinkElement.setAttribute('rel', 'stylesheet');
        revealcssLinkElement.setAttribute('href', coreCssURL);
        revealcssLinkElement.id = 'revealCssRoot';
        revealcssLinkElement.addEventListener('load', function () {
            dispatchEvent(new CustomEvent('RevealCSSLoaded'));
        });
        appendLinkElement(revealcssLinkElement, null);
    }

    // Load JS (vendored, lazy).
    var Reveal = await loadModule('mod_minilesson/external/reveal');
    var RevealMarkdown = await loadModule('mod_minilesson/external/reveal-markdown');

    //Load Theme
    setTheme(themename);

    const instance = new Reveal(element, {
        hash: false,
        fragmentInURL: false,
        slideNumber: true,
        plugins: [ RevealMarkdown ],
            // Prevent keyboard capture when not focused
        keyboard: {
            37: 'prev',
            39: 'next',
            // Disable all other keyboard shortcuts
            32: null,  // space
            13: null,  // enter
            27: null,  // escape
            33: null,  // page up
            34: null,  // page down
            36: null,  // home
            35: null   // end
        },
        embedded: true,
        // Enable touch events for the entire document
        touch: true,
        // Disable overview mode
        overview: false,
        // Show controls
        controls: true,
        // Show progress bar
        progress: true
    });
    instance.on('ready', function () {
        instance.layout();
    });
    addEventListener('RevealCSSLoaded', function () {
        instance.layout();
    });
    return instance;
}

/**
 * Load (or swap to) a reveal.js theme stylesheet from the local css directory.
 *
 * @param {String} newtheme the theme name to load (defaults to beige).
 */
export function setTheme(newtheme)
{
    themeLinkElement = getCSSElement(currenttheme + '.min.css');
    if (!themeLinkElement) {
        themeLinkElement = document.createElement('link');
        themeLinkElement.setAttribute('rel', 'stylesheet');
        themeLinkElement.setAttribute('href', themeCssURL(newtheme || defaultTheme));
        themeLinkElement.addEventListener('load', function () {
            dispatchEvent(new CustomEvent('RevealCSSLoaded'));
        });
        appendLinkElement(themeLinkElement);
    } else {
        themeLinkElement.setAttribute('href', themeCssURL(newtheme || defaultTheme));
    }
    currenttheme = newtheme || defaultTheme;
}
