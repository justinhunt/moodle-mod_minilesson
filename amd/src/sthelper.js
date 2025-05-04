define(['jquery',
    'core/log',
    'core/ajax',
    'mod_minilesson/definitions',
    'mod_minilesson/pollyhelper',
    'mod_minilesson/ttrecorder',
    'mod_minilesson/animatecss',
], function($,  log, Ajax, def, polly, ttrecorder, anim) {
  "use strict"; // jshint ;_;

  /*
  This is for the speech test helper
   */

  log.debug('MiniLesson Speech Test Helper: initialising');

var app = {
    passmark: 90,
    pointer: 1,
    jsondata: null,
    props: null,
    dryRun: false,
    language: 'en-US',
    terms: [],
    phonetics: [],
    displayterms: [],
    results: [],
    controls: {},
    ttrec: null, //a handle on the tt recorder


    init: function(props) {
      var dd = this;

      //pick up opts from html
      var theid='#amdopts_' + props.widgetid;
      var configcontrol = $(theid).get(0);
      if(configcontrol){
        dd.activitydata = JSON.parse(configcontrol.value);
        $(theid).remove();
      }else{
        //if there is no config we might as well give up
        log.debug('MiniLesson Speech Test helper: No config found on page. Giving up.');
        return;
      }

          this.init_polly();
          this.init_controls();
          this.initComponents();
          this.register_events();
    },
    init_polly: function() {
        //get the polly token
        var pollytoken = this.activitydata.token;
        var pollyregion = this.activitydata.region;
        var pollycloudpoodllurl = this.activitydata.cloudpoodllurl;
        var pollyowner = 'poodll';
        polly.init(pollytoken, pollyregion, pollyowner, pollycloudpoodllurl);
        log.debug('polly initialised');
    },

    init_controls: function() {
            log.debug('sthelper init controls');
          app.controls = {};
          app.controls.pollybutton = $("#speechtester_pollybutton");
          app.controls.pollyvoice = $("#speechtester_voice");
          app.controls.pollylanguage = $("#speechtester_language");
          app.controls.pollytext = $("#speechtester_text");
          app.controls.audioplayer = $("#speechtester_audioplayer");
          app.controls.transcribebutton = $("#speechtester_transcribebutton");
          app.controls.transcription = $("#speechtester_transcription");
          app.controls.transcriptioncoverage = $("#speechtester_transcription_coverage");
          app.controls.stt_guided = $("#speechtester_stt_guided");
          app.controls.forcestreaming = $("#speechtester_forcestreaming");
          app.controls.recorder = $("#uniqueidforspeechtester_recorderdiv");
    },

        register_events: function() {
            log.debug('sthelper register events');

            //polly button
          app.controls.pollybutton.on('click',function() {
              log.debug('pollybutton clicked');
              log.debug(app.controls.pollytext.val());
              log.debug(app.controls.pollyvoice.val());
                polly.fetch_polly_url(app.controls.pollytext.val(),'text', app.controls.pollyvoice.val()).then(function(audiourl) {
                    app.controls.audioplayer.attr('src',audiourl);
                    log.debug(audiourl);
                });
          });
          //transcribe button
            app.controls.transcribebutton.on('click',function() {
                log.debug('transcribebutton clicked');
                log.debug(app.controls.audioplayer.attr('src'));
                app.downloadAndSubmitMP3(app.controls.audioplayer.attr('src'),app.doTranscribe);
            });
        },


    // Function to download the MP3 file and submit it for transcription
    downloadAndSubmitMP3: function(url, submitFunction) {
        // Fetch the MP3 file from the URL
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                // Log the response headers for debugging
                log.debug('Response headers:', response.headers);
                return response.blob();
            })
            .then(blob => {
                // Check the blob type for debugging
                log.debug('Blob size:', blob.size);
                log.debug('Blob type:', blob.type);
                if (!blob.type.startsWith('audio/')) {
                    throw new Error('The fetched file is not an audio file.');
                }
                // Call the submit function with the Blob as an argument
                app.convertMP3ToWAV(blob).then(wavblob => {
                    submitFunction(wavblob)
                });
            })
            .catch(error => {
                log.debug('Error downloading MP3:', error);
            });
    },

    doTranscribe: function(blob) {
        //clear the existing results
       // app.controls.transcriptioncoverage.html('');
        //init the transcription results div
        app.controls.transcription.html('<i class="fa fa-spinner fa-spin" style="font-size:24px;"></i>');
        app.controls.transcription.show();

        //set the scorer if we have one
        var scorer = app.controls.recorder.data('passagehash');
        //build the form data
        var bodyFormData = new FormData();
        var blobname = Math.floor(Math.random() * 100) +  '.mp3';
        var guided = app.controls.stt_guided.prop('checked')==true;
        log.debug('guided is: ' + guided);
        var prompt = app.controls.pollytext.val();
        bodyFormData.append('audioFile', blob, blobname);
        bodyFormData.append('scorer', scorer);
        if(guided) {
            bodyFormData.append('strictmode', 'false');
        }else{
            bodyFormData.append('strictmode', 'true');
        }
        //prompt is used by whisper and other transcibers down the line
        if(guided) {
            bodyFormData.append('prompt', app.controls.pollytext.val());
        }
        bodyFormData.append('lang', app.controls.pollylanguage.val());
        bodyFormData.append('wwwroot', M.cfg.wwwroot);

        var oReq = new XMLHttpRequest();
        oReq.open("POST", app.activitydata.asrurl, true);
        oReq.onUploadProgress= function(progressEvent) {};
        oReq.onload = function(oEvent) {
            if (oReq.status === 200) {
                var respObject = JSON.parse(oReq.response);
                if(respObject.data.hasOwnProperty('transcript')) {
                    var transcript = respObject.data.transcript;
                    app.controls.transcription.text(transcript);
                    //correct the transcript
                    /*
                    app.comparePassageToTranscript(prompt,transcript).then(function(ajaxresult) {
                        var comparison = JSON.parse(ajaxresult);
                        if (comparison) {
                            var allCorrect = comparison.filter(function(e){return !e.matched;}).length==0;
                            var coverage = comparison.filter(function(e){return e.matched;}).length/comparison.length;
                            coverage = coverage * 100;
                            coverage = Math.round(coverage);
                            var tc_report = 'All correct: ' + allCorrect + '<br>';
                            tc_report += 'Coverage: ' + coverage + '%<br>';
                            if(coverage<100){
                                $.each(comparison, function (index, value) {
                                    if (!value.matched) {
                                        tc_report += 'unmatched word: ' + value.word + '<br>';
                                        //var start = value.start;
                                        //var end = value.end;
                                    }
                                });
                            }
                            app.controls.transcriptioncoverage.html(tc_report);
                        }
                    });
                    */
                }else{
                    app.controls.transcription.text("no transcript was in the result");
                }
            } else {
                app.controls.transcription.text( "error");
                log.debug(oReq.error);
            }
            app.controls.transcription.show();
        };
        try {
            oReq.send(bodyFormData);

        }catch(err){
            app.controls.transcription.text( "error");
            log.debug(err);
        }
    },

    comparePassageToTranscript: function (passage,transcript){
        return Ajax.call([{
            methodname: 'mod_minilesson_compare_passage_to_transcript',
            args: {
                passage: passage,
                transcript: transcript,
                alternatives: '',
                phonetic: '',
                language: app.controls.pollylanguage.val(),
                region: app.activitydata.region,
                cmid: app.activitydata.cmid
            },
            async: false
        }])[0];
    },

    convertMP3ToWAV: function (mp3Blob){
        return new Promise((resolve, reject) => {
            const reader = new FileReader();

            reader.onload = (event) => {
                const arrayBuffer = event.target.result;
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();

                audioContext.decodeAudioData(arrayBuffer)
                    .then((audioBuffer) => {
                        const numberOfChannels = audioBuffer.numberOfChannels;
                        const length = audioBuffer.length * numberOfChannels;
                        const sampleRate = audioBuffer.sampleRate;
                        const buffer = audioContext.createBuffer(numberOfChannels, audioBuffer.length, sampleRate);

                        for (let channel = 0; channel < numberOfChannels; channel++) {
                            const channelData = audioBuffer.getChannelData(channel);
                            buffer.copyToChannel(channelData, channel);
                        }

                        const wavBlob = app.bufferToWave(buffer);
                        resolve(wavBlob);
                    })
                    .catch((error) => {
                        reject(error);
                    });
            };
            reader.onerror = (error) => {
                reject(error);
            }
            reader.readAsArrayBuffer(mp3Blob);
        });
    },

    bufferToWave: function(audioBuffer) {
        const numberOfChannels = audioBuffer.numberOfChannels;
        const sampleRate = audioBuffer.sampleRate;
        const length = audioBuffer.length * numberOfChannels * 2 + 44;
        const buffer = new ArrayBuffer(length);
        const view = new DataView(buffer);
        let pos = 0;

        const writeString = (view, offset, string) => {
            for (let i = 0; i < string.length; i++) {
                view.setUint8(offset + i, string.charCodeAt(i));
            }
        };

        const writeUint32 = (view, offset, value) => {
            view.setUint32(offset, value, true);
        };

        const writeUint16 = (view, offset, value) => {
            view.setUint16(offset, value, true);
        };

        writeString(view, pos, 'RIFF'); pos += 4;
        writeUint32(view, pos, length - 8); pos += 4;
        writeString(view, pos, 'WAVE'); pos += 4;
        writeString(view, pos, 'fmt '); pos += 4;
        writeUint32(view, pos, 16); pos += 4;
        writeUint16(view, pos, 1); pos += 2;
        writeUint16(view, pos, numberOfChannels); pos += 2;
        writeUint32(view, pos, sampleRate); pos += 4;
        writeUint32(view, pos, sampleRate * numberOfChannels * 2); pos += 4;
        writeUint16(view, pos, numberOfChannels * 2); pos += 2;
        writeUint16(view, pos, 16); pos += 2;
        writeString(view, pos, 'data'); pos += 4;
        writeUint32(view, pos, length - pos - 4); pos += 4;

        function floatTo16BitPCM(output, offset, input) {
            for (let i = 0; i < input.length; i++, offset += 2) {
                const s = Math.max(-1, Math.min(1, input[i]));
                output.setInt16(offset, s < 0 ? s * 0x8000 : s * 0x7FFF, true);
            }
        }

        for (let channel = 0; channel < numberOfChannels; channel++) {
            floatTo16BitPCM(view, pos, audioBuffer.getChannelData(channel));
            pos += audioBuffer.length * 2;
        }

        return new Blob([buffer], { type: 'audio/wav' });
    },

     initComponents: function() {

              var theCallback = function(message) {

                switch (message.type) {
                  case 'recording':
                   app.controls.transcription.html('<i class="fa fa-spinner fa-spin" style="font-size:24px;"></i>');
                   break;

                  case 'speech':
                    var speechtext = message.capturedspeech;
                    log.debug('speechtext:',speechtext);
                    app.controls.transcription.text(speechtext);
                    app.controls.transcription.show();

                } //end of switch message type
              };

              //init tt recorder
              var opts = {};
              opts.uniqueid = app.activitydata.uniqueid;
              opts.callback = theCallback;
              opts.stt_guided= app.controls.stt_guided.prop('checked')==true;
              app.ttrec = ttrecorder.clone();
              app.ttrec.init(opts);

        },


      }; //end of app definition
      return app;


});