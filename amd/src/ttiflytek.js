define(['jquery', 'core/log'], function ($, log) {
    "use strict"; // jshint ;_;
    /*
    This file is the streamer to iflytek
     */

    log.debug('TT iFlyTek Streamer initialising');

    return {

        speechtoken: null,
        socket: null,
        audiohelper: null,
        earlyaudio: [],
        partials: [],
        finals: [],
        ready: false,
        finaltext: '',

        //for making multiple instances
        clone: function () {
            return $.extend(true, {}, this);
        },

        init: function (speechtoken, theaudiohelper) {
            this.speechtoken = speechtoken;
            this.audiohelper = theaudiohelper;
            this.preparesocket();
        },

        preparesocket: async function () {
            var that = this;

            // establish wss with iFlyTek at 16000 sample rate

            var socketurl = this.speechtoken;

            var streamconfig = {
                common: {
                    app_id: "ga63dbb0"
                },
                business: {
                    language: "en_us", // For English recognition
                    domain: "ist",     // iST = iFlytek Realtime Transcription
                    accent: "english",
                    vad_eos: 2000,     // Silence duration before ending session (ms)
                    dwa: "wpgs"        // Enables dynamic result returns (streaming feel)
                },
                data: {
                    status: 0,         // 0: first frame, 1: middle, 2: last
                    format: "audio/L16;rate=16000",
                    encoding: "raw"
                }
            };

            this.socket = await new WebSocket(socketurl);

            log.debug('TT iFlyTek Streamer socket prepared');


            // handle incoming messages which contain the transcription
            this.socket.onmessage = function (message) {
                let msg = "";
                const res = JSON.parse(message.data);

                if (res.code !== 0) {
                    log.debug('iFlyTek error: ' + res.message);
                    return;
                }


                switch (res.status) { // AssemblyAI field
                    case 1:
                        var words = res.ws;
                        // Join all words with space
                        var thetext = words.map(word => word.w).join(' ');
                        that.partials[res.audio_start] = thetext;
                        var keys = Object.keys(that.partials);
                        keys.sort((a, b) => a - b);
                        for (const key of keys) {
                            if (that.partials[key]) {
                                msg += ` ${that.partials[key]}`;
                            }
                        }
                        that.audiohelper.oninterimspeechcapture(that.finaltext + ' ' + msg);
                        break;

                    case 2:
                        //clear partials if we have a final
                        that.partials = [];
                        //process finals Join all words with space
                        var words = res.ws;
                        var thetext = words.map(word => word.w).join(' ');
                        that.finals[res.audio_start] = thetext;
                        var keys = Object.keys(that.finals);
                        keys.sort((a, b) => a - b);
                        for (const key of keys) {
                            if (that.finals[key]) {
                                msg += ` ${that.finals[key]}`;
                            }
                        }
                        that.finaltext = msg;
                        that.audiohelper.oninterimspeechcapture(msg);
                        log.debug('interim (final) transcript: ' + msg);
                        break;
                    default:
                        break;
                }
                log.debug(msg);
            };

            this.socket.onopen = (event) => {
                log.debug('TT iFlyTek Streamer socket opened');
                that.partials = [];
                that.finals = [];
                // Frame 1: Send Configuration
                that.socket.send(JSON.stringify(streamconfig));
                that.audiohelper.onSocketReady('fromsocketopen');
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

        updatetoken: function (newtoken) {
            var that = this;
            if (that.socket) {
                that.socket.close();
            }
            that.speechtoken = newtoken;
            that.preparesocket();
        },

        audioprocess: function (stereodata) {
            var that = this;
            const base64data = this.binarytobase64(stereodata[0]);

            //this would be an event that occurs after recorder has stopped or before we are ready
            //session opening can be slower than socket opening, so store audio data until session is open
            if (this.ready === undefined || !this.ready) {
                log.debug('TT iFlyTek Streamer storing base64 audio');
                this.earlyaudio.push(base64data);

                //session opened after we collected audio data, send earlyaudio first
            } else if (this.earlyaudio.length > 0) {
                for (var i = 0; i < this.earlyaudio.length; i++) {
                    this.sendaudio(this.earlyaudio[i]);
                }
                //clear earlyaudio and send the audio we just got
                this.earlyaudio = [];
                this.sendaudio(base64data);

            } else {
                //just send the audio we got
                log.debug('TT iFlyTek Streamer sending current audiodata');
                this.sendaudio(base64data);
            }
        },

        binarytobase64: function (monoaudiodata) {
            var that = this;

            //convert to 16 bit pcm
            var tempbuffer = []
            for (let i = 0; i < monoaudiodata.length; i++) {
                const sample = Math.max(-1, Math.min(1, monoaudiodata[i]))
                const intSample = sample < 0 ? sample * 0x8000 : sample * 0x7fff
                tempbuffer.push(intSample & 0xff)
                tempbuffer.push((intSample >> 8) & 0xff)
            }
            var sendbuffer = new Uint8Array(tempbuffer)

            // Encode binary string to base64
            var binary = '';
            for (var i = 0; i < sendbuffer.length; i++) {
                binary += String.fromCharCode(sendbuffer[i]);
            }
            var base64 = btoa(binary);
            return base64;
        },

        sendaudio: function (base64) {
            var that = this;
            //Send it off !!
            if (that.socket) {
                that.socket.send(
                    JSON.stringify({
                        data: { status: 1, audio: base64, encoding: "raw" }
                    }),
                );
            }
        },

        finish: function (mimeType) {
            var that = this;

            //this would be an event that occurs after recorder has stopped lets just ignore it
            if (this.ready === undefined || !this.ready) {
                return;
            }
            log.debug('forcing end utterance');
            //get any remanining transcription
            if (that.socket) {
                that.socket.send(
                    JSON.stringify({
                        data: { status: 2, audio: "", encoding: "raw" }
                    }),
                );
            }
            log.debug('timing out');
            setTimeout(function () {
                var msg = "";
                var sets = [that.finals, that.partials];
                for (const set of sets) {
                    var keys = Object.keys(set);
                    keys.sort((a, b) => a - b);
                    for (const key of keys) {
                        if (set[key]) {
                            msg += ` ${set[key]}`;
                        }
                    }
                }
                log.debug('sending final speech capture event');
                that.audiohelper.onfinalspeechcapture(msg);
                that.cleanup();
            }, 1000);
        },

        cancel: function () {
            this.ready = false;
            this.earlyaudio = [];
            this.partials = [];
            this.finals = [];
            this.finaltext = '';
            if (this.socket) {
                this.socket.close();
            }
        },

        cleanup: function () {
            this.cancel();
        }

    };//end of return value

});
