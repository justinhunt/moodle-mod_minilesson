define(['jquery', 'core/log'], function ($, log) {
    "use strict"; // jshint ;_;
    /*
    This file is the engine that drives audio rec and canvas drawing. TT Recorder is the just the glory kid
     */

    log.debug('TT Wav Encoder initialising');

    var ttw = {



        init: function(sampleRate, numChannels) {
            ttw.sampleRate = sampleRate;
            ttw.numChannels = numChannels;
            ttw.numSamples = 0;
            ttw.dataViews = [];
        },

        encode: function(buffer) {
            //this would be an event that occurs after recorder has stopped lets just ignore it
            if(ttw.dataViews===undefined){
                return;
            }

            var len = buffer[0].length,
                nCh = ttw.numChannels,
                view = new DataView(new ArrayBuffer(len * nCh * 2)),
                offset = 0;
            for (var i = 0; i < len; ++i)
                for (var ch = 0; ch < nCh; ++ch) {
                    var x = buffer[ch][i] * 0x7fff;
                    view.setInt16(offset, x < 0 ? Math.max(x, -0x8000) : Math.min(x, 0x7fff), true);
                    offset += 2;
                }
            ttw.dataViews.push(view);
            ttw.numSamples += len;
        },

        setString: function(view, offset, str) {
            var len = str.length;
            for (var i = 0; i < len; ++i) {
                view.setUint8(offset + i, str.charCodeAt(i));
            }
        },

        finish: function(mimeType) {
            var dataSize = ttw.numChannels * ttw.numSamples * 2;
            var view = new DataView(new ArrayBuffer(44));
            ttw.setString(view, 0, 'RIFF');
            view.setUint32(4, 36 + dataSize, true);
            ttw.setString(view, 8, 'WAVE');
            ttw.setString(view, 12, 'fmt ');
            view.setUint32(16, 16, true);
            view.setUint16(20, 1, true);
            view.setUint16(22, ttw.numChannels, true);
            view.setUint32(24, ttw.sampleRate, true);
            view.setUint32(28, ttw.sampleRate * 4, true);
            view.setUint16(32, ttw.numChannels * 2, true);
            view.setUint16(34, 16, true);
            ttw.setString(view, 36, 'data');
            view.setUint32(40, dataSize, true);
            ttw.dataViews.unshift(view);
            var blob = new Blob(ttw.dataViews, { type: 'audio/wav' });
            ttw.cleanup();
            return blob;
        },

        cancel: function() {
            delete ttw.dataViews;
        },

        cleanup: function() {
            ttw.cancel();
        }

     };//end of return value
    return ttw;
});