define(['jquery', 'core/log', 'mod_minilesson/definitions','mod_minilesson/cloudpoodllloader',
    'mod_minilesson/ttrecorder','core/notification','core/str'],
    function($, log, def,cloudpoodll, ttrecorder, notification, str) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the Passage Reading item type
   */

  log.debug('MiniLesson Passage Reading: initialising');

  var app= {

    allwords: {},
    totalcorrect: 0,
    strings: {},

    //for making multiple instances
      clone: function () {
          return $.extend(true, {}, this);
     },

    init: function(index, itemdata, quizhelper) {
      this.index = index;
      this.itemdata = itemdata;
      this.quizhelper = quizhelper;
      this.init_strings();
      this.init_components(quizhelper,itemdata);
      this.register_events(index, itemdata, quizhelper);

    },

    init_strings: function(){
      var self = this; 
      str.get_strings([
          { "key": "reattempt", "component": 'mod_minilesson'},
          { "key": "reallyreattempt", "component": 'mod_minilesson'},
      ]).done(function (s) {
          var i = 0;
          self.strings.reattempt = s[i++];
          self.strings.reallyreattempt = s[i++];
      });
  }, 

    next_question: function() {
      var self = this;
      var stepdata = {};
      stepdata.index = self.index;
      stepdata.hasgrade = true;
      stepdata.totalitems = self.allwords.length;
      stepdata.correctitems = self.totalcorrect;
      var percent = 0;
      if(self.allwords.length> 0) {
        percent = Math.round((self.totalcorrect /self.allwords.length) * 100 );
      }
      stepdata.grade = percent;
      this.quizhelper.do_next(stepdata);
    },

    register_events: function(index, itemdata, quizhelper) {
      var self = this;
      
      self.nextbutton.on('click', function(e) {
        e.preventDefault();
        self.next_question();
      });

      self.reattemptbutton.on('click', function(e) {
        e.preventDefault();
        notification.confirm(self.strings.reattempt, 
                            self.strings.reallyreattempt,
                            self.strings.reattempt,'',
                            function(){
                                self.reattemptcontainer.hide();
                                self.recordercontainer.show();
                                 //Reset all words css
                                self.allwords.removeClass("pr_correct pr_incorrect pr_unreached");
                                self.allspaces.removeClass("pr_correct pr_incorrect pr_unreached");
                            });
      });

    },

    init_components: function(quizhelper,itemdata){
          var self=this;
          self.allwords = $("#" + self.itemdata.uniqueid + "_container .mod_minilesson_mu_passage_word");
          self.allspaces = $("#" + self.itemdata.uniqueid + "_container .mod_minilesson_mu_passage_space");
          self.recordercontainer = $("#" + self.itemdata.uniqueid + "_container .ml_passagereading_speakbtncontainer");
          self.reattemptcontainer = $("#" + self.itemdata.uniqueid + "_container .ml_passagereading_reattemptcontainer");
          self.reattemptbutton =  self.reattemptcontainer.find(".ml_reattemptbutton");
          self.nextbutton = $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton");

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

      //Reset all words css
      self.allwords.removeClass("pr_correct pr_incorrect pr_unreached");
      self.allspaces.removeClass("pr_correct pr_incorrect pr_unreached");

      //how many correct
      var allCorrect = comparison.filter(function(e){return !e.matched;}).length==0;
      
      //mark up the words as correct or not
      if (allCorrect && comparison && comparison.length > 0) {

        log.debug("gotComparison: all correct");
        self.allwords.addClass("pr_correct");
        self.allspaces.addClass("pr_correct");

      } else {
        log.debug("gotComparison: not all correct");
        var lastmatched = 0;
        comparison.forEach(function(obj) {
          var theword = $("#" + self.itemdata.uniqueid + "_container .mod_minilesson_mu_passage_word[data-wordnumber='" + obj.wordnumber + "']");
          var thespace = $("#" + self.itemdata.uniqueid + "_container .mod_minilesson_mu_passage_space[data-wordnumber='" + obj.wordnumber + "']");
          if(!obj.matched){
            theword.addClass("pr_incorrect");
            thespace.addClass("pr_incorrect");
          } else {
            theword.addClass("pr_correct");
            thespace.addClass("pr_correct");
            if(lastmatched < obj.wordnumber){
              lastmatched = obj.wordnumber;
            }
          }
        });

        //2nd pass now we know what each word is
        comparison.forEach(function(obj) {
          var theword = $("#" + self.itemdata.uniqueid + "_container .mod_minilesson_mu_passage_word[data-wordnumber='" + obj.wordnumber + "']");
          var thespace = $("#" + self.itemdata.uniqueid + "_container .mod_minilesson_mu_passage_space[data-wordnumber='" + obj.wordnumber + "']");
          var nextword = $("#" + self.itemdata.uniqueid + "_container .mod_minilesson_mu_passage_word[data-wordnumber='" + (obj.wordnumber + 1) + "']");
          //mark incorrect as unreached if after last match
          if(lastmatched < obj.wordnumber && theword.hasClass('pr_incorrect')){
            theword.addClass("pr_unreached");
            thespace.addClass("pr_unreached");
            theword.removeClass("pr_incorrect");
            thespace.removeClass("pr_incorrect");
          }
          //clear formatting on spaces in between correct/incorrect and incorrect/correct words
          if(nextword.length>0){
            if(theword.hasClass('pr_incorrect') && nextword.hasClass('pr_correct')){
              thespace.removeClass('pr_incorrect');
            }else if(theword.hasClass('pr_correct') && nextword.hasClass('pr_incorrect')){
              thespace.removeClass('pr_correct');
            }
          }
        });
      }

      //update our scores
      var correctwords = $("#" + self.itemdata.uniqueid + "_container .mod_minilesson_mu_passage_word.pr_correct");
      self.totalcorrect = correctwords.length;
      log.debug(("totalcorrect", self.totalcorrect));
      log.debug(("allwords", self.allwords.length));

       //Hide the recorder and show the reattempt button
       self.recordercontainer.hide();
       self.reattemptcontainer.show();

       //To auto transition you could do this
       var moveon = false;
       if (moveon) {
          setTimeout(function() {
            self.end();
          }, 2200);
        }
    },

  };//end of return objects
  return app;
});