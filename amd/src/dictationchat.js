define(['jquery', 'core/log', 'mod_poodlltime/definitions', 'mod_poodlltime/pollyhelper'], function($, log, def, polly) {
  "use strict"; // jshint ;_;

  log.debug('Poodll Time dictation chat: initialising');

  return {

    init: function(index, itemdata, quizhelper) {
      var self = this;
      self.itemdata = itemdata;
      self.quizhelper = quizhelper;
      self.index = index;
      self.register_events();
      self.setvoice();
      self.getItems();
    },

    register_events: function() {

      var self = this;

      $("#" + self.itemdata.uniqueid + "_container .poodlltime_nextbutton").on('click', function(e) {
        var stepdata = {};
        var grade = 50;
        stepdata.index = index;
        stepdata.grade = grade;
        self.quizhelper.do_next(stepdata);
      });

      $("#" + self.itemdata.uniqueid + "_container .dictate_start_btn").on("click", function() {
        self.start();
      });

      $("#" + self.itemdata.uniqueid + "_container .dictate_listen_btn").on("click", function() {
        self.items[self.game.pointer].audio.load();
        self.items[self.game.pointer].audio.play();
      });

      $("#" + self.itemdata.uniqueid + "_container .dictate_skip_btn").on("click", function() {
        $("#" + self.itemdata.uniqueid + "_container .dictate_ctrl-btn").prop("disabled", true);
        $("#" + self.itemdata.uniqueid + "_container .dictate_speech.dictate_teacher_left").text(self.items[self.game.pointer].target + "");
        setTimeout(function(){
          if (self.game.pointer < self.items.length - 1) {
            self.items[self.game.pointer].answered = true;
            self.items[self.game.pointer].correct = false;
            self.game.pointer++;
            self.nextPrompt();
          } else {
            self.end();
          }
        },3000);
      });

      $("#" + self.itemdata.uniqueid + "_container .dictate_check_btn").on("click", function() {

        var passage = self.items[self.game.pointer].target;
        var transcriptArray = [];

        $("#" + self.itemdata.uniqueid + "_container .dictate_targetWord").each(function() {
          transcriptArray.push($(this).val().trim()==""?"|":$(this).val().trim());
        })

        var transcript = transcriptArray.join(" ");

        self.getComparison(passage, transcript, function(comparison) {
          self.gotComparison(comparison, transcript);
        });

      })
    },
    spliton: new RegExp('([,.!?:;" ])', 'g'),
    game: {
      pointer: 0
    },
    usevoice: 'Amy',
    setvoice: function() {
      var self = this;
      var language = "English(US)";
      var mf = "Female";
      var voice = 'Amy';
      switch (language) {
        case "English(US)":
          voice = mf == 'Male' ? 'Joey' : 'Kendra';
          break;
        case "English(GB)":
          voice = mf == 'Male' ? 'Brian' : 'Amy';
          break;
        case "English(AU)":
          voice = mf == 'Male' ? 'Russell' : 'Nicole';
          break;
        case "English(IN)":
          voice = mf == 'Male' ? 'Aditi' : 'Raveena';
          break;
        case "English(Welsh)":
          voice = mf == 'Male' ? 'Geraint' : 'Geraint';
          break;
        case "Danish":
          voice = mf == 'Male' ? 'Mads' : 'Naja';
          break;
        case "Dutch":
          voice = mf == 'Male' ? 'Ruben' : 'Lotte';
          break;
        case "French(FR)":
          voice = mf == 'Male' ? 'Mathieu' : 'Celine';
          break;
        case "French(CA)":
          voice = mf == 'Male' ? 'Chantal' : 'Chantal';
          break;
        case "German":
          voice = mf == 'Male' ? 'Hans' : 'Marlene';
          break;
        case "Icelandic":
          voice = mf == 'Male' ? 'Karl' : 'Dora';
          break;
        case "Italian":
          voice = mf == 'Male' ? 'Carla' : 'Giorgio';
          break;
        case "Japanese":
          voice = mf == 'Male' ? 'Takumi' : 'Mizuki';
          break;
        case "Korean":
          voice = mf == 'Male' ? 'Seoyan' : 'Seoyan';
          break;
        case "Norwegian":
          voice = mf == 'Male' ? 'Liv' : 'Liv';
          break;
        case "Polish":
          voice = mf == 'Male' ? 'Jacek' : 'Ewa';
          break;
        case "Portugese(BR)":
          voice = mf == 'Male' ? 'Ricardo' : 'Vitoria';
          break;
        case "Portugese(PT)":
          voice = mf == 'Male' ? 'Cristiano' : 'Ines';
          break;
        case "Romanian":
          voice = mf == 'Male' ? 'Carmen' : 'Carmen';
          break;
        case "Russian":
          voice = mf == 'Male' ? 'Maxim' : 'Tatyana';
          break;
        case "Spanish(ES)":
          voice = mf == 'Male' ? 'Enrique' : 'Conchita';
          break;
        case "Spanish(US)":
          voice = mf == 'Male' ? 'Miguel' : 'Penelope';
          break;
        case "Swedish":
          voice = mf == 'Male' ? 'Astrid' : 'Astrid';
          break;
        case "Turkish":
          voice = mf == 'Male' ? 'Filiz' : 'Filiz';
          break;
        case "Welsh":
          voice = mf == 'Male' ? 'Gwyneth' : 'Gwyneth';
          break;
        default:
          voice = mf == 'Male' ? 'Brian' : 'Amy';
      }
      self.usevoice = voice;
    },
    getItems: function() {
      var self = this;

      var text_items = self.itemdata.customtext1.split("\n");

      self.items = text_items.map(function(target) {
        return {
          dictate_targetWords: target.trim().split(self.spliton).filter(function(e) {
            return e !== "";
          }),
          target: target,
          typed: "",
          answered: false,
          correct: false,
          audio: null
        };
      }).filter(function(e) {
        return e.target !== "";
      });

      $.each(self.items, function(index, item) {
        polly.fetch_polly_url(item.target, 'text', 'Amy').then(function(audiourl) {
          item.audio = new Audio();
          item.audio.src = audiourl;
          if (self.items.filter(function(e) {
              return e.audio == null
            }).length == 0) {
            self.appReady();
          } else {
            console.log(self.items);
          }
        });

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

      $("#" + self.itemdata.uniqueid + "_container .dictate_targetWord").addClass("dictate_correct").removeClass("dictate_incorrect");

      if (!Object.keys(comparison).length) {
        $("#" + self.itemdata.uniqueid + "_container .dictate_speech.dictate_teacher_left").text(self.items[self.game.pointer].target + "");

        self.items[self.game.pointer].answered = true;
        self.items[self.game.pointer].correct = true;
        self.items[self.game.pointer].typed = typed;

        $("#" + self.itemdata.uniqueid + "_container .dictate_ctrl-btn").prop("disabled", true);
        if (self.game.pointer < self.items.length - 1) {
          setTimeout(function() {
            self.game.pointer++;
            self.nextPrompt();
          }, 3000);
        } else {
          setTimeout(function() {
            self.end();
          }, 3000);
        }

      } else {

        Object.keys(comparison).forEach(function(idx) {
          $("#" + self.itemdata.uniqueid + "_container .dictate_targetWord[data-idx='" + idx + "']").removeClass("dictate_correct").addClass("dictate_incorrect");
        });

        $("#" + self.itemdata.uniqueid + "_container .dictate_reply_" + self.game.pointer).effect("shake", function() {

          $("#" + self.itemdata.uniqueid + "_container .dictate_ctrl-btn").prop("disabled", false);

        });

      }

      $("#" + self.itemdata.uniqueid + "_container .dictate_targetWord.dictate_correct").each(function() {
        var realidx = $(this).data("realidx");
        var dictate_targetWord = self.items[self.game.pointer].dictate_targetWords[realidx];
        $(this).val(dictate_targetWord).prop("disabled", true);
      });

    },
    getWords: function(thetext) {
      var self = this;
      var checkcase = false;
      if (checkcase == 'false') {
        thetext = thetext.toLowerCase();
      }
      var chunks = thetext.split(self.spliton).filter(function(e) {
        return e !== "";
      });
      var words = [];
      for (var i = 0; i < chunks.length; i++) {
        if (!chunks[i].match(self.spliton)) {
          words.push(chunks[i]);
        }
      }
      return words;
    },
    getSimpleComparison(passage, transcript, callback) {
      var self = this;
      var pwords = self.getWords(passage);
      var twords = self.getWords(transcript);
      var ret = {};
      for (var pi = 0; pi < pwords.length && pi < twords.length; pi++) {
        if (pwords[pi] != twords[pi]) {
          ret[pi + 1] = {
            "word": pwords[pi],
            "number": pi + 1
          };
        }
      }
      callback(ret);
    },
    getComparison: function(passage, transcript, callback) {
      var self = this;
      var comparison = "simple";
      if (comparison == 'simple') {
        self.getSimpleComparison(passage, transcript, callback);
        return;
      }

      $(".dictate_ctrl-btn").prop("disabled", true);

      this.ajax.call([{
        methodname: 'mod_readaloud_compare_passage_to_transcript',
        args: {
          passage: passage,
          transcript: transcript,
          alternatives: '',
          language: 'en-US'
        },
        done: function(ajaxresult) {
          var payloadobject = JSON.parse(ajaxresult);
          if (payloadobject) {
            callback(payloadobject);
          } else {
            callback(false);
          }
        },
        fail: function(err) {
          console.log(err);
        }
      }]);

    },
    end: function() {
      var self = this;

      var numCorrect = self.items.filter(function(e) {
        return e.correct;
      }).length;

      var totalNum = self.items.length;

      $("#" + self.itemdata.uniqueid + "_container .dictate_results").html("TOTAL<br/>" + numCorrect + "/" + totalNum).show();

      setTimeout(function() {
        $("#" + self.itemdata.uniqueid + "_container .dictate_results").fadeOut();
      }, 2000);

      $("#" + self.itemdata.uniqueid + "_container .dictate_game").hide();
      $("#" + self.itemdata.uniqueid + "_container .dictate_start_btn").show();
      $("#" + self.itemdata.uniqueid + "_container .dictate_mainmenu").show();
      $("#" + self.itemdata.uniqueid + "_container .dictate_controls").hide();
      $("#" + self.itemdata.uniqueid + "_container .dictate_title").html("Listen and Repeat");

    },
    start: function() {
      var self = this;

      $("#" + self.itemdata.uniqueid + "_container .dictate_ctrl-btn").prop("disabled", true);
      
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

      self.nextPrompt();

    },
    nextPrompt: function() {

      var self = this;

      var target = self.items[self.game.pointer].target;
      var code = "<div class='dictate_prompt dictate_prompt_"+self.game.pointer+"' style='display:none;'>";

      code += "<i class='fa fa-graduation-cap dictate_speech-icon-left'></i>";
      code += "<div style='margin-left:90px;' class='dictate_speech dictate_teacher_left'>";
      code += target.replace(/[^a-zA-Z0-9 ]/g, '').replace(/[a-zA-Z0-9]/g, '•');
      code += "</div>";
      code += "</div>";

      $("#" + self.itemdata.uniqueid + "_container .dictate_game").html(code);
      $(".dictate_ctrl-btn").prop("disabled", false);

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

      $("#" + self.itemdata.uniqueid + "_container .dictate_title").html(progress);
      $(".dictate_prompt_" + self.game.pointer).toggle("slide", {
        direction: 'left'
      });

      self.nextReply();

    },
    nextReply: function() {
      var self = this;
      var target = self.items[self.game.pointer].target;
      var code = "<div class='dictate_reply dictate_reply_"+self.game.pointer+"' style='display:none;'>";
      code += "<i class='fa fa-user dictate_speech-icon-right'></i>";
      var dictate_targetWordsCode = "";
      var idx = 1;
      self.items[self.game.pointer].dictate_targetWords.forEach(function(word, realidx) {
        if (!word.match(self.spliton)) {
          dictate_targetWordsCode += "<input type='text' maxlength='" + word.length + "' size='" + (word.length + 1) + "' class='dictate_targetWord' data-realidx='" + realidx + "' data-idx='" + idx + "'>";
          idx++;

        } else {
          dictate_targetWordsCode += word;
        }
      });
      code += "<div style='margin-right:90px;' class='dictate_speech dictate_right'>" + dictate_targetWordsCode + "</div>";
      code += "</div>";
      $("#" + self.itemdata.uniqueid + "_container .dictate_game").append(code);
      $(".dictate_reply_" + self.game.pointer).toggle("slide", {
        direction: 'right'
      });
      $("#" + self.itemdata.uniqueid + "_container .dictate_ctrl-btn").prop("disabled", false);
    },
    animateCSS: function(element, animationName, callback) {
      const node = document.querySelector(element)
      node.classList.add('animated', animationName)

      function handleAnimationEnd() {
        node.classList.remove('animated', animationName)
        node.removeEventListener('animationend', handleAnimationEnd)

        if (typeof callback === 'function') callback()
      }

      node.addEventListener('animationend', handleAnimationEnd)
    }

  };
});