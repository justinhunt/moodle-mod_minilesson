define(['jquery',
    'core/log',
    'core/ajax',
    'mod_minilesson/definitions',
    'mod_minilesson/animatecss',
    'mod_minilesson/progresstimer',
    'core/templates'
], function($, log, ajax, def, anim, progresstimer, templates) {
    "use strict"; // jshint ;_;

    log.debug('MiniLesson typing gap fill: initialising');

    return {

        // For making multiple instances
        clone: function() {
            return $.extend(true, {}, this);
        },

        init: function(index, itemdata, quizhelper) {
            var self = this;
            self.itemdata = itemdata;
            self.quizhelper = quizhelper;
            self.index = index;

            var animopts = {};
            animopts.useanimatecss = quizhelper.useanimatecss;
            anim.init(animopts);

            self.init_controls();
            self.register_events();
            self.getItems();
            self.appReady();
        },

        init_controls: function() {
            var self = this;
            self.controls = {
                container: $("#" + self.itemdata.uniqueid + "_container"),
                listen_cont: $("#" + self.itemdata.uniqueid + "_container .tgapfill_listen_cont"),
                nextbutton: $("#" + self.itemdata.uniqueid + "_container .minilesson_nextbutton"),
                start_btn: $("#" + self.itemdata.uniqueid + "_container .tgapfill_start_btn"),
                skip_btn: $("#" + self.itemdata.uniqueid + "_container .tgapfill_skip_btn"),
                ctrl_btn: $("#" + self.itemdata.uniqueid + "_container .tgapfill_ctrl-btn"),
                check_btn: $("#" + self.itemdata.uniqueid + "_container .tgapfill_check_btn"),
                game: $("#" + self.itemdata.uniqueid + "_container .tgapfill_game"),
                controlsbox: $("#" + self.itemdata.uniqueid + "_container .tgapfill_controls"),
                resultscontainer: $("#" + self.itemdata.uniqueid + "_container .tgapfill_resultscontainer"),
                mainmenu: $("#" + self.itemdata.uniqueid + "_container .tgapfill_mainmenu"),
                title: $("#" + self.itemdata.uniqueid + "_container .tgapfill_title"),
                progress_container: $("#" + self.itemdata.uniqueid + "_container .progress-container"),
                progress_bar: $("#" + self.itemdata.uniqueid + "_container .progress-container .progress-bar"),
                question: $("#" + self.itemdata.uniqueid + "_container .question")
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

            // Next page button.
            self.controls.nextbutton.on('click', function(e) {
                self.next_question();
            });

            // Start button.
            self.controls.start_btn.on("click", function() {
                self.start();
            });

            // Skip.
            self.controls.skip_btn.on("click", function() {
                $(this).prop("disabled", true);
                self.controls.check_btn.prop("disabled", true);
                self.stopTimer(self.items[self.game.pointer].timer);

                //mark as answered and incorrect
                self.items[self.game.pointer].answered = true;
                self.items[self.game.pointer].correct = false;

                // Move on after short time, to next prompt, or next question.
                if (self.game.pointer < self.items.length - 1) {
                    setTimeout(function() {
                        self.controls.container.find('.tgapfill_reply_' + self.game.pointer).hide();
                        self.game.pointer++;
                        self.nextPrompt();
                    }, 2000);
                } else {
                    self.end();
                }
            });

            // Check.
            self.controls.check_btn.on("click", function() {
                self.check_answer();
            });

            // Listen for enter key on input boxes
            self.controls.container.on("keydown", ".single-character", function(e) {
                if (e.which == 13) {
                    self.check_answer();
                }
            });
        },

        game: {
            pointer: 0
        },

        check_answer: function() {
            var self = this;
            var passage = self.items[self.game.pointer].parsedstring;
            var characterunputs = self.controls.container.find('.tgapfill_reply_' + self.game.pointer + ' input.single-character');
            var transcript = [];

            characterunputs.each(function() {
                var index = $(this).data('index');
                var value = $(this).val();
                transcript.push = ({
                    index: index,
                    value: value
                });
            });

            self.getComparison(passage, transcript, function(comparison) {
                self.gotComparison(comparison);
            });
        },

        getItems: function() {
            var self = this;
            var text_items = self.itemdata.sentences;

            self.items = text_items.map(function(target) {
                return {
                    target: target.sentence,
                    prompt: target.prompt,
                    parsedstring: target.parsedstring,
                    definition: target.definition,
                    timer: [],
                    typed: "",
                    answered: false,
                    correct: false,
                    audio: null,
                    imageurl: target.imageurl,
                };
            }).filter(function(e) {
                return e.target !== "";
            });
        },

        appReady: function() {
            var self = this;
            self.controls.container.find('.tgapfill_not_loaded').hide();
            self.controls.container.find('.tgapfill_loaded').show();
            if(self.itemdata.hidestartpage){
                self.start();
            }else{
                self.controls.start_btn.prop("disabled", false);
            }
        },

        gotComparison: function(comparison) {
            var self = this;
            var timelimit_progressbar = self.controls.progress_bar;
            if (comparison) {
                self.controls.container.find('.tgapfill_reply_' + self.game.pointer + ' .tgapfill_feedback[data-idx="' + self.game.pointer + '"]').addClass("fa fa-check");
                self.items[self.game.pointer].answered = true;
                self.items[self.game.pointer].correct = true;
                self.items[self.game.pointer].typed = false;
                //make the input boxes green and move forward
                self.controls.container.find('.tgapfill_reply_' + self.game.pointer + ' input').addClass("ml_gapfill_char_correct");

            //if they cant retry OR the time limit is up, move on
            } else if(!self.itemdata.allowretry || timelimit_progressbar.hasClass('progress-bar-complete')) {
                self.controls.container.find('.tgapfill_reply_' + self.game.pointer + ' .tgapfill_feedback[data-idx="' + self.game.pointer + '"]').addClass("fa fa-times");
                self.items[self.game.pointer].answered = true;
                self.items[self.game.pointer].correct = false;
                self.items[self.game.pointer].typed = false;
            } else {
                //it was wrong but they can retry
                var thereply = self.controls.container.find('.tgapfill_reply_' + self.game.pointer);
                anim.do_animate(thereply, 'shakeX animate__faster').then(
                    function() {
                        self.controls.ctrl_btn.prop("disabled", false);
                    }
                );
                return;
            }

            self.stopTimer(self.items[self.game.pointer].timer);

            if (self.game.pointer < self.items.length - 1) {
                setTimeout(function() {
                    self.controls.container.find(".tgapfill_reply_" + self.game.pointer).hide();
                    self.game.pointer++;
                    self.nextPrompt();
                }, 2000);
            } else {
                self.end();
            }
        },

        getComparison: function(passage, transcript, callback) {
            var self = this;

            self.controls.ctrl_btn.prop("disabled", true);

            var correctanswer = true;

            passage.forEach(function(data, index) {
                var char = '';

                if (data.type === 'input') {
                    if (correctanswer === true) {
                        char = self.controls.container.find('.tgapfill_reply_' + self.game.pointer + ' input.single-character[data-index="' + index + '"]').val();
                        if (char == '') {
                            correctanswer = false;
                        } else if (char != data.character) {
                            correctanswer = false;
                        }
                    }
                }
            });

            callback(correctanswer);
        },

        end: function() {
            var self = this;
            self.controls.nextbutton.prop("disabled", true);

            //progress dots are updated on next_item. The last item has no next item, so we update from here
            self.updateProgressDots();

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

            self.controls.question.show();
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
            var code = "<div class='tgapfill_reply tgapfill_reply_" + self.game.pointer + " text-center' style='display:none;'>";
            var brackets = {started: false, ended: false, index: null};

            code += "<div class='form-container'>";
            self.items[self.game.pointer].parsedstring.forEach(function(data, index) {
                if (brackets.started && !brackets.ended && brackets.index !== data.index) {
                    brackets.started = brackets.ended = false;
                    code += '</span>';
                }
                if ((data.type === 'input' || data.type === 'mtext') && !brackets.started) {
                    code += '<span class="form-input-phrase-online" data-mindex="'+data.index+'">';
                    brackets.started = true;
                }
                brackets.index = data.index;
                if (data.type === 'input') {
                    code += "<input class='single-character' autocomplete='off' autocapitalize='off' type='text' name='filltext" + index + "' maxlength='1' data-index='" + index + "'>";
                } else if (data.type === 'mtext') {
                    code += "<input class='single-character-mtext' type='text' name='readonly" + index + "' maxlength='1' value='" + data.character + "' readonly>";
                } else {
                    code += data.character;
                }
            });
            if (brackets.started && !brackets.ended) {
                code += '</span>';
            }
            code += " <i data-idx='" + self.game.pointer + "' class='tgapfill_feedback'></i></div>";

            //hint - image
            if( self.items[self.game.pointer].imageurl) {
                code += "<div class='minilesson_sentence_image'><div class='minilesson_padded_image'><img src='"
                    + self.items[self.game.pointer].imageurl + "' alt='Image for gap fill' /></div></div>";
            }
            //hint - definition
            if( self.items[self.game.pointer].definition) {
                code += "<div class='definition-container'><div class='definition'>"
                    + self.items[self.game.pointer].definition + "</div>";
            }            code += "</div>";
            self.controls.question.append(code);
            var newreply = self.controls.container.find('.tgapfill_reply_' + self.game.pointer);

            anim.do_animate(newreply, 'zoomIn animate__faster', 'in').then(
                function() {
                }
            );

            self.controls.ctrl_btn.prop("disabled", false);

            var inputElements = Array.from(self.controls.container.find('.tgapfill_reply_' + self.game.pointer + ' input.single-character'));
            self.formReady(inputElements);

            self.controls.container.find('.tgapfill_reply_' + self.game.pointer + ' input.single-character:first').focus();

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

        formReady: function(inputElements) {
            inputElements.forEach(function(ele, index) {
                ele.addEventListener("keydown", function(e) {
                    // If the keycode is backspace & the current field is empty
                    // focus the input before the current. Then the event happens
                    // which will clear the "before" input box.
                    if (e.keyCode === 8 && e.target.value === "") {
                     inputElements[Math.max(0, index - 1)].focus();
                    }
                });
                ele.addEventListener("input", function(e) {
                    // Take the first character of the input
                    // this actually breaks if you input an emoji like 👨‍👩‍👧‍👦....
                    // but I'm willing to overlook insane security code practices.
                    const [first, ...rest] = e.target.value;
                    e.target.value = first ?? ""; // First will be undefined when backspace was entered, so set the input to ""
                    const lastInputBox = index === inputElements.length - 1;
                    const didInsertContent = first !== undefined;
                    if (didInsertContent && !lastInputBox) {
                        // Continue to input the rest of the string
                        inputElements[index + 1].focus();
                        inputElements[index + 1].value = rest.join("");
                        inputElements[index + 1].dispatchEvent(new Event("input"));
                    }
                });
            });
        },
    };
});