define(['jquery','core/log'], function($,log) {
    "use strict"; // jshint ;_;


    log.debug('MiniLesson YT Clip Player: initialising');

    return{

        init: function(uniqueid){

            //fetch all the controls and data that we need
            var dialoglines = $('#' + uniqueid + '_ttsdialogplayer .ttsdialogline');
            var linecount = dialoglines.length;
            var stoppedstate = '<i class="fa fa-play"></i>';
            var playingstate = '<i class="fa fa-stop"></i>';

            var currentline=-1;

            var player = $('#' + uniqueid + '_ttsdialogplayer .ttsdialog_audioplayer');
            var speakertext = $('#' + uniqueid + '_ttsdialogplayer .ttsdialog_text');
            var playbutton = $('#' + uniqueid + '_ttsdialogplayer .ttsdialog_button');
            var actor = $('#' + uniqueid + '_ttsdialogplayer .ttsdialog_actor');

            //declare the functions we need to call
            var next_play = function(){
                log.debug("currentline: " + currentline);
                log.debug("linecount: " + linecount);
                currentline++;
                if (currentline >= linecount){
                    currentline =-1;
                    playbutton.html(stoppedstate);
                    speakertext.text(" ");
                    switch_actor('none');
                    return;
                }
                player.attr('src',dialoglines.eq(currentline).data('audiourl'));
                var speaker = dialoglines.eq(currentline).data('speaker');
                if(speaker=='soundeffect'){
                    switch_actor('none');
                    speakertext.text(' ');
                }else{
                    switch_actor(speaker);
                    speakertext.text(dialoglines.eq(currentline).data('speakertext'));
                }
                player[0].play();
            };

            var switch_actor = function(speaker){
                switch(speaker){
                    case 'none':
                        actor.removeClass('rolea');
                        actor.removeClass('roleb');
                        actor.removeClass('rolec');
                        actor.addClass('rolenone');
                        break;

                    case 'a':
                        actor.removeClass('rolenone');
                        actor.removeClass('roleb');
                        actor.removeClass('rolec');
                        actor.addClass('rolea');
                        break;
                    case 'b':
                        actor.removeClass('rolenone');
                        actor.removeClass('rolea');
                        actor.removeClass('rolec');
                        actor.addClass('roleb');
                        break;
                    case 'c':
                        actor.removeClass('rolenone');
                        actor.removeClass('rolea');
                        actor.removeClass('roleb');
                        actor.addClass('rolec');
                        break;
                }

            };//end of switch actor

            //register_events
            player[0].addEventListener('ended', next_play);
            playbutton.click(function(){
                if(!(player[0].paused)){
                    currentline--;
                    player[0].pause();
                    playbutton.html(stoppedstate);
                }else{
                    next_play();
                    playbutton.html(playingstate);
                }

            });

        }//end of init function

    };//end of return value
});