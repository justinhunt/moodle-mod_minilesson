define(['jquery', 'core/log', 'mod_minilesson/definitions', 'mod_minilesson/pollyhelper',
    'mod_minilesson/cloudpoodllloader','mod_minilesson/ttrecorder'], function($, log, def, polly,cloudpoodll, ttrecorder) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the quiz stage
   */

  log.debug('Poodll Time Multiaudio: initialising');

  return {

      passmark: 85,

    //for making multiple instances
      clone: function () {
          return $.extend(true, {}, this);
     },

    init: function(index, itemdata, quizhelper) {
      this.register_events(index, itemdata, quizhelper);
      this.init_components(index, itemdata, quizhelper);
    },
    next_question: function(percent) {
      var self = this;
      var stepdata = {};
      stepdata.index = self.index;
      stepdata.hasgrade = true;
      stepdata.totalitems = 1;
      stepdata.correctitems = percent>0?1:0;
      stepdata.grade = percent;
      self.quizhelper.do_next(stepdata);
    },
    register_events: function(index, itemdata, quizhelper) {
      
      var self = this;
      self.index = index;
      self.quizhelper = quizhelper;
      
      $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton").on('click', function(e) {
        self.next_question(0);
      });
      
      $("#" + itemdata.uniqueid + "_container .minilesson_mc_response").on('click', function(e) {
        //if disabled =>just return (already answered)
          if($("#" + itemdata.uniqueid + "_container .minilesson_mc_response").hasClass('minilesson_mc_disabled')){
            return;
          }

          //get selected item index
          var checked = $(this).data('index');

          //disable the answers, cos its answered
          $("#" + itemdata.uniqueid + "_container .minilesson_mc_response").addClass('minilesson_mc_disabled');

        //reveal answers
        $("#" + itemdata.uniqueid + "_container .minilesson_mc_wrong").show();
        $("#" + itemdata.uniqueid + "_option" + itemdata.correctanswer + " .minilesson_mc_wrong").hide();
        $("#" + itemdata.uniqueid + "_option" + itemdata.correctanswer + " .minilesson_mc_right").show();
        
        //highlight selected answers
        $("#" + itemdata.uniqueid + "_option" + checked).addClass('minilesson_mc_selected');


        var percent = checked == itemdata.correctanswer ? 100 : 0;
        
        $(".minilesson_nextbutton").prop("disabled", true);
        setTimeout(function() {
          $(".minilesson_nextbutton").prop("disabled", false);
          self.next_question(percent);
        }, 2000);
        
      });
      
    },//end of register events

    init_components: function(index, itemdata, quizhelper) {
        var app= this;
        var correcttext = $("#" + itemdata.uniqueid + "_option" + itemdata.correctanswer).text();
        var cleancorrecttext = quizhelper.cleanText(correcttext);

        var theCallback = function(message) {

            switch (message.type) {
                case 'recording':

                    break;

                case 'speech':
                    log.debug("speech at multiaudio");
                    var speechtext = message.capturedspeech;
                    var cleanspeechtext = quizhelper.cleanText(speechtext);
                    var spoken = cleanspeechtext;
                    log.debug('speechtext:',speechtext);
                    log.debug('spoken:',spoken);
                    log.debug('correct:',cleancorrecttext);
                    //Similarity check by character matching
                    var similarity = quizhelper.similarity(spoken, cleancorrecttext);
                    log.debug('JS similarity: ' + spoken + ':' + cleancorrecttext + ':' + similarity);

                    //Similarity check by direct-match/acceptable-mistranscription
                    if (similarity >= app.passmark ||
                        app.wordsDoMatch(quizhelper, cleanspeechtext, cleancorrecttext)) {
                        log.debug('local match:' + ':' + spoken + ':' + cleancorrecttext);
                        app.flagCorrectAndTransition();
                        return;
                    }

                    //Similarity check by phonetics(ajax)
                    quizhelper.checkByPhonetic(spoken, cleancorrecttext, itemdata.language).then(function(similarity) {
                        if (similarity === false) {
                            return $.Deferred().reject();
                        } else {
                            log.debug('PHP similarity: ' + spoken + ':' + correct + ':' + similarity);
                            if (similarity >= app.passmark) {
                                app.flagCorrectAndTransition();
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
            ttrecorder.clone().init(opts);
        }else{
            //init cloudpoodll push recorder
            cloudpoodll.init('minilesson-recorder-multiaudio-' + itemdata.id, theCallback);
        }

    }, //end of init components

    wordsDoMatch: function(quizhelper, phraseheard, currentphrase) {
        //lets lower case everything
        phraseheard = quizhelper.cleanText(phraseheard);
        currentphrase = quizhelper.cleanText(currentphrase);
        if (phraseheard == currentphrase) {
            return true;
        }
        return false;
    },
  };
});