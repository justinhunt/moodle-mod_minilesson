define(['jquery', 'core/log', 'mod_minilesson/definitions','mod_minilesson/cloudpoodllloader',
    'mod_minilesson/ttrecorder',],
    function($, log, def, cloudpoodll, ttrecorder) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the free speaking item type
   */

  log.debug('MiniLesson FreeSpeaking: initialising');

  return {

    //for making multiple instances
      clone: function () {
          return $.extend(true, {}, this);
     },

    init: function(index, itemdata, quizhelper) {
      this.itemdata = itemdata;
      this.quizhelper = quizhelper;
      this.init_components(quizhelper,itemdata);
      this.register_events(index, itemdata, quizhelper);

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
      var nextbutton = $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton");
      
      nextbutton.on('click', function(e) {
        self.next_question(0);
      });

    },

    init_components: function(quizhelper,itemdata){
      var self=this;
      self.allwords = $("#" + self.itemdata.uniqueid + "_container.mod_minilesson_mu_passage_word");
      self.thebutton = "thettrbutton"; // To Do impl. this
      self.resultbox = $("#" + self.itemdata.uniqueid + "_container .ml_freespeaking_resultbox");

      var theCallback = function(message) {

        switch (message.type) {
          case 'recording':
            break;

          case 'speech':
            log.debug("speech at speechcards");
            var speechtext = message.capturedspeech;
            //var spoken_clean  = quizhelper.cleanText(speechtext);
            //log.debug('speechtext:',speechtext);
            //log.debug('spoken:',spoken_clean);
            self.do_evaluation(
              speechtext
            );    
        } //end of switch message type
      };

      if(quizhelper.use_ttrecorder()) {
          //init tt recorder
          var opts = {};
          opts.uniqueid = itemdata.uniqueid;
          opts.callback = theCallback;
          opts.stt_guided=false;
          self.ttrec = ttrecorder.clone();
          self.ttrec.init(opts);

      }else{
          //init cloudpoodll push recorder
          cloudpoodll.init('minilesson-recorder-passagereading-' + itemdata.id, theCallback);
      }
    }, //end of init components

    do_evaluation: function(speechtext) {
      log.debug('do_evaluation:',speechtext);
      this.resultbox.html(speechtext);

    },
  };
});