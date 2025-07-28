define(['jquery',
    'core/log',
    'core/ajax',
    'mod_minilesson/definitions',
    'mod_minilesson/pollyhelper',
    'mod_minilesson/animatecss',
    'mod_minilesson/progresstimer',
    'core/templates'
], function($, log, ajax, def, polly, anim, progresstimer, templates) {
    "use strict"; // jshint ;_;

    log.debug('MiniLesson wordshuffle: initialising');

    return {

        // For making multiple instances
        clone: function() {
            return $.extend(true, {}, this);
        },

        usevoice: 'Amy',

        init: function(index, itemdata, quizhelper) {
            var self = this;
            self.itemdata = itemdata;
            self.quizhelper = quizhelper;
            self.index = index;

            // Anim
            var animopts = {};
            animopts.useanimatecss = quizhelper.useanimatecss;
            anim.init(animopts);

            self.init_controls();
            self.register_events();
            self.setvoice();
            self.getItems();
        },

        init_controls: function() {
            var self = this;
            self.controls = {
                container: $("#" + self.itemdata.uniqueid + "_container"),
                listen_cont: $("#" + self.itemdata.uniqueid + "_container .wordshuffle_listen_cont"),
                nextbutton: $("#" + self.itemdata.uniqueid + "_container .minilesson_nextbutton"),
                start_btn: $("#" + self.itemdata.uniqueid + "_container .wordshuffle_start_btn"),
                skip_btn: $("#" + self.itemdata.uniqueid + "_container .wordshuffle_skip_btn"),
                ctrl_btn: $("#" + self.itemdata.uniqueid + "_container .wordshuffle_ctrl-btn"),
                check_btn: $("#" + self.itemdata.uniqueid + "_container .wordshuffle_check_btn"),
                game: $("#" + self.itemdata.uniqueid + "_container .wordshuffle_game"),
                controlsbox: $("#" + self.itemdata.uniqueid + "_container .wordshuffle_controls"),
                resultscontainer: $("#" + self.itemdata.uniqueid + "_container .wordshuffle_resultscontainer"),
                mainmenu: $("#" + self.itemdata.uniqueid + "_container .wordshuffle_mainmenu"),
                title: $("#" + self.itemdata.uniqueid + "_container .wordshuffle_title"),
                progress_container: $("#" + self.itemdata.uniqueid + "_container .progress-container"),
                progress_bar: $("#" + self.itemdata.uniqueid + "_container .progress-container .progress-bar"),
                question: $("#" + self.itemdata.uniqueid + "_container .question"),
                listen_btn: $("#" + self.itemdata.uniqueid + "_container .wordshuffle_listen_btn"),
            };
        },

        next_question: function() {
            var self = this;
            var stepdata = {};
            stepdata.index = self.index;
            stepdata.hasgrade = true;
            stepdata.totalitems = self.items.length;
            stepdata.correctitems = self.items.filter(function(e) {
                return e.correct;
            }).length;
            stepdata.grade = Math.round((stepdata.correctitems / stepdata.totalitems) * 100);

            //stop audio
            self.stop_audio();

            self.quizhelper.do_next(stepdata);
        },

        show_item_review:function(){
            var self=this;
            var review_data = {};
            review_data.items = self.items;
            review_data.totalitems=self.items.length;
            review_data.correctitems=self.items.filter(function(e) {return e.correct;}).length;

            //Get controls
            var listencont = self.controls.listen_cont;
            var qbox = self.controls.question;
            var gamebox = self.controls.game;
            var controlsbox = self.controls.controlsbox;
            var resultsbox = self.controls.resultscontainer;

            //display results
            templates.render('mod_minilesson/listitemresults',review_data).then(
              function(html,js){
                  resultsbox.html(html);
                  //show and hide
                  resultsbox.show();
                  gamebox.hide();
                  controlsbox.hide();
                  listencont.hide();
                  qbox.hide();
                  //recorderbox.hide();
                  // Run js for audio player events
                  templates.runTemplateJS(js);
              }
            );// End of templates
        },

        register_events: function() {

            var self = this;

            self.controls.nextbutton.on('click', function(e) {
                self.next_question();
            });

            self.controls.start_btn.on("click", function() {
                self.start();
            });

            //AUDIO PLAYER events
            var audioplayerbtn = self.controls.listen_btn;
            //audio button click event
            audioplayerbtn.on("click", function() {
                var theaudio =self.items[self.game.pointer].audio;

                //if we are already playing stop playing
                if(!theaudio.paused){
                    theaudio.pause();
                    theaudio.currentTime=0;
                    $(audioplayerbtn).children('.fa').removeClass('fa-stop');
                    $(audioplayerbtn).children('.fa').addClass('fa-volume-up');
                    return;
                }

                //change icon to indicate playing state
                theaudio.addEventListener('ended', function(){
                    $(audioplayerbtn).children('.fa').removeClass('fa-stop');
                    $(audioplayerbtn).children('.fa').addClass('fa-volume-up');
                });

                theaudio.addEventListener('play', function(){
                    $(audioplayerbtn).children('.fa').removeClass('fa-volume-up');
                    $(audioplayerbtn).children('.fa').addClass('fa-stop');
                });

                theaudio.load();
                theaudio.play();
            });

            //toggle audio playback on spacekey press in input boxes
            self.controls.container.on("keydown", ".single-character", function(e) {
                if (e.which == 32) {
                    e.preventDefault();
                    audioplayerbtn.trigger("click");
                }
            });

            // On skip button click
            self.controls.skip_btn.on("click", function() {
                // Disable buttons
                self.controls.ctrl_btn.prop("disabled", true);
                // Reveal prompt
                self.controls.container.find('.wordshuffle_speech.wordshuffle_teacher_left').text(self.items[self.game.pointer].prompt + "");
                // Reveal answers
                self.controls.container.find('.wordshuffle_targetWord').each(function() {
                    var realidx = $(this).data("realidx");
                    var wordshuffle_targetWord = self.items[self.game.pointer].wordshuffle_targetWords[realidx];
                    $(this).val(wordshuffle_targetWord);
                });

                self.stopTimer(self.items[self.game.pointer].timer);

                //mark as answered and incorrect
                self.items[self.game.pointer].answered = true;
                self.items[self.game.pointer].correct = false;

                // Move on after short time, to next prompt, or next question
                if (self.game.pointer < self.items.length - 1) {
                    setTimeout(function() {
                        self.controls.container.find('.wordshuffle_reply_' + self.game.pointer).hide();
                        self.game.pointer++;
                        self.nextPrompt();
                    }, 2000);
                } else {
                    self.end();
                }
            });


            self.controls.check_btn.on("click", function() {
                self.check_answer();
            });
        },

        game: {
            pointer: 0
        },

        check_answer: function() {
            var self = this;


        },

        setvoice: function() {
            var self = this;
            self.usevoice = self.itemdata.usevoice;
            self.voiceoption = self.itemdata.voiceoption;
            return;
        },

        getItems: function() {
            var self = this;
            var text_items = self.itemdata.sentences;

            self.items = text_items.map(function(target) {
                return {
                    target: target.sentence,
                    timer: [],
                    answered: false,
                    correct: false,
                    audio: null,
                    audiourl: target.audiourl ? target.audiourl : "",
                    imageurl: target.imageurl,
                };
            }).filter(function(e) {
                return e.target !== "";
            });

            //Prepare audio
            $.each(self.items, function (index, item) {
                item.audio = new Audio();
                item.audio.src = item.audiourl;
            });
            self.appReady();

        },

        appReady: function() {
            var self = this;
            self.controls.container.find('.wordshuffle_not_loaded').hide();
            self.controls.container.find('.wordshuffle_loaded').show();
            if(self.itemdata.hidestartpage){
                self.start();
            }else{
                self.controls.start_btn.prop("disabled", false);
            }
        },

        gotComparison: function(comparison) {
            var self = this;
            log.debug("gotComparison", comparison);
            var timelimit_progressbar = self.controls.progress_bar;
            if (comparison) {
                log.debug("correct!!");


                //if they cant retry OR the time limit is up, move on
            } else if(!self.itemdata.allowretry || timelimit_progressbar.hasClass('progress-bar-complete')) {
                log.debug("incorrect");
                
            } else {
                //it was wrong but they can retry
                log.debug("incorrect!! retry");
                return;
            }

            self.stopTimer(self.items[self.game.pointer].timer);

            if (self.game.pointer < self.items.length - 1) {
                setTimeout(function() {
                    self.controls.container.find('.wordshuffle_reply_' + self.game.pointer).hide();
                    self.game.pointer++;
                    self.nextPrompt();
                }, 2000);
            } else {
                self.end();
            }
        },
        

        end: function() {
            var self = this;
            self.controls.nextbutton.prop("disabled", true);

            //progress dots are updated on next_item. The last item has no next item, so we update from here
            self.updateProgressDots();

            //disable the buttons and go to next question or review
            setTimeout(function() {
                self.controls.nextbutton.prop("disabled",false);
                if(self.quizhelper.showitemreview){
                    self.show_item_review();
                }else{
                    self.next_question();
                }
            }, 2000);
        },

        start: function() {
            var self = this;

            self.controls.ctrl_btn.prop("disabled", true);

            self.items.forEach(function(item) {
                item.spoken = "";
                item.answered = false;
                item.correct = false;
            });

            self.game.pointer = 0;
            self.controls.listen_cont.show();
            self.controls.question.show();
            self.controls.game.show();
            self.controls.start_btn.hide();
            self.controls.mainmenu.hide();
            self.controls.controlsbox.show();

            self.nextPrompt();
        },

        nextPrompt: function() {

            var self = this;

            self.controls.ctrl_btn.prop("disabled", false);

            self.updateProgressDots();

            self.nextReply();

            // We autoplay the audio on item entry, if its not a mobile user.
            // If we do not have a start page and its the first item, we play on the item show event
            if (self.items[self.game.pointer].audio !==null && !self.quizhelper.mobile_user()){
                if(self.itemdata.hidestartpage && self.game.pointer === 0){
                    self.controls.container.on("showElement", () => {
                        setTimeout(function() {
                            self.controls.listen_btn.trigger('click');
                        }, 1000);
                    });
                }else{
                    setTimeout(function() {
                        self.controls.listen_btn.trigger('click');
                    }, 1000);
                }
            }
        },

        updateProgressDots: function() {
            var self = this;
            var color;
            var progress = self.items.map(function(item, idx) {
              color = "gray";
              if (self.items[idx].answered && self.items[idx].correct) {
                color = "green";
              } else if (self.items[idx].answered && !self.items[idx].correct) {
                color = "red";
              }
              return "<i style='color:" + color + "' class='fa fa-circle'></i>";
            }).join(" ");
            self.controls.title.html(progress);
        },

        nextReply: function() {
            var self = this;
            var code = "<div class='wordshuffle_reply wordshuffle_reply_" + self.game.pointer + " text-center' style='display:none;'>";
            var brackets = {started: false, ended: false, index: null};

            code += "<div class='form-container'>";


            code += " <i data-idx='" + self.game.pointer + "' class='wordshuffle_feedback'></i></div>";

            //hint - image
            if( self.items[self.game.pointer].imageurl) {
                code += "<div class='minilesson_sentence_image'><div class='minilesson_padded_image'><img src='"
                    + self.items[self.game.pointer].imageurl + "' alt='Image for gap fill' /></div></div>";
            }
            //hint - definition
            if( self.items[self.game.pointer].definition) {
                code += "<div class='definition-container'><div class='definition'>"
                    + self.items[self.game.pointer].definition + "</div>";
            }

            code += "</div>";
            code += "</div>";
            self.controls.question.append(code);
            var newreply = self.controls.container.find('.wordshuffle_reply_' + self.game.pointer);

            anim.do_animate(newreply, 'zoomIn animate__faster', 'in').then(
                function() {
                }
            );

            self.controls.ctrl_btn.prop("disabled", false);


            self.startTimer();
        },

         startTimer: function(){
            var self = this;
            // If we have a time limit, set up the timer, otherwise return
            if (self.itemdata.timelimit > 0) {
               // This is a function to start the timer (we call it conditionally below)
                var doStartTimer = function() {
                     // This shows progress bar
                    self.controls.progress_container.show();
                    self.controls.progress_container.find('i').show();
                    var progresbar = self.controls.progress_container.find('#progresstimer').progressTimer({
                        height: '5px',
                        timeLimit: self.itemdata.timelimit,
                        onFinish: function() {
                            self.controls.skip_btn.trigger('click');
                        }
                    });
                    progresbar.each(function() {
                        self.items[self.game.pointer].timer.push($(this).attr('timer'));
                    });
                }

                // This adds the timer and starts it. But if we dont have a start page and its the first item
                // we need to defer the timer start until the item is shown
                if(self.itemdata.hidestartpage && self.game.pointer === 0){
                    self.controls.container.on("showElement", () => {
                        doStartTimer();
                    });
                }else{
                    doStartTimer();
                }
            }
        },

        stopTimer: function(timers) {
            if (timers.length) {
                timers.forEach(function(timer) {
                    clearInterval(timer);
                });
            }
        },

        // Stop audio .. usually when leaving the item or sentence
        stop_audio: function(){
            var self =this;
            //pause audio if its playing
            var theaudio = self.items[self.game.pointer].audio;
            if(theaudio && !theaudio.paused) {
                theaudio.pause();
            }
        },
    };
});