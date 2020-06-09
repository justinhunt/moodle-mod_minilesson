define(['jquery', 'core/log', 'mod_poodlltime/definitions', 'core/templates', 'mod_poodlltime/pollyhelper','mod_poodlltime/dictation','mod_poodlltime/dictationchat','mod_poodlltime/multichoice'],
    function($, log, def, templates,polly,dictation,dictationchat,multichoice) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the quiz stage
   */

  log.debug('Poodll Time Quiz helper: initialising');

  return {

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
    },

    prepare_html: function() {
      var dd = this;

      var submitbutton = '<button type="button" class="' + this.submitbuttonclass + '">Submit Quiz</button><br>';




      dd.controls.quizcontainer.append(submitbutton);


    },

     init_questions: function(quizdata,polly) {
          var dd = this;
          $.each(quizdata,function(index,item){
            switch(item.type){
                case def.qtype_dictation:
                  dictation.init(item,polly);
                  break;
                case def.qtype_dictationchat:
                  dictationchat.init(item,polly);
                    break;
                case def.qtype_multichoice:
                    multichoice.init(item);
                    break;
            }

          });

      },

    register_events: function() {
      var dd = this;
      $('.' + this.submitbuttonclass).on('click', function() {
        var quizresults = {
          "qanswer1": "1",
          "qanswer2": "2",
          "qanswer3": "3",
          "qanswer4": "4",
          "qanswer5": "1",
          "qtextanswer1": "abc"
        };
        dd.send_quizresults(quizresults);
      });
    },

    send_quizresults: function(quizresults) {
      var params = {};
      params.action = 'quizresults';
      params.attemptid = this.attemptid;
      params.cmid = this.cmid;
      params.quizresults = JSON.stringify(quizresults);
      //set up our ajax request
      var xhr = new XMLHttpRequest();
      var that = this;

      //set up our handler for the response
      xhr.onreadystatechange = function(e) {
        if (this.readyState === 4) {
          if (xhr.status == 200) {
            log.debug('ok we got an attempt quiz submission response');
          } else {
            log.debug('NOT GOOD attempt quiz submission  response');
          }
          //let our parent class know about the submission
          var payload = xhr.responseText;
          var payloadobject = JSON.parse(payload);
          if (payloadobject) {
            that.onSubmit(payloadobject);
          } else {
            that.onSubmit(false);
          }
        }
      };
      //send it off
      xhr.open("POST", M.cfg.wwwroot + '/mod/poodlltime/ajaxhelper.php', true);
      xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
      xhr.setRequestHeader("Cache-Control", "no-cache");
      xhr.send($.param(params));
    },

    //this function is overridden by the calling class
    onSubmit: function() {
      alert('quiz submitted. Override this');
    },

  }; //end of return value
});