define(['jquery', 'core/log', 'mod_poodlltime/definitions', 'core/templates', 'core/ajax', 'mod_poodlltime/pollyhelper',
    'mod_poodlltime/dictation', 'mod_poodlltime/dictationchat', 'mod_poodlltime/multichoice', 'mod_poodlltime/speechcards', 'mod_poodlltime/listenrepeat','mod_poodlltime/page'
  ],
  function($, log, def, templates, Ajax, polly, dictation, dictationchat, multichoice, speechcards, listenrepeat, page) {
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
      stepresults: [],

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
        this.controls.quizfinished=$("#mod_poodlltime_quiz_finished");

      },

      init_questions: function(quizdata, polly) {
        var dd = this;
        $.each(quizdata, function(index, item) {
          switch (item.type) {
            case def.qtype_dictation:
              dictation.clone().init(index, item, dd, polly);
              break;
            case def.qtype_dictationchat:
              dictationchat.clone().init(index, item, dd, polly);
              break;
            case def.qtype_multichoice:
              multichoice.clone().init(index, item, dd);
              break;
              case def.qtype_speechcards:
              //speechcards init needs to occur when it is visible. lame.
              // so we do that in do_next function, down below
              speechcards.clone().init(index, item, dd);
              break;
            case def.qtype_listenrepeat:
              listenrepeat.clone().init(index, item, dd);
              break;

             case def.qtype_page:
                  page.clone().init(index, item, dd);
                  break;
          }

        });

      },

      register_events: function() {
        $('.' + this.submitbuttonclass).on('click', function() {
          //do something
        });
      },
      render_quiz_progress:function(current,total){
        var array = [];
        for(var i=0;i<total;i++){
          array.push(i);
        }
        var slice = array.slice(current,current+5);
        var html = "<div class='poodlltime_quiz_progress_line'></div>";
        slice.forEach(function(i){
          html+="<div class='poodlltime_quiz_progress_item "+(i==current?'poodlltime_quiz_progress_item_current':'')+" "+(i<current?'poodlltime_quiz_progress_item_completed':'')+"'>"+(i+1)+"</div>";
        });
        html+="";
        $(".poodlltime_quiz_progress").html(html);
      },

      do_next: function(stepdata){
        var dd = this;
        dd.report_step_grade(stepdata);
        //hide current question
        var currentquizdataindex =   stepdata.index;
        var currentitem = this.quizdata[currentquizdataindex];
        $("#" + currentitem.uniqueid + "_container").hide();
        //show next question or End Screen
        if (dd.quizdata.length > currentquizdataindex+1) {
          var nextindex = currentquizdataindex+ 1;
          var nextitem = this.quizdata[nextindex];
            //show the question
            $("#" + nextitem.uniqueid + "_container").show();
          //any per question type init that needs to occur can go here
          switch (nextitem.type) {
              case def.qtype_speechcards:
                  //speechcards.init(nextindex, nextitem, dd);
                  break;
              case def.qtype_dictation:
              case def.qtype_dictationchat:
              case def.qtype_multichoice:
              case def.qtype_listenrepeat:
              default:
          }//end of nextitem switch

        } else {

          var results = dd.stepresults.filter(function(e){return e.hasgrade;});
          var correctitems = 0;
          var totalitems = 0;
          results.forEach(function(result,i){
            result.index=i+1;
            result.title=dd.quizdata[i].title;
            correctitems += result.correctitems;
            totalitems += result.totalitems;
          })
          var totalpercent = Math.round((correctitems/totalitems)*100);
          console.log(results,correctitems,totalitems,totalpercent);
          templates.render('mod_poodlltime/quizfinished',{results:results,total:totalpercent}).then(
              function(html,js){
                  dd.controls.quizfinished.html(html);
                  dd.controls.quizfinished.show();
              }
          );

        }
        
        this.render_quiz_progress(stepdata.index+1,this.quizdata.length);
        
      },

      report_step_grade: function(stepdata) {
        var dd = this;
        //store results locally
        this.stepresults.push(stepdata);

        //push results to server
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
        this.render_quiz_progress(0,this.quizdata.length);
      },

      //this function is overridden by the calling class
      onSubmit: function() {
        alert('quiz submitted. Override this');
      },

    }; //end of return value
  });