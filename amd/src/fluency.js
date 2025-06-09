define(['jquery', 'core/log', 'mod_minilesson/definitions','mod_minilesson/pollyhelper',
    'mod_minilesson/ttrecorder', 'mod_minilesson/animatecss',
    'mod_minilesson/progresstimer', 'core/templates', 'core/chartjs'],
    function($, log, def,polly, ttrecorder,anim, progresstimer, templates, chartjs) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the fluency item type
   */

  log.debug('MiniLesson Fluency: initialising');

  var thefluencyitem = {
    phonemeWarningThreshold: 90, // Threshold for phoneme error rate
    phonemeErrorThreshold: 70, // Threshold for phoneme error rate

    speechConfig: null,
    //this is just a placeholder for the actualtext which is from the sentences in items
    referencetext: 'I met my love by the gas works wall',
    game: {pointer: 0},
    usevoice: '',
    items: null,

    //for making multiple instances
      clone: function () {
          return $.extend(true, {}, this);
     },

    init: function(index, itemdata, quizhelper) {
      this.itemdata = itemdata;
      this.quizhelper = quizhelper;
      this.index = index;
      this.init_components(quizhelper, itemdata);

      //correct threshold
      this.phonemeWarningThreshold = itemdata.correctthreshold || this.phonemeWarningThreshold;
      if(this.phonemeErrorThreshold >= this.phonemeWarningThreshold) {
          this.phonemeErrorThreshold = Math.max(this.phonemeWarningThreshold - 25, 1);
      }

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
           // self.do_evaluation_results(speechresults);   
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
      var includeaudioself = false;
      results_data.items = self.items_for_results_display(includeaudioself);
      stepdata.resultsdata = results_data;
      self.quizhelper.do_next(stepdata);
    },

    register_events: function(index, itemdata, quizhelper) {
      
      var self = this;
      // On next button click
      self.smallnextbtn.on('click', function(e) {
          self.next_question();
      });

      // On start button click
      self.startbtn.on("click", function() {
          self.start();
      });

      //AUDIO PLAYER Button events
      // On listen button click
      self.audioplayerbtn.on("click", function () {
        const $button = $(this);  // capture the button
        if($button.hasClass('audioself')) {
          var theaudio = self.items[self.game.pointer].audioself;
        }else{
          var theaudio = self.items[self.game.pointer].audio;
        }

        //if we are already playing stop playing
        if(!theaudio.paused){
            theaudio.pause();
            theaudio.currentTime=0;
            $button.children('.fa').removeClass('fa-stop');
            $button.children('.fa').addClass('fa-play');
            return;
        }

        //change icon to indicate playing state
        theaudio.addEventListener('ended', function () {
            $button.children('.fa').removeClass('fa-stop');
            $button.children('.fa').addClass('fa-play');
        });

        theaudio.addEventListener('play', function () {
            $button.children('.fa').removeClass('fa-play');
            $button.children('.fa').addClass('fa-stop');
        });
        theaudio.load();
        theaudio.play();
      });


      // On skip button click
      self.skipbtn.on("click", function() {

          self.stopTimer(self.items[self.game.pointer].timer);

          if (self.game.pointer < self.items.length - 1) {
              // Move on after short time to next prompt
              setTimeout(function() {
                  $(".fluency_reply_" + self.game.pointer).hide();
                  self.game.pointer++;
                  self.nextPrompt();
              }, 2000);
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
      review_data.items = self.items_for_results_display(includeaudioself);

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
      $("#" + self.itemdata.uniqueid + "_container .fluency_title").html(progress);
  },

  nextPrompt: function() {
      var self = this;
      self.ctrlbtns.prop("disabled", false);
      self.updateProgressDots();
      var newprompt = $(".fluency_prompt_" + self.game.pointer);
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
     
      //correct or not
      code += " <i data-idx='" + self.game.pointer + "' class='fluency_feedback'></i></div>";

      //hint - image
    if( self.items[self.game.pointer].imageurl) {
        code += "<div class='minilesson_sentence_image'><div class='minilesson_padded_image'><img src='"
            + self.items[self.game.pointer].imageurl + "' alt='Image for gap fill' /></div></div>";
    }

      //feedback and results containers
      code += "<div class='item-results-container'></div>";
      code += "<div class='item-feedback-container'></div>";
      
      $("#" + self.itemdata.uniqueid + "_container .question").append(code);
      var newreply = $(".fluency_reply_" + self.game.pointer);
      anim.do_animate(newreply, 'zoomIn animate__faster', 'in').then(
          function() {
          }
      );
      self.ctrlbtns.prop("disabled", false);

      if (self.itemdata.timelimit > 0) {
          self.progresscont.show();
          self.progresscont.find('i').show();
          var progresbar = self.progresscont.find('#progresstimer').progressTimer({
              height: '5px',
              timeLimit: self.itemdata.timelimit,
              onFinish: function() {
                  self.skipbtn.trigger('click');
              }
          });

          progresbar.each(function() {
              self.items[self.game.pointer].timer.push($(this).attr('timer'));
          });
      }

      //Hide the response audio because its not ready yet
       self.audioplayerbtn_audioself.hide();

      //Autoplay the audio
      if (!self.quizhelper.mobile_user()) {
          setTimeout(function() {
              self.audioplayerbtn_audiomodel.trigger('click');
          }, 1000);
      }

      //target is the speech we expect
      var target = self.items[self.game.pointer].target;
      //in some cases ttrecorder wants to know the target
      if(self.quizhelper.use_ttrecorder()) {
        self.ttrec.update_currentprompt(target);
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

    do_evaluation_feedback: function (pronunciation_result) {
        var self = this;
        //this is part of the generated html for each sentence in the item, so we need to create a handle each time
        var itemfeedbackcontainer = $("#" + self.itemdata.uniqueid + "_container .item-feedback-container");

        // Clear previous results
        itemfeedbackcontainer.html("");

        var twoletterlang = self.itemdata.language.substr(0, 2);
        if(self.itemdata.rtl){
            var words = pronunciation_result.privPronJson.Words.reverse();
        }else{
            var words = pronunciation_result.privPronJson.Words;
        }
      
      
        // Render pronunciation feedback for each word
        var lineresulthtml="";
        words.forEach(function (wordobject) {
            //MS Returns syllables, at least for English, and these have a grapheme so its the best data
            //for some words e.g "didn't" sometimes the grapheme is missing. duh
            //so we send it down the no grapheme path
            var have_graphemes = wordobject.Syllables && wordobject.Syllables.length > 0 && wordobject.Syllables[0].Grapheme;
            if(have_graphemes){
                var adata =[];
                wordobject.Syllables.forEach(function(syllable){
                    adata.push({
                        letter: syllable.Grapheme,
                        phoneme: syllable.Syllable,
                        score: syllable.PronunciationAssessment.AccuracyScore,
                    });
                });
            //If no syllable data we do our best to simulate it    
            }else{
                var adata = self.markuphelper.alignPhonemesToLetters(wordobject.Word, 
                    wordobject.Phonemes, 
                    twoletterlang);
            }
            lineresulthtml += self.markuphelper.renderPronunciationFeedback(adata);
        });

        //Display result
        itemfeedbackcontainer.html(lineresulthtml);

        //store results for later use
        self.items[self.game.pointer].pronunciation_result = pronunciation_result;
        self.items[self.game.pointer].answered = true;
        self.items[self.game.pointer].correct = pronunciation_result.privPronJson.PronunciationAssessment.AccuracyScore >= self.phonemeWarningThreshold;
        self.items[self.game.pointer].audioself = new Audio();
        self.items[self.game.pointer].audioself.src = URL.createObjectURL(self.ttrec.audio.blob);
        self.items[self.game.pointer].lineresulthtml = lineresulthtml;

        //since we now have audio, show the self audio player button
        self.audioplayerbtn_audioself.show();

        //update progress dots
        self.updateProgressDots();
        
        //We no longer do this!!! left in for future ref.
        // If we are correct move to next item
        if(false && self.items[self.game.pointer].correct ){
            if ((self.game.pointer < self.items.length - 1)) {
                log.debug('moving to next prompt B');
                setTimeout(function() {
                    $("#" + self.itemdata.uniqueid + "_container .fluency_reply_" + self.game.pointer).hide();
                    self.game.pointer++;
                    self.nextReply();
                }, 2000);
            } else {
                self.end();
            }
        }
    },

    do_evaluation_stars: function(pronunciation_result) {
        var self = this;
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
        var itemresultscontainer = $("#" + self.itemdata.uniqueid + "_container .item-results-container");

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
    items_for_results_display: function(includeaudioself) {
          var self = this;
          return self.items.map(function(target) {
              return {
                  target: target.lineresulthtml, //target.sentence,
                  pronunciation_result: target.pronunciation_result,
                  answered: target.answered,
                  correct: target.correct,
                  audio: target.audio ? {src: target.audio.src} : null,
                  audioself: (includeaudioself && target.audioself) ? {src: target.audioself.src} : null,
              };
          });
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
            ar: {
                graphemeGroups: [], // Arabic uses isolated characters
                phonemeGroups: {}   // Optional: use Arabic IPA mappings if needed
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

        scoreToColorClass: function(score)
        {
            if (score === null) return "letter_missing"; //grey
            if (score >= thefluencyitem.phonemeWarningThreshold) return "letter_good"; //green
            if (score >= thefluencyitem.phonemeErrorThreshold) return "letter_fair"; //orange
            return "letter_wrong"; //red
        },

        renderPronunciationFeedback: function( alignmentData) {
            var mhelper = this;

            const $wrapper = $("<div class='fluencywordresult'>");

            alignmentData.forEach(({letter, phoneme, score}) => {
                var resultcolorclass = mhelper.scoreToColorClass(score);
                const $span = $("<span class='fluencyletterresult " + resultcolorclass + "'>")
                    .text(letter);

                // Tooltip
                const tooltipText = score === null
                    ? "No phoneme matched"
                    : `Phoneme: ${phoneme}, Score: ${score}`;
                $span.attr("title", tooltipText);
                $wrapper.append($span);
            });

            return $wrapper.prop('outerHTML');
        } //end of renderPronunciationFeedback
    }//end of markup helper


    };//end of thefluencyitem
    return thefluencyitem;
});