import log from 'core/log';

const revealJSremoteCssHost = 'https://cdn.jsdelivr.net/npm/reveal.js@5.2.1';
const defaultTheme = 'black';
const remoteCssURL = `${revealJSremoteCssHost}/dist/theme/{theme}.css`;
let linkElement = null;
let defaultRemoteCssEl = document.querySelector('link#revealCssRoot');

log.debug('MiniLesson Reveal JS helper: initialising');

const getRemoteCssURL = (theme = undefined) => remoteCssURL.replace('{theme}', (theme || defaultTheme));

function appendLinkElement(linkEl, appendLinkEl = defaultRemoteCssEl) {
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

if (!defaultRemoteCssEl) {
    defaultRemoteCssEl = document.createElement('link');
    defaultRemoteCssEl.setAttribute('rel', 'stylesheet');
    defaultRemoteCssEl.setAttribute('href', `${revealJSremoteCssHost}/dist/reveal.css`);
    defaultRemoteCssEl.id = 'revealCssRoot';
    defaultRemoteCssEl.addEventListener('load', function() {
        dispatchEvent(new CustomEvent('RevealCSSLoaded'));
    });
    appendLinkElement(defaultRemoteCssEl, null);
}

export async function init(element, themename = undefined) {
    const Reveal = await import(`${revealJSremoteCssHost}/dist/reveal.js`);
    const RevealMarkdown = await import(`${revealJSremoteCssHost}/plugin/markdown/markdown.js`);

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
        // Disable touch events for the entire document
        touch: true,
        // Disable overview mode
        overview: false,
        // Don't show controls
        controls: true,
        // Don't show progress bar
        progress: true
    });
    instance.on('ready', function() {
        instance.layout();
    });
    addEventListener('RevealCSSLoaded', function() {
        instance.layout();
    });
    return instance;
}

export function setTheme(theme) {
    if (!linkElement) {
        linkElement = document.createElement('link');
        linkElement.setAttribute('rel', 'stylesheet');
        linkElement.setAttribute('href', getRemoteCssURL(theme));
        linkElement.addEventListener('load', function() {
            dispatchEvent(new CustomEvent('RevealCSSLoaded'));
        });
        appendLinkElement(linkElement);
    } else {
        linkElement.setAttribute('href', getRemoteCssURL(theme));
    }
}