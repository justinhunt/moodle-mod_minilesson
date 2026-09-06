define(['core/log', 'core/ajax'], function (log, Ajax) {
    "use strict"; // jshint ;_;
    /*
    This file gets a TTS (Polly) audio URL at runtime.

    The URL is resolved by the mod_minilesson_fetch_polly_url web service, which reads/writes the
    shared server side media cache (minilesson_media_cache) and holds the CloudPoodll credentials.
    The old direct browser -> CloudPoodll request (and its SSML building) now lives server side in
    utils::fetch_polly_url.
     */

    log.debug('Polly helper: initialising');

    return {
        cmid: 0,

        init: function (cmid) {
            this.cmid = cmid;
        },

        /**
         * Resolve the audio URL for some text in a given voice.
         *
         * @param {string} speaktext the text to read aloud
         * @param {number|string} voiceoption 0 normal, 1 slow, 2 very slow, 3 ssml
         * @param {string} voice the TTS voice name
         * @return {Promise<string>} resolves with the audio URL
         */
        fetch_polly_url: function (speaktext, voiceoption, voice) {
            var that = this;
            return new Promise(function (resolve, reject) {
                if (!speaktext || !voice) {
                    reject('fetch_polly_url: missing text or voice');
                    return;
                }
                Ajax.call([{
                    methodname: 'mod_minilesson_fetch_polly_url',
                    args: {
                        cmid: that.cmid,
                        text: speaktext,
                        voiceoption: parseInt(voiceoption, 10) || 0,
                        voice: voice
                    }
                }])[0].then(function (response) {
                    if (response && response.url) {
                        resolve(response.url);
                    } else {
                        reject('fetch_polly_url: no url returned');
                    }
                    return response;
                }).catch(function (err) {
                    log.debug('fetch_polly_url request failed');
                    log.debug(err);
                    reject(err);
                });
            });
        }

    };//end of return value
});
