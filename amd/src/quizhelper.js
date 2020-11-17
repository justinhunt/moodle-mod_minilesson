define(['jquery', 'core/log', 'mod_poodlltime/definitions', 'core/templates', 'core/ajax', 'mod_poodlltime/pollyhelper',
    'mod_poodlltime/dictation', 'mod_poodlltime/dictationchat', 'mod_poodlltime/multichoice', 'mod_poodlltime/speechcards', 'mod_poodlltime/listenrepeat',
        'mod_poodlltime/page','mod_poodlltime/teachertools','mod_poodlltime/shortanswer'],
  function($, log, def, templates, Ajax, polly, dictation, dictationchat, multichoice, speechcards, listenrepeat, page, teachertools, shortanswer) {
    "use strict"; // jshint ;_;

    /*
    This file is to manage the quiz stage
     */

    log.debug('Poodll Time Quiz helper: initialising');

    return {
      

      controls: {},
      submitbuttonclass: 'mod_poodlltime_quizsubmitbutton',
      stepresults: [],

      init: function(quizcontainer, activitydata, cmid, attemptid) {
        this.quizdata = activitydata.quizdata;
        this.region = activitydata.region;
        this.ttslanguage = activitydata.ttslanguage;
        this.controls.quizcontainer = quizcontainer;
        this.attemptid = attemptid;
        this.courseurl = activitydata.courseurl;
        this.cmid = cmid;
        this.prepare_html();
        this.init_questions(this.quizdata);
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

              case def.qtype_teachertools:
                  teachertools.clone().init(index, item, dd);
                  break;

              case def.qtype_shortanswer:
                  shortanswer.clone().init(index, item, dd);
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
        //get current question
        var currentquizdataindex =   stepdata.index;
        var currentitem = this.quizdata[currentquizdataindex];

        //in preview mode do no do_next
        if(currentitem.preview===true){return;}

        //post grade
        dd.report_step_grade(stepdata);
        //hide current question
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
              case def.qtype_teachertools:
              case def.qtype_shortanswer:
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
          });
          var totalpercent = Math.round((correctitems/totalitems)*100);
          console.log(results,correctitems,totalitems,totalpercent);
          templates.render('mod_poodlltime/quizfinished',{results:results,total:totalpercent, courseurl: this.courseurl}).then(
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
            step: JSON.stringify(stepdata),
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

        mobile_user: function() {

            if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
                return true;
            } else {
                return false;
            }
        },

        chrome_user: function(){
            if(/Chrome/i.test(navigator.userAgent)) {
                return true;
            }else{
                return false;
            }
        },

        use_ttrecorder: function(){
            var ret =false;
            if(this.mobile_user()){
                ret = true;
            }else if(this.chrome_user()){
                ret = false;
            }else{
                ret = true;
            }
            if(ret===false){return false;}

            //check if language and region are ok
            switch(this.region){
                case 'tokyo':
                case 'useast1':
                case 'dublin':
                case 'sydney':
                    ret = this.language.substr(0,2)==='en';
                    break;
                default:
                    ret = false;
            }
            return ret;
        },


    }; //end of return value
  });