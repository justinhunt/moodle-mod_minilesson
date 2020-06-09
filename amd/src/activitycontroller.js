/* jshint ignore:start */
define(['jquery','jqueryui', 'core/log','mod_poodlltime/definitions','mod_poodlltime/quizhelper','mod_poodlltime/pollyhelper'], function($, jqui, log, def,  quizhelper,polly) {

    "use strict"; // jshint ;_;

    log.debug('Activity controller: initialising');

    return {

        cmid: null,
        activitydata: null,
        sorryboxid: null,
        controls: null,


        //pass in config, the jquery video/audio object, and a function to be called when conversion has finshed
        init: function(props){
            var dd = this;

            //pick up opts from html
            var theid='#amdopts_' + props.widgetid;
            var configcontrol = $(theid).get(0);
            if(configcontrol){
                dd.activitydata = JSON.parse(configcontrol.value);
                $(theid).remove();
            }else{
                //if there is no config we might as well give up
                log.debug('Poodll Time Test Controller: No config found on page. Giving up.');
                return;
            }

            dd.cmid = props.cmid;
            dd.sorryboxid = props.widgetid + '_sorrybox';

            polly.init(dd.activitydata.token,dd.activitydata.region,dd.activitydata.owner);

            dd.register_events();
            dd.process_html();
            //OR to show quiz and no recorder do dd.doquizlayout
            dd.doquizlayout();
        },



        process_html: function(){
            var opts = this.activitydata;
            //these css classes/ids are all passed in from php in
            //renderer.php::fetch_activity_amd
            var controls ={
                introbox: $('.' + 'mod_intro_box'),
                quizcontainer: $('.' +  opts['quizcontainer']),
                feedbackcontainer: $('.' +  opts['feedbackcontainer']),
                errorcontainer: $('.' +  opts['errorcontainer']),
                instructionscontainer: $('.' +  opts['instructionscontainer']),
                wheretonextcontainer: $('.' +  opts['wheretonextcontainer'])
            };
            this.controls = controls;
        },




        register_events: function() {
            var dd = this;

        },



        doquizlayout: function(){
            var m = this;
            m.controls.instructionscontainer.hide();

            //set up the quiz
            quizhelper.onSubmit = function(returndata){m.dofinishedreadinglayout(returndata);};
            quizhelper.init(m.controls.quizcontainer,this.activitydata.quizdata,this.cmid,this.attemptid, polly);

            //show the quiz
            m.controls.quizcontainer.show();

        },


        doerrorlayout: function(){
            var m = this;
            m.controls.errorcontainer.show();
            m.controls.wheretonextcontainer.show();
        }
    };//end of returned object
});//total end
