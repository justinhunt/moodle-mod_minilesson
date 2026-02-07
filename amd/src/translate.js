define(['jquery', 'core/log'], function ($, log) {
    "use strict";

    return {

        session: null,
        sourceLang: null,
        destLang: null,

        /**
         * Check if translation is available and get availability status.
         * 
         * @param {string} sourceLang Source language code
         * @param {string} destLang Destination language code
         * @returns {Promise<string>} 'ready', 'download_needed', or 'unavailable'
         */
        check_availability: async function (sourceLang, destLang) {
            if (!this.is_chrome()) {
                return 'unavailable';
            }
            try {
                if ('Translator' in window) {
                    const availability = await window.Translator.availability({
                        sourceLanguage: sourceLang,
                        targetLanguage: destLang,
                    });

                    switch (availability) {
                        case 'available':
                            return 'ready';
                        case 'downloadable':
                        case 'downloading':
                            return 'download_needed';
                        case 'unavailable':
                        default:
                            return 'unavailable';
                    }
                }
            } catch (e) {
                log.error('Availability check failed: ' + e.message);
            }
            return 'unavailable';
        },

        /**
         * Create translation session. MUST be called from user gesture if download needed.
         * 
         * @param {string} sourceLang Source language code
         * @param {string} destLang Destination language code
         * @returns {Promise<boolean>} True if session created successfully
         */
        create_session: async function (sourceLang, destLang, progressCallback) {
            try {
                log.debug('Creating translator session: ' + sourceLang + ' -> ' + destLang);
                log.debug('About to call window.Translator.create()...');

                // Create a timeout promise
                const timeoutPromise = new Promise((resolve, reject) => {
                    setTimeout(() => {
                        reject(new Error('Session creation timed out after 120 seconds'));
                    }, 120000);
                });

                // Create a promise that resolves when the session is created
                const createPromise = window.Translator.create({
                    sourceLanguage: sourceLang,
                    targetLanguage: destLang,
                    monitor: (m) => {
                        log.debug('Monitor callback invoked');
                        m.addEventListener("downloadprogress", (event) => {
                            const percent = ((event.loaded / event.total) * 100).toFixed(1);
                            log.debug('Download progress: ' + percent + '% (' + event.loaded + '/' + event.total + ')');
                            // If progress callback is set and is a function, call it with the % progress
                            if (progressCallback && typeof progressCallback === 'function') {
                                progressCallback(percent);
                            }
                            if (event.loaded === event.total) {
                                log.debug('Download complete! Waiting for create() to resolve...');
                            }
                        });
                    }
                });

                log.debug('Waiting for translator creation or timeout...');
                this.session = await Promise.race([createPromise, timeoutPromise]);

                log.debug('Promise resolved! Setting session properties...');
                this.sourceLang = sourceLang;
                this.destLang = destLang;
                log.debug('Session created successfully');
                return true;
            } catch (e) {
                log.error('Session creation failed: ' + e.message);
                log.error('Error details: ' + JSON.stringify(e));
                return false;
            }
        },

        /**
         * Translate text using the browser's native translation API.
         * 
         * @param {string} text The text to translate.
         * @returns {Promise<string|boolean>} The translated text or false if failed.
         */
        translate: async function (text) {
            if (!text) {
                log.debug('no text to translate');
                return false;
            }

            try {
                log.debug('translating : ' + text);
                var translated = await this.session.translate(text);
                log.debug(translated);
                log.debug('translated : ' + translated);
                return translated;

            } catch (e) {
                log.error('Native translation failed: ' + e.message);
            }

            return false;
        },

        is_chrome: function () {
            var ua = window.navigator.userAgent;
            var isChrome = /Chrome/.test(ua) || /CriOS/.test(ua);
            var isEdge = /Edg\//.test(ua);
            var isOpera = /OPR\//.test(ua) || /Opera/.test(ua);
            var isVivaldi = /Vivaldi/.test(ua);
            log.debug('isChrome: ' + isChrome);
            log.debug('isEdge: ' + isEdge);
            log.debug('isOpera: ' + isOpera);
            log.debug('isVivaldi: ' + isVivaldi);
            // If it has Chrome and doesn't have Edge, Opera or Vivaldi, it's likely Google Chrome.
            return !!(isChrome && !isEdge && !isOpera && !isVivaldi);
        }
    };
});
