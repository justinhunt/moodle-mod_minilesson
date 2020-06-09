define(['jquery', 'core/log', 'mod_poodlltime/definitions','mod_poodlltime/pollyhelper'], function($, log, def, polly) {
  "use strict"; // jshint ;_;

  /*
  This file is for dictation chat
   */

  log.debug('Poodll Time dictation chat: initialising');

  return {


    init: function(itemdata,polly) {

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