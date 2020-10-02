define(['jquery', 'jqueryui', 'core/log', 'core/ajax', 'mod_poodlltime/definitions', 'mod_poodlltime/pollyhelper',
  'mod_poodlltime/cloudpoodllloader','mod_poodlltime/ttrecorder'
], function($, jqui, log, Ajax, def, polly, cloudpoodll, ttrecorder) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the quiz stage
   */

  log.debug('Poodll Time Speechcards: initialising');

  return {

      //for making multiple instances
      clone: function () {
          return $.extend(true, {}, this);
      },


      init: function(index, itemdata, quizhelper) {

      this.init_app(index, itemdata, quizhelper);
    },

    init_app: function(index, itemdata, quizhelper) {

      console.log(itemdata);

      var app = {
        passmark: 75,
        pointer: 1,
        jsondata: null,
        props: null,
        dryRun: false,
        language: 'en-US',
        terms: [],
        results: [],
        controls: {},

        init: function() {

          //init terms
          for (var i = 0; i < itemdata.sentences.length; i++) {
            app.terms[i] = itemdata.sentences[i].sentence;
          }
          log.debug("app terms", app.terms);
          app.language = itemdata.language;

          this.init_controls();
          this.initComponents();
          this.register_events();
        },

        init_controls: function() {
          app.controls = {};
          app.controls.star_rating = $("#" + itemdata.uniqueid + "_container .poodlltime_star_rating");
          app.controls.next_button = $("#" + itemdata.uniqueid + "_container .poodlltime-speechcards_nextbutton");
        },
        next_question: function() {
          var stepdata = {};
          stepdata.index = index;
          stepdata.hasgrade = true;
          stepdata.totalitems = app.terms.length;
          stepdata.correctitems = app.results.filter(function(e){return e.points>0;}).length;
          stepdata.grade = Math.round((stepdata.totalitems/stepdata.correctitems)*100);
          quizhelper.do_next(stepdata);
        },
        register_events: function() {

          $("#" + itemdata.uniqueid + "_container .poodlltime_nextbutton").on('click', function(e) {
            app.next_question();
          });

          app.controls.next_button.click(function() {
            //user has given up ,update word as failed
            app.check(false);

            //transition if required
            if (app.is_end()) {
              setTimeout(function() {
                app.do_end();
              }, 200);
            } else {
              setTimeout(function() {
                app.do_next();
              }, 200);
            }

          });
        },

        initComponents: function() {

          var theCallback = function(message) {

            switch (message.type) {
              case 'recording':

                break;

              case 'speech':
                log.debug("speech at speechcards");
                var speechtext = message.capturedspeech;
                var cleanspeechtext = app.cleanText(speechtext);

                var spoken = cleanspeechtext;
                var correct = app.terms[app.pointer - 1];

                //Similarity check by character matching
                var similarity = app.similarity(spoken, correct);
                log.debug('JS similarity: ' + spoken + ':' + correct + ':' + similarity);

                //Similarity check by direct-match/acceptable-mistranscription
                if (similarity >= app.passmark ||
                  app.wordsDoMatch(cleanspeechtext, app.terms[app.pointer - 1])) {
                  log.debug('local match:' + ':' + spoken + ':' + correct);
                  app.showStarRating(100);
                  app.flagCorrectAndTransition();
                  return;
                }

                //Similarity check by phonetics(ajax)
                app.checkByPhonetic(spoken, correct).then(function(similarity) {
                  if (similarity === false) {
                    return $.Deferred().reject();
                  } else {
                    log.debug('PHP similarity: ' + spoken + ':' + correct + ':' + similarity);
                    app.showStarRating(similarity);
                    if (similarity >= app.passmark) {
                      app.flagCorrectAndTransition();
                    }
                  } //end of if check_by_phonetic result
                }); //end of check by phonetic

            } //end of switch message type
          };

          //init cloudpoodll push recorder
         // cloudpoodll.init('poodlltime-recorder-speechcards-' + itemdata.id, theCallback);

         //tt recorder
            var opts = {};
            opts.uniqueid=itemdata.uniqueid;
            opts.callback=theCallback;
            ttrecorder.init(opts);


          //init progress dots
          app.progress_dots(app.results, app.terms);

          app.writeCurrentTerm();


        },

        writeCurrentTerm: function() {
          $(".poodlltime_speechcards_target_phrase").toggle("slide",{direction:"left"})
          $(".poodlltime_speechcards_target_phrase").text(app.terms[app.pointer - 1]);
          $(".poodlltime_speechcards_target_phrase").toggle("slide",{direction:"right"})
        },

        flagCorrectAndTransition: function() {

          //update students word log if matched
          app.check(true);

          //transition if required
          if (app.is_end()) {
            setTimeout(function() {
              app.do_end();
            }, 700);
          } else {
            setTimeout(function() {
              app.do_next();
            }, 700);
          }

        },

        wordsDoMatch: function(phraseheard, currentphrase) {
          //lets lower case everything
          phraseheard = app.cleanText(phraseheard);
          currentphrase = app.cleanText(currentphrase);
          if (phraseheard == currentphrase) {
            return true;
          }
          return false;
        },

        similarity: function(s1, s2) {
          var longer = s1;
          var shorter = s2;
          if (s1.length < s2.length) {
            longer = s2;
            shorter = s1;
          }
          var longerLength = longer.length;
          if (longerLength == 0) {
            return 1.0;
          }
          return (longerLength - app.editDistance(longer, shorter)) / parseFloat(longerLength);
        },
        editDistance: function(s1, s2) {
          s1 = s1.toLowerCase();
          s2 = s2.toLowerCase();

          var costs = new Array();
          for (var i = 0; i <= s1.length; i++) {
            var lastValue = i;
            for (var j = 0; j <= s2.length; j++) {
              if (i == 0)
                costs[j] = j;
              else {
                if (j > 0) {
                  var newValue = costs[j - 1];
                  if (s1.charAt(i - 1) != s2.charAt(j - 1))
                    newValue = Math.min(Math.min(newValue, lastValue),
                      costs[j]) + 1;
                  costs[j - 1] = lastValue;
                  lastValue = newValue;
                }
              }
            }
            if (i > 0)
              costs[s2.length] = lastValue;
          }
          return costs[s2.length];
        },

        cleanText: function(text) {
          return text.toLowerCase().replace(/[^\w\s]|_/g, "").replace(/\s+/g, " ").trim();
        },

        //this will return the promise, the result of which is an integer 100 being perfect match, 0 being no match
        checkByPhonetic: function(spoken, correct) {

          return Ajax.call([{
            'methodname': 'mod_poodlltime_check_by_phonetic',
            'args': {
              'spoken': spoken,
              'correct': correct,
              'language': app.language,
            }
          }])[0];

        },

        showStarRating: function(similarity) {
          //how many stars code
          var stars = [true, true, true];
          if (similarity < 1) {
            stars = [true, true, false];
          }
          if (similarity < app.passmark) {
            stars = [true, false, false];
          }
          if (similarity < 0.5) {
            stars = [false, false, false];
          }

          //prepare stars html
          var code = "";
          stars.forEach(function(star) {
            if (star === true) {
              code += '<i class="fa fa-star"></i>';
            } else {
              code += '<i class="fa fa-star-o"></i>';
            }
          });

          app.controls.star_rating.html(code);
        },

        check: function(correct) {
          var points = 1;
          if (correct == true) {
            points = 1;
          } else {
            points = 0;
          }
          var result = {
            points: points
          };
          app.results.push(result);
        },

        do_next: function() {
          app.pointer++;
          app.progress_dots(app.results, app.terms);
          app.clearStarRating();
          if (!app.is_end()) {
            app.writeCurrentTerm();
          } else {
            app.do_end();
          }
        },

        clearStarRating: function() {
          app.controls.star_rating.html('· · ·');
        },

        do_end: function() {
          app.next_question();
        },

        is_end: function() {
          //pointer is 1 based but array is, of course, 0 based
          if (app.pointer <= app.terms.length) {
            return false;
          } else {
            return true;
          }
        },

        progress_dots: function(results, terms) {

          var code = "";
          var color = "";
          terms.forEach(function(o, i) {
            color = "darkgray";
            if (results[i] !== undefined) {
              if (results[i].points) {
                color = "green";
              } else {
                color = "red";
              }
            }
            code += '<i style="color:' + color + ';" class="fa fa-circle"></i>';
          });

          $("#" + itemdata.uniqueid + "_container .poodlltime_progress_dots").html(code);

        },
      }; //end of app definition
      app.init();

    } //end of init_App


  }; //end of return value
});