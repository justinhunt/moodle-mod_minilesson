define(['jquery', 'core/log', 'mod_minilesson/definitions','mod_minilesson/pollyhelper',
    'mod_minilesson/ttrecorder', 'mod_minilesson/animatecss',
    'mod_minilesson/progresstimer', 'core/templates', 'core/chartjs', 'core/str', 'core/notification'],
    function($, log, def,polly, ttrecorder,anim, progresstimer, templates, chartjs, str, notification) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the fluency item type
   */

  log.debug('MiniLesson Fluency: initialising');

  var thefluencyitem = {
    phonemeWarningThreshold: 90, // Upper threshold for phoneme warning rate
    phonemeErrorThreshold: 70, // Upper threshold for phoneme error rate
    hidewarning: false, // Whether to hide the orange and just show red
    speechConfig: null,
    //this is just a placeholder for the actual text which is from the sentences in items
    referencetext: 'I met my love by the gas works wall',
    game: {pointer: 0},
    usevoice: '',
    items: null,
    strings: {},

    //for making multiple instances
    clone: function () {
          return $.extend(true, {}, this);
     },

    init: function(index, itemdata, quizhelper) {
      this.itemdata = itemdata;
      this.quizhelper = quizhelper;
      this.index = index;
      this.init_components(quizhelper, itemdata);
      this.init_strings();

      //correct threshold
      this.phonemeWarningThreshold = itemdata.correctthreshold || this.phonemeWarningThreshold;
      if(this.phonemeErrorThreshold >= this.phonemeWarningThreshold) {
          this.phonemeErrorThreshold = Math.max(this.phonemeWarningThreshold - 25, 1);
      }

      //Hide warning
      this.hidewarning = itemdata.hidewarning === 1;

      // Anim
      var animopts = {};
      animopts.useanimatecss = quizhelper.useanimatecss;
      anim.init(animopts);

      //Events and voice and items parse
      this.register_events(index, itemdata, quizhelper);
      this.setvoice();
      this.getItems();
    },

    init_components: function(quizhelper,itemdata){
      var self=this;

      self.thebutton = "thettrbutton"; // To Do impl. this
      self.container = $("#" + self.itemdata.uniqueid + "_container");
      self.wordcount = $("#" + self.itemdata.uniqueid + "_container span.ml_wordcount");
      self.actionbox = $("#" + self.itemdata.uniqueid + "_container div.ml_fluency_actionbox");
      self.pendingbox = $("#" + self.itemdata.uniqueid + "_container div.ml_fluency_pendingbox");
      self.resultsbox = $("#" + self.itemdata.uniqueid + "_container div.fluency_resultscontainer");
      self.timerdisplay = $("#" + self.itemdata.uniqueid + "_container div.ml_fluency_timerdisplay");
      self.audioplayerbtn =$("#" + self.itemdata.uniqueid + "_container .fluency_listen_btn");
      self.audioplayerbtn_audiomodel =$("#" + self.itemdata.uniqueid + "_container .fluency_listen_btn.audiomodel");
      self.audioplayerbtn_audioself =$("#" + self.itemdata.uniqueid + "_container .fluency_listen_btn.audioself");
      self.skipbtn = $("#" + self.itemdata.uniqueid + "_container .fluency_skip_btn");
      self.startbtn = $("#" + self.itemdata.uniqueid + "_container .fluency_start_btn");
      self.smallnextbtn = $("#" + self.itemdata.uniqueid + "_container .minilesson_nextbutton");
      self.ctrlbtns =  $("#" + self.itemdata.uniqueid + "_container .fluency_ctrl_btn");
      self.speakbtncont = $("#" + self.itemdata.uniqueid + "_container .fluency_speakbtncontainer");
      self.questioncont = $("#" + self.itemdata.uniqueid + "_container .question");
      self.listencont = $("#" + self.itemdata.uniqueid + "_container .fluency_listen_cont");
      self.mainmenu = $("#" + self.itemdata.uniqueid + "_container .fluency_mainmenu");
      self.mainstage = $("#" + self.itemdata.uniqueid + "_container .fluency_mainstage");
      self.controls = $("#" + self.itemdata.uniqueid + "_container .fluency_controls");
      self.progresscont = $("#" + self.itemdata.uniqueid + "_container .progress-container");
      self.description = $("#" + self.itemdata.uniqueid + "_container .fluency_description");
      self.image = $("#" + self.itemdata.uniqueid + "_container .fluency_image_container");
      self.maintitle = $("#" + self.itemdata.uniqueid + "_container .fluency_maintitle");
      self.itemquestion = $("#" + self.itemdata.uniqueid + "_container .fluency_itemtext");
      self.title = $("#" + self.itemdata.uniqueid + "_container .fluency_title");

      // Callback: Recorder updates.
      var recorderCallback = function(message) {

        switch (message.type) {
          case 'recording':
            break;

          case 'pronunciation_results':
            var speechresults= message.results;
            log.debug(speechresults);
            self.do_evaluation_feedback(speechresults);
            self.do_evaluation_stars(speechresults);
            self.do_recolor_continue_button(speechresults);
            // self.do_evaluation_results(speechresults);
            break;

            case 'mediasaved':
                // Save the returned media URL with the current item
                // There is a potential race condition here, since audio is set from blob in do_evaluation_feedback if not here.
                self.items[self.game.pointer].audioself = new Audio();
                self.items[self.game.pointer].audioself.src = message.mediaurl;
                log.debug('Media saved at fluency: ' + self.mediaurl);
                break;

        } //end of switch message type
      };

        //init tt recorder
        var opts = {};
        opts.uniqueid = itemdata.uniqueid;
        opts.callback = recorderCallback;
        opts.stt_guided=false;
        opts.referencetext=this.referencetext;
        self.ttrec = ttrecorder.clone();
        self.ttrec.init(opts);

    }, //end of init components

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
              target: target.sentence,
              prompt: target.prompt,
              parsedstring: target.parsedstring,
              displayprompt: target.displayprompt,
              definition: target.definition,
              phonetic: target.phonetic,
              words: target.words,
              typed: "",
              timer: [],
              answered: false,
              correct: false,
              audio: null,
              audiourl: target.audiourl ? target.audiourl : "",
              imageurl: target.imageurl,
              hintdisplay: target.hintdisplay,
          };
      }).filter(function(e) {
          return e.target !== "";
      });

      $.each(self.items, function(index, item) {
          item.audio = new Audio();
          item.audio.src = item.audiourl;
          if (self.items.filter(function(e) {
              return e.audio === null;
            }).length === 0) {
            self.appReady();
          }
      });
    },

    appReady: function() {
      var self = this;
      $("#" + self.itemdata.uniqueid + "_container .fluency_not_loaded").hide();
      $("#" + self.itemdata.uniqueid + "_container .fluency_loaded").show();
      if(self.itemdata.hidestartpage){
          self.start();
      }else{
          $("#" + self.itemdata.uniqueid + "_container .fluency_start_btn").prop("disabled", false);
      }
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

    next_question: function() {
      var self = this;
      var stepdata = {};
      stepdata.index = self.index;
      stepdata.hasgrade = true;
      stepdata.totalitems = self.items.length;
      stepdata.correctitems = self.items.filter(function(e) {
                return e.correct;
            }).length;
      stepdata.grade = Math.round((stepdata.correctitems / stepdata.totalitems) * 100);

        //stop audio
        self.stop_audio();

      //prepare results data for detailed review on finished page or by teacher
      var results_data = {};
      results_data.correctitems = self.items.filter(function(e) {return e.correct;}).length;
      results_data.totalitems = self.items.length;
      var includeaudioself = self.itemdata.savemedia === 1; // if false only get current session audio, not saved on s3 for later
      results_data.items = self.items_for_results_display(includeaudioself);
      stepdata.resultsdata = results_data;
      self.quizhelper.do_next(stepdata);
    },

    register_events: function(index, itemdata, quizhelper) {

      var self = this;
      // On next button click
      self.smallnextbtn.on('click', function(e) {
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

      // On start button click
      self.startbtn.on("click", function() {
          self.start();
      });

      //AUDIO PLAYER Button events
      // On listen button click
      self.audioplayerbtn.on("click", function () {
        const $button = $(this);  // capture the button
        var theaudioaudioself, theaudio;
        if($button.hasClass('audioself')) {
          theaudioaudioself = self.items[self.game.pointer].audioself;
        }else{
          theaudio = self.items[self.game.pointer].audio;
        }

        if (theaudioaudioself) {
            if(!theaudioaudioself.paused){
                theaudioaudioself.pause();
                theaudioaudioself.currentTime=0;
                $button.children('.fa').removeClass('fa-stop');
                $button.children('.fa').addClass('fa-play');
                return;
        }

            theaudioaudioself.addEventListener('ended', function () {
                $button.children('.fa').removeClass('fa-stop');
                $button.children('.fa').addClass('fa-play');
            });

            theaudioaudioself.addEventListener('play', function () {
                $button.children('.fa').removeClass('fa-play');
                $button.children('.fa').addClass('fa-stop');
            });
            theaudioaudioself.load();
            theaudioaudioself.play();
        }

        if(!theaudio.paused){
            theaudio.pause();
            theaudio.currentTime=0;
            $button.children('.fa').removeClass('fa-stop');
            $button.children('.fa').addClass('fa-volume-up');
            return;
        }

        //change icon to indicate playing state
        theaudio.addEventListener('ended', function () {
            $button.children('.fa').removeClass('fa-stop');
            $button.children('.fa').addClass('fa-volume-up');
        });

        theaudio.addEventListener('play', function () {
            $button.children('.fa').removeClass('fa-volume-up');
            $button.children('.fa').addClass('fa-stop');
        });
        theaudio.load();
        theaudio.play();
      });


      // On skip button click
      self.skipbtn.on("click", function() {

          self.stopTimer(self.items[self.game.pointer].timer);

          if (self.game.pointer < self.items.length - 1) {
              // Disable button and show spinner in place of text or arrow
              self.skipbtn.prop("disabled", true);
              self.skipbtn.children('.fa').removeClass('fa-arrow-right');
              self.skipbtn.children('.fa').addClass('fa-spinner fa-spin');


              // Move on after short time to next prompt
              setTimeout(function() {
                // Re enable button and reset icons and text.
                  self.skipbtn.children('.fa').removeClass('fa-spinner fa-spin');
                  self.skipbtn.children('.fa').addClass('fa-arrow-right');
                  self.skipbtn.prop("disabled", false);

                  // Move to next item.
                  self.container.find(".fluency_reply_" + self.game.pointer).hide();
                  self.game.pointer++;
                  self.nextPrompt();
              }, 1500);
              // End question
          } else {
              self.end();
          }
      });
    },

    end: function() {
      var self = this;
      self.smallnextbtn.prop("disabled", true);

      //progress dots are updated on next_item. The last item has no next item, so we update from here
      self.updateProgressDots();

      setTimeout(function() {
          self.smallnextbtn.prop("disabled",false);
          if(self.quizhelper.showitemreview){
               self.title.hide();
              self.show_item_review();
          }else{
              self.next_question();
          }
      }, 2000);

  },

  start: function() {
      var self = this;

      self.ctrlbtns.prop("disabled", true);
      self.speakbtncont.show();

      self.items.forEach(function(item) {
          item.spoken = "";
          item.answered = false;
          item.correct = false;
      });

      self.game.pointer = 0;

      self.questioncont.show();
      self.listencont.show();
      self.startbtn.hide();
      self.mainmenu.hide();
      self.description.hide();
      self.image.hide();
      self.maintitle.show();
      self.itemquestion.show();
      self.controls.show();

      self.nextPrompt();
  },

  show_item_review:function(){
      var self=this;
      //build review data
      var review_data = {};
      review_data.correctitems = self.items.filter(function(e) {return e.correct;}).length;
      review_data.totalitems = self.items.length;
      var includeaudioself = true;
      review_data.items = self.items_for_results_display(includeaudioself, true);

      //display results
      templates.render('mod_minilesson/listitemresults',review_data).then(
        function(html,js){
            self.resultsbox.html(html);
            //show and hide
            self.resultsbox.show();
            self.mainstage.hide();
            // Run js for audio player events
            templates.runTemplateJS(js);
        }
      );// End of templates
    },

  updateProgressDots: function(){
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
      $("#" + self.itemdata.uniqueid + "_container .fluency_title").html(progress);
  },

  nextPrompt: function() {
      var self = this;
      self.ctrlbtns.prop("disabled", false);
      self.updateProgressDots();
      var newprompt = self.container.find(".fluency_prompt_" + self.game.pointer);
      anim.do_animate(newprompt, 'zoomIn animate__faster', 'in').then(
          function() {
          }
      );
      self.nextReply();
  },


  nextReply: function() {
      var self = this;

      var code = "<div class='fluency_reply fluency_reply_" + self.game.pointer + " text-center' style='display:none;'>";

      code += "<div class='form-container'>";
      code += "<div class='fluency_prompt fluency_prompt_" + self.game.pointer + "'>";
      code += self.items[self.game.pointer].displayprompt || self.items[self.game.pointer].prompt;
      code += "</div>";
      if (self.items[self.game.pointer].hintdisplay) {
        code += "<div class='fluency_prompt_hint'>" + self.items[self.game.pointer].target + "</div>";
      }

      //correct or not
      code += " <i data-idx='" + self.game.pointer + "' class='fluency_feedback'></i></div>";

      //hint - image
    if( self.items[self.game.pointer].imageurl) {
        code += "<div class='minilesson_sentence_image'><div class='minilesson_padded_image'><img src='"
            + self.items[self.game.pointer].imageurl + "' alt='Image for gap fill' /></div></div>";
    }

      //feedback and results containers
      code += "<div class='item-results-container'></div>";
      code += "<div class='item-feedback-container my-4'></div>";

      $("#" + self.itemdata.uniqueid + "_container .question").append(code);
      var newreply = self.container.find(".fluency_reply_" + self.game.pointer);
      anim.do_animate(newreply, 'zoomIn animate__faster', 'in').then(
          function() {
          }
      );
      self.ctrlbtns.prop("disabled", false);

      //Start timer if we have one
      self.startTimer();

      //Hide the response audio because its not ready yet
       self.audioplayerbtn_audioself.hide();

      // We autoplay the audio on item entry, if its not a mobile user.
      // If we do not have a start page and its the first item, we play on the item show event
      if (!self.quizhelper.mobile_user()){
        if(self.itemdata.hidestartpage && self.game.pointer === 0){
            self.container.on("showElement", () => {
                setTimeout(function() {
                    self.audioplayerbtn_audiomodel.trigger('click');
                }, 1000);
            });
        }else{
            setTimeout(function() {
                self.audioplayerbtn_audiomodel.trigger('click');
            }, 1000);
        }
      }

      //target is the speech we expect
      var prompt = self.items[self.game.pointer].prompt;
      //in some cases ttrecorder wants to know the target
      if(self.quizhelper.use_ttrecorder()) {
        self.ttrec.update_currentprompt(prompt);
      }
    },

    startTimer: function(){
        var self = this;
        // If we have a time limit, set up the timer, otherwise return
        if (self.itemdata.timelimit > 0) {
            // This is a function to start the timer (we call it conditionally below)
            var doStartTimer = function() {
                    // This shows progress bar
                self.progresscont.show();
                self.progresscont.addClass('d-flex align-items-center');
                self.progresscont.find('i').show();
                var progresbar = self.progresscont.find('#progresstimer').progressTimer({
                    height: '5px',
                    timeLimit: self.itemdata.timelimit,
                    onFinish: function() {
                        self.skip_btn.trigger('click');
                    }
                });
                progresbar.each(function() {
                    self.items[self.game.pointer].timer.push($(this).attr('timer'));
                });
            };

            // This adds the timer and starts it. But if we dont have a start page and its the first item
            // we need to defer the timer start until the item is shown
            if(self.itemdata.hidestartpage && self.game.pointer === 0){
                self.container.on("showElement", () => {
                    doStartTimer();
                });
            }else{
                doStartTimer();
            }
        }
    },

    stopTimer: function(timers) {
      if (timers.length) {
          timers.forEach(function(timer) {
              clearInterval(timer);
          });
      }
    },

      // Stop audio .. usually when leaving the item or sentence
      stop_audio: function(){
          var self =this;
          //pause audio if its playing
          var theaudio = self.items[self.game.pointer].audio;
          if(theaudio && !theaudio.paused) {
              theaudio.pause();
          }
      },

    do_evaluation_feedback: function (pronunciation_result, isReview) {
        var self = this;
        //this is part of the generated html for each sentence in the item, so we need to create a handle each time
        var itemfeedbackcontainer = self.container.find(".item-feedback-container");

        // Clear previous results
        itemfeedbackcontainer.html("");

        var twoletterlang = self.itemdata.language.substr(0, 2);
        if(self.itemdata.rtl){
            var words = pronunciation_result.privPronJson.Words.reverse();
        }else{
            var words = pronunciation_result.privPronJson.Words;
        }


        // Render pronunciation feedback for each word
        var wordresults= [];
        words.forEach(function (wordobject) {

            // We are going to skip insertions, because MS Speech in Japanese especially seems to hallucinate them
            if(wordobject.PronunciationAssessment?.ErrorType === "Insertion"){
                log.debug("Skipping insertion word: ", wordobject.Word);
                return; // Skip this word
            }

            //For the bar beneath the word we need an array of phoneme scores
            // If we have syllables we use those, oddly syllables and phoneme scores are often different
            // so if we always used phonemes, the text markup and bar markup would be visibly different
            var word_phoneme_score_classes = [];


            // For the word/character markup we need to map phonemes or syllables to characters
            // we call this adata (alignment data)
            //MS Returns syllables, at least for English, and these have a grapheme so its the best data
            //for some words e.g "didn't" sometimes the grapheme is missing. duh
            //so we send it down the no grapheme path
            var have_graphemes = wordobject.Syllables && wordobject.Syllables.length > 0 && wordobject.Syllables[0].Grapheme;
            if(have_graphemes){
                //build our phonemes bar data
                wordobject.Syllables.forEach(function(syllable){
                    //If errortype =omission there will be no score, we use null to flag that
                    var thescore = syllable.PronunciationAssessment?.AccuracyScore || null;
                    word_phoneme_score_classes.push(
                        self.scoreToColorClass(thescore)
                    );
                });

                //Build alignment data
                var adata =[];
                //If errortype =omission there will be no score, we use null to flag that
                var thescore = wordobject.PronunciationAssessment?.AccuracyScore || null;
                wordobject.Syllables.forEach(function(syllable){
                    adata.push({
                        letter: syllable.Grapheme,
                        phoneme: syllable.Syllable,
                        score: thescore,
                    });
                });
            //If no syllable data we do our best to simulate it
            }else{

                //build our phonemes bar data
                //No syllables so use phonemes for our phonemes bar
                if(wordobject.Phonemes && wordobject.Phonemes.length > 0){
                    wordobject.Phonemes.forEach(function(phoneme){
                        //If errortype =omission there will be no score, we use null to flag that
                        var thescore = phoneme.PronunciationAssessment?.AccuracyScore || null;
                        word_phoneme_score_classes.push(
                            self.scoreToColorClass(thescore)
                        );
                    });
                }

                //Build alignment data
                // It turns out most msspeech langs will not have phoneme names which makes mapping hard(impossible)
                // but if we have them we can try to map them to letters.
                var have_phoneme_names = wordobject.Phonemes && wordobject.Phonemes.length > 0 && wordobject.Phonemes[0].Phoneme !== "";
                if(have_phoneme_names) {
                    var adata = self.markuphelper.alignPhonemesToLetters(wordobject.Word,
                        wordobject.Phonemes,
                        twoletterlang);
                // if we have no phoneme names or graphemes we just show the word as is with its score. This is awful
                // we just call the text 'phoneme' here but it means the word chunk and its score.
                // And the word chunk is the whole word
                }else{
                    var adata = [];
                    //If errortype =omission there will be no score, we use null to flag that
                    var thescore = wordobject.PronunciationAssessment?.AccuracyScore || null;
                    adata.push({
                        letter: wordobject.Word,
                        phoneme: wordobject.Word,
                        score: thescore,
                    });
                }
            }

            //assign css color classes to each item in alignment data based on its score and the item's warning threshold
            adata.forEach(function(a){
                a.scoreclass = self.scoreToColorClass(a.score);
            });

            // Store the results for this word, excluding phoneme bars if in review mode
            if (isReview) {
                wordresults.push({alignmentdata: adata});
            } else {
            wordresults.push({alignmentdata: adata, wordphonemes: word_phoneme_score_classes});
            }

        });

        //Display result of all words, with phoneme bars per word if we have them
        templates.render('mod_minilesson/fluencylineresult', {wordresults: wordresults}).then(
            function(html, js) {
                // Add result html to feedback container
                itemfeedbackcontainer.html(html);
                //store results for later use
                self.items[self.game.pointer].pronunciation_result = pronunciation_result;
                self.items[self.game.pointer].answered = true;
                self.items[self.game.pointer].correct = pronunciation_result.privPronJson.PronunciationAssessment.AccuracyScore >= self.phonemeWarningThreshold;
                // Store audio blob url for playback later.
                // But if savemedia is set this will already (probably) be an s3 url.
                // If this causes issues, replace this if with: if (self.itemdata.savemedia !== 1) {
                if(!self.items[self.game.pointer].audioself) {
                    self.items[self.game.pointer].audioself = new Audio();
                    self.items[self.game.pointer].audioself.src = URL.createObjectURL(self.ttrec.audio.blob);
                }
                self.items[self.game.pointer].lineresulthtml = html;

                // Also store a review version without phoneme bars if not already in review mode
                if (!isReview) {
                    // Generate review version without phoneme bars
                    var wordresults_review = [];
                    wordresults.forEach(function(wr) {
                        wordresults_review.push({alignmentdata: wr.alignmentdata});
                    });
                    templates.render('mod_minilesson/fluencylineresult', {wordresults: wordresults_review}).then(
                        function(reviewhtml, reviewjs) {
                            self.items[self.game.pointer].lineresulthtml_review = reviewhtml;
                        }
                    );
                }

                //since we now have audio, show the self audio player button
                self.audioplayerbtn_audioself.show();

                //update progress dots
                self.updateProgressDots();
            }
        );
    },

    do_recolor_continue_button: function(pronunciation_result) {
        var self = this;

        var accuracyScore = pronunciation_result.accuracyScore;
        var warningthreshold = self.phonemeWarningThreshold;
    },

    do_evaluation_stars: function(pronunciation_result) {
        var self = this;
        log.debug("Accuracy score: ", pronunciation_result.accuracyScore);

        //this is part of the generated html for each sentence in the item, so we need to create a handle each time
        var itemstarscontainer = $("#" + self.itemdata.uniqueid + "_container .item-results-container");
        // Clear previous results
        itemstarscontainer.html("");
        // Create star ratings
        var starRating = $("<div class='fluency_star_rating'>");
        var accuracyScore = pronunciation_result.accuracyScore;
        var maxStars = 5;
        // calculate starBandWidth // band 1 = 0-19 / band 2 = 20-39 etc.
        var correctBandwidth = 100 - self.phonemeWarningThreshold;
        var starBandWidth = (100 - correctBandwidth) / 4;

        for (var i = 0; i < maxStars; i++) {
            var star = $("<i class='fa'>");
            if (i <= accuracyScore / starBandWidth ) {
                star.addClass("fa-star");
            } else {
                star.addClass("fa-star-o");
            }
            starRating.append(star);
        }
        // Append star rating to the feedback container
        itemstarscontainer.append(starRating);
        // Add a message based on the accuracy score
        /*
        var message = $("<div class='fluency_feedback_message'>");
        if (accuracyScore >= 80) {
            message.text("Great job! Your pronunciation is excellent.");
        } else if (accuracyScore >= 50) {
            message.text("Good effort! Keep practicing to improve your pronunciation.");
        } else {
            message.text("Keep trying! Focus on the pronunciation of individual words.");
        }

        itemstarscontainer.append(message);
         */
    },

      //The old evaluation display with some radial charts etc. We might want to use it later
      do_evaluation_results: function(pronunciation_result) {
        var self = this;
        //this is part of the generated html for each sentence in the item, so we need to create a handle each time
        var itemresultscontainer = self.container.find(".item-results-container");

        // Clear previous results
        itemresultscontainer.html("");
        var itemresults = [];

        itemresults.push("Accuracy score: " + pronunciation_result.accuracyScore);
        itemresults.push("Pronunciation score: " + pronunciation_result.pronunciationScore);
        itemresults.push("Completeness score: " + pronunciation_result.completenessScore);
        itemresults.push("Fluency score: " + pronunciation_result.fluencyScore);
        itemresults.push("Prosody score: " +  pronunciation_result.prosodyScore);
        itemresultscontainer.append(itemresults.join("<br>"));

        //make a chart for each score
        var labels = ["Accuracy", "Pronunciation", "Completeness", "Fluency", "Prosody"];
        var data = [
            pronunciation_result.accuracyScore,
            pronunciation_result.pronunciationScore,
            pronunciation_result.completenessScore,
            pronunciation_result.fluencyScore,
            pronunciation_result.prosodyScore
        ];
        labels.forEach(function(label, index) {
            var chartContainerId = self.itemdata.uniqueid + "_chart_" + index;
            itemresultscontainer.append('<canvas id="' + chartContainerId + '"></canvas>');
            self.createRadialChart(chartContainerId, [label], [data[index]]);
        });

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

    createRadialChart: function(containerId, labels, data) {
        var ctx = document.getElementById(containerId).getContext('2d');
        new chartjs(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Scores',
                    data: data,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scale: {
                    ticks: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    },

    //Prepare items for display in the listitemresults template (here and letter in finished review)
    items_for_results_display: function(includeaudioself, isReview) {
          var self = this;
          return self.items.map(function(target) {
              var resulthtml = target.answered ? (isReview && target.lineresulthtml_review ? target.lineresulthtml_review : target.lineresulthtml) : target.target;
              return {
                  target: resulthtml,
                  pronunciation_result: target.pronunciation_result,
                  answered: target.answered,
                  correct: target.correct,
                  audio: target.audio ? {src: target.audio.src} : null,
                  audioself: (includeaudioself && target.audioself) ? {src: target.audioself.src} : null,
              };
          });
    },

    scoreToColorClass: function(score)
      {
          if (score === null) return "letter_missing"; //grey
          if (score >= this.phonemeWarningThreshold) return "letter_good"; //green
          if (score >= this.phonemeErrorThreshold){
              if(this.hidewarning) {
                  return "letter_wrong"; // red
              }else{
                  return "letter_fair"; // orange
              }
          }
          return "letter_wrong"; //red
     },

    //Marking up graphemes letters and phonemes is a dark art, we do that in this object
    markuphelper: {
        // Language mappings for phoneme to grapheme groups
        languageMappings: {
            en: {
                graphemeGroups: ["sh", "th", "ch", "ph", "wh", "ck", "ng"],
                phonemeGroups: {
                    "ʃ": ["sh"],
                    "tʃ": ["ch"],
                    "θ": ["th"],
                    "ŋ": ["ng"],
                }
            },
            es: {
                graphemeGroups: ["ll", "ch", "rr"],
                phonemeGroups: {
                    "ʎ": ["ll"],
                    "tʃ": ["ch"],
                    "r": ["rr"],
                }
            },
            fr: {
                graphemeGroups: ["ch", "gn", "ou", "eau", "oi"],
                phonemeGroups: {
                    "ʃ": ["ch"],
                    "ɲ": ["gn"],
                    "u": ["ou"],
                    "o": ["eau"],
                    "wa": ["oi"]
                }
            },
            // This assumes no diacritics or accents in the Arabic letters
            ar: {
                    graphemeGroups: ["ث", "ذ", "ش", "خ", "غ", "ق", "ع", "ص", "ض", "ط", "ظ", "ء"],
                    phonemeGroups: {
                    // Fricatives
                    "θ": ["ث"],     // voiceless dental fricative
                    "ð": ["ذ"],     // voiced dental fricative
                    "ʃ": ["ش"],     // voiceless postalveolar fricative (sh)
                    "x": ["خ"],     // voiceless uvular fricative
                    "ɣ": ["غ"],     // voiced uvular fricative
                    "ħ": ["ح"],     // voiceless pharyngeal fricative
                    "ʕ": ["ع"],     // voiced pharyngeal fricative
                    "h": ["ه"],     // glottal fricative

                    // Emphatics
                    "sˤ": ["ص"],
                    "dˤ": ["ض"],
                    "tˤ": ["ط"],
                    "ðˤ": ["ظ"],

                    // Stops and other consonants
                    "q": ["ق"],
                    "ʔ": ["ء"],     // glottal stop (hamza)

                    // Long vowels (represented as letters)
                    "aː": ["ا"],    // alif
                    "uː": ["و"],    // waw
                    "iː": ["ي"]     // yaa
                    }
                }
                ,
            ru: {
                graphemeGroups: ["щ", "ч", "ш", "ж", "ю", "я"],
                phonemeGroups: {
                "ɕː": ["щ"],
                "tɕ": ["ч"],
                "ʂ": ["ш"],
                "ʒ": ["ж"],
                "ju": ["ю"],
                "ja": ["я"]
                }
            },
            no: {
                graphemeGroups: ["kj", "sj", "ng"],
                phonemeGroups: {
                "ç": ["kj"],
                "ʃ": ["sj"],
                "ŋ": ["ng"]
                }
            },
            so: {
                graphemeGroups: ["kh", "dh", "sh"],
                phonemeGroups: {
                "x": ["kh"],
                "ð": ["dh"],
                "ʃ": ["sh"]
                }
            },
            de: {
                graphemeGroups: ["sch", "ch", "ng", "sp", "st", "z"],
                phonemeGroups: {
                "ʃ": ["sch"],
                "ç": ["ch"],
                "ŋ": ["ng"],
                "ʃp": ["sp"],
                "ʃt": ["st"],
                "ts": ["z"]
                }
            },
            it: {
                graphemeGroups: ["gl", "gn", "sc", "ch", "ci", "ce"],
                phonemeGroups: {
                "ʎ": ["gl"],
                "ɲ": ["gn"],
                "ʃ": ["sc"],
                "k": ["ch"],
                "tʃ": ["ci", "ce"]
                }
            }
        },


        alignPhonemesToLetters: function(word, phonemesWithScores, language = "en") {
            const { graphemeGroups = [], phonemeGroups = {} } = this.languageMappings[language] || {};

            // Normalize letters (strip accents, diacritics)
            function normalizeWord(word, language) {
                switch (language) {
                    case "ar": return word.normalize("NFKD").replace(/[\u064B-\u065F\u0670]/g, "");
                    default: return word.normalize("NFKD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
                }
            }

            // Chunk graphemes into known groups
            function tokenizeGraphemes(word, groups) {
                const tokens = [];
                let i = 0;
                while (i < word.length) {
                    let matched = false;
                    for (const g of groups.sort((a, b) => b.length - a.length)) {
                        if (word.slice(i, i + g.length) === g) {
                            tokens.push(g);
                            i += g.length;
                            matched = true;
                            break;
                        }
                    }
                    if (!matched) {
                        tokens.push(word[i]);
                        i += 1;
                    }
                }
                return tokens;
            }

            // Tokenize phonemes based on mappings
            function normalizePhoneme(p) {
                for (const group in phonemeGroups) {
                    if (phonemeGroups[group].includes(p)) return group;
                }
                return p;
            }

            const normWord = normalizeWord(word, language);
            const graphemes = tokenizeGraphemes(normWord, graphemeGroups);
            const phonemes = phonemesWithScores.map(p => normalizePhoneme(p.Phoneme));

            // Dynamic programming
            const m = graphemes.length, n = phonemes.length;
            const dp = Array(m + 1).fill().map(() => Array(n + 1).fill(0));
            const traceback = Array(m + 1).fill().map(() => Array(n + 1).fill(null));

            const matchCost = 0, mismatchCost = 1, gap = 1;

            for (let i = 0; i <= m; i++) { dp[i][0] = i * gap; traceback[i][0] = "up"; }
            for (let j = 0; j <= n; j++) { dp[0][j] = j * gap; traceback[0][j] = "left"; }

            for (let i = 1; i <= m; i++) {
                for (let j = 1; j <= n; j++) {
                    const letter = graphemes[i - 1];
                    const phoneme = phonemes[j - 1];
                    const cost = letter === phoneme ? matchCost : mismatchCost;

                    const diag = dp[i - 1][j - 1] + cost;
                    const up = dp[i - 1][j] + gap;
                    const left = dp[i][j - 1] + gap;

                    dp[i][j] = Math.min(diag, up, left);
                    traceback[i][j] = (dp[i][j] === diag) ? "diag" : (dp[i][j] === up ? "up" : "left");
                }
            }

            let i = m, j = n;
            const result = [];

            while (i > 0 || j > 0) {
                const move = traceback[i][j];
                if (move === "diag") {
                    result.unshift({
                        letter: graphemes[i - 1],
                        phoneme: phonemesWithScores[j - 1].Phoneme,
                        score: phonemesWithScores[j - 1].PronunciationAssessment.AccuracyScore,
                    });
                    i--; j--;
                } else if (move === "up") {
                    result.unshift({ letter: graphemes[i - 1], phoneme: null, score: null });
                    i--;
                } else {
                    j--;
                }
            }

            return result;
        },

    }//end of markup helper


    };//end of thefluencyitem
    return thefluencyitem;
});