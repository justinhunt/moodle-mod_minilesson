define(['jquery', 'core/log', 'mod_minilesson/definitions',
    'mod_minilesson/ttrecorder',  'core/templates'],
    function($, log, def, ttrecorder, templates) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the free speaking item type
   */

  log.debug('MiniLesson AudioChat: initialising');

  return {


    //for making multiple instances
      clone: function () {
          return $.extend(true, {}, this);
     },

    init: function(index, itemdata, quizhelper) {
      this.itemdata = itemdata;
      log.debug('itemdata',itemdata);
      this.quizhelper = quizhelper;
      this.init_components(quizhelper,itemdata);
      this.register_events(index, itemdata, quizhelper);

    },

    next_question: function() {
      var self = this;
      var stepdata = {};
      stepdata.index = self.index;
      stepdata.hasgrade = true;
      stepdata.totalitems = self.itemdata.totalmarks;
      stepdata.correctitems = self.itemdata.totalmarks;
      stepdata.grade = 1;
      stepdata.resultsdata = {};
      self.quizhelper.do_next(stepdata);
    },

    register_events: function(index, itemdata, quizhelper) {
      
      var self = this;
      self.index = index;
      self.quizhelper = quizhelper;
      var nextbutton = $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton");
      
      nextbutton.on('click', function(e) {
        self.next_question();
      });

      $("#" + itemdata.uniqueid + "_container").on('showElement', () => {
        if (itemdata.timelimit > 0) {
            $("#" + itemdata.uniqueid + "_container .progress-container").show();
            $("#" + itemdata.uniqueid + "_container .progress-container i").show();
            $("#" + itemdata.uniqueid + "_container .progress-container #progresstimer").progressTimer({
                height: '5px',
                timeLimit: itemdata.timelimit,
                onFinish: function() {
                    nextbutton.trigger('click');
                }
            });
        }
      });

    },

    init_components: function(quizhelper,itemdata) {
        var self = this;
    },

  };
});