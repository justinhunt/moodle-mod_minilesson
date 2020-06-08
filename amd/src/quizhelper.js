define(['jquery','core/log','mod_poodlltime/definitions'], function($,log,def) {
    "use strict"; // jshint ;_;

/*
This file is to manage the quiz stage
 */

    log.debug('Poodll Time Quiz helper: initialising');

    return{

        controls: {},
        submitbuttonclass: 'mod_poodlltime_quizsubmitbutton',

        init: function(quizcontainer,quizdata,cmid,attemptid,passagepictureurl){
            this.quizdata=quizdata;
            this.controls.quizcontainer=quizcontainer;
            this.attemptid=attemptid;
            this.passagepictureurl=passagepictureurl;
            this.cmid=cmid;
            this.prepare_html();
            this.register_events();
        },

        prepare_html: function() {
            var dd = this;
            var qtemplate = '<span><b>QUESTION @@INDEX@@: @@DATA@@</b></span><br>';
            var text_answer_template = '<span>A: @@DATA@@</span><br>';
            var audio_answer_template = '<span><i>A: [ AUDIO RECORDER GOES HERE ] </i></span><br>';
            var submitbutton = '<button type="button" class="'+ this.submitbuttonclass +'">Submit Quiz</button><br>';
            var passagepicture = '<span><img src="' + this.passagepictureurl + '"></img></span><br>';
            //shoe passage picture
            dd.controls.quizcontainer.append(passagepicture);

            $.each(this.quizdata,function(index,item){
                var question =qtemplate.replace('@@DATA@@',item.text);
                question = question.replace('@@INDEX@@',index +1)
                dd.controls.quizcontainer.append(question);

                switch(item.type){
                    case def.qtype_textpromptaudio:
                        dd.controls.quizcontainer.append(audio_answer_template);
                        break;
                    case def.qtype_textpromptlong:
                    case def.qtype_textpromptshort:
                    default:
                        dd.controls.quizcontainer.append(text_answer_template.replace('@@DATA@@', item.answer1));
                        dd.controls.quizcontainer.append(text_answer_template.replace('@@DATA@@', item.answer2));
                        dd.controls.quizcontainer.append(text_answer_template.replace('@@DATA@@', item.answer3));
                        dd.controls.quizcontainer.append(text_answer_template.replace('@@DATA@@', item.answer4));
                }
            });

            dd.controls.quizcontainer.append(submitbutton);
        },

        register_events: function() {
            var dd = this;
            $('.' + this.submitbuttonclass).on('click',function(){
                var quizresults = {"qanswer1": "1","qanswer2": "2","qanswer3": "3","qanswer4": "4","qanswer5": "1","qtextanswer1": "abc"};
                dd.send_quizresults(quizresults);
            });
        },

        send_quizresults: function(quizresults){
            var params ={};
            params.action='quizresults';
            params.attemptid=this.attemptid;
            params.cmid=this.cmid;
            params.quizresults=JSON.stringify(quizresults);
            //set up our ajax request
            var xhr = new XMLHttpRequest();
            var that = this;

            //set up our handler for the response
            xhr.onreadystatechange = function(e) {
                if (this.readyState === 4) {
                    if (xhr.status == 200) {
                        log.debug('ok we got an attempt quiz submission response');
                    } else {
                        log.debug('NOT GOOD attempt quiz submission  response');
                    }
                 //let our parent class know about the submission
                    var payload = xhr.responseText;
                    var payloadobject = JSON.parse(payload);
                    if(payloadobject) {
                        that.onSubmit(payloadobject);
                    }
                    else{
                        that.onSubmit(false);
                    }
                }
            };
            //send it off
            xhr.open("POST",M.cfg.wwwroot + '/mod/poodlltime/ajaxhelper.php', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.setRequestHeader("Cache-Control", "no-cache");
            xhr.send($.param(params));
        },

        //this function is overridden by the calling class
        onSubmit: function(){alert('quiz submitted. Override this');},

    };//end of return value
});