define(['jquery',
      'core/log',
      'core/ajax',
      'mod_minilesson/definitions',
      'mod_minilesson/pollyhelper',
      'mod_minilesson/ttrecorder',
      'mod_minilesson/animatecss',
      'core/templates'
    ], function($, log, ajax, def, polly, ttrecorder, anim, templates) {
  "use strict"; // jshint ;_;

  log.debug('MiniLesson listen and repeat: initialising');

  return {

    //a handle on the tt recorder
    ttrec: null,

    //for making multiple instances
    clone: function () {
      return $.extend(true, {}, this);
    },

    init: function (index, itemdata, quizhelper) {
      var self = this;
      var theCallback = function (message) {

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
                function (comparison) {
                  self.gotComparison(comparison, message);
                }
            );
            break;

        }

      };

      if (quizhelper.use_ttrecorder()) {
        var opts = {};
        opts.uniqueid = itemdata.uniqueid;
        opts.callback = theCallback;
        opts.stt_guided = quizhelper.is_stt_guided();
        opts.wwwroot = quizhelper.is_stt_guided();
        self.ttrec = ttrecorder.clone();
        self.ttrec.init(opts);
      } else {
        //init cloudpoodll push recorder
        cloudpoodll.init('minilesson-recorder-listenrepeat-' + itemdata.id, theCallback);
      }

      self.itemdata = itemdata;
      self.quizhelper = quizhelper;
      self.index = index;

      //anim
      var animopts = {};
      animopts.useanimatecss = quizhelper.useanimatecss;
      anim.init(animopts);

      self.register_events();
      self.setvoice();
      self.getItems();
    },

    next_question: function (percent) {
      var self = this;
      var stepdata = {};
      stepdata.index = self.index;
      stepdata.hasgrade = true;
      stepdata.totalitems = self.items.length;
      stepdata.correctitems = self.items.filter(function (e) {
        return e.correct;
      }).length;
      stepdata.grade = Math.round((stepdata.correctitems / stepdata.totalitems) * 100);

      //stop audio
      self.stop_audio();

      //transition
      self.quizhelper.do_next(stepdata);
    },

    show_item_review: function () {
      var self = this;
      var review_data = {};
      review_data.items = self.items;
      review_data.totalitems = self.items.length;
      review_data.correctitems = self.items.filter(function (e) {
        return e.correct;
      }).length;

      //display results
      var gamebox = $("#" + self.itemdata.uniqueid + "_container .landr_game");
      var controlsbox = $("#" + self.itemdata.uniqueid + "_container .landr_controls");
      var recorderbox = $("#" + self.itemdata.uniqueid + "_container .landr_speakbtncontainer");
      var resultsbox = $("#" + self.itemdata.uniqueid + "_container .landr_resultscontainer");
      templates.render('mod_minilesson/listitemresults', review_data).then(
          function (html, js) {
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

    register_events: function () {

      var self = this;
      //on next button click
      $("#" + self.itemdata.uniqueid + "_container .minilesson_nextbutton").on('click', function (e) {
        self.next_question();
      });
      //on start button click
      $("#" + self.itemdata.uniqueid + "_container .landr_start_btn").on("click", function () {
        self.start();
      });


      //AUDIO PLAYER Button events
      var audioplayerbtn=$("#" + self.itemdata.uniqueid + "_container .landr_listen_btn");
      // On listen button click
      audioplayerbtn.on("click", function () {
        var theaudio = self.items[self.game.pointer].audio;

        //if we are already playing stop playing
        if(!theaudio.paused){
          theaudio.pause();
          theaudio.currentTime=0;
          $(audioplayerbtn).children('.fa').removeClass('fa-stop');
          $(audioplayerbtn).children('.fa').addClass('fa-volume-up');
          return;
        }

        //change icon to indicate playing state
        theaudio.addEventListener('ended', function () {
          $(audioplayerbtn).children('.fa').removeClass('fa-stop');
          $(audioplayerbtn).children('.fa').addClass('fa-volume-up');
        });

        theaudio.addEventListener('play', function () {
          $(audioplayerbtn).children('.fa').removeClass('fa-volume-up');
          $(audioplayerbtn).children('.fa').addClass('fa-stop');
        });
        theaudio.load();
        theaudio.play();
      });

      //on skip button click
      $("#" + self.itemdata.uniqueid + "_container .landr_skip_btn").on("click", function () {
        //disable the buttons
        $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", true);
        //reveal the prompt
        $("#" + self.itemdata.uniqueid + "_container .landr_speech.landr_teacher_left").text(self.items[self.game.pointer].displayprompt + "");
        //reveal the answer
        $("#" + self.itemdata.uniqueid + "_container .landr_word_input").each(function () {
          var realidx = $(this).data("realidx");
          var landr_word_input = self.items[self.game.pointer].landr_targetWords[realidx];
          $(this).val(landr_word_input);
        });

        //mark as answered and incorrect
        self.items[self.game.pointer].answered = true;
        self.items[self.game.pointer].correct = false;

        //next prompt or end
        if (self.game.pointer < self.items.length - 1) {
          //move on after short time to next prompt
          setTimeout(function () {
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
    setvoice: function () {
      var self = this;
      self.usevoice = self.itemdata.usevoice;
      self.voiceoption = self.itemdata.voiceoption;
      return;
    },
    getItems: function () {
      var self = this;
      var text_items = self.itemdata.sentences;

      self.items = text_items.map(function (target) {
        return {
          landr_targetWords: target.sentence.trim().split(self.quizhelper.spliton_regexp).filter(function (e) {
            return e !== "";
          }),
          target: target.sentence,
          prompt: target.prompt,
          displayprompt: target.displayprompt,
          phonetic: target.phonetic,
          typed: "",
          answered: false,
          correct: false,
          audio: null,
          audiourl: target.audiourl ? target.audiourl : "",
          imageurl: target.imageurl,
        };
      }).filter(function (e) {
        return e.target !== "";
      });


      $.each(self.items, function (index, item) {
        item.audio = new Audio();
        item.audio.src = item.audiourl;
        if (self.items.filter(function (e) {
          return e.audio === null;
        }).length === 0) {
          self.appReady();
        }
      });
    },

    appReady: function () {
      var self = this;
      $("#" + self.itemdata.uniqueid + "_container .landr_not_loaded").hide();
      $("#" + self.itemdata.uniqueid + "_container .landr_loaded").show();
      $("#" + self.itemdata.uniqueid + "_container .landr_start_btn").prop("disabled", false);
    },
    gotComparison: function (comparison, typed) {
      var self = this;

      $("#" + self.itemdata.uniqueid + "_container .landr_word_input").removeClass("landr_correct landr_incorrect");
      $("#" + self.itemdata.uniqueid + "_container .landr_feedback_icon").removeClass("fa fa-check fa-times");

      var allCorrect = comparison.filter(function (e) {
        return !e.matched;
      }).length == 0;

      if (allCorrect && comparison && comparison.length > 0) {

        $("#" + self.itemdata.uniqueid + "_container .landr_word_input").addClass("landr_correct");
        $("#" + self.itemdata.uniqueid + "_container .landr_feedback_icon").addClass("fa fa-check");
        $("#" + self.itemdata.uniqueid + "_container .landr_speech.landr_teacher_left").text(self.items[self.game.pointer].displayprompt + "");

        self.items[self.game.pointer].answered = true;
        self.items[self.game.pointer].correct = true;
        self.items[self.game.pointer].typed = typed;

        $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", true);
        if (self.game.pointer < self.items.length - 1) {
          setTimeout(function () {
            self.game.pointer++;
            self.nextPrompt();
          }, 2200);
        } else {
          self.end();
        }

      } else {
        //mark up the words as correct or not
        comparison.forEach(function (obj) {
          if (!obj.matched) {
            $("#" + self.itemdata.uniqueid + "_container .landr_word_input[data-idx='" + obj.wordnumber + "']").addClass("landr_incorrect");
            $("#" + self.itemdata.uniqueid + "_container .landr_feedback_icon[data-idx='" + obj.wordnumber + "']").addClass("fa fa-times");
          } else {
            $("#" + self.itemdata.uniqueid + "_container .landr_word_input[data-idx='" + obj.wordnumber + "']").addClass("landr_correct");
            $("#" + self.itemdata.uniqueid + "_container .landr_feedback_icon[data-idx='" + obj.wordnumber + "']").addClass("fa fa-check");
          }
        });
        var thereply = $("#" + self.itemdata.uniqueid + "_container .landr_reply_" + self.game.pointer);
        anim.do_animate(thereply, 'shakeX animate__faster').then(
            function () {
              $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", false);
            }
        );

      }
      //show all the correct words
      $("#" + self.itemdata.uniqueid + "_container .landr_word_input.landr_correct").each(function () {
        var realidx = $(this).data("realidx");
        var landr_word_input = self.items[self.game.pointer].landr_targetWords[realidx];
        $(this).val(landr_word_input);
      });

    },

    getWords: function (thetext) {
      var self = this;
      var checkcase = false;
      if (checkcase == 'false') {
        thetext = thetext.toLowerCase();
      }
      var chunks = thetext.split(self.quizhelper.spliton_regexp).filter(function (e) {
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
    getComparison: function (passage, transcript, phonetic, callback) {
      var self = this;

      $(".landr_ctrl-btn").prop("disabled", true);
      self.quizhelper.comparePassageToTranscript(passage, transcript, phonetic, self.itemdata.language, self.itemdata.alternates).then(function (ajaxresult) {
        var payloadobject = JSON.parse(ajaxresult);
        if (payloadobject) {
          callback(payloadobject);
        } else {
          callback(false);
        }
      });

    },
    end: function () {
      var self = this;
      $(".minilesson_nextbutton").prop("disabled", true);

      //progress dots are updated on next_item. The last item has no next item, so we update from here
      self.updateProgressDots();

      //disable the buttons and go to next question or review
      setTimeout(function () {
        $(".minilesson_nextbutton").prop("disabled", false);
        if (self.quizhelper.showitemreview) {
          self.show_item_review();
        } else {
          self.next_question();
        }
      }, 2200);

    },
    start: function () {
      var self = this;

      $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", true);
      $("#" + self.itemdata.uniqueid + "_container .landr_speakbtncontainer").show();
      $("#" + self.itemdata.uniqueid + "_container .landr_title").show();

      self.items.forEach(function (item) {
        item.spoken = "";
        item.answered = false;
        item.correct = false;
      });

      self.game.pointer = 0;

      $("#" + self.itemdata.uniqueid + "_container .landr_game").show();
      $("#" + self.itemdata.uniqueid + "_container .landr_start_btn").hide();
      $("#" + self.itemdata.uniqueid + "_container .landr_listen_cont").show();
      $("#" + self.itemdata.uniqueid + "_container .landr_mainmenu").hide();
      $("#" + self.itemdata.uniqueid + "_container .landr_controls").show();

      self.nextPrompt();

    },

    nextPrompt: function () {
      var showText = parseInt(this.itemdata.show_text);
      var self = this;

      //target is the speech we expect
      var target = self.items[self.game.pointer].target;
      //in some cases ttrecorder wants to know the target
      if (self.quizhelper.use_ttrecorder()) {
        self.ttrec.currentPrompt = target;
      }
      var displayprompt = self.items[self.game.pointer].displayprompt;
      if (!showText) {
        var nopunc = displayprompt.replace(self.quizhelper.nopunc_regexp, "");
        var dots = nopunc.replace(self.quizhelper.nonspaces_regexp, '•');
        var showprompt = dots;
      } else {
        var showprompt = displayprompt;
      }

      templates.render('mod_minilesson/listenrepeat_prompt',
          {showprompt: showprompt, pointer: self.game.pointer})
          .then(function (html, js) {
                $("#" + self.itemdata.uniqueid + "_container .landr_game").html(html);
                $(".landr_ctrl-btn").prop("disabled", false);
                self.updateProgressDots();
                var newprompt = $(".landr_prompt_" + self.game.pointer);
                anim.do_animate(newprompt, 'zoomIn animate__faster', 'in').then(
                    function () {
                    }
                );
                self.nextReply();
              }
          );// End of templates
    },

    updateProgressDots: function () {
      var self = this;
      var color;
      var progress = self.items.map(function (item, idx) {
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

    nextReply: function () {
      var self = this;
      var idx = 0;
      var words = self.items[self.game.pointer].landr_targetWords.map(function (word, realidx) {
        var theword = {};
        if (!word.match(self.quizhelper.spliton_regexp)) {
          idx++;
          theword.isword = true;
          theword.idx = idx;
          theword.length = word.length;
          theword.lengthplusone = word.length + 1;
          theword.realidx = realidx;
        } else {
          theword.isword = false;
          theword.text = word;
        }
        return theword;
      });
      var displaytimer = self.itemdata.timelimit > 0;

      templates.render('mod_minilesson/listenrepeat_reply',
          {words: words, pointer: self.game.pointer, imageurl: self.items[self.game.pointer].imageurl, displaytimer})
          .then(function (html, js) {
            //update html reply area
            $("#" + self.itemdata.uniqueid + "_container .landr_game").append(html);
            // set handle to the reply and animate it in
            var newreply = $(".landr_reply_" + self.game.pointer);
            anim.do_animate(newreply, 'zoomIn animate__faster', 'in').then(
                function () {
                }
            );

            // Enable the skip button
            $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", false);

            //play the audio if we are not a mobile user and that is the setting
            if (!self.quizhelper.mobile_user()) {
              setTimeout(function () {
                $("#" + self.itemdata.uniqueid + "_container .landr_listen_btn").trigger('click');
              }, 1000);
            }

            if (displaytimer) {
              $("#" + self.itemdata.uniqueid + "_container .landr_game .progress-container").show();
              $("#" + self.itemdata.uniqueid + "_container .landr_game .progress-container i").show();
              $("#" + self.itemdata.uniqueid + "_container .landr_game .progress-container #progresstimer").progressTimer({
                  height: '5px',
                  timeLimit: self.itemdata.timelimit,
                  onFinish: function() {
                      $("#" + self.itemdata.uniqueid + "_container .landr_skip_btn").trigger('click');
                  }
              });
            }
          });//end of templates.render
    },//end of next reply

    // Stop audio .. usually when leaving the item or sentence
    stop_audio: function(){
      var self =this;
      //pause audio if its playing
      var theaudio = self.items[self.game.pointer].audio;
      if(theaudio && !theaudio.paused) {
        theaudio.pause();
      }
    }

  } // end of return
});//end of define