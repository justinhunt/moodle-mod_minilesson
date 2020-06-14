define(['jquery', 'core/log', 'mod_poodlltime/definitions','mod_poodlltime/pollyhelper'], function($, log, def, polly) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the quiz stage
   */

  log.debug('Poodll Time Quiz helper: initialising');

  return {


    init: function(index, itemdata,quizhelper, polly) {

      this.prepare_audio(itemdata,polly);
      this.register_events(index, itemdata, quizhelper);

    },

    prepare_html: function(itemdata) {
      //do something
    },

    prepare_audio: function(itemdata) {
        $.each(itemdata.sentences,function(index,sentence) {
          polly.fetch_polly_url(sentence.sentence,'text','Amy').then(function(audiourl){
            $("#" + itemdata.uniqueid + "_container .dictationplayer_" + index + " .dictationtrigger").attr("data-src",audiourl);
          });
        });
    },

    register_events: function(index,itemdata,quizhelper) {

        var theplayer = $("#" + itemdata.uniqueid + "_player");

       //key events in text box
        $("#" + itemdata.uniqueid + "_container .poodlldictationinput input").on("input",function(e){

          var index = $(this).data("index");
          var correct = itemdata.sentences[index].sentence.trim().toLowerCase();
          var typed = $(this).val().trim().toLowerCase();
          if(correct == typed){
            $(".dictate-feedback[data-index='"+index+"']").removeClass("fa-times").addClass("fa-check");
          } else {
            $(".dictate-feedback[data-index='"+index+"']").removeClass("fa-check").addClass("fa-times");
          }

        });

        //audio play requests
        $("#" + itemdata.uniqueid + "_container .dictationtrigger").on('click', function(e){
            theplayer.attr('src',$(this).attr('data-src'));
            theplayer[0].play();
        });

        //When click next button , report and leave it up to parent to eal with it.
        $("#" + itemdata.uniqueid + "_container .poodlltime_nextbutton").on('click', function(e){
            var stepdata = {};
            var correct = $('#' + itemdata.uniqueid + '_container .dictate-feedback.fa-check').length;
            var total = $('#' + itemdata.uniqueid + '_container .dictate-feedback').length;
            var grade = Math.round(correct/total,2) * 100;
            stepdata.index=index;
            stepdata.grade= grade;
            quizhelper.do_next(stepdata);
        });
    }
  }; //end of return value
});