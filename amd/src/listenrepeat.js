define(['jquery', 'core/log', 'core/ajax', 'mod_poodlltime/definitions', 'mod_poodlltime/pollyhelper', 'mod_poodlltime/cloudpoodllloader'], function($, log, ajax, def, polly, cloudpoodll) {
  "use strict"; // jshint ;_;

  log.debug('Poodll Time dictation chat: initialising');

  return {

    init: function(index, itemdata, quizhelper) {
      var self = this;
      cloudpoodll.init('poodlltime-poodllrecorder', function(message) {

        switch (message.type) {
          case 'recording':
            break;

          case 'speech':
            console.log(message);
            self.getComparison(
              self.items[self.game.pointer].target,
              message.capturedspeech,
              function(comparison) {
                self.gotComparison(comparison, message);
              }
            );
            break;

        }

      });
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
        stepdata.index = self.index;
        stepdata.grade = grade;
        self.quizhelper.do_next(stepdata);
      });

      $("#" + self.itemdata.uniqueid + "_container .landr_start_btn").on("click", function() {
        self.start();
      });

      $("#" + self.itemdata.uniqueid + "_container .landr_listen_btn").on("click", function() {
        self.items[self.game.pointer].audio.load();
        self.items[self.game.pointer].audio.play();
      });

      $("#" + self.itemdata.uniqueid + "_container .landr_skip_btn").on("click", function() {
        $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", true);
        $("#" + self.itemdata.uniqueid + "_container .landr_speech.landr_teacher_left").text(self.items[self.game.pointer].target + "");
        setTimeout(function() {
          if (self.game.pointer < self.items.length - 1) {
            self.items[self.game.pointer].answered = true;
            self.items[self.game.pointer].correct = false;
            self.game.pointer++;
            self.nextPrompt();
          } else {
            self.end();
          }
        }, 3000);
      });
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
          voice = mf === 'Male' ? 'Joey' : 'Kendra';
          break;
        case "English(GB)":
          voice = mf === 'Male' ? 'Brian' : 'Amy';
          break;
        case "English(AU)":
          voice = mf === 'Male' ? 'Russell' : 'Nicole';
          break;
        case "English(IN)":
          voice = mf === 'Male' ? 'Aditi' : 'Raveena';
          break;
        case "English(Welsh)":
          voice = mf === 'Male' ? 'Geraint' : 'Geraint';
          break;
        case "Danish":
          voice = mf === 'Male' ? 'Mads' : 'Naja';
          break;
        case "Dutch":
          voice = mf === 'Male' ? 'Ruben' : 'Lotte';
          break;
        case "French(FR)":
          voice = mf === 'Male' ? 'Mathieu' : 'Celine';
          break;
        case "French(CA)":
          voice = mf === 'Male' ? 'Chantal' : 'Chantal';
          break;
        case "German":
          voice = mf === 'Male' ? 'Hans' : 'Marlene';
          break;
        case "Icelandic":
          voice = mf === 'Male' ? 'Karl' : 'Dora';
          break;
        case "Italian":
          voice = mf === 'Male' ? 'Carla' : 'Giorgio';
          break;
        case "Japanese":
          voice = mf === 'Male' ? 'Takumi' : 'Mizuki';
          break;
        case "Korean":
          voice = mf === 'Male' ? 'Seoyan' : 'Seoyan';
          break;
        case "Norwegian":
          voice = mf === 'Male' ? 'Liv' : 'Liv';
          break;
        case "Polish":
          voice = mf === 'Male' ? 'Jacek' : 'Ewa';
          break;
        case "Portugese(BR)":
          voice = mf === 'Male' ? 'Ricardo' : 'Vitoria';
          break;
        case "Portugese(PT)":
          voice = mf === 'Male' ? 'Cristiano' : 'Ines';
          break;
        case "Romanian":
          voice = mf === 'Male' ? 'Carmen' : 'Carmen';
          break;
        case "Russian":
          voice = mf === 'Male' ? 'Maxim' : 'Tatyana';
          break;
        case "Spanish(ES)":
          voice = mf === 'Male' ? 'Enrique' : 'Conchita';
          break;
        case "Spanish(US)":
          voice = mf === 'Male' ? 'Miguel' : 'Penelope';
          break;
        case "Swedish":
          voice = mf === 'Male' ? 'Astrid' : 'Astrid';
          break;
        case "Turkish":
          voice = mf === 'Male' ? 'Filiz' : 'Filiz';
          break;
        case "Welsh":
          voice = mf === 'Male' ? 'Gwyneth' : 'Gwyneth';
          break;
        default:
          voice = mf === 'Male' ? 'Brian' : 'Amy';
      }
      self.usevoice = voice;
    },
    getItems: function() {
      var self = this;

      var text_items = self.itemdata.customtext1.split("\n");

      self.items = text_items.map(function(target) {
        return {
          landr_targetWords: target.trim().split(self.spliton).filter(function(e) {
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
      $("#" + self.itemdata.uniqueid + "_container .landr_not_loaded").hide();
      $("#" + self.itemdata.uniqueid + "_container .landr_loaded").show();
      $("#" + self.itemdata.uniqueid + "_container .landr_start_btn").prop("disabled", false);
    },
    gotComparison: function(comparison, typed) {

      console.log(comparison, typed);

      var self = this;

      $("#" + self.itemdata.uniqueid + "_container .landr_targetWord").addClass("landr_correct").removeClass("landr_incorrect");

      if (!Object.keys(comparison).length) {
        $("#" + self.itemdata.uniqueid + "_container .landr_speech.landr_teacher_left").text(self.items[self.game.pointer].target + "");

        self.items[self.game.pointer].answered = true;
        self.items[self.game.pointer].correct = true;
        self.items[self.game.pointer].typed = typed;

        $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", true);
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
          $("#" + self.itemdata.uniqueid + "_container .landr_targetWord[data-idx='" + idx + "']").removeClass("landr_correct").addClass("landr_incorrect");
        });

        $("#" + self.itemdata.uniqueid + "_container .landr_reply_" + self.game.pointer).effect("shake", function() {

          $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", false);

        });

      }

      $("#" + self.itemdata.uniqueid + "_container .landr_targetWord.landr_correct").each(function() {
        var realidx = $(this).data("realidx");
        var landr_targetWord = self.items[self.game.pointer].landr_targetWords[realidx];
        $(this).val(landr_targetWord).prop("disabled", true);
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
      /*
      var comparison = "simple";
      if (comparison == 'simple') {
        self.getSimpleComparison(passage, transcript, callback);
        return;
      }
      */

      console.log(passage,transcript);
      
      $(".landr_ctrl-btn").prop("disabled", true);

      ajax.call([{
        methodname: 'mod_poodlltime_compare_passage_to_transcript',
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

      $("#" + self.itemdata.uniqueid + "_container .landr_results").html("TOTAL<br/>" + numCorrect + "/" + totalNum).show();

      setTimeout(function() {
        $("#" + self.itemdata.uniqueid + "_container .landr_results").fadeOut(function() {
          $("#" + self.itemdata.uniqueid + "_container .landr_start_btn").show();
        });
      }, 2000);

      $("#" + self.itemdata.uniqueid + "_container .landr_game").hide();
      $("#" + self.itemdata.uniqueid + "_container .landr_mainmenu").show();
      $("#" + self.itemdata.uniqueid + "_container .landr_controls").hide();
      $("#" + self.itemdata.uniqueid + "_container .landr_title").html("Listen and Repeat");
      $("#" + self.itemdata.uniqueid + "_container .landr_speakbtncontainer").hide();

    },
    start: function() {
      var self = this;

      $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", true);
      $("#" + self.itemdata.uniqueid + "_container .landr_speakbtncontainer").show();

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

      var showText = true;
      var self = this;

      var target = self.items[self.game.pointer].target;
      var code = "<div class='landr_prompt landr_prompt_" + self.game.pointer + "' style='display:none;'>";

      code += "<i class='fa fa-graduation-cap landr_speech-icon-left'></i>";
      code += "<div style='margin-left:90px;' class='landr_speech landr_teacher_left'>";
      if(!showText){
        code += target.replace(/[^a-zA-Z0-9 ]/g, '').replace(/[a-zA-Z0-9]/g, '•');
      } else{
        code += target;
      }
      code += "</div>";
      code += "</div>";

      $("#" + self.itemdata.uniqueid + "_container .landr_game").html(code);
      $(".landr_ctrl-btn").prop("disabled", false);

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
      $(".landr_prompt_" + self.game.pointer).toggle("slide", {
        direction: 'left'
      });

      self.nextReply();

    },
    nextReply: function() {
      var self = this;
      var target = self.items[self.game.pointer].target;
      var code = "<div class='landr_reply landr_reply_" + self.game.pointer + "' style='display:none;'>";
      code += "<i class='fa fa-user landr_speech-icon-right'></i>";
      var landr_targetWordsCode = "";
      var idx = 1;
      self.items[self.game.pointer].landr_targetWords.forEach(function(word, realidx) {
        if (!word.match(self.spliton)) {
          landr_targetWordsCode += "<input disabled type='text' maxlength='" + word.length + "' size='" + (word.length + 1) + "' class='landr_targetWord' data-realidx='" + realidx + "' data-idx='" + idx + "'>";
          idx++;

        } else {
          landr_targetWordsCode += word;
        }
      });
      code += "<div style='margin-right:90px;' class='landr_speech landr_right'>" + landr_targetWordsCode + "</div>";
      code += "</div>";
      $("#" + self.itemdata.uniqueid + "_container .landr_game").append(code);
      $(".landr_reply_" + self.game.pointer).toggle("slide", {
        direction: 'right'
      });
      $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", false);
      if(!self.quizhelper.mobile_user()){
        setTimeout(function(){
          $("#" + self.itemdata.uniqueid + "_container .landr_listen_btn").trigger('click');
        },1000);
      }
    }

  };
});