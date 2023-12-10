define(['jquery',
    'core/log',
    'core/ajax',
    'mod_minilesson/definitions',
    'mod_minilesson/pollyhelper',
    'mod_minilesson/cloudpoodllloader',
    'mod_minilesson/ttrecorder',
    'mod_minilesson/animatecss',
], function($,  log, Ajax, def, polly, cloudpoodll, ttrecorder, anim) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the quiz stage
   */

  log.debug('MiniLesson ST Helper: initialising');

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
        log.debug('MiniLesson ST helper: No config found on page. Giving up.');
        return;
      }

         this.init_polly();

          this.init_controls();
        //  this.initComponents();
          this.register_events();
    },
    init_polly: function() {

        //get the polly token
        var pollytoken = this.activitydata.token;
        var pollyregion = this.activitydata.region;
        var pollyowner = 'poodll';
        polly.init(pollytoken, pollyregion, pollyowner);
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
          app.controls.opentranscription = $("#speechtester_open");

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

    downloadAndSubmitMP3: function(url, submitFunction) {
        // Fetch the MP3 file from the URL
        fetch(url)
            .then(response => response.blob())
            .then(blob => {
                // Call the submit function with the Blob as an argument
                submitFunction(blob);
            })
            .catch(error => {
                log.debug('Error downloading MP3:', error);
            });
    },

    doTranscribe: function(blob) {
        //clear the existing results
        app.controls.transcriptioncoverage.html('');
        app.controls.transcription.html('');

        var bodyFormData = new FormData();
        var blobname = Math.floor(Math.random() * 100) +  '.wav';
        var guided = app.controls.opentranscription.prop('checked')==false;
        log.debug('guided is: ' + guided);
        var prompt = app.controls.pollytext.val();
        bodyFormData.append('audioFile', blob, blobname);
        bodyFormData.append('scorer', '');
        if(guided) {
            bodyFormData.append('strictmode', 'false');
        }else{
            bodyFormData.append('strictmode', 'true');
        }
        //prompt is used by whisper and other transcibers down the line
        if(guided){
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
                }else{
                    app.controls.transcription.text("no transcript was in the result");
                }
            } else {
                app.controls.transcription.text( "error");
                log.debug(oReq.error);
            }
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

     initComponents: function() {

              var theCallback = function(message) {

                switch (message.type) {
                  case 'recording':

                    break;

                  case 'speech':
                    log.debug("speech at speechcards");
                    var speechtext = message.capturedspeech;
                    var spoken_clean  = quizhelper.cleanText(speechtext);
                    var correct_clean = quizhelper.cleanText(app.terms[app.pointer - 1]);
                    var correctphonetic = app.phonetics[app.pointer - 1];
        log.debug('speechtext:',speechtext);
        log.debug('spoken:',spoken_clean);
        log.debug('correct:',correct_clean);
                    //Similarity check by character matching
                    var similarity_js = quizhelper.similarity(spoken_clean, correct_clean);
                    log.debug('JS similarity: ' + spoken_clean + ':' + correct_clean + ':' + similarity_js);

                    //Similarity check by direct-match/acceptable-mistranscription
                    if (similarity_js >= app.passmark ||
                      app.wordsDoMatch(spoken_clean, correct_clean)) {
                      log.debug('local match:' + ':' + spoken_clean + ':' + correct_clean);
                      app.showStarRating(100);
                      app.flagCorrectAndTransition();
                      return;
                    }

                    //Similarity check by phonetics(ajax)
                    quizhelper.checkByPhonetic(correct_clean, spoken_clean, correctphonetic, app.language).then(function(similarity_php) {
                      if (similarity_php === false) {
                        return $.Deferred().reject();
                      } else {
                        log.debug('PHP similarity: ' + spoken_clean + ':' + correct_clean + ':' + similarity_php);

                        if (similarity_php >= app.passmark) {
                            app.showStarRating(similarity_php);
                            app.flagCorrectAndTransition();
                        }else{
                            //show the greater of the ratings
                            app.showStarRating(Math.max(similarity_js,similarity_php));
                        }
                      } //end of if check_by_phonetic result
                    }); //end of check by phonetic

                } //end of switch message type
              };



             if(quizhelper.use_ttrecorder()) {
                 //init tt recorder
                 var opts = {};
                 opts.uniqueid = itemdata.uniqueid;
                 opts.callback = theCallback;
                 opts.stt_guided=quizhelper.is_stt_guided();
                 app.ttrec = ttrecorder.clone();
                 app.ttrec.init(opts);
                 //init prompt for first card
                 //in some cases ttrecorder wants to know the target
                 app.ttrec.currentPrompt=app.displayterms[app.pointer - 1];

             }else{
                 //init cloudpoodll push recorder
                 cloudpoodll.init('minilesson-recorder-speechcards-' + itemdata.id, theCallback);
             }


              //init progress dots
              app.progress_dots(app.results, app.terms);

              app.initSlider();


        },



        wordsDoMatch: function(phraseheard, currentphrase) {
          //lets lower case everything
          phraseheard = quizhelper.cleanText(phraseheard);
          currentphrase = quizhelper.cleanText(currentphrase);
          if (phraseheard == currentphrase) {
            return true;
          }
          return false;
        },

        check: function(correct) {
          var points = 1;
          if (correct == true) {
            points = 1;
          } else {
            points = 0;
          }
          var result = {
            points: points
          };
          app.results.push(result);
        },


      }; //end of app definition
      return app;


});