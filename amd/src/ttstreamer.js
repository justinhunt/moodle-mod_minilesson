define(['jquery', 'core/log'], function ($, log) {
    "use strict"; // jshint ;_;
    /*
    This file is the engine that drives audio rec and canvas drawing. TT Recorder is the just the glory kid
     */

    log.debug('TT Streamer initialising');

    return {

        streamingtoken: null,
        socket: null,

        //for making multiple instances
        clone: function () {
            return $.extend(true, {}, this);
        },

        init: function(streamingtoken) {
            this.streamingtoken = streamingtoken;
            this.preparesocket();

        },

        preparesocket: async function(){
            var that = this;

            // establish wss with AssemblyAI (AAI) at 16000 sample rate
            this.socket = await new WebSocket(
                `wss://api.assemblyai.com/v2/realtime/ws?sample_rate=16000&encoding=pcm_s16le&token=${this.streamingtoken}`,
            );

            // handle incoming messages which contain the transcription
            const texts = {};
            this.socket.onmessage = (message) => {
                let msg = "";
                const res = JSON.parse(message.data);
                texts[res.audio_start] = res.text;
                const keys = Object.keys(texts);
                keys.sort((a, b) => a - b);
                for (const key of keys) {
                    if (texts[key]) {
                        msg += ` ${texts[key]}`;
                    }
                }
                log.debug(msg);
            };

            this.socket.onopen = (event) => {
                log.debug('TT Streamer socket opened');
                that.ready = true;
            };

            this.socket.onerror = (event) => {
                log.debug(event);
                that.socket.close();
            };

            this.socket.onclose = (event) => {
                log.debug(event);
                that.socket = null;
            };
        },

        audioprocess: function(audiodata) {
            var that = this;
            //this would be an event that occurs after recorder has stopped lets just ignore it
            if(this.ready===undefined || !this.ready){
                return;
            }

            var monoaudiodata = audiodata[0];
            var tempbuffer = []
            for (let i = 0; i < monoaudiodata.length; i++) {
                const sample = Math.max(-1, Math.min(1, monoaudiodata[i]))
                const intSample = sample < 0 ? sample * 0x8000 : sample * 0x7fff
                tempbuffer.push(intSample & 0xff)
                tempbuffer.push((intSample >> 8) & 0xff)
            }
            var sendbuffer = new Uint8Array(tempbuffer)


            var binary = '';
            for (var i = 0; i < sendbuffer.length; i++) {
                binary += String.fromCharCode(sendbuffer[i]);
            }

            // Encode binary string to base64
            var base64 = btoa(binary);
            if (that.socket) {
                that.socket.send(
                    JSON.stringify({
                        audio_data: base64,
                    }),
                );
            }
        },


        finish: function(mimeType) {
            //this would be an event that occurs after recorder has stopped lets just ignore it
            if(this.ready===undefined || !this.ready){
                return;
            }


            this.cleanup();
        },

        cancel: function() {
            delete this.ready;
        },

        cleanup: function() {
            this.cancel();
        }

     };//end of return value

});