define(['jquery','core/log'], function($,log) {
    "use strict"; // jshint ;_;


    log.debug('MiniLesson TTS Passage: initialising');

    return{

        init: function(PASSAGEID){

            //DECLARATIONS and INITs ...........................
            var thesentence_number =0;
            var lettered= false;
            var stoporpause='stop';



            //audio player declarations
            var aplayer = $('#' + PASSAGEID + '_player');
            var fa = $('#' + PASSAGEID + ' .fa');

            //some common selectors
            var sentenceselector = '#' + PASSAGEID+ '_textblock span.tbr_sentence';
            var passagelines = $(sentenceselector);

            var numberSentences = function(){
                var linecount = passagelines.length;
                for (var thesentence=0; thesentence < linecount; thesentence++){
                    $(passagelines[thesentence]).attr('data-sentenceindex',thesentence);
                }//end of for loop
            };

            //FUNCTION:  unhighlight a sentence as active
            var dehighlight_all = function(){
                passagelines.removeClass('passageplayer_activesentence');
            };

            //FUNCTION:  highlight a sentence as active
            var highlight_sentence = function(thesentence){
                passagelines.removeClass('passageplayer_activesentence');
                $(passagelines[thesentence]).addClass('passageplayer_activesentence');
                // $(sentenceselector + '[data-sentenceindex=' + thesentence + ']').addClass('passageplayer_activesentence');
            };

            //FUNCTION: play a single sentence and mark it active for display purposes
            var doplayaudio = function(thesentence){
                log.debug(thesentence);
                log.debug($(passagelines[thesentence]).data('audiourl'));
                highlight_sentence(thesentence);
                aplayer.attr('src',$(passagelines[thesentence]).data('audiourl'));
                aplayer[0].play();
            };


            //AUDIO PLAYER events
            aplayer[0].addEventListener('ended', function(){
                if(thesentence_number< passagelines.length -1){
                    thesentence_number++;
                    doplayaudio(thesentence_number);
                }else{
                    dehighlight_all();
                    $(fa).removeClass('fa-stop');
                    $(fa).addClass('fa-volume-up');
                    thesentence_number=0;
                    aplayer.removeAttr('src');
                }
            });

            //handle audio player button clicks
            $('#' + PASSAGEID).click(function(){
                if(!aplayer[0].paused && !aplayer[0].ended){
                    aplayer[0].pause();
                    if(stoporpause=='stop'){
                        aplayer[0].load();
                        thesentence_number=0;
                    }
                    $(fa).removeClass('fa-stop');
                    $(fa).addClass('fa-volume-up');

                    //if paused and in limbo no src state
                }else if(aplayer[0].paused && aplayer.attr('src')){
                    aplayer[0].play();
                    $(fa).removeClass('fa-volume-up');
                    $(fa).addClass('fa-stop');
                    //play
                }else{
                    if(!lettered){
                        //spanify_text_passage();
                        lettered=true;
                    }//end of if lettered
                    if(stoporpause=='stop'){
                        thesentence_number=0;
                    }
                    doplayaudio(thesentence_number);
                    $(fa).removeClass('fa-volume-up');
                    $(fa).addClass('fa-stop');
                }//end of if paused ended
            });

            //handle sentence clicks
            $('#' + PASSAGEID + '_textblock  .tbr_innerdiv').on('click', '.tbr_sentence',function(){
                aplayer[0].pause();
                var sentenceindex = $(this).attr('data-sentenceindex');
                $(fa).removeClass('fa-volume-up');
                $(fa).addClass('fa-stop');
                thesentence_number = sentenceindex;
                doplayaudio(sentenceindex);
            });

            //PROCEDURAL stuff
            numberSentences();

            //end of instance wrapper
        }

    };//end of return value
});