define(['jquery', 'core/log', 'mod_minilesson/definitions', 'mod_minilesson/pollyhelper'], function($, log, def, polly) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the quiz stage
   */

  log.debug('Minilesson Quiz helper: initialising');

  return {

    playing: false,

      //for making multiple instances
      clone: function () {
          return $.extend(true, {}, this);
      },

    init: function(index, itemdata, quizhelper, polly) {
      this.index = index;
      this.itemdata = itemdata;
      this.quizhelper = quizhelper;
      this.cardscore=100;
      this.prepare_audio(itemdata, polly);
      this.register_events(index, itemdata, quizhelper);

    },

    prepare_html: function(itemdata) {
      //do something
    },

    prepare_audio: function(itemdata) {
     //do something
    },

    register_events: function(index, itemdata, quizhelper) {

      var self = this;
        $("#" + itemdata.uniqueid + "_container .mod_ln_btn").on('click', function(){
            var action = $(this).data('action');
            if( action=='qnext'){
                self.next_card(self.cardscore);
            }else{
                self.cardscore= Math.max(0, self.cardscore-25);
                $(this).hide();
            }
        });

    },

    next_card: function(percent) {
        var self = this;
        var stepdata = {};
        stepdata.index = self.index;
        stepdata.hasgrade = true;
        stepdata.totalitems = 1;
        stepdata.correctitems = percent>0?1:0;
        stepdata.grade = percent;
        self.quizhelper.do_next(stepdata);
    },
  }; //end of return value
});