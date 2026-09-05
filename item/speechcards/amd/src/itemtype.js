define(['jquery',
  'core/log',
  'core/ajax',
  'mod_minilesson/definitions',
  'mod_minilesson/pollyhelper',
  'mod_minilesson/ttrecorder',
  'mod_minilesson/animatecss',
  'core/str',
  'core/notification'
], function ($, log, Ajax, def, polly, ttrecorder, anim, str, notification) {
    "use strict"; // jshint ;_;

  /*
  This file is to manage the quiz stage
   */

    log.debug('MiniLesson Speechcards: initialising');

    return {

      //for making multiple instances
        clone: function () {
            return $.extend(true, {}, this);
        },

        init: function (index, itemdata, quizhelper) {

            this.init_app(index, itemdata, quizhelper);
        },

        init_app: function (index, itemdata, quizhelper) {

            console.log(itemdata);

            var app = {
                passmark: 90,
                pointer: 1,
                jsondata: null,
                props: null,
                dryRun: false,
                language: 'en-US',
                terms: [],
                phonetics: [],
                displayterms: [],
                audios: [],
                results: [],
                controls: {},
                ttrec: null, //a handle on the tt recorder
                strings: {},

                init: function () {

                  //init terms
                    for (var i = 0; i < itemdata.sentences.length; i++) {
                        app.terms[i] = itemdata.sentences[i].sentence;
                        app.phonetics[i] = itemdata.sentences[i].phonetic;
                        app.displayterms[i] = itemdata.sentences[i].prompt;
                      //the card audio is the teacher's upload, or the TTS audio prepared on the server
                        if (itemdata.showlistenbutton && itemdata.sentences[i].audiourl) {
                            app.audios[i] = new Audio();
                            app.audios[i].src = itemdata.sentences[i].audiourl;
                        } else {
                            app.audios[i] = null;
                        }
                    }
                    app.language = itemdata.language;

                  //anim
                    var animopts = {};
                    animopts.useanimatecss = quizhelper.useanimatecss;
                    anim.init(animopts);

                    this.init_controls();
                    this.init_strings();
                    this.initComponents();
                    this.register_events();
                },

                init_controls: function () {
                    app.controls = {};
                    app.controls.star_rating = $("#" + itemdata.uniqueid + "_container .minilesson_star_rating");
                    app.controls.next_button = $("#" + itemdata.uniqueid + "_container .minilesson-speechcards_nextbutton");
                    app.controls.slider = $("#" + itemdata.uniqueid + "_container .minilesson_speechcards_target_phrase");
                    app.controls.listen_cont = $("#" + itemdata.uniqueid + "_container .minilesson_speechcards_listen_cont");
                    app.controls.listen_btn = $("#" + itemdata.uniqueid + "_container .minilesson_speechcards_listen_btn");
                },
                init_strings: function () {
                    var app = this;
                    str.get_strings([
                    { "key": "nextlessonitem", "component": 'mod_minilesson' },
                    { "key": "confirm_desc", "component": 'mod_minilesson' },
                    { "key": "yes", "component": 'moodle' },
                    { "key": "no", "component": 'moodle' },
                    ]).done(function (s) {
                        var i = 0;
                        app.strings.nextlessonitem = s[i++];
                        app.strings.confirm_desc = s[i++];
                        app.strings.yes = s[i++];
                        app.strings.no = s[i++];
                    });
                },
                next_question: function () {
                    app.stop_audio();
                    var stepdata = {};
                    stepdata.index = index;
                    stepdata.hasgrade = true;
                    stepdata.totalitems = app.terms.length;
                    stepdata.correctitems = app.results.filter(function (e) {
                        return e.points > 0; }).length;
                    stepdata.grade = stepdata.totalitems > 0 ? Math.round((stepdata.correctitems / stepdata.totalitems) * 100) : 0;
                    quizhelper.do_next(stepdata);
                },
                register_events: function () {

                    $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton").on('click', function (e) {
                        if (app.results.length <= app.terms.length) {
                            notification.confirm(
                                app.strings.nextlessonitem,
                                app.strings.confirm_desc,
                                app.strings.yes,
                                app.strings.no,
                                function () {
                                    app.next_question();
                                }
                            );
                        } else {
                            app.next_question();
                        }
                    });

                    app.controls.next_button.click(function () {
                      //user has given up ,update word as failed
                        app.check(false);

                      //transition if required
                        if (app.is_end()) {
                            setTimeout(function () {
                                app.do_end();
                            }, 200);
                        } else {
                            app.controls.next_button.prop("disabled", true);
                            app.controls.next_button.children('.fa').removeClass('fa-times');
                            app.controls.next_button.children('.fa').addClass('fa-spinner fa-spin');
                            setTimeout(function () {
                                app.controls.next_button.children('.fa').removeClass('fa-spinner fa-spin');
                                app.controls.next_button.children('.fa').addClass('fa-times');
                                app.controls.next_button.prop("disabled", false);
                                app.do_next();
                            }, 200);
                        }

                    });

                  //listen button plays (or stops) the audio for the card on screen
                    app.controls.listen_btn.on('click', function () {
                        app.play_audio();
                    });

                  //keep the button icon in step with the audio it is playing
                    app.audios.forEach(function (theaudio) {
                        if (theaudio === null) {
                            return;
                        }
                        theaudio.addEventListener('play', function () {
                            app.controls.listen_btn.children('.fa').removeClass('fa-volume-up').addClass('fa-stop');
                        });
                        theaudio.addEventListener('ended', function () {
                            app.controls.listen_btn.children('.fa').removeClass('fa-stop').addClass('fa-volume-up');
                        });
                        theaudio.addEventListener('pause', function () {
                            app.controls.listen_btn.children('.fa').removeClass('fa-stop').addClass('fa-volume-up');
                        });
                    });
                },

                play_audio: function () {
                    var theaudio = app.audios[app.pointer - 1];
                    if (!theaudio) {
                        return;
                    }
                  //if we are already playing, stop
                    if (!theaudio.paused) {
                        theaudio.pause();
                        theaudio.currentTime = 0;
                        return;
                    }
                    theaudio.load();
                    theaudio.play();
                },

              //stop audio .. usually when leaving the card or the item
                stop_audio: function () {
                    var theaudio = app.audios[app.pointer - 1];
                    if (theaudio && !theaudio.paused) {
                        theaudio.pause();
                        theaudio.currentTime = 0;
                    }
                },

              //the listen button only makes sense on cards that have audio
                update_listen_button: function () {
                    if (app.audios[app.pointer - 1]) {
                        app.controls.listen_cont.show();
                    } else {
                        app.controls.listen_cont.hide();
                    }
                },

                initComponents: function () {

                    var theCallback = function (message) {

                        switch (message.type) {
                            case 'recording':
                              //don't record the model audio playing over the top of the student
                                app.stop_audio();
                            break;

                            case 'speech':
                                log.debug("speech at speechcards");
                                var speechtext = message.capturedspeech;
                                var spoken_clean = quizhelper.cleanText(speechtext);
                                var correct_clean = quizhelper.cleanText(app.terms[app.pointer - 1]);
                                var correctphonetic = app.phonetics[app.pointer - 1];
                                log.debug('speechtext:', speechtext);
                                log.debug('spoken:', spoken_clean);
                                log.debug('correct:', correct_clean);
                              //Similarity check by character matching
                                var similarity_js = quizhelper.similarity(spoken_clean, correct_clean);
                                log.debug('JS similarity: ' + spoken_clean + ':' + correct_clean + ':' + similarity_js);

                              //Similarity check by direct-match/acceptable-mistranscription
                                if (similarity_js >= app.passmark ||
                                app.wordsDoMatch(spoken_clean, correct_clean)) {
                                      log.debug('local match:' + ':' + spoken_clean + ':' + correct_clean);
                                      app.showStarRating(100);
                                      app.flagCorrectAndTransition();
                                      return;
                                }

                              //Similarity check by phonetics(ajax)
                                quizhelper.checkByPhonetic(correct_clean, spoken_clean, correctphonetic, app.language).then(function (similarity_php) {
                                    if (similarity_php === false) {
                                        return $.Deferred().reject();
                                    } else {
                                        log.debug('PHP similarity: ' + spoken_clean + ':' + correct_clean + ':' + similarity_php);

                                        if (similarity_php >= app.passmark) {
                                            app.showStarRating(similarity_php);
                                            app.flagCorrectAndTransition();
                                        } else {
                                          //show the greater of the ratings
                                            app.showStarRating(Math.max(similarity_js, similarity_php));
                                        }
                                    } //end of if check_by_phonetic result
                                }); //end of check by phonetic
                        } //end of switch message type
                    };

                  //init tt recorder
                    var opts = {};
                    opts.uniqueid = itemdata.uniqueid;
                    opts.callback = theCallback;
                    opts.stt_guided = quizhelper.is_stt_guided();
                    app.ttrec = ttrecorder.clone();
                    app.ttrec.init(opts);
                  //init prompt for first card
                  //in some cases ttrecorder wants to know the target
                    app.ttrec.currentPrompt = app.displayterms[app.pointer - 1];

                  //init progress dots
                    app.progress_dots(app.results, app.terms);

                    app.initSlider();


                },

                initSlider: function () {
                    app.controls.slider.text(app.displayterms[app.pointer - 1]);
                    app.controls.slider.show();
                    app.update_listen_button();
                },

                writeCurrentTerm: function () {
                  /*
                  app.controls.slider.toggle("slide",{direction:"left"});
                  app.controls.slider.text(app.displayterms[app.pointer - 1]);
                  app.controls.slider.toggle("slide",{direction:"right"})
                   */
                    anim.do_animate(app.controls.slider, 'zoomOut animate__faster', 'out').then(
                        function () {
                            app.controls.slider.text(app.displayterms[app.pointer - 1]);
                            anim.do_animate(app.controls.slider, 'zoomIn animate__faster', 'in');
                        }
                    );
                },

                flagCorrectAndTransition: function () {

                  //update students word log if matched
                    app.check(true);

                  //transition if required
                    if (app.is_end()) {
                        setTimeout(function () {
                            app.do_end();
                        }, 700);
                    } else {
                        setTimeout(function () {
                            app.do_next();
                        }, 700);
                    }

                },

                wordsDoMatch: function (phraseheard, currentphrase) {
                  //lets lower case everything
                    phraseheard = quizhelper.cleanText(phraseheard);
                    currentphrase = quizhelper.cleanText(currentphrase);
                    if (phraseheard == currentphrase) {
                        return true;
                    }
                    return false;
                },


                showStarRating: function (similarity) {
                  //how many stars code
                    var stars = [true, true, true];
                    if (similarity < app.passmark) {
                        stars = [true, true, false];
                    }
                    if (similarity < .75) {
                        stars = [true, false, false];
                    }
                    if (similarity < 0.5) {
                        stars = [false, false, false];
                    }

                  //prepare stars html
                    var code = "";
                    stars.forEach(function (star) {
                        if (star === true) {
                            code += '<i class="fa fa-star"></i>';
                        } else {
                            code += '<i class="fa fa-star-o"></i>';
                        }
                    });

                    app.controls.star_rating.html(code);
                },

                check: function (correct) {
                    var points = 1;
                    if (correct == true) {
                        points = 1;
                    } else {
                        points = 0;
                    }
                    var result = {
                        points: points
                    };
                    app.results.push(result);
                },

                do_next: function () {
                    app.stop_audio();
                    app.pointer++;
                    app.update_listen_button();
                    app.progress_dots(app.results, app.terms);
                    app.clearStarRating();
                    if (!app.is_end()) {
                        app.writeCurrentTerm();
                      //in some cases ttrecorder wants to know the target
                        if (quizhelper.use_ttrecorder()) {
                            app.ttrec.currentPrompt = app.displayterms[app.pointer - 1];
                        }
                    } else {
                        app.do_end();
                    }
                },

                clearStarRating: function () {
                    app.controls.star_rating.html('· · ·');
                },

                do_end: function () {
                    app.next_question();
                },

                is_end: function () {
                  //pointer is 1 based but array is, of course, 0 based
                    if (app.pointer <= app.terms.length) {
                        return false;
                    } else {
                        return true;
                    }
                },

                progress_dots: function (results, terms) {

                    var code = "";
                    var color = "";
                    terms.forEach(function (o, i) {
                        color = "#E6E9FD";
                        var icon = "fa fa-square";
                        if (results[i] !== undefined) {
                            if (results[i].points) {
                                color = "#74DC72";
                                icon = "fa fa-check-square";
                            } else {
                                color = "#FB6363";
                                icon = "fa fa-window-close";
                            }
                        }
                        code += '<i style="color:' + color + ';" class="' + icon + ' pl-1"></i>';
                    });

                    $("#" + itemdata.uniqueid + "_container .minilesson_progress_dots").html(code);

                },
            }; //end of app definition
            app.init();

        } //end of init_App


    }; //end of return value
});