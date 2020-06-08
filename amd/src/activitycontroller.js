/* jshint ignore:start */
define(['jquery','jqueryui', 'core/log','mod_poodlltime/definitions','mod_poodlltime/recorderhelper','mod_poodlltime/quizhelper'], function($, jqui, log, def, recorderhelper, quizhelper) {

    "use strict"; // jshint ;_;

    log.debug('Activity controller: initialising');

    return {

        cmid: null,
        activitydata: null,
        holderid: null,
        recorderid: null,
        playerid: null,
        sorryboxid: null,
        controls: null,
        ra_recorder: null,
        rec_time_start: 0,

        //CSS in this file
        passagefinished: def.passagefinished,

        //for making multiple instances
        clone: function(){
            return $.extend(true,{},this);
        },

        //pass in config, the jquery video/audio object, and a function to be called when conversion has finshed
        init: function(props){
            var dd = this.clone();

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
            dd.holderid = props.widgetid + '_holder';
            dd.recorderid = props.widgetid + '_recorder';
            dd.playerid = props.widgetid + '_player';
            dd.sorryboxid = props.widgetid + '_sorrybox';

            //if the browser doesn't support html5 recording.
            //then warn and do not go any further
            if(!dd.is_browser_ok()){
                $('#' + dd.sorryboxid).show();
                return;
            }

            //EITHER to show recorder FIRST , then on submit show quiz
            //use dd.setup_recorder
            //dd.setup_recorder();

            dd.process_html();
            dd.register_events();

            //OR to show quiz and no recorder do dd.doquizlayout
            dd.doquizlayout();
        },



        process_html: function(){
            var opts = this.activitydata;
            //these css classes/ids are all passed in from php in
            //renderer.php::fetch_activity_amd
            var controls ={
                hider: $('.' + opts['hider']),
                introbox: $('.' + 'mod_intro_box'),
                quizcontainer: $('.' +  opts['quizcontainer']),
                progresscontainer: $('.' +  opts['progresscontainer']),
                feedbackcontainer: $('.' +  opts['feedbackcontainer']),
                errorcontainer: $('.' +  opts['errorcontainer']),
                passagecontainer: $('.' +  opts['passagecontainer']),
                recordingcontainer: $('.' +  opts['recordingcontainer']),
                dummyrecorder: $('.' +  opts['dummyrecorder']),
                recordercontainer: $('.' +  opts['recordercontainer']),
                instructionscontainer: $('.' +  opts['instructionscontainer']),
                recinstructionscontainerright: $('.' +  opts['recinstructionscontainerright']),
                recinstructionscontainerleft: $('.' +  opts['recinstructionscontainerleft']),
                allowearlyexit: $('.' +  opts['allowearlyexit']),
                wheretonextcontainer: $('.' +  opts['wheretonextcontainer'])
            };
            this.controls = controls;
        },

        beginall: function(){
            var m = this;
           // m.dorecord();
            m.passagerecorded = true;
        },

        is_browser_ok: function(){
            return (navigator && navigator.mediaDevices
                && navigator.mediaDevices.getUserMedia);
        },

        setup_recorder: function(){
            var dd = this;

            //Set up the callback functions for the audio recorder

            //originates from the recording:started event
            //contains no meaningful data
            //See https://api.poodll.com
            var on_recording_start= function(eventdata){

                dd.rec_time_start = new Date().getTime();
                dd.dopassagelayout();
                dd.controls.passagecontainer.show(1000,dd.beginall);
            };

            //originates from the recording:ended event
            //contains no meaningful data
            //See https://api.poodll.com
            var on_recording_end= function(eventdata){
                //its a bit hacky but the rec end event can arrive immed. somehow probably when the mic test ends
                var now = new Date().getTime();
                if((now - dd.rec_time_start) < 3000){
                    return;
                }
                dd.douploadlayout();
            };

            //data sent here originates from the awaiting_processing event
            //See https://api.poodll.com
           var on_audio_processing= function(eventdata){
                //at this point we know the submission has been uploaded and we know the fileURL
               //so we send the submission
               var now = new Date().getTime();
               var rectime = now - dd.rec_time_start;
               if(rectime > 0){
                   rectime = Math.ceil(rectime/1000);
               }
               //this will trigger the quiz after submission and proceed to doquizlayout
               //we need to get back the attemptid otherwise we would just proceed now
               dd.send_submission(eventdata.mediaurl,rectime);

            };

            //init the recorder
            recorderhelper.init(dd.activitydata,
                on_recording_start,
                on_recording_end,
                on_audio_processing);
        },

        register_events: function() {
            var dd = this;

			//events for other controls on the page
            //ie not recorders
            //dd.controls.passagecontainer.click(function(){log.debug('clicked');})
        },

        send_submission: function(filename,rectime){

            //set up our ajax request
            var xhr = new XMLHttpRequest();
            var that = this;
            
            //set up our handler for the response
            xhr.onreadystatechange = function(e){
                if(this.readyState===4){
                    if(xhr.status==200){
                        log.debug('ok we got an attempt submission response');
                        //get a yes or forgetit or tryagain
                        var payload = xhr.responseText;
                        var payloadobject = JSON.parse(payload);
                        if(payloadobject){
                            switch(payloadobject.success) {
                                case false:
                                    log.debug('attempted item evaluation failure');
                                    if (payloadobject.message) {
                                        log.debug('message: ' + payloadobject.message);
                                    }

                                case true:
                                default:
                                    log.debug('attempted submission accepted');
                                    that.attemptid=payloadobject.data;
                                    that.doquizlayout();
                                    break;

                            }
                        }
                     }else{
                        log.debug('Not 200 response:' + xhr.status);
                    }


                }
            };

            var params = "cmid=" + that.cmid + "&filename=" + filename + "&rectime=" + rectime;
            xhr.open("POST",M.cfg.wwwroot + '/mod/poodlltime/ajaxhelper.php', true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.setRequestHeader("Cache-Control", "no-cache");
            xhr.send(params);
        },

        dopassagelayout: function(){
            var m = this;
            m.controls.introbox.hide();
            m.controls.quizcontainer.hide();

            //m.controls.instructionscontainer.hide();
            if(m.controls.allowearlyexit){
              //  m.controls.stopbutton.hide();
            }
        },
        douploadlayout: function(){
            var m = this;
            m.controls.passagecontainer.addClass(m.passagefinished);
            m.controls.hider.fadeIn('fast');
            m.controls.progresscontainer.fadeIn('fast');
        },

        doquizlayout: function(){
            var m = this;
            m.controls.hider.fadeOut('fast');
            m.controls.progresscontainer.fadeOut('fast');
            m.controls.instructionscontainer.hide();
            m.controls.passagecontainer.hide();
            m.controls.recordingcontainer.hide();

            //set up the quiz
            quizhelper.onSubmit = function(returndata){m.dofinishedreadinglayout(returndata);};
            quizhelper.init(m.controls.quizcontainer,this.activitydata.quizdata,this.cmid,this.attemptid,
                this.activitydata.passagepictureurl);

            //show the quiz
            m.controls.quizcontainer.show();

        },

        dofinishedreadinglayout: function(returndata){
            var m = this;
            m.controls.quizcontainer.hide();
            m.controls.feedbackcontainer.show();
            if(returndata && returndata.success && returndata.data){
                var flower = returndata.data;
                m.controls.feedbackcontainer.append('<img src="' + flower.picurl + '"></img>');
            }
            m.controls.wheretonextcontainer.show();

        },
        doerrorlayout: function(){
            var m = this;
            m.controls.hider.fadeOut('fast');
            m.controls.progresscontainer.fadeOut('fast');
            m.controls.passagecontainer.hide();
            m.controls.recordingcontainer.hide();
            m.controls.errorcontainer.show();
            m.controls.wheretonextcontainer.show();
        }
    };//end of returned object
});//total end
