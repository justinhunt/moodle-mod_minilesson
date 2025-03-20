define(['jquery', 'core/log', 'mod_minilesson/definitions','mod_minilesson/cloudpoodllloader',
    'mod_minilesson/ttrecorder', 'core/templates'],
    function($, log, def,cloudpoodll, ttrecorder, templates) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the fluency item type
   */

  log.debug('MiniLesson Fluency: initialising');

  return {

    speechConfig: null,
    referencetext: 'I met my love by the gas works wall',

    //for making multiple instances
      clone: function () {
          return $.extend(true, {}, this);
     },

    init: function(index, itemdata, quizhelper) {
      this.itemdata = itemdata;
      this.register_events(index, itemdata, quizhelper);
      this.referencetext = itemdata.referencetext;
      this.init_components(quizhelper, itemdata);
    },

    init_components: function(quizhelper,itemdata){
      var self=this;

      self.thebutton = "thettrbutton"; // To Do impl. this
      self.wordcount = $("#" + self.itemdata.uniqueid + "_container span.ml_wordcount");
      self.actionbox = $("#" + self.itemdata.uniqueid + "_container div.ml_fluency_actionbox");
      self.pendingbox = $("#" + self.itemdata.uniqueid + "_container div.ml_fluency_pendingbox");
      self.resultsbox = $("#" + self.itemdata.uniqueid + "_container div.ml_fluency_resultsbox");
      self.timerdisplay = $("#" + self.itemdata.uniqueid + "_container div.ml_fluency_timerdisplay");

      // Callback: Recorder updates.
      var recorderCallback = function(message) {

        switch (message.type) {
          case 'recording':
            break;

          case 'pronunciation_results':
            var speechresults= message.results;
            self.do_evaluation(speechresults);    
        } //end of switch message type
      };

      if(quizhelper.use_ttrecorder()) {
          //init tt recorder
          var opts = {};
          opts.uniqueid = itemdata.uniqueid;
          opts.callback = recorderCallback;
          opts.stt_guided=false;
          opts.referencetext=this.referencetext;
          self.ttrec = ttrecorder.clone();
          self.ttrec.init(opts);

      }else{
          //init cloudpoodll push recorder
          cloudpoodll.init('minilesson-recorder-fluency-' + itemdata.id, recorderCallback);
      }
    }, //end of init components



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
      var nextbutton = $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton");
      
      nextbutton.on('click', function(e) {
        self.next_question(0);
      });

    },

    do_evaluation: function (pronunciation_result) {
      log.debug("Accuracy score: ", pronunciation_result.accuracyScore);
      log.debug("Pronunciation score: ", pronunciation_result.pronunciationScore);
      log.debug("Completeness score : ", pronunciation_result.completenessScore);
      log.debug("Fluency score: ", pronunciation_result.fluencyScore);
      log.debug("Prosody score: ", pronunciation_result.prosodyScore);
      
      log.debug("  Word-level details:");
      pronunciation_result.detailResult.Words.forEach(function (word, idx) {
          console.log("    ", idx + 1, ": word: ", word.Word, "\taccuracy score: ", word.PronunciationAssessment.AccuracyScore, "\terror type: ", word.PronunciationAssessment.ErrorType, ";");
      });
    },
  };
});