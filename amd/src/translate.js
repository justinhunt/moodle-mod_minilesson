define(['jquery', 'core/log', 'core/ajax', 'core/str', 'core/notification'],
        function ($, log, Ajax, str, notification) {
    "use strict";

    return {

        session: null,
        sourceLang: null,
        destLang: null,
        // 'native' uses the browser's built in Translator API, 'remote' calls the Poodll translation web service.
        mode: null,
        cmid: null,
        itemid: null,
        // The language tags the native Translator API accepted, resolved by check_availability.
        // May be less specific than the requested tags (e.g. pt when pt-BR has no model).
        nativeSourceLang: null,
        nativeDestLang: null,
        // Resolved once by fetch_strings, for the model download prompt.
        stringspromise: null,

        /**
         * Set the context needed for the remote (web service) translation fallback.
         * If this is not called, only native browser translation is offered.
         *
         * @param {int} cmid The course module id
         * @param {int} itemid The minilesson item id
         */
        init: function (cmid, itemid) {
            this.cmid = cmid;
            this.itemid = itemid;
        },

        /**
         * Candidate language tag pairs for the native Translator API, most specific
         * first, so a regional preference (e.g. pt-BR) is honored when the browser
         * has a model for it, and degrades to the base language (pt) when not.
         *
         * @param {string} sourceLang Source language tag, e.g. en-US
         * @param {string} destLang Destination language tag, e.g. pt-BR
         * @returns {Array} Array of [source, dest] tag pairs to try in order
         */
        candidate_pairs: function (sourceLang, destLang) {
            var sources = [sourceLang];
            var dests = [destLang];
            var basesource = sourceLang.split('-')[0];
            var basedest = destLang.split('-')[0];
            if (basesource !== sourceLang) {
                sources.push(basesource);
            }
            if (basedest !== destLang) {
                dests.push(basedest);
            }
            var pairs = [];
            sources.forEach(function (s) {
                dests.forEach(function (d) {
                    pairs.push([s, d]);
                });
            });
            return pairs;
        },

        /**
         * Check if translation is available and get availability status.
         *
         * @param {string} sourceLang Source language code
         * @param {string} destLang Destination language code
         * @returns {Promise<string>} 'ready', 'download_needed', or 'unavailable'
         */
        check_availability: async function (sourceLang, destLang) {
            // Native browser translation first: it is free and instant.
            try {
                if ('Translator' in window) {
                    var downloadpair = null;
                    var pairs = this.candidate_pairs(sourceLang, destLang);
                    for (var i = 0; i < pairs.length; i++) {
                        var pair = pairs[i];
                        var availability = await window.Translator.availability({
                            sourceLanguage: pair[0],
                            targetLanguage: pair[1],
                        });
                        if (availability === 'available') {
                            this.mode = 'native';
                            this.nativeSourceLang = pair[0];
                            this.nativeDestLang = pair[1];
                            return 'ready';
                        }
                        if ((availability === 'downloadable' || availability === 'downloading') && !downloadpair) {
                            downloadpair = pair;
                        }
                    }
                    if (downloadpair) {
                        this.mode = 'native';
                        this.nativeSourceLang = downloadpair[0];
                        this.nativeDestLang = downloadpair[1];
                        return 'download_needed';
                    }
                }
            } catch (e) {
                log.error('Availability check failed: ' + e.message);
            }

            // Fall back to the Poodll translation web service.
            if (this.remote_available()) {
                this.mode = 'remote';
                return 'ready';
            }
            return 'unavailable';
        },

        /**
         * Whether the remote (web service) translation fallback can be used.
         *
         * @returns {boolean}
         */
        remote_available: function () {
            return !!(this.cmid && this.itemid);
        },

        /**
         * Switch to remote translation, e.g. when the user declines a native model download.
         *
         * @returns {boolean} True if remote translation is available and was selected
         */
        force_remote: function () {
            if (!this.remote_available()) {
                return false;
            }
            this.mode = 'remote';
            this.session = null;
            return true;
        },

        /**
         * Create translation session. MUST be called from user gesture if download needed.
         *
         * @param {string} sourceLang Source language code
         * @param {string} destLang Destination language code
         * @param {Function} progressCallback Optional callback invoked with model download progress.
         * @returns {Promise<boolean>} True if session created successfully
         */
        create_session: async function (sourceLang, destLang, progressCallback) {
            // Remote mode has no session to set up: just record the language pair.
            if (this.mode === 'remote') {
                this.session = {remote: true};
                this.sourceLang = sourceLang;
                this.destLang = destLang;
                return true;
            }

            // Use the tags that check_availability resolved against the browser's
            // models; they may be less specific than the requested tags.
            var nativesource = this.nativeSourceLang || sourceLang;
            var nativedest = this.nativeDestLang || destLang;
            try {
                log.debug('Creating translator session: ' + nativesource + ' -> ' + nativedest);
                log.debug('About to call window.Translator.create()...');

                // Create a timeout promise
                const timeoutPromise = new Promise((resolve, reject) => {
                    setTimeout(() => {
                        reject(new Error('Session creation timed out after 120 seconds'));
                    }, 120000);
                });

                // Create a promise that resolves when the session is created
                const createPromise = window.Translator.create({
                    sourceLanguage: nativesource,
                    targetLanguage: nativedest,
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
         * Translate text natively in the browser or via the Poodll web service.
         *
         * @param {string} text The text to translate.
         * @returns {Promise<string|boolean>} The translated text or false if failed.
         */
        translate: async function (text) {
            if (!text) {
                log.debug('no text to translate');
                return false;
            }

            if (this.session && this.session.remote) {
                return this.translate_remote(text);
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

        /**
         * Translate text via the Poodll translation web service.
         * The target language is derived server side from the activity settings.
         *
         * @param {string} text The text to translate.
         * @returns {Promise<string|boolean>} The translated text or false if failed.
         */
        translate_remote: async function (text) {
            try {
                log.debug('translating remotely : ' + text);
                const response = await Ajax.call([{
                    methodname: 'mod_minilesson_translate_message',
                    args: {
                        cmid: this.cmid,
                        itemid: this.itemid,
                        text: text,
                    },
                }])[0];
                if (response && response.success) {
                    log.debug('translated remotely : ' + response.translation);
                    return response.translation;
                }
                log.debug('remote translation failed');
            } catch (e) {
                log.error('Remote translation failed: ' + e.message);
            }
            return false;
        },

        /**
         * The strings do_translate needs, fetched once and reused.
         *
         * @returns {Promise<object>} Resolves with the resolved strings.
         */
        fetch_strings: function () {
            if (!this.stringspromise) {
                this.stringspromise = str.get_strings([
                    {key: 'downloadtranslationmodel', component: 'mod_minilesson'},
                    {key: 'downloadtranslationmodel_desc', component: 'mod_minilesson'},
                    {key: 'download', component: 'mod_minilesson'},
                    {key: 'skip', component: 'mod_minilesson'},
                    {key: 'downloadingtranslator', component: 'mod_minilesson'}
                ]).then(function (s) {
                    return {
                        downloadtranslationmodel: s[0],
                        downloadtranslationmodel_desc: s[1],
                        download: s[2],
                        skip: s[3],
                        downloadingtranslator: s[4]
                    };
                });
            }
            return this.stringspromise;
        },

        /**
         * Translate a piece of text end to end: reuse the current session if it fits, else
         * check availability, ask the user before downloading a browser translation model,
         * and fall back to the Poodll web service when there is no model to be had.
         *
         * The callback may be called more than once: while a model downloads it receives
         * progress messages (with its second argument true), and then the translation, or
         * an empty string on failure.
         *
         * @param {string} sourceLang Source language tag.
         * @param {string} destLang Destination (native) language tag.
         * @param {string} text The text to translate.
         * @param {Function} callback Called with the translated (or progress/error) string,
         *                            and whether that string is a progress message.
         */
        do_translate: function (sourceLang, destLang, text, callback) {
            var that = this;

            // Translate with the session we have, reporting failures as an empty string.
            var translate_now = function () {
                return that.translate(text).then(function (translation) {
                    callback(translation ? translation : '');
                }).catch(function (e) {
                    log.error('Translation error: ' + e);
                    callback('');
                });
            };

            // Reuse an existing session for the same language pair.
            if (this.session && this.sourceLang === sourceLang && this.destLang === destLang) {
                translate_now();
                return;
            }

            var start_session = function (progresscallback) {
                return that.create_session(sourceLang, destLang, progresscallback).then(function () {
                    return translate_now();
                }).catch(function (e) {
                    log.error('Translation error: ' + e);
                    callback('');
                });
            };

            this.check_availability(sourceLang, destLang).then(function (status) {
                if (status === 'unavailable') {
                    log.debug('Translation not available for this language pair');
                    callback('');
                    return;
                }
                if (status !== 'download_needed') {
                    start_session();
                    return;
                }
                // A browser translation model has to be downloaded first, so ask.
                that.fetch_strings().then(function (strings) {
                    notification.confirm(
                        strings.downloadtranslationmodel,
                        strings.downloadtranslationmodel_desc,
                        strings.download,
                        strings.skip,
                        function () {
                            start_session(function (percent) {
                                callback('<em>' + strings.downloadingtranslator.replace('{$a}', percent) + '</em>', true);
                            });
                        },
                        function () {
                            // The user skipped the model download: fall back to remote if we can.
                            if (that.force_remote()) {
                                start_session();
                            } else {
                                callback('');
                            }
                        }
                    );
                    return;
                }).catch(function (e) {
                    log.error('Translation error: ' + e);
                    callback('');
                });
            });
        }
    };
});
