define(['jquery', 'core/log', 'core/str', 'core/notification', 'mod_minilesson/translate'],
        function ($, log, str, notification, translate) {
    "use strict"; // jshint ;_;

    log.debug('MiniLesson TTS Dialog Player: initialising');

    return {

        init: function (uniqueid) {

            var that = this;
            var player = $('#' + uniqueid + '_ttsdialogplayer');

            // Fetch all the controls and data that we need.
            var dialoglines = player.find('.ttsdialogline');
            var linecount = dialoglines.length;
            var blocks = player.find('.ttsdialog_block');
            var audio = player.find('.ttsdialog_audioplayer');
            var playbutton = player.find('.ttsdialog_button');
            var backbutton = player.find('.ttsdialog_back');
            var forwardbutton = player.find('.ttsdialog_forward');
            var stopbutton = player.find('.ttsdialog_stop');

            var stoppedstate = '<i class="fa fa-play" aria-hidden="true"></i>';
            var playingstate = '<i class="fa fa-pause" aria-hidden="true"></i>';
            var lineplayicon = '<i class="fa fa-play-circle" aria-hidden="true"></i>';
            var linestopicon = '<i class="fa fa-stop-circle" aria-hidden="true"></i>';

            var currentline = -1;
            // 'sequence' = the master transport plays lines back to back; 'single' = a block's
            // own play button plays just its line and does not advance to the next block.
            var playmode = 'sequence';
            // The block play button currently showing a stop icon (single-play mode), if any.
            var singlebtn = null;

            // Revert the active single-play block button to its play icon.
            var reset_single_btn = function () {
                if (singlebtn) {
                    singlebtn.html(lineplayicon);
                    singlebtn = null;
                }
            };

            // Translation context, read from the template data attributes.
            var cmid = parseInt(player.data('cmid'), 10);
            var itemid = parseInt(player.data('itemid'), 10);
            var sourceLang = String(player.data('sourcelang') || '');
            var destLang = String(player.data('nativelang') || '');
            translate.init(cmid, itemid);

            var strings = {translating: 'Translating ...'};
            str.get_strings([
                {key: 'ttsdialog:translating', component: 'mod_minilesson'},
                {key: 'downloadtranslationmodel', component: 'mod_minilesson'},
                {key: 'downloadtranslationmodel_desc', component: 'mod_minilesson'},
                {key: 'download', component: 'mod_minilesson'},
                {key: 'skip', component: 'mod_minilesson'},
                {key: 'downloadingtranslator', component: 'mod_minilesson'}
            ]).done(function (s) {
                strings.translating = s[0];
                strings.downloadtranslationmodel = s[1];
                strings.downloadtranslationmodel_desc = s[2];
                strings.download = s[3];
                strings.skip = s[4];
                strings.downloadingtranslator = s[5];
            });

            // Highlight the block for the given line index and scroll it into view.
            var highlight_block = function (index) {
                blocks.removeClass('ttsdialog_active');
                if (index < 0) {
                    return;
                }
                var block = blocks.filter('[data-index="' + index + '"]');
                if (block.length) {
                    block.addClass('ttsdialog_active');
                    if (block[0].scrollIntoView) {
                        block[0].scrollIntoView({behavior: 'smooth', block: 'nearest'});
                    }
                }
            };

            // Play a specific line by index, in the given mode ('sequence' or 'single').
            var play_line = function (index, mode) {
                mode = mode || 'sequence';
                if (index < 0 || index >= linecount) {
                    stop_play();
                    return;
                }
                reset_single_btn();
                playmode = mode;
                currentline = index;
                audio.attr('src', dialoglines.eq(index).data('audiourl'));
                highlight_block(index);
                // Only the master transport reflects playing state; single play uses the block button.
                playbutton.html(mode === 'sequence' ? playingstate : stoppedstate);
                audio[0].play();
            };

            // Advance to the next line (called when a line finishes in sequence mode).
            var next_play = function () {
                if (currentline + 1 >= linecount) {
                    stop_play();
                    return;
                }
                play_line(currentline + 1, 'sequence');
            };

            // Stop and reset to the start.
            var stop_play = function () {
                audio[0].pause();
                currentline = -1;
                playmode = 'sequence';
                playbutton.html(stoppedstate);
                highlight_block(-1);
                reset_single_btn();
            };

            // A line finished playing.
            var on_ended = function () {
                if (playmode === 'single') {
                    // Single block play: do not advance to the next block.
                    reset_single_btn();
                    highlight_block(-1);
                    currentline = -1;
                    return;
                }
                next_play();
            };

            // Register events.
            audio[0].addEventListener('ended', on_ended);

            playbutton.on('click', function () {
                if (!audio[0].paused) {
                    // Currently playing -> pause.
                    audio[0].pause();
                    playbutton.html(stoppedstate);
                    reset_single_btn();
                } else if (currentline >= 0 && audio.attr('src')) {
                    // Paused mid-line -> resume as a sequence.
                    reset_single_btn();
                    playmode = 'sequence';
                    playbutton.html(playingstate);
                    audio[0].play();
                } else {
                    // Stopped -> start from the beginning.
                    play_line(0, 'sequence');
                }
            });

            backbutton.on('click', function () {
                var target = currentline <= 0 ? 0 : currentline - 1;
                play_line(target, 'sequence');
            });

            forwardbutton.on('click', function () {
                if (currentline + 1 < linecount) {
                    play_line(currentline + 1, 'sequence');
                }
            });

            stopbutton.on('click', function () {
                stop_play();
            });

            // Clicking a block's play button plays just that line and toggles to a stop button.
            // When the line finishes it does not advance to the next block.
            player.on('click', '.ttsdialog_speaker', function () {
                var btn = $(this);
                var index = parseInt(btn.closest('.ttsdialog_block').data('index'), 10);
                // Clicking the button of the line already playing -> stop it.
                if (playmode === 'single' && singlebtn && singlebtn.is(btn) && !audio[0].paused) {
                    stop_play();
                    return;
                }
                play_line(index, 'single');
                singlebtn = btn;
                btn.html(linestopicon);
            });

            // Clicking a block's translate icon toggles its translation.
            player.on('click', '.ttsdialog_translate', function () {
                var btn = $(this);
                var block = btn.closest('.ttsdialog_block');
                var panel = block.find('.ttsdialog_block_translation');

                // Already translated: just toggle visibility and the button's active state.
                if (block.data('translated')) {
                    panel.toggleClass('hide');
                    btn.toggleClass('ttsdialog_iconbtn_active', !panel.hasClass('hide'));
                    return;
                }

                var index = parseInt(block.data('index'), 10);
                var text = String(dialoglines.eq(index).data('speakertext'));
                panel.removeClass('hide').html('<em>' + strings.translating + '</em>');
                btn.addClass('ttsdialog_iconbtn_active');

                that.do_translate(sourceLang, destLang, text, strings, function (translation) {
                    panel.html(translation);
                    block.data('translated', true);
                });
            });

        }, // end of init function

        /**
         * Translate a single line, mirroring the fiction item type's flow: native
         * browser translation first, then the Poodll web service fallback, with a
         * model-download confirmation when needed.
         *
         * @param {string} sourceLang Source language tag.
         * @param {string} destLang Destination (native) language tag.
         * @param {string} text The text to translate.
         * @param {object} strings Resolved language strings.
         * @param {Function} callback Called with the translated (or error) string.
         */
        do_translate: function (sourceLang, destLang, text, strings, callback) {
            // Reuse an existing session for the same language pair.
            if (translate.session && translate.sourceLang === sourceLang && translate.destLang === destLang) {
                translate.translate(text).then(function (translation) {
                    callback(translation ? translation : '');
                }).catch(function (e) {
                    log.error('Translation error: ' + e);
                    callback('');
                });
                return;
            }

            translate.check_availability(sourceLang, destLang).then(function (status) {
                if (status === 'unavailable') {
                    log.debug('Translation not available for this language pair');
                    callback('');
                    return;
                }

                if (status === 'download_needed') {
                    notification.confirm(
                        strings.downloadtranslationmodel,
                        strings.downloadtranslationmodel_desc,
                        strings.download,
                        strings.skip,
                        function () {
                            translate.create_session(sourceLang, destLang, function (percent) {
                                callback('<em>' + strings.downloadingtranslator.replace('{$a}', percent) + '</em>');
                            }).then(function (success) {
                                return success ? translate.translate(text) : null;
                            }).then(function (translation) {
                                callback(translation ? translation : '');
                            }).catch(function (e) {
                                log.error('Translation error: ' + e);
                                callback('');
                            });
                        },
                        function () {
                            // User skipped the model download: fall back to remote if possible.
                            if (translate.force_remote()) {
                                translate.create_session(sourceLang, destLang).then(function () {
                                    return translate.translate(text);
                                }).then(function (translation) {
                                    callback(translation ? translation : '');
                                }).catch(function (e) {
                                    log.error('Translation error: ' + e);
                                    callback('');
                                });
                            } else {
                                callback('');
                            }
                        }
                    );
                } else if (status === 'ready') {
                    translate.create_session(sourceLang, destLang).then(function () {
                        return translate.translate(text);
                    }).then(function (translation) {
                        callback(translation ? translation : '');
                    }).catch(function (e) {
                        log.error('Translation error: ' + e);
                        callback('');
                    });
                }
            });
        }

    }; // end of return value
});
