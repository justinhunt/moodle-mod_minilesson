define(['jquery', 'core/str', 'core/log', 
    'https://cdn.jsdelivr.net/npm/reveal.js@4.3.1/dist/reveal.js',
    'https://cdn.jsdelivr.net/npm/reveal.js@4.3.1/plugin/markdown/markdown.js'],
function($, str, log, Reveal, RevealMarkdown) {
    "use strict"; // jshint ;_;

    log.debug('MiniLesson Reveal JS helper: initialising');

    

    return {
        // pass in any config
        init: function(revealconfig) {
            Reveal.initialize({
                    hash: true,
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
        }
    };
});