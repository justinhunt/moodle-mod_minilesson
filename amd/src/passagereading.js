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
          self.allwords = $("#" + self.itemdata.uniqueid + "_container.mod_minilesson_mu_passage_word");
          self.thebutton = "thettrbutton"; // To Do impl. this
          var theCallback = function(message) {

            switch (message.type) {
              case 'recording':

                break;

              case 'speech':
                log.debug("speech at speechcards");
                var speechtext = message.capturedspeech;
                var spoken_clean  = quizhelper.cleanText(speechtext);
                var phonetic = ''; // TO DO - add phonetic option
                log.debug('speechtext:',speechtext);
                log.debug('spoken:',spoken_clean);
                self.getComparison(
                  self.itemdata.passagetext,
                  spoken_clean,
                  phonetic,
                  function(comparison) {
                      self.gotComparison(comparison, message);
                  }
              );
               
    
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


    }, //end of init components

    getComparison: function(passage, transcript, phonetic, callback) {
      var self = this;
      
      //TO DO disable the TT Recorder button
      $("#" + self.thebutton ).prop("disabled", true);

      self.quizhelper.comparePassageToTranscript(passage,transcript,phonetic,self.itemdata.language).then(function(ajaxresult) {
            var payloadobject = JSON.parse(ajaxresult);
            if (payloadobject) {
                callback(payloadobject);
            } else {
                callback(false);
            }
       });

    },

    gotComparison: function(comparison, typed) {
      var self = this;
      log.debug("gotComparison");

      //TO DO mark up the words as correct
      self.allwords.removeClass("pr_correct pr_incorrect");

      var allCorrect = comparison.filter(function(e){return !e.matched;}).length==0;
      
      if (allCorrect && comparison && comparison.length>0) {
        log.debug("gotComparison: all correct");
        //TO DO mark up the words as correct
        $("#" + self.allwords ).addClass("pr_correct");

        // Mark the item as answered and correct.
        self.items[self.game.pointer].answered = true;
        self.items[self.game.pointer].correct = true;
        self.items[self.game.pointer].typed = typed;

        //TO DO disable the TT Recorder button
        $("#" + self.thebutton ).prop("disabled", true);

        //Move on to the next item
        if (self.game.pointer < self.items.length - 1) {
          setTimeout(function() {
            self.game.pointer++;
            self.nextPrompt();
          }, 2200);
        } else {
            self.end();
        }

      } else {
        log.debug("gotComparison: not all correct");

        //mark up the words as correct or not
        comparison.forEach(function(obj) {
          log.debug("#" + self.itemdata.uniqueid + "_container .mod_minilesson_mu_passage_word[data-wordnumber='" + obj.wordnumber + "']");
          if(!obj.matched){
            $("#" + self.itemdata.uniqueid + "_container .mod_minilesson_mu_passage_word[data-wordnumber='" + obj.wordnumber + "']").addClass("pr_incorrect");
          } else {
            $("#" + self.itemdata.uniqueid + "_container .mod_minilesson_mu_passage_word[data-wordnumber='" + obj.wordnumber + "']").addClass("pr_correct");
          }
        });
        

      }

    },

  };//end of return objects
  return app;
});