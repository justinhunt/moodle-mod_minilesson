define(['jquery', 'core/log', 'mod_poodlltime/definitions','mod_poodlltime/pollyhelper'], function($, log, def, polly) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the quiz stage
   */

  log.debug('Poodll Time Multichoice: initialising');

  return {

    init: function(index, itemdata,quizhelper) {

      this.register_events(index,itemdata,quizhelper);
    },

    prepare_html: function(itemdata) {
      //do something
    },

    register_events: function(index,itemdata,quizhelper) {
        //When click next button , report and leave it up to parent to eal with it.
        $("#" + itemdata.uniqueid + "_container .poodlltime_nextbutton").on('click', function(e){
            var stepdata = {};
            var grade = 50;
            stepdata.index=index;
            stepdata.grade= grade;
            quizhelper.do_next(stepdata);
        });
    }

  }; //end of return value
});