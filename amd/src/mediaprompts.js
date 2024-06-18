define(['jquery','core/log','core/notification','core/str'], function($,log,notification,str) {
    "use strict"; // jshint ;_;

/*
This file contains functions for media prompts on the mform
 */

    log.debug('MiniLesson mediaprompts: initialising');

    return{

        controls:{},
        strings: {},

        //init the media prompts
        init: function(opts){
            var that = this;
            this.init_strings();
            this.init_controls();
            this.register_events();

            //init the visibility of the fieldsets
            $.each(opts, function(key, value){
                log.debug('key: '+key+' value: '+value);
                if(value===1){
                    var thefieldset=$('#ml_mediaprompt_panel_'+key);
                    thefieldset.show();
                    //disable the option in the dropdown
                    that.controls.select.find('option[value="' + key + '"]').prop('disabled', true);
                }
            });

            //unselect the media prompts thingy
            that.controls.select.find('option[value="0"]').prop('disabled', true);
            that.controls.select.prop('selectedIndex',0);
            

        },

         // Set up strings
        init_strings: function(){
            var that = this;  
            str.get_strings([
                { "key": "reallydeletemediaprompt", "component": 'mod_minilesson' },
                { "key": "deletemediaprompt", "component": 'mod_minilesson' },
                { "key": "delete", "component": 'core' },
            ]).done(function (s) {
                var i = 0;
                that.strings.reallydeletemediaprompt = s[i++];
                that.strings.deletemediaprompt = s[i++];
                that.strings.delete = s[i++];
            });
        },

        //get handles on all the page elements we will refer to
        init_controls: function(){
            //the media prompt select dropdown
            this.controls.select = $('#id_mediaprompts');
        },

        //register events on select and fieldsets etc
        register_events: function(){
            var that=this;

            //on select change add the fieldset
            this.controls.select.on('change',function(){
                var mediaprompt = $(this).val();
                var thefieldset = $('#ml_mediaprompt_panel_' + mediaprompt);
                thefieldset.show();
                //disable the option in the dropdown
                that.controls.select.find('option[value="' + mediaprompt + '"]').prop('disabled', true);
                //deselect all options
                that.controls.select.prop('selectedIndex',0);
                
            });

            //close the fieldset on button click
            var fieldset_close = $('.ml_mediaprompt_panel button.close');
            fieldset_close.on('click',function(){
                var thefieldset = $(this).closest('fieldset');
                var keyfieldname = thefieldset.data('keyfield');
                var mediaprompt = thefieldset.data('mediaprompt');
                console.log('keyfieldname:' + keyfieldname);
                //fetch the input of name keyfield nested under thefieldset
                switch (keyfieldname) {
                    case 'itemttsdialog':
                    case 'itemttspassage':
                        var keyfield = thefieldset.find("textarea[name='"+keyfieldname +"']");
                        break;
                    default:
                        var keyfield = thefieldset.find("input[name='"+keyfieldname +"']");
                }
                
                //fetch the legend text
                var legend = thefieldset.find("legend").text();

                //function to delete the fieldset .. we may seek confirmation first, or not, depending on if the keyfield has data
                var dodelete=function(){
                    //clear the data
                    if(keyfield){
                        keyfield.val('');
                    }
                    //hide the fieldset
                    thefieldset.hide();
                    //re-enable the select option
                    var mediaprompt = thefieldset.data('mediaprompt');
                    that.controls.select.find('option[value="' + mediaprompt + '"]').prop('disabled', false);
                }
                //if we have data confirm deletion, then delete
                log.debug('keyfield val: '+keyfield.val());
                if(keyfield.length>0 && keyfield.val()!=''){
                    notification.confirm(that.strings.deletemediaprompt, that.strings.reallydeletemediaprompt + legend + '?',that.strings.delete,'',dodelete);
                }else{
                    dodelete();
                }
            });
        }

    };//end of return value
});