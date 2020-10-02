define(['jquery', 'core/log', 'mod_poodlltime/ttaudiohelper', 'core/notification'], function ($, log, audioHelper, notification) {
    "use strict"; // jshint ;_;
    /*
    *  The TT recorder
     */

    log.debug('TT Recorder: initialising');

    var ttr= {
        waveHeight: 75,
        audio: {
            stream: null,
            blob: null,
            dataURI: null,
            start: null,
            end: null,
            isRecording: false,
            isRecognizing: false,
            transcript: null
        },
        submitting: false,
        owner: '',
        controls: {},
        uniqueid: null,
        audio_updated: null,
        maxTime: 15000,
        passagehash: null,
        region: null,
        asrurl: null,

        init: function(opts){
            this.uniqueid=opts['uniqueid'];
            this.callback=opts['callback'];
            this.prepare_html();
            this.register_events();
            audioHelper.init(this.waveHeight,this.uniqueid,this);
            audioHelper.onError = this.error;
            audioHelper.onStop = this.stopped;
            audioHelper.onStream = this.got_stream;
        },

        prepare_html: function(){
            this.controls.recordercontainer =$('#ttrec_container_' + this.uniqueid);
            this.controls.recorderbutton = $('#' + this.uniqueid + '_recorderdiv');
            this.passagehash =this.controls.recorderbutton.data('passagehash');
            this.region=this.controls.recorderbutton.data('region');
            this.asrurl=this.controls.recorderbutton.data('asrurl');
            this.maxTime=this.controls.recorderbutton.data('maxtime');
            this.waveHeight=this.controls.recorderbutton.data('waveheight');
        },

        update_audio: function(newprops,val){
            if (typeof newprops === 'string') {
                log.debug('update_audio:' + newprops + ':' + val);
                if (this.audio[newprops] != val) {
                    this.audio[newprops] = val;
                    this.audio_updated();
                }
            }else{
                for (var theprop in newprops) {
                    this.audio[theprop] = newprops[theprop];
                    log.debug('update_audio:' + theprop + ':' + newprops[theprop]);
                }
                this.audio_updated();
            }
        },

        register_events: function(){
            var that = this;
            this.controls.recordercontainer.click(function(){
                that.toggleRecording();
            });

            this.audio_updated=function() {
                //pointer
                if (that.audio.isRecognizing || that.isComplete()) {
                    that.show_recorder_pointer('none');
                } else {
                    that.show_recorder_pointer('auto');
                }

                //background WHEN?
                if (that.isComplete()) {
                    that.show_recorder_complete(true);
                } else {
                    that.show_recorder_complete(false);
                }

                //div content WHEN?
                that.controls.recorderbutton.html(that.recordBtnContent());
            }

        },

        show_recorder_pointer: function(show){
            if(show) {
                this.controls.recorderbutton.css('pointer-events', 'none');
            }else{
                this.controls.recorderbutton.css('pointer-events', 'auto');
            }

        },
        show_recorder_complete: function(show){
            if(show) {
                this.controls.recorderbutton.css('background', 'green');
            }else{
                this.controls.recorderbutton.css('background', '#e52');
            }
        },

        gotRecognition:function(transcript){
            log.debug('transcript');
            var message={};
            message.type='speech';
            message.capturedspeech = transcript;
            ttr.callback(message);
        },

        cleanWord: function(word) {
            return word.replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
        },

        recordBtnContent: function() {
            if(!ttr.audio.isRecognizing){
                if (!ttr.isComplete()) {
                    if (ttr.audio.isRecording) {
                        return '<i class="fa fa-stop">';
                    } else {
                        return '<i class="fa fa-microphone">';
                    }
                } else {
                    return '<i class="fa fa-check">';
                }
            } else {
                return '<i class="fa fa-spinner fa-spin">';
            }
        },
        toggleRecording: function() {
            if (ttr.audio.isRecording) {
                ttr.update_audio('isRecognizing',true);
                audioHelper.stop();
            } else {
                ttr.start();
            }
        },
        got_stream: function(stream) {
            var newaudio={stream: stream, isRecording: true};
            ttr.update_audio(newaudio);
            ttr.currentTime = 0;

            ttr.interval = setInterval(function() {
                if (ttr.currentTime < ttr.maxTime) {
                    ttr.currentTime += 10;
                } else {
                    ttr.update_audio('isRecognizing',true);
                   // vm.isRecognizing = true;
                    audioHelper.stop();
                }
            }, 10);

        },
        start: function() {

            var newaudio = {
                stream: null,
                blob: null,
                dataURI: null,
                start: new Date(),
                end: null,
                isRecording: false,
                isRecognizing:false,
                transcript: null
            };
            ttr.update_audio(newaudio);
            audioHelper.start();
        },
        stopped: function(blob) {

            clearInterval(ttr.interval);

            var newaudio = {
                blob: blob,
                dataURI: URL.createObjectURL(blob),
                end: new Date(),
                isRecording: false,
                length: Math.round((ttr.audio.end - ttr.audio.start) / 1000),
            };
            ttr.update_audio(newaudio);

            var scorer= 'abc';
            ttr.deepSpeech2(ttr.audio.blob, function(response){
                log.debug(response);
                ttr.update_audio('isRecognizing',false);
                if(response.data.result=="success" && response.data.transcript){
                    ttr.gotRecognition(response.data.transcript.trim());
                } else {
                    notification.alert("Information","We could not recognize your speech.", "OK");
                }
            });

        },
        error: function(error) {
            switch (error.name) {
                case 'PermissionDeniedError':
                case 'NotAllowedError':
                    notification.alert("Error",'Please allow access to your microphone!', "OK");
                    break;
                case 'DevicesNotFoundError':
                case 'NotFoundError':
                    notification.alert("Error",'No microphone detected!', "OK");
                    break;
            }
        },

        deepSpeech2: function(blob, callback) {


            var bodyFormData = new FormData();
            var blobname = ttr.uniqueid + Math.floor(Math.random() * 100) +  '.wav';
            bodyFormData.append('audioFile', blob, blobname);
            bodyFormData.append('scorer', ttr.passagehash);

            var oReq = new XMLHttpRequest();
            oReq.open("POST", ttr.asrurl, true);
            oReq.onUploadProgress= function(progressEvent) {};
            oReq.onload = function(oEvent) {
                if (oReq.status === 200) {
                    callback(JSON.parse(oReq.response));
                } else {
                    console.error(oReq.error);
                }
            };
            oReq.send(bodyFormData);

        },

        //not really OK here, this is something else
        isComplete: function() {
            return false;
        },
    };//end of return value

    return ttr;
});