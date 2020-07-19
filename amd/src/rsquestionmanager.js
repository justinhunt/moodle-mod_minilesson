/* jshint ignore:start */
define(['jquery','jqueryui', 'core/log','mod_poodlltime/definitions','mod_poodlltime/modalformhelper'], function($, jqui, log, def, mfh) {

    "use strict"; // jshint ;_;

    log.debug('RSQuestion manager: initialising');

    return {

        cmid: null,
        controls: null,


        //pass in config
        init: function(props){
            var dd = this;
            dd.contextid=props.contextid;

            dd.register_events();
            dd.process_html();
        },

        process_html: function(){
            this.controls = [];
        },

        register_events: function() {
            var dd = this;
            var qtypes =[def.qtype_dictation,def.qtype_dictationchat,def.qtype_page,
                def.qtype_speechcards,def.qtype_listenrepeat, def.qtype_multichoice];
            //register ajax modal handler
            for(var i = 0; i<qtypes.length; i++){
                mfh.init('#' + def.component + '_qedit_' + qtypes[i], dd.contextid, qtypes[i]);
            }
        }

    };//end of returned object
});//total end
