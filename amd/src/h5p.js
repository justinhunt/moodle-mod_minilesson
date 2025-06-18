define(['jquery', 'core/log', 'mod_minilesson/definitions'],
    function($, log, def, anim) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the H5P item type
   */

  log.debug('MiniLesson H5P: initialising');

  return {

    //for making multiple instances
      clone: function () {
          return $.extend(true, {}, this);
     },

    init: function(index, itemdata, quizhelper) {
      this.itemdata = itemdata;
      this.register_events(index, itemdata, quizhelper);
      log.debug('MiniLesson H5P URL: ' + itemdata.h5purl);
      log.debug('MiniLesson H5P Total Marks: ' + itemdata.totalmarks);

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
      self.index = index;
      self.quizhelper = quizhelper;
      var h5pplayer = $("#" + itemdata.uniqueid + "_h5pplayer");
      var nextbutton = $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton");
      
      nextbutton.on('click', function(e) {
        self.next_question(0);
      });

    },

  };
});