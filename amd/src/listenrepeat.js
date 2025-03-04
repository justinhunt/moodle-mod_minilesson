define(['jquery',
      'core/log',
      'core/ajax',
      'mod_minilesson/definitions',
      'mod_minilesson/pollyhelper',
      'mod_minilesson/cloudpoodllloader',
      'mod_minilesson/ttrecorder',
      'mod_minilesson/animatecss',
      'core/templates'
    ], function($, log, ajax, def, polly, cloudpoodll, ttrecorder, anim, templates) {
  "use strict"; // jshint ;_;

  log.debug('MiniLesson listen and repeat: initialising');

  return {

      //a handle on the tt recorder
      ttrec: null,

      //for making multiple instances
      clone: function () {
          return $.extend(true, {}, this);
      },

      init: function(index, itemdata, quizhelper) {
        var self = this;
        var theCallback = function(message) {

            switch (message.type) {
                case 'recording':
                    break;

                case 'speech':
                    log.debug("speech at listen_repeat");
                    //var wordcount = quizhelper.count_words(message.capturedspeech);
                    self.getComparison(
                        self.items[self.game.pointer].target,
                        message.capturedspeech,
                        self.items[self.game.pointer].phonetic,
                        function(comparison) {
                            self.gotComparison(comparison, message);
                        }
                    );
                    break;

            }

        };

        if(quizhelper.use_ttrecorder()) {
            var opts = {};
            opts.uniqueid = itemdata.uniqueid;
            opts.callback = theCallback;
            opts.stt_guided=quizhelper.is_stt_guided();
            opts.wwwroot=quizhelper.is_stt_guided();
            self.ttrec = ttrecorder.clone();
            self.ttrec.init(opts);
        }else{
            //init cloudpoodll push recorder
            cloudpoodll.init('minilesson-recorder-listenrepeat-' + itemdata.id, theCallback);
        }

        self.itemdata = itemdata;
        self.quizhelper = quizhelper;
        self.index = index;

        //anim
        var animopts = {};
        animopts.useanimatecss= quizhelper.useanimatecss;
        anim.init(animopts);

        self.register_events();
        self.setvoice();
        self.getItems();
    },

    next_question:function(percent){
      var self=this;
      var stepdata = {};
      stepdata.index = self.index;
      stepdata.hasgrade = true;
      stepdata.totalitems=self.items.length;
      stepdata.correctitems=self.items.filter(function(e) {return e.correct;}).length;
      stepdata.grade = Math.round((stepdata.correctitems/stepdata.totalitems)*100);
      self.quizhelper.do_next(stepdata);
    },

    show_item_review:function(){
      var self=this;
      var review_data = {};
      review_data.items = self.items;
      review_data.totalitems=self.items.length;
      review_data.correctitems=self.items.filter(function(e) {return e.correct;}).length;

      //display results
      var gamebox= $("#" + self.itemdata.uniqueid + "_container .landr_game");
      var controlsbox = $("#" + self.itemdata.uniqueid + "_container .landr_controls");
      var recorderbox = $("#" + self.itemdata.uniqueid + "_container .landr_speakbtncontainer");
      var resultsbox = $("#" + self.itemdata.uniqueid + "_container .landr_resultscontainer");
      templates.render('mod_minilesson/listitemresults',review_data).then(
        function(html,js){
            resultsbox.html(html);
            //show and hide
            resultsbox.show();
            gamebox.hide();
            controlsbox.hide();
            recorderbox.hide();
            // Run js for audio player events
            templates.runTemplateJS(js);
        }
      );// End of templates
    },

    register_events: function() {

      var self = this;
      //on next button click
      $("#" + self.itemdata.uniqueid + "_container .minilesson_nextbutton").on('click', function(e) {
        self.next_question();
      });
      //on start button click
      $("#" + self.itemdata.uniqueid + "_container .landr_start_btn").on("click", function() {
        self.start();
      });
      //on listen button click
      $("#" + self.itemdata.uniqueid + "_container .landr_listen_btn").on("click", function() {
        self.items[self.game.pointer].audio.load();
        self.items[self.game.pointer].audio.play();
      });
      //on skip button click
      $("#" + self.itemdata.uniqueid + "_container .landr_skip_btn").on("click", function() {
        //disable the buttons
        $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", true);
        //reveal the prompt
        $("#" + self.itemdata.uniqueid + "_container .landr_speech.landr_teacher_left").text(self.items[self.game.pointer].displayprompt + "");
        //reveal the answer
        $("#" + self.itemdata.uniqueid + "_container .landr_targetWord").each(function() {
          var realidx = $(this).data("realidx");
          var landr_targetWord = self.items[self.game.pointer].landr_targetWords[realidx];
          $(this).val(landr_targetWord);
        });

        //mark as answered and incorrect
        self.items[self.game.pointer].answered = true;
        self.items[self.game.pointer].correct = false;

        //next prompt or end
        if (self.game.pointer < self.items.length - 1) {
          //move on after short time to next prompt
          setTimeout(function() {
            self.game.pointer++;
            self.nextPrompt();
          }, 2200);
        //end question
        } else {
          self.end();
        }
      });
      
    },
    

    game: {
      pointer: 0
    },
    usevoice: '',
    setvoice: function() {
            var self = this;
            self.usevoice = self.itemdata.usevoice;
            self.voiceoption=self.itemdata.voiceoption;
            return;
    },
    getItems: function() {
      var self = this;
      var text_items = self.itemdata.sentences;

      self.items = text_items.map(function(target) {
        return {
          landr_targetWords: target.sentence.trim().split(self.quizhelper.spliton_regexp).filter(function(e) {
            return e !== "";
          }),
          target: target.sentence,
          prompt: target.prompt,
          displayprompt: target.displayprompt,
          phonetic: target.phonetic,
          typed: "",
          answered: false,
          correct: false,
          audio: null
        };
      }).filter(function(e) {
        return e.target !== "";
      });


      $.each(self.items, function(index, item) {
        polly.fetch_polly_url(item.prompt,  self.voiceoption, self.usevoice).then(function(audiourl) {
          item.audio = new Audio();
          item.audio.src = audiourl;
          if (self.items.filter(function(e) {
              return e.audio === null;
            }).length === 0) {
            self.appReady();
          }
        });
      });
    },
    appReady: function() {
      var self = this;
      $("#" + self.itemdata.uniqueid + "_container .landr_not_loaded").hide();
      $("#" + self.itemdata.uniqueid + "_container .landr_loaded").show();
      $("#" + self.itemdata.uniqueid + "_container .landr_start_btn").prop("disabled", false);
    },
    gotComparison: function(comparison, typed) {
      var self = this;

      $("#" + self.itemdata.uniqueid + "_container .landr_targetWord").removeClass("landr_correct landr_incorrect");
      $("#" + self.itemdata.uniqueid + "_container .landr_feedback").removeClass("fa fa-check fa-times");

      var allCorrect = comparison.filter(function(e){return !e.matched;}).length==0;
      
      if (allCorrect && comparison && comparison.length>0) {
        
        $("#" + self.itemdata.uniqueid + "_container .landr_targetWord").addClass("landr_correct");
        $("#" + self.itemdata.uniqueid + "_container .landr_feedback").addClass("fa fa-check");
        $("#" + self.itemdata.uniqueid + "_container .landr_speech.landr_teacher_left").text(self.items[self.game.pointer].displayprompt + "");

        self.items[self.game.pointer].answered = true;
        self.items[self.game.pointer].correct = true;
        self.items[self.game.pointer].typed = typed;

        $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", true);
        if (self.game.pointer < self.items.length - 1) {
          setTimeout(function() {
            self.game.pointer++;
            self.nextPrompt();
          }, 2200);
        } else {
            self.end();
        }

      } else {
        //mark up the words as correct or not
        comparison.forEach(function(obj) {
          if(!obj.matched){
            $("#" + self.itemdata.uniqueid + "_container .landr_targetWord[data-idx='" + obj.wordnumber + "']").addClass("landr_incorrect");
            $("#" + self.itemdata.uniqueid + "_container .landr_feedback[data-idx='" + obj.wordnumber + "']").addClass("fa fa-times");
          } else {
            $("#" + self.itemdata.uniqueid + "_container .landr_targetWord[data-idx='" + obj.wordnumber + "']").addClass("landr_correct");
            $("#" + self.itemdata.uniqueid + "_container .landr_feedback[data-idx='" + obj.wordnumber + "']").addClass("fa fa-check");
          }
        });
        var thereply = $("#" + self.itemdata.uniqueid + "_container .landr_reply_" + self.game.pointer);
        anim.do_animate(thereply,'shakeX animate__faster').then(
          function(){$("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", false);}
        );
        //shake the screen
        /*
        $("#" + self.itemdata.uniqueid + "_container .landr_reply_" + self.game.pointer).effect("shake", function() {
          $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", false);
        });

         */

      }
      //show all the correct words
      $("#" + self.itemdata.uniqueid + "_container .landr_targetWord.landr_correct").each(function() {
        var realidx = $(this).data("realidx");
        var landr_targetWord = self.items[self.game.pointer].landr_targetWords[realidx];
        $(this).val(landr_targetWord);
      });

    },
    getWords: function(thetext) {
      var self = this;
      var checkcase = false;
      if (checkcase == 'false') {
        thetext = thetext.toLowerCase();
      }
      var chunks = thetext.split(self.quizhelper.spliton_regexp).filter(function(e) {
        return e !== "";
      });
      var words = [];
      for (var i = 0; i < chunks.length; i++) {
        if (!chunks[i].match(self.quizhelper.spliton_regexp)) {
          words.push(chunks[i]);
        }
      }
      return words;
    },
    getComparison: function(passage, transcript, phonetic, callback) {
      var self = this;
      
      $(".landr_ctrl-btn").prop("disabled", true);
      self.quizhelper.comparePassageToTranscript(passage,transcript,phonetic,self.itemdata.language, self.itemdata.alternates).then(function(ajaxresult) {
            var payloadobject = JSON.parse(ajaxresult);
            if (payloadobject) {
                callback(payloadobject);
            } else {
                callback(false);
            }
       });

    },
    end: function() {
      var self = this;
      $(".minilesson_nextbutton").prop("disabled",true);
      
      //progress dots are updated on next_item. The last item has no next item, so we update from here
      self.updateProgressDots();
      
      //disable the buttons and go to next question or review
      setTimeout(function() {
        $(".minilesson_nextbutton").prop("disabled",false);
        if(self.quizhelper.showitemreview){
          self.show_item_review();
        }else{
          self.next_question();
        }
      }, 2200);

    },
    start: function() {
      var self = this;

      $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", true);
      $("#" + self.itemdata.uniqueid + "_container .landr_speakbtncontainer").show();
      $("#" + self.itemdata.uniqueid + "_container .landr_title").show();

      self.items.forEach(function(item) {
        item.spoken = "";
        item.answered = false;
        item.correct = false;
      });

      self.game.pointer = 0;

      $("#" + self.itemdata.uniqueid + "_container .landr_game").show();
      $("#" + self.itemdata.uniqueid + "_container .landr_start_btn").hide();
      $("#" + self.itemdata.uniqueid + "_container .landr_mainmenu").hide();
      $("#" + self.itemdata.uniqueid + "_container .landr_controls").show();

      self.nextPrompt();

    },
    nextPrompt: function() {
      var showText = parseInt(this.itemdata.show_text);
      var self = this;

      //target is the speech we expect
      var target = self.items[self.game.pointer].target;
      //in some cases ttrecorder wants to know the target
      if(self.quizhelper.use_ttrecorder()) {
        self.ttrec.currentPrompt=target;
      }
      var displayprompt = self.items[self.game.pointer].displayprompt;
      var code = "<div class='landr_prompt landr_prompt_" + self.game.pointer + "' style='display:none;'>";

      code += "<i class='fa fa-graduation-cap landr_speech-icon-left'></i>";
      code += "<div style='margin-left:90px;' class='landr_speech landr_teacher_left'>";
      if(!showText){
        var nopunc = displayprompt.replace(self.quizhelper.nopunc_regexp,"");
        var dots = nopunc.replace(self.quizhelper.nonspaces_regexp, '•');
        code += dots;
      } else{
        code += displayprompt;
      }
      code += "</div>";
      code += "</div>";

      $("#" + self.itemdata.uniqueid + "_container .landr_game").html(code);
      $(".landr_ctrl-btn").prop("disabled", false);

      self.updateProgressDots();

      var newprompt = $(".landr_prompt_" + self.game.pointer);
      anim.do_animate(newprompt,'zoomIn animate__faster','in').then(
          function(){}
      );
      self.nextReply();
    },

    updateProgressDots: function() {
      var self = this;
      var color;
      var progress = self.items.map(function(item, idx) {
        color = "gray";
        if (self.items[idx].answered && self.items[idx].correct) {
          color = "green";
        } else if (self.items[idx].answered && !self.items[idx].correct) {
          color = "red";
        }
        return "<i style='color:" + color + "' class='fa fa-circle'></i>";
      }).join(" ");
      $("#" + self.itemdata.uniqueid + "_container .landr_title").html(progress);
    },

    nextReply: function() {
      var self = this;
      var target = self.items[self.game.pointer].target;
      var code = "<div class='landr_reply landr_reply_" + self.game.pointer + "' style='display:none;'>";
      code += "<i class='fa fa-user landr_speech-icon-right'></i>";
      var landr_targetWordsCode = "";
      var idx = 1;
      self.items[self.game.pointer].landr_targetWords.forEach(function(word, realidx) {
        if (!word.match(self.quizhelper.spliton_regexp)) {
          landr_targetWordsCode += "<ruby><input disabled type='text' maxlength='" + word.length + "' size='" + (word.length + 1) + "' class='landr_targetWord' data-realidx='" + realidx + "' data-idx='" + idx + "'><rt><i data-idx='" + idx + "' class='landr_feedback'></i></rt></ruby>";
          idx++;

        } else {
          landr_targetWordsCode += word;
        }
      });
      code += "<div style='margin-right:90px;' class='landr_speech landr_right'>" + landr_targetWordsCode + "</div>";
      code += "</div>";
      $("#" + self.itemdata.uniqueid + "_container .landr_game").append(code);
      var newreply = $(".landr_reply_" + self.game.pointer);
      anim.do_animate(newreply,'zoomIn animate__faster','in').then(
          function(){}
      );
      /*
      $(".landr_reply_" + self.game.pointer).toggle("slide", {
        direction: 'right'
      });
       */
      $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", false);
      if(!self.quizhelper.mobile_user()){
        setTimeout(function(){
          $("#" + self.itemdata.uniqueid + "_container .landr_listen_btn").trigger('click');
        },1000);
      }
    }

  };
});