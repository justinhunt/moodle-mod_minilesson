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

  return {



      //for making multiple instances
      clone: function () {
          return $.extend(true, {}, this);
      },

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

      //this.init_app(index, itemdata, quizhelper);
    },

    init_app: function(index, itemdata, quizhelper) {

      console.log(itemdata);

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

        init: function() {



          this.init_controls();
          this.initComponents();
          this.register_events();
        },

        init_controls: function() {
          app.controls = {};
          app.controls.star_rating = $("#" + itemdata.uniqueid + "_container .minilesson_star_rating");
          app.controls.next_button = $("#" + itemdata.uniqueid + "_container .minilesson-speechcards_nextbutton");
          app.controls.slider = $("#" + itemdata.uniqueid + "_container .minilesson_speechcards_target_phrase");
        },

        register_events: function() {

          $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton").on('click', function(e) {
            app.next_question();
          });

          app.controls.next_button.click(function() {
            //user has given up ,update word as failed
            app.check(false);

            //transition if required
            if (app.is_end()) {
              setTimeout(function() {
                app.do_end();
              }, 200);
            } else {
              setTimeout(function() {
                app.do_next();
              }, 200);
            }

          });
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
      app.init();

    } //end of init_App


  }; //end of return value
});