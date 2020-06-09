define(['jquery', 'core/log', 'mod_poodlltime/definitions','mod_poodlltime/pollyhelper'], function($, log, def, polly) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the quiz stage
   */

  log.debug('Poodll Time Quiz helper: initialising');

  return {


    init: function(itemdata) {

      this.register_events(itemdata);
    },

    prepare_html: function(itemdata) {
      //do something


    },

    register_events: function(itemdata) {
       //do something
    }

  }; //end of return value
});