define(['jquery', 'core/log'], function ($, log) {
    "use strict"; // jshint ;_;
    /*
    This file is the engine that drives audio rec and canvas drawing. TT Recorder is the just the glory kid
     */

    log.debug('TT Streamer initialising');

    return {

        streamingtoken: null,
        socket: null,
        audiohelper: null,
        partials: [],
        finals: [],
        ready: false,
        finaltext: '',

        //for making multiple instances
        clone: function () {
            return $.extend(true, {}, this);
        },

        init: function(streamingtoken, theaudiohelper) {
            this.streamingtoken = streamingtoken;
            this.audiohelper = theaudiohelper;
            this.preparesocket();

        },

        preparesocket: async function(){
            var that = this;

            // establish wss with AssemblyAI (AAI) at 16000 sample rate
            switch(this.audiohelper.region){
                case 'frankfurt':
                case 'london':
                case 'dublin':
                    //did not work
               //     this.socket = await new WebSocket(
               //         `wss://api.eu.assemblyai.com/v2/realtime/ws?sample_rate=16000&encoding=pcm_s16le&token=${this.streamingtoken}`,
                //    );
                //    break;
                default:
                    this.socket = await new WebSocket(
                        `wss://api.assemblyai.com/v2/realtime/ws?sample_rate=16000&encoding=pcm_s16le&token=${this.streamingtoken}`,
                    );
            }
            log.debug('TT Streamer socket prepared');
            

            // handle incoming messages which contain the transcription
            this.socket.onmessage= function(message) {
                let msg = "";
                const res = JSON.parse(message.data);
                switch(res.message_type){
                    case 'PartialTranscript':
                        that.partials[res.audio_start] = res.text;
                        var keys = Object.keys(that.partials);
                        keys.sort((a, b) => a - b);
                        for (const key of keys) {
                            if (that.partials[key]) {
                                msg += ` ${that.partials[key]}`;
                            }
                        }
                        that.audiohelper.oninterimspeechcapture(that.finaltext + ' ' + msg);
                        break;

                    case 'FinalTranscript':
                        //clear partials if we have a final
                        that.partials = [];
                        //process finals
                        that.finals[res.audio_start] = res.text;
                        var keys = Object.keys(that.finals);
                        keys.sort((a, b) => a - b);
                        for (const key of keys) {
                            if (that.finals[key]) {
                                msg += ` ${that.finals[key]}`;
                            }
                        }
                        that.finaltext = msg;
                        //we do not send final speech capture event until the speaking session ends
                        //that.audiohelper.onfinalspeechcapture(msg);
                        that.audiohelper.oninterimspeechcapture(msg);
                        log.debug('interim (final) transcript: ' + msg);
                        break;
                    case 'Session_Begins':
                            break;      
                    case 'Session_Ends':
                            break;    
                    case 'Session_Information':
                        break;
                    case 'Realtime_Error':
                        log.debug(res.error);
                        break;    
                    default:
                        break;
                }
                log.debug(msg);
            };

            this.socket.onopen = (event) => {
                log.debug('TT Streamer socket opened');
                that.ready = true;
                that.partials = [];
                that.finals = [];
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
            var that = this;

            //this would be an event that occurs after recorder has stopped lets just ignore it
            if(this.ready===undefined || !this.ready){
                return;
            }
            setTimeout(function() {
                var msg = "";
                var sets = [that.finals,that.partials];
                for (const set of sets) {
                    var keys = Object.keys(set);
                    keys.sort((a, b) => a - b);
                    for (const key of keys) {
                        if (set[key]) {
                            msg += ` ${set[key]}`;
                        }
                    }
                }
                that.audiohelper.onfinalspeechcapture(msg);
                that.cleanup();
            }, 1000);
        },

        cancel: function() {
           this.ready = false;
           this.partials = [];
           this.finals = [];
           this.finaltext = '';
           if(this.socket){
               this.socket.close();
           }
        },

        cleanup: function() {
            this.cancel();
        }

     };//end of return value

});