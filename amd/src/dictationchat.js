define(['jquery',
  'core/log',
  'core/ajax',
  'mod_minilesson/definitions',
  'mod_minilesson/pollyhelper',
  'mod_minilesson/animatecss',
  'core/templates'
    ], function($, log, ajax, def, polly, anim, templates) {
  "use strict"; // jshint ;_;

  log.debug('MiniLesson dictation chat: initialising');

  return {

      //for making multiple instances
      clone: function () {
          return $.extend(true, {}, this);
      },

      usevoice: 'Amy',

      init: function(index, itemdata, quizhelper) {
        var self = this;
        self.itemdata = itemdata;
        self.quizhelper = quizhelper;
        self.index = index;

        //anim
        var animopts = {};
        animopts.useanimatecss=quizhelper.useanimatecss;
        anim.init(animopts);

        self.register_events();
        self.setvoice();
        self.getItems();

    },
    next_question:function(){
      var self=this;
      var stepdata = {};
      stepdata.index = self.index;
      stepdata.hasgrade = true;
      stepdata.totalitems=self.items.length;
      stepdata.correctitems=self.items.filter(function(e) {return e.correct;}).length;
      stepdata.grade = Math.round((stepdata.correctitems/stepdata.totalitems)*100);

      //stop audio
      self.stop_audio();

      //move to next question
      self.quizhelper.do_next(stepdata);
    },

    show_item_review:function(){
      var self=this;

      self.items.forEach(function(item){
          var itemwordlist = [];

          item.dictate_targetWords.forEach(function(data) {
              if (data !== '') {
                  itemwordlist.push(data);
              }
          });
          var wordmatch = itemwordlist.join("");
          var regex = new RegExp(wordmatch, "gi");
          var answerclass = item.correct ? 'correctitem' : 'wrongitem';
          var result = item.target.replace(regex, ` <span class="${answerclass}">${wordmatch}</span>`);
          item.target = result;
      });

      var review_data = {};
      review_data.items = self.items;
      review_data.totalitems=self.items.length;
      review_data.correctitems=self.items.filter(function(e) {return e.correct;}).length;

      //display results
      var gamebox= $("#" + self.itemdata.uniqueid + "_container .dictate_game");
      var controlsbox = $("#" + self.itemdata.uniqueid + "_container .dictate_controls");
      var recorderbox = $("#" + self.itemdata.uniqueid + "_container .dictate_speakbtncontainer");
      var resultsbox = $("#" + self.itemdata.uniqueid + "_container .dictate_resultscontainer");
      var resultsbox = $("#" + self.itemdata.uniqueid + "_container .dictate_resultscontainer");
      var title = $("#" + self.itemdata.uniqueid + "_container .dictate_title");
      var listencontroler = $("#" + self.itemdata.uniqueid + "_container .dictate_listen_controler");
      templates.render('mod_minilesson/listitemresults',review_data).then(
        function(html,js){
            resultsbox.html(html);
            //show and hide
            resultsbox.show();
            gamebox.hide();
            controlsbox.hide();
            recorderbox.hide();
            title.hide();
            listencontroler.hide();
            // Run js for audio player events
            templates.runTemplateJS(js);
        }
      );// End of templates
    },

    register_events: function() {

      var self = this;

      $("#" + self.itemdata.uniqueid + "_container .minilesson_nextbutton").on('click', function(e) {
        self.next_question();
      });

      $("#" + self.itemdata.uniqueid + "_container .dictate_start_btn").on("click", function() {
        self.start();
      });

      var audioplayerbtn = $("#" + self.itemdata.uniqueid + "_container .dictate_listen_btn");
      audioplayerbtn.on("click", function() {
        var theaudio = self.items[self.game.pointer].audio;
        if(!theaudio.paused){
          theaudio.pause();
          theaudio.currentTime=0;
          audioplayerbtn.children('.fa').removeClass('fa-stop');
          audioplayerbtn.children('.fa').addClass('fa-volume-up');
          return;
        }

        theaudio.addEventListener('ended', function () {
          audioplayerbtn.children('.fa').removeClass('fa-stop');
          audioplayerbtn.children('.fa').addClass('fa-volume-up');
        });

        theaudio.addEventListener('play', function () {
          audioplayerbtn.children('.fa').removeClass('fa-volume-up');
          audioplayerbtn.children('.fa').addClass('fa-stop');
        });

        theaudio.load();
        theaudio.play();
      });

      //on skip button click
      $("#" + self.itemdata.uniqueid + "_container .dictate_skip_btn").on("click", function() {
        //disable buttons
        $("#" + self.itemdata.uniqueid + "_container .dictate_ctrl-btn").prop("disabled", true);
        //reveal prompt
        $("#" + self.itemdata.uniqueid + "_container .dictate_speech.dictate_teacher_left").text(self.items[self.game.pointer].displayprompt + "");
        //reveal answers
        //reveal the answer
        $("#" + self.itemdata.uniqueid + "_container .dictate_targetWord").each(function() {
          var realidx = $(this).data("realidx");
          var dictate_targetWord = self.items[self.game.pointer].dictate_targetWords[realidx];
          $(this).val(dictate_targetWord);
        });


          //move on after short time, to next prompt, or next question
          if (self.game.pointer < self.items.length - 1) {
            setTimeout(function() {
              self.items[self.game.pointer].answered = true;
              self.items[self.game.pointer].correct = false;
              self.game.pointer++;
              self.nextPrompt();
            }, 3000);
          } else {
            self.end();
          }
      });


      $("#" + self.itemdata.uniqueid + "_container .dictate_check_btn").on("click", function() {
        self.check_answer();
      });

      //listen for enter key
      $("#" + self.itemdata.uniqueid + "_container").on("keydown",".dictate_targetWord", function(e) {
        if (e.which == 13) {
          self.check_answer();
        }
      });

      //auto nav between inputs
      $("#" + self.itemdata.uniqueid + "_container").on("keyup",".dictate_targetWord", function(e) {

        //move focus between textboxes
        //log.debug(e);
        var target = e.srcElement || e.target;
        var maxLength = parseInt(target.attributes["maxlength"].value, 10);
        var myLength = target.value.length;
        var key = e.which;
        if (myLength >= maxLength) {
          var nextIdx = $(this).data('idx') + 1;
          var next = $("#" + self.itemdata.uniqueid + "_container input.dictate_targetWord[data-idx=\""+nextIdx+"\"");
          if (next.length === 1){
            next.focus();
          }

          // Move to previous field if empty (user pressed backspace or delete)
        } else if (( key == 8 || key == 46 ) && myLength === 0) {
          var previousIdx = $(this).data('idx') - 1;
          var previous = $("#" + self.itemdata.uniqueid + "_container input.dictate_targetWord[data-idx=\""+previousIdx+"\"");
          if (previous.length === 1){
            previous.focus();
          }
        }
      });

    },

    game: {
      pointer: 0
    },

    check_answer: function(){
      var self = this;
      var passage = self.items[self.game.pointer].target;
      var transcript = '';
      $("#" + self.itemdata.uniqueid + "_container .dictate_targetBit").each(function() {
        if($(this).hasClass('dictate_targetWord')){
          transcript += $(this).val();
        }else if($(this).hasClass('dictate_targetWordPunc')){
          transcript += $(this).text();
        }
      });

    //the old code looped over dictate_targetWord, pushed to transcriptArray, and joined with a space
    //But that did not account for words split by punc, eg It's.
      // Kept that here for now, but can delete  I think.
      /*
      var transcriptArray = [];
      $("#" + self.itemdata.uniqueid + "_container .dictate_targetWord").each(function() {
        transcriptArray.push($(this).val().trim() == "" ? "|" : $(this).val().trim());
      });
     var transcript = transcriptArray.join(" ");
    */

      self.getComparison(passage, transcript, function(comparison) {
        self.gotComparison(comparison, transcript);
      });
    },
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
          dictate_targetWords: target.sentence.trim().split(self.quizhelper.spliton_regexp).filter(function(e) {
            return e !== "";
          }),
          target: target.sentence,
          prompt: target.prompt,
          displayprompt: target.displayprompt,
          typed: "",
          answered: false,
          correct: false,
          audio: null,
          audiourl: target.audiourl ? target.audiourl : "",
          imageurl: target.imageurl,
        };
      }).filter(function(e) {
        return e.target !== "";
      });

      $.each(self.items, function(index, item) {
          item.audio = new Audio();
          item.audio.src = item.audiourl;
          if (self.items.filter(function(e) {
              return e.audio == null;
            }).length == 0) {
            self.appReady();
          } else {
            console.log(self.items);
          }
      });

    },
    appReady: function() {
      var self = this;
      $("#" + self.itemdata.uniqueid + "_container .dictate_not_loaded").hide();
      $("#" + self.itemdata.uniqueid + "_container .dictate_loaded").show();
      $("#" + self.itemdata.uniqueid + "_container .dictate_start_btn").prop("disabled", false);
    },
    gotComparison: function(comparison, typed) {

      var self = this;

      $("#" + self.itemdata.uniqueid + "_container .dictate_targetWord").removeClass("dictate_correct dictate_incorrect");
      $("#" + self.itemdata.uniqueid + "_container .dictate_feedback").removeClass("fa fa-check fa-times");

      var allCorrect = comparison.filter(function(e) {
        return !e.matched;
      }).length == 0;

      if (allCorrect) {

        $("#" + self.itemdata.uniqueid + "_container .dictate_targetWord").addClass("dictate_correct").prop("disabled",true);
        $("#" + self.itemdata.uniqueid + "_container .dictate_feedback").addClass("fa fa-check");
        $("#" + self.itemdata.uniqueid + "_container .dictate_speech.dictate_teacher_left").text(self.items[self.game.pointer].displayprompt + "");

        self.items[self.game.pointer].answered = true;
        self.items[self.game.pointer].correct = true;
        self.items[self.game.pointer].typed = typed;

        $("#" + self.itemdata.uniqueid + "_container .dictate_ctrl-btn").prop("disabled", true);
        if (self.game.pointer < self.items.length - 1) {
          setTimeout(function() {
            self.game.pointer++;
            self.nextPrompt();
          }, 2200);
        } else {
            self.end();
        }

      } else {

        comparison.forEach(function(obj) {
          if (!obj.matched) {
            $("#" + self.itemdata.uniqueid + "_container .dictate_targetWord[data-idx='" + obj.wordnumber + "']").addClass("dictate_incorrect");
            $("#" + self.itemdata.uniqueid + "_container .dictate_feedback[data-idx='" + obj.wordnumber + "']").addClass("fa fa-times");
          } else {
            $("#" + self.itemdata.uniqueid + "_container .dictate_targetWord[data-idx='" + obj.wordnumber + "']").addClass("dictate_correct").prop("disabled",true);
            $("#" + self.itemdata.uniqueid + "_container .dictate_feedback[data-idx='" + obj.wordnumber + "']").addClass("fa fa-check");
          }
        });
        var thereply = $("#" + self.itemdata.uniqueid + "_container .dictate_reply_" + self.game.pointer);
        anim.do_animate(thereply,'shakeX animate__faster').then(
            function() {$("#" + self.itemdata.uniqueid + "_container .dictate_ctrl-btn").prop("disabled", false);}
        );
        /*
        $("#" + self.itemdata.uniqueid + "_container .dictate_reply_" + self.game.pointer).effect("shake", function() {
          $("#" + self.itemdata.uniqueid + "_container .dictate_ctrl-btn").prop("disabled", false);
        }
        );
        */

      }

      $("#" + self.itemdata.uniqueid + "_container .dictate_targetWord.dictate_correct").each(function() {
        var realidx = $(this).data("realidx");
        var dictate_targetWord = self.items[self.game.pointer].dictate_targetWords[realidx];
        $(this).val(dictate_targetWord);
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
    getComparison: function(passage, transcript, callback) {
        var self = this;

        $(".dictate_ctrl-btn").prop("disabled", true);
        var phonetic ='';//we do not want a phonetic match in dictation.
        self.quizhelper.comparePassageToTranscript(passage,transcript,phonetic,self.itemdata.language).then(function(ajaxresult) {
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

      $("#" + self.itemdata.uniqueid + "_container .dictate_ctrl-btn").prop("disabled", true);
      $("#" + self.itemdata.uniqueid + "_container .dictate_title").show();

      self.items.forEach(function(item) {
        item.spoken = "";
        item.answered = false;
        item.correct = false;
      });

      self.game.pointer = 0;

      $("#" + self.itemdata.uniqueid + "_container .dictate_game").show();
      $("#" + self.itemdata.uniqueid + "_container .dictate_start_btn").hide();
      $("#" + self.itemdata.uniqueid + "_container .dictate_mainmenu").hide();
      $("#" + self.itemdata.uniqueid + "_container .dictate_controls").show();
      $("#" + self.itemdata.uniqueid + "_container .dictate_image_container").hide();
      $("#" + self.itemdata.uniqueid + "_container .dictate_description").hide();
      $("#" + self.itemdata.uniqueid + "_container .dictate_maintitle").show();
      $("#" + self.itemdata.uniqueid + "_container .dictate_itemtext").show();
      $("#" + self.itemdata.uniqueid + "_container .dictate_listen_controler").show();

      self.nextPrompt();

    },
    nextPrompt: function() {

      var self = this;
      var target = self.items[self.game.pointer].target;
      var prompt = self.items[self.game.pointer].prompt;
      var displayprompt = self.items[self.game.pointer].displayprompt;

      var nopunc = prompt.replace(self.quizhelper.nopunc_regexp,"");
      var dots = nopunc.replace(self.quizhelper.nonspaces_regexp, '•');
      var code = "<div class='dictate_prompt dictate_prompt_" + self.game.pointer + "' style='display:none;'>";

      code += "<i class='fa fa-graduation-cap dictate_speech-icon-left'></i>";
      code += "<div style='margin-left:90px;' class='dictate_speech dictate_teacher_left'>";
      code += dots;
      code += "</div>";
      code += "</div>";

      $("#" + self.itemdata.uniqueid + "_container .dictate_game").html(code);
      $(".dictate_ctrl-btn").prop("disabled", false);

      self.updateProgressDots();

      var newprompt = $(".dictate_prompt_" + self.game.pointer);
      anim.do_animate(newprompt,'zoomIn animate__faster','in').then(
          function(){}
      );
      /*
      $(".dictate_prompt_" + self.game.pointer).toggle("slide", {
        direction: 'left'
      });
       */

      self.nextReply();

    },

    updateProgressDots: function() {
      var self = this;
      var color,icon;
      var progress = self.items.map(function(item, idx) {
        color = "#E6E9FD";
        icon = "fa fa-square";
        if (self.items[idx].answered && self.items[idx].correct) {
          color = "#74DC72";
          icon = 'fa fa-check-square';
        } else if (self.items[idx].answered && !self.items[idx].correct) {
          color = "#FB6363";
          icon = "fa fa-window-close";
        }
        return "<i style='color:" + color + "' class='"+ icon +" pl-1'></i>";
      }).join(" ");
      $("#" + self.itemdata.uniqueid + "_container .dictate_title").html(progress);
    },

    nextReply: function() {
      var self = this;
      var target = self.items[self.game.pointer].target;
      var code = "<div class='dictate_reply dictate_reply_" + self.game.pointer + "' style='display:none;'>";
      code += "<i class='fa fa-user dictate_speech-icon-right'></i>";
      var dictate_targetWordsCode = "";
      var idx = 1;
      self.items[self.game.pointer].dictate_targetWords.forEach(function(word, realidx) {
        if (!word.match(self.quizhelper.spliton_regexp)) {
          dictate_targetWordsCode += "<ruby><input type='text' maxlength='" + word.length + "' size='" + (word.length + 1) + "' class='dictate_targetBit dictate_targetWord' data-realidx='" + realidx + "' data-idx='" + idx + "'><rt><i data-idx='" + idx + "' class='dictate_feedback'></i></rt></ruby>";
          idx++;
        } else {
          dictate_targetWordsCode += "<span class='dictate_targetBit dictate_targetWordPunc' data-idx='" + idx + "'>" + word + "</span>";
        }
      });
      code += "<div style='margin-right:90px;' class='dictate_speech dictate_right'>" + dictate_targetWordsCode + "</div>";
      code += "</div>";
      $("#" + self.itemdata.uniqueid + "_container .dictate_game").append(code);
      var newreply = $(".dictate_reply_" + self.game.pointer);
      anim.do_animate(newreply,'zoomIn animate__faster','in').then(
          function(){}
      );
      /*
      $(".dictate_reply_" + self.game.pointer).toggle("slide", {
        direction: 'right'
      });
       */
      $("#" + self.itemdata.uniqueid + "_container .dictate_ctrl-btn").prop("disabled", false);
      setTimeout(function() {
        $(".dictate_targetWord").first().focus();
        $("#" + self.itemdata.uniqueid + "_container .dictate_listen_btn").trigger('click');
      }, 500);
    },

    // Stop audio .. usually when leaving the item or sentence
    stop_audio: function(){
      var self =this;
      //pause audio if its playing
      var theaudio = self.items[self.game.pointer].audio;
      if(theaudio && !theaudio.paused) {
        theaudio.pause();
      }
    }

  };
});