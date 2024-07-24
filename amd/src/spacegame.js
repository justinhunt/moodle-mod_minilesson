define(['jquery', 'core/log', 'mod_minilesson/definitions'],
    function($, log, def) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the Space Game item type
   */

  log.debug('MiniLesson Space Game: initialising');

  return {

    //for making multiple instances
      clone: function () {
          return $.extend(true, {}, this);
     },

    init: function(index, itemdata, quizhelper) {
      this.itemdata = itemdata;
      this.register_events(index, itemdata, quizhelper);

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
      var nextbutton = $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton");
      var clickbutton = $("#" + itemdata.uniqueid + "_container .ml_sg_clickclick");
      
      nextbutton.on('click', function(e) {
        self.next_question(0);
      });

      clickbutton.on('click', function(e) {
        var displayitems = self.itemdata.spacegameitems.join(' ');
        alert('click ' + displayitems);
        //self.next_question(100);
      });

    },

  };
});