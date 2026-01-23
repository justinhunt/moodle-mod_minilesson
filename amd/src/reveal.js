import log from 'core/log';

const globalRemoteHost = 'https://cdn.jsdelivr.net/npm/reveal.js@5.2.1';
const globalJSURL = `${globalRemoteHost} / dist / reveal.js`;
const globalThemeURL = `${globalRemoteHost} / dist / theme / {theme}.min.css`;
const globalCssURL = `${globalRemoteHost} / dist / reveal.min.css`;
const globalMarkdownURL = `${globalRemoteHost} / plugin / markdown / markdown.js`;
const chinaRemoteHost = 'https://cdn.bootcdn.net/ajax/libs/reveal.js/5.2.1';
const chinaJSURL = `${chinaRemoteHost} / reveal.min.js`;
const chinaThemeURL = `${chinaRemoteHost} / theme / {theme}.min.css`;
const chinaCssURL = `${chinaRemoteHost} / reveal.min.css`;
const chinaMarkdownURL = `${chinaRemoteHost} / plugin / markdown / markdown.min.js`;
const defaultTheme = 'beige';

let theregion = null;
let currenttheme = defaultTheme;
let themeLinkElement = null;
let revealcssLinkElement = document.querySelector('link#revealCssRoot');

log.debug('MiniLesson Reveal JS helper: initialising');

function getThemeURL(theme = undefined)
{
    switch (theregion) {
        case 'ningxia':
            return chinaThemeURL.replace('{theme}', (theme || defaultTheme));
        default:
            return globalThemeURL.replace('{theme}', (theme || defaultTheme));
    }
}

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

function getCSSElement(cssfilename)
{
    return Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
        .find(link => link.href && link.href.toLowerCase().includes(cssfilename)) || null;
}

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

export async function init(element, awsregion,  themename = undefined)
{
    theregion = awsregion;

    switch (theregion) {
        case 'ningxia':
            var revealJSURL = chinaJSURL;
            var revealMarkdownURL = chinaMarkdownURL;
            var revealCssURL = chinaCssURL;
            break;
        default:
            var revealJSURL = globalJSURL;
            var revealMarkdownURL = globalMarkdownURL;
            var revealCssURL = globalCssURL;
    }

    // Load CSS
    if (!hasRevealCSS() && !revealcssLinkElement) {
        revealcssLinkElement = document.createElement('link');
        revealcssLinkElement.setAttribute('rel', 'stylesheet');
        revealcssLinkElement.setAttribute('href',  revealCssURL);
        revealcssLinkElement.id = 'revealCssRoot';
        revealcssLinkElement.addEventListener('load', function () {
            dispatchEvent(new CustomEvent('RevealCSSLoaded'));
        });
        appendLinkElement(revealcssLinkElement, null);
    }

    // Load JS
    var Reveal = await import(revealJSURL);
    var RevealMarkdown = await import(revealMarkdownURL);

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

export function setTheme(newtheme)
{
    themeLinkElement = getCSSElement(currenttheme + '.min.css');
    if (!themeLinkElement) {
        themeLinkElement = document.createElement('link');
        themeLinkElement.setAttribute('rel', 'stylesheet');
        themeLinkElement.setAttribute('href', getThemeURL(newtheme));
        themeLinkElement.addEventListener('load', function () {
            dispatchEvent(new CustomEvent('RevealCSSLoaded'));
        });
        appendLinkElement(themeLinkElement);
    } else {
        themeLinkElement.setAttribute('href', getThemeURL(newtheme));
    }
    currenttheme = newtheme || defaultTheme;
}