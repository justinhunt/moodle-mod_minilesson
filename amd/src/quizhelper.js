define(['jquery', 'core/log', 'mod_poodlltime/definitions', 'core/templates', 'core/ajax', 'mod_poodlltime/pollyhelper',
    'mod_poodlltime/dictation', 'mod_poodlltime/dictationchat', 'mod_poodlltime/multichoice', 'mod_poodlltime/speechcards', 'mod_poodlltime/listenrepeat'
  ],
  function($, log, def, templates, Ajax, polly, dictation, dictationchat, multichoice, speechcards, listenrepeat) {
    "use strict"; // jshint ;_;

    /*
    This file is to manage the quiz stage
     */

    log.debug('Poodll Time Quiz helper: initialising');

    return {
      
      mobile_user: function() {

        if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
          return true;
        } else {
          return false;
        }

      },

      controls: {},
      submitbuttonclass: 'mod_poodlltime_quizsubmitbutton',

      init: function(quizcontainer, quizdata, cmid, attemptid) {
        this.quizdata = quizdata;
        this.controls.quizcontainer = quizcontainer;
        this.attemptid = attemptid;
        this.cmid = cmid;
        this.prepare_html();
        this.init_questions(quizdata);
        this.register_events();
        this.start_quiz();
      },

      prepare_html: function() {

        // this.controls.quizcontainer.append(submitbutton);


      },

      init_questions: function(quizdata, polly) {
        var dd = this;
        $.each(quizdata, function(index, item) {
          switch (item.type) {
            case def.qtype_dictation:
              dictation.init(index, item, dd, polly);
              break;
            case def.qtype_dictationchat:
              dictationchat.init(index, item, dd, polly);
              break;
            case def.qtype_multichoice:
              multichoice.init(index, item, dd);
              break;
              case def.qtype_speechcards:
              //speechcards init needs to occur when it is visible. lame.
              //speechcards.init(index, item, dd);
              break;
            case def.qtype_listenrepeat:
              listenrepeat.init(index, item, dd);
              break;
          }

        });

      },

      register_events: function() {
        $('.' + this.submitbuttonclass).on('click', function() {
          //do something
        });
      },

      do_next(stepdata) {
        var dd = this;
        this.report_step_grade(stepdata);
        //hide current question
        var currentitem = this.quizdata[stepdata.index];
        $("#" + currentitem.uniqueid + "_container").hide();

        //show next question or End Screen
        if (this.quizdata.length > stepdata.index + 1) {
          var nextindex = stepdata.index + 1;
          var nextitem = this.quizdata[nextindex];
            //show the question
            $("#" + nextitem.uniqueid + "_container").show();
          //any per question type init that needs to occur can go here
          switch (nextitem.type) {
              case def.qtype_speechcards:
                  speechcards.init(nextindex, nextitem, dd);
                  break;
              case def.qtype_dictation:
              case def.qtype_dictationchat:
              case def.qtype_multichoice:
              case def.qtype_listenrepeat:
              default:
          }//end of nextitem switch

        } else {
          var scores = [{"name":"listenandrepeat","score":0}];
          this.controls.quizcontainer.append("<h2>FINISHED Tada</h2>");
        }
      },

      report_step_grade: function(stepdata) {
        var dd = this;
        Ajax.call([{
          methodname: 'mod_poodlltime_report_step_grade',
          args: {
            cmid: dd.cmid,
            step: stepdata.index,
            grade: stepdata.grade
          }
        }]);
      },

      start_quiz: function() {
        $("#" + this.quizdata[0].uniqueid + "_container").show();
      },

      //this function is overridden by the calling class
      onSubmit: function() {
        alert('quiz submitted. Override this');
      },

    }; //end of return value
  });