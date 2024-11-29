define(['jquery', 'core/log', 'mod_minilesson/definitions','mod_minilesson/cloudpoodllloader',
    'mod_minilesson/ttrecorder',],
    function($, log, def,cloudpoodll, ttrecorder) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the Passage Reading item type
   */

  log.debug('MiniLesson Passage Reading: initialising');

  var app= {

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
      var stepdata = {};
      stepdata.index = self.index;
      stepdata.hasgrade = true;
      stepdata.totalitems = 1;
      stepdata.correctitems = percent>0?1:0;
      stepdata.grade = percent;
      this.quizhelper.do_next(stepdata);
    },

    register_events: function(index, itemdata, quizhelper) {
      var self = this;
      var nextbutton = $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton");
      
      nextbutton.on('click', function(e) {
        self.next_question(0);
      });

    },

    init_components: function(quizhelper,itemdata){
          var self=this;
          var theCallback = function(message) {

            switch (message.type) {
              case 'recording':

                break;

              case 'speech':
                log.debug("speech at speechcards");
                var speechtext = message.capturedspeech;
                var spoken_clean  = quizhelper.cleanText(speechtext);
               // var correct_clean = app.quizhelper.cleanText(app.terms[app.pointer - 1]);
               // var correctphonetic = app.phonetics[app.pointer - 1];
                log.debug('speechtext:',speechtext);
                log.debug('spoken:',spoken_clean);
               // log.debug('correct:',correct_clean);
    
            } //end of switch message type
          };



        if(quizhelper.use_ttrecorder()) {
            //init tt recorder
            var opts = {};
            opts.uniqueid = itemdata.uniqueid;
            opts.callback = theCallback;
            opts.stt_guided=quizhelper.is_stt_guided();
            self.ttrec = ttrecorder.clone();
            self.ttrec.init(opts);
            //init prompt for first card
            //in some cases ttrecorder wants to know the target
            //app.ttrec.currentPrompt=app.displayterms[app.pointer - 1];

        }else{
            //init cloudpoodll push recorder
            cloudpoodll.init('minilesson-recorder-passagereading-' + itemdata.id, theCallback);
        }


    }//end of init components

  };//end of return objects
  return app;
});