define(['jquery', 'core/log', 'mod_minilesson/definitions','mod_minilesson/pollyhelper','mod_minilesson/cloudpoodllloader',
    'mod_minilesson/ttrecorder', 'mod_minilesson/animatecss',
    'mod_minilesson/progresstimer', 'core/templates', 'core/chartjs'],
    function($, log, def,polly, cloudpoodll, ttrecorder,anim, progresstimer, templates, chartjs) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the fluency item type
   */

  log.debug('MiniLesson Fluency: initialising');

  return {

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
      this.init_components(quizhelper, itemdata);

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
      self.resultsbox = $("#" + self.itemdata.uniqueid + "_container div.ml_fluency_resultsbox");
      self.timerdisplay = $("#" + self.itemdata.uniqueid + "_container div.ml_fluency_timerdisplay");
      self.audioplayerbtn =$("#" + self.itemdata.uniqueid + "_container .fluency_listen_btn");
      self.skipbtn = $("#" + self.itemdata.uniqueid + "_container .fluency_skip_btn");
      self.startbtn = $("#" + self.itemdata.uniqueid + "_container .fluency_start_btn");
      self.smallnextbtn = $("#" + self.itemdata.uniqueid + "_container .minilesson_nextbutton");
      self.bignextbtn = $(".minilesson_nextbutton");
      self.ctrlbtns =  $("#" + self.itemdata.uniqueid + "_container .fluency_ctrl_btn");
      self.speakbtncont = $("#" + self.itemdata.uniqueid + "_container .fluency_speakbtncontainer");
      self.questioncont = $("#" + self.itemdata.uniqueid + "_container .question");
      self.listencont = $("#" + self.itemdata.uniqueid + "_container .fluency_listen_cont");
      self.mainmenu = $("#" + self.itemdata.uniqueid + "_container .fluency_mainmenu");
      self.controls = $("#" + self.itemdata.uniqueid + "_container .fluency_controls");
      self.progresscont = $("#" + self.itemdata.uniqueid + "_container .progress-container");
      self.itemresultscont = $("#" + self.itemdata.uniqueid + "_container .item-results-container");

      // Callback: Recorder updates.
      var recorderCallback = function(message) {

        switch (message.type) {
          case 'recording':
            break;

          case 'pronunciation_results':
            var speechresults= message.results;
            self.do_evaluation(speechresults);    
        } //end of switch message type
      };

      if(quizhelper.use_ttrecorder()) {
          //init tt recorder
          var opts = {};
          opts.uniqueid = itemdata.uniqueid;
          opts.callback = recorderCallback;
          opts.stt_guided=false;
          opts.referencetext=this.referencetext;
          self.ttrec = ttrecorder.clone();
          self.ttrec.init(opts);

      }else{
          //init cloudpoodll push recorder
          cloudpoodll.init('minilesson-recorder-fluency-' + itemdata.id, recorderCallback);
      }
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
      $("#" + self.itemdata.uniqueid + "_container .fluency_not_loaded").hide();
      $("#" + self.itemdata.uniqueid + "_container .fluency_loaded").show();
      if(self.itemdata.hidestartpage){
          self.start();
      }else{
          $("#" + self.itemdata.uniqueid + "_container .fluency_start_btn").prop("disabled", false);
      }
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
      // On next button click
      self.bignextbtn.on('click', function(e) {
          self.next_question(0);
      });

      // On start button click
      self.startbtn.on("click", function() {
          self.start();
      });

      //AUDIO PLAYER Button events
      // On listen button click
      if(self.itemdata.readsentence) {
          self.audioplayerbtn.on("click", function () {
              var theaudio = self.items[self.game.pointer].audio;

              //if we are already playing stop playing
              if(!theaudio.paused){
                  theaudio.pause();
                  theaudio.currentTime=0;
                  $(self.audioplayerbtn).children('.fa').removeClass('fa-stop');
                  $(self.audioplayerbtn).children('.fa').addClass('fa-volume-up');
                  return;
              }

              //change icon to indicate playing state
              theaudio.addEventListener('ended', function () {
                  $(self.audioplayerbtn).children('.fa').removeClass('fa-stop');
                  $(self.audioplayerbtn).children('.fa').addClass('fa-volume-up');
              });

              theaudio.addEventListener('play', function () {
                  $(self.audioplayerbtn).children('.fa').removeClass('fa-volume-up');
                  $(self.audioplayerbtn).children('.fa').addClass('fa-stop');
              });
              theaudio.load();
              theaudio.play();
          });
      }

      // On skip button click
      self.skipbtn.on("click", function() {
          // Disable all the control buttons
         self.ctrlbtns.prop("disabled", true);
          // Reveal the prompt
          $("#" + self.itemdata.uniqueid + "_container .fluency_speech.fluency_teacher_left").text(self.items[self.game.pointer].prompt + "");
          // Reveal the answer
          var targetwords =  $("#" + self.itemdata.uniqueid + "_container .fluency_targetWord");
          targetwords.each(function() {
              var realidx = $(this).data("realidx");
              var fluency_targetWord = self.items[self.game.pointer].fluency_targetWords[realidx];
              $(this).val(fluency_targetWord);
          });

          self.stopTimer(self.items[self.game.pointer].timer);

          //mark as answered and incorrect
          self.items[self.game.pointer].answered = true;
          self.items[self.game.pointer].correct = false;

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
      self.bignextbtn.prop("disabled", true);

      //progress dots are updated on next_item. The last item has no next item, so we update from here
      self.updateProgressDots();

      setTimeout(function() {
          self.bignextbtn.prop("disabled",false);
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
      if(self.itemdata.readsentence) {
          self.listencont.show();
      }
      self.startbtn.hide();
      self.mainmenu.hide();
      self.controls.show();

      self.nextPrompt();
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
      self.items[self.game.pointer].parsedstring.forEach(function(data, index) {
          if (data.type === 'input') {
              code += "<input class='single-character' autocomplete='off' type='text' name='filltext" + index + "' maxlength='1' data-index='" + index + "' readonly>";
          } else if (data.type === 'mtext') {
              code += "<input class='single-character-mtext' type='text' name='readonly" + index + "' maxlength='1' value='" + data.character + "' readonly>";
          } else {
              code += data.character;
          }
      });
      //correct or not
      code += " <i data-idx='" + self.game.pointer + "' class='fluency_feedback'></i></div>";

      //definition
      code += "<div class='item-results-container'>";
      code += "</div>";


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

      if (!self.quizhelper.mobile_user()) {
          setTimeout(function() {
              self.audioplayerbtn.trigger('click');
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

    do_evaluation: function (pronunciation_result) {
      var self = this;
      var itemresultscont = $("#" + self.itemdata.uniqueid + "_container .item-results-container");
      itemresultscont.html("");
      
      var itemresults = [];

      itemresults.push("Accuracy score: " + pronunciation_result.accuracyScore);
      itemresults.push("Pronunciation score: " + pronunciation_result.pronunciationScore);
      itemresults.push("Completeness score: " + pronunciation_result.completenessScore);
      itemresults.push("Fluency score: " + pronunciation_result.fluencyScore);
      itemresults.push("Prosody score: " +  pronunciation_result.prosodyScore);
      itemresultscont.append(itemresults.join("<br>"));

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
            itemresultscont.append('<canvas id="' + chartContainerId + '"></canvas>');
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

  };
});