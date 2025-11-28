define(['jquery',
      'core/log',
      'core/ajax',
      'mod_minilesson/definitions',
      'mod_minilesson/pollyhelper',
      'mod_minilesson/ttrecorder',
      'mod_minilesson/animatecss',
      'core/templates',
      'core/str',
      'core/notification'
    ], function($, log, ajax, def, polly, ttrecorder, anim, templates,str,notification) {
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
      self.strings = {};

      //anim
      var animopts = {};
      animopts.useanimatecss = quizhelper.useanimatecss;
      anim.init(animopts);

      self.init_controls();
      self.init_strings();
      self.register_events();
      self.setvoice();
      self.getItems();
    },

    init_controls: function() {
      var self = this;
      self.controls = {
        container: $("#" + self.itemdata.uniqueid + "_container"),
        nextbutton: $("#" + self.itemdata.uniqueid + "_container .minilesson_nextbutton"),
        start_btn: $("#" + self.itemdata.uniqueid + "_container .landr_start_btn"),
        skip_btn: $("#" + self.itemdata.uniqueid + "_container .landr_skip_btn"),
        ctrl_btn: $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn"),
        game: $("#" + self.itemdata.uniqueid + "_container .landr_game"),
        controlsbox: $("#" + self.itemdata.uniqueid + "_container .landr_controls"),
        resultscontainer: $("#" + self.itemdata.uniqueid + "_container .landr_resultscontainer"),
        speakbtncontainer: $("#" + self.itemdata.uniqueid + "_container .landr_speakbtncontainer"),
        mainmenu: $("#" + self.itemdata.uniqueid + "_container .landr_mainmenu"),
        title: $("#" + self.itemdata.uniqueid + "_container .landr_title"),
        question: $("#" + self.itemdata.uniqueid + "_container .landr_question"),
        speech_container: $("#" + self.itemdata.uniqueid + "_container .landr_speechcontainer"),
        landr_correctfeedback: $("#" + self.itemdata.uniqueid + "_container .landr_correctfeedback"),
        landr_incorrectfeedback: $("#" + self.itemdata.uniqueid + "_container .landr_incorrectfeedback"),
        landr_fbcontainer: $("#" + self.itemdata.uniqueid + "_container .landr_fbcontainer"),
        landr_feedback: $("#" + self.itemdata.uniqueid + "_container .landr_feedback"),
        landr_listen_btn: $("#" + self.itemdata.uniqueid + "_container .landr_listen_btn"),
        progress_container: $("#" + self.itemdata.uniqueid + "_container .progress-container"),
        description: $("#" + self.itemdata.uniqueid + "_container .landr_description"),
        image: $("#" + self.itemdata.uniqueid + "_container .landr_image_container"),
        maintitle: $("#" + self.itemdata.uniqueid + "_container .landr_maintitle"),
      };
    },

    init_strings: function() {
        var self = this;
        str.get_strings([
            { "key": "nextlessonitem", "component": 'mod_minilesson'},
            { "key": "confirm_desc", "component": 'mod_minilesson'},
            { "key": "yes", "component": 'moodle'},
            { "key": "no", "component": 'moodle'},
        ]).done(function (s) {
            var i = 0;
            self.strings.nextlessonitem = s[i++];
            self.strings.confirm_desc = s[i++];
            self.strings.yes = s[i++];
            self.strings.no = s[i++];
        });
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

      self.items.forEach(function(item){
          var itemwordlist = [];
          item.landr_targetWords.forEach(function(data) {
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
      review_data.totalitems = self.items.length;
      review_data.correctitems = self.items.filter(function (e) {
        return e.correct;
      }).length;

      //display results
      var gamebox = self.controls.game;
      var controlsbox = self.controls.controlsbox;
      var recorderbox = self.controls.speakbtncontainer;
      var resultsbox = self.controls.resultscontainer;
      var audioplayerbtn = self.controls.landr_listen_btn;
      templates.render('mod_minilesson/listitemresults', review_data).then(
          function (html, js) {
            resultsbox.html(html);
            //show and hide
            resultsbox.show();
            gamebox.hide();
            controlsbox.hide();
            recorderbox.hide();
            audioplayerbtn.hide();
            self.controls.progress_container.hide();
            self.controls.title.hide();
            // Run js for audio player events
            templates.runTemplateJS(js);
          }
      );// End of templates
    },

    register_events: function () {

      var self = this;
      //on next button click
      self.controls.nextbutton.on('click', function (e) {
        if (self.items.some(item => !item.answered)) {
            notification.confirm(self.strings.nextlessonitem,
                self.strings.confirm_desc,
                self.strings.yes,
                self.strings.no,
                function() {
                    self.next_question();
                }
            );
        } else {
            self.next_question();
        }
      });
      //on start button click
      self.controls.start_btn.on("click", function () {
        self.start();
      });


      //AUDIO PLAYER Button events
      var audioplayerbtn = self.controls.landr_listen_btn;
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
      self.controls.skip_btn.on("click", function () {
        //disable the buttons
        self.controls.ctrl_btn.prop("disabled", true);
        //reveal the prompt
        self.controls.container.find('.landr_speech.landr_teacher_left').text(self.items[self.game.pointer].displayprompt + "");
        //reveal the answer
        self.controls.container.find('.landr_word_input').each(function () {
          var realidx = $(this).data("realidx");
          var landr_word_input = self.items[self.game.pointer].landr_targetWords[realidx];
          $(this).val(landr_word_input);
        });

        //mark as answered and incorrect
        self.items[self.game.pointer].answered = true;
        self.items[self.game.pointer].correct = false;

        //Stop timer
        self.stopTimer(self.items[self.game.pointer].timer);

        //next prompt or end
        if (self.game.pointer < self.items.length - 1) {
          self.controls.skip_btn.prop("disabled", true);
          self.controls.skip_btn.children('.fa').removeClass('fa-arrow-right');
          self.controls.skip_btn.children('.fa').addClass('fa-spinner fa-spin');
          //move on after short time to next prompt
          setTimeout(function () {
            self.controls.skip_btn.children('.fa').removeClass('fa-spinner fa-spin');
            self.controls.skip_btn.children('.fa').addClass('fa-arrow-right');
            self.controls.skip_btn.prop("disabled", false);
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
          timer: [],
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
      self.controls.container.find('.landr_not_loaded').hide();
      self.controls.container.find('.landr_loaded').show();
      if(self.itemdata.hidestartpage){
        self.start();
      }else{
        self.controls.start_btn.prop("disabled", false);
      }

    },

    gotComparison: function (comparison, typed) {
      var self = this;

      self.controls.container.find('.landr_word_input').removeClass("landr_correct landr_incorrect");
      self.controls.container.find('.landr_feedback_icon').removeClass("fa fa-check fa-times");

      var allCorrect = comparison.filter(function (e) {
        return !e.matched;
      }).length == 0;

      if (allCorrect && comparison && comparison.length > 0) {

        self.controls.container.find('.landr_word_input').addClass("landr_correct");
        self.controls.container.find('.landr_feedback_icon').addClass("fa fa-check");
        self.controls.container.find('.landr_speech.landr_teacher_left').text(self.items[self.game.pointer].displayprompt + "");

        self.items[self.game.pointer].answered = true;
        self.items[self.game.pointer].correct = true;
        self.items[self.game.pointer].typed = typed;

        //stop timer
        self.stopTimer(self.items[self.game.pointer].timer);

        self.controls.ctrl_btn.prop("disabled", true);
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
            self.controls.container.find('.landr_word_input[data-idx="' + obj.wordnumber + '"]').addClass("landr_incorrect");
            self.controls.container.find('.landr_feedback_icon[data-idx="' + obj.wordnumber + '"]').addClass("fa fa-times");
          } else {
            self.controls.container.find('.landr_word_input[data-idx="' + obj.wordnumber + '"]').addClass("landr_correct");
            self.controls.container.find('.landr_feedback_icon[data-idx="' + obj.wordnumber + '"]').addClass("fa fa-check");
          }
        });
        var thereply = self.controls.container.find('.landr_reply_' + self.game.pointer);
        anim.do_animate(thereply, 'shakeX animate__faster').then(
            function () {
              self.controls.ctrl_btn.prop("disabled", false);
            }
        );

      }
      //show all the correct words
      self.controls.container.find('.landr_word_input.landr_correct').each(function () {
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

      self.controls.ctrl_btn.prop("disabled", true);
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
      self.controls.nextbutton.prop("disabled", true);

      //progress dots are updated on next_item. The last item has no next item, so we update from here
      self.updateProgressDots();

      //disable the buttons and go to next question or review
      setTimeout(function () {
        self.controls.nextbutton.prop("disabled", false);
        if (self.quizhelper.showitemreview) {
          self.show_item_review();
        } else {
          self.next_question();
        }
      }, 2200);

    },
    start: function () {
      var self = this;

      self.controls.ctrl_btn.prop("disabled", true);
      self.controls.speakbtncontainer.show();
      self.controls.title.show();

      self.items.forEach(function (item) {
        item.spoken = "";
        item.answered = false;
        item.correct = false;
      });

      self.game.pointer = 0;

      self.controls.game.show();
      self.controls.start_btn.hide();
      self.controls.description.hide();
      self.controls.image.hide();
      self.controls.maintitle.show();
      self.controls.container.find('.landr_listen_cont').show();
      self.controls.mainmenu.hide();
      self.controls.controlsbox.show();

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
                self.controls.game.html(html);
                self.controls.ctrl_btn.prop("disabled", false);
                self.updateProgressDots();
                var newprompt = self.controls.container.find(".landr_prompt_" + self.game.pointer);
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
      var color,icon;
      var progress = self.items.map(function (item, idx) {
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
      self.controls.title.html(progress);
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
            self.controls.game.append(html);
            // set handle to the reply and animate it in
            var newreply = self.controls.container.find(".landr_reply_" + self.game.pointer);
            anim.do_animate(newreply, 'zoomIn animate__faster', 'in').then(
                function () {
                }
            );

            // Enable the skip button
            self.controls.ctrl_btn.prop("disabled", false);

            //we autoplay the audio on item entry, if its not a mobile user
            //and we have a startpage (or we have a startpage but its not the first item)
            if (!self.quizhelper.mobile_user()){
              if(self.itemdata.hidestartpage && self.game.pointer === 0){
                  self.controls.container.on("showElement", () => {
                      setTimeout(function() {
                          self.controls.landr_listen_btn.trigger('click');
                      }, 1000);
                  });
              }else{
                  setTimeout(function() {
                      self.controls.landr_listen_btn.trigger('click');
                  }, 1000);
              }
            }

            //do the timer
            if (displaytimer) {
              self.startTimer();
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
    },

    startTimer: function(){
        var self = this;
        var progress_container = self.controls.progress_container;
        // If we have a time limit, set up the timer, otherwise return
        if (self.itemdata.timelimit > 0) {
            // This is a function to start the timer (we call it conditionally below)
            var doStartTimer = function() {
                  // This shows progress bar
                progress_container.show();
                progress_container.find('i').show();
                var progresbar = progress_container.find('#progresstimer').progressTimer({
                    height: '5px',
                    timeLimit: self.itemdata.timelimit,
                    onFinish: function() {
                        self.controls.skip_btn.trigger('click');
                    }
                });
                progresbar.each(function() {
                    self.items[self.game.pointer].timer.push($(this).attr('timer'));
                });
            }

            // This adds the timer and starts it. But if we dont have a start page and its the first item
            // we need to defer the timer start until the item is shown
            if(self.itemdata.hidestartpage && self.game.pointer === 0){
                self.controls.container.on("showElement", () => {
                    doStartTimer();
                });
            }else{
                doStartTimer();
            }
        }
    }, //end of start timer

    stopTimer: function(timers) {
        if (timers.length) {
            timers.forEach(function(timer) {
                clearInterval(timer);
            });
        }
    },// end of stop timer

  } // end of return
});//end of define