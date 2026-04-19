define(
    [
    'jquery', 'core/log', 'mod_minilesson/definitions', 'minilessonitem_slides/reveal',
    'core/str', 'core/modal_factory', 'core/fragment', 'mod_minilesson/fullscreen_helper'
    ],
    function ($, log, def, RevealImplement, Str, ModalFactory, Fragment, FullscreenHelper) {

        "use strict"; // jshint ;_;

        /*
       This file is to manage the slides item type
        */

        log.debug('MiniLesson Slides: initialising');

        return {

            instance: null,

            itemdata: {},

            //for making multiple instances
            clone: function () {
                return $.extend(true, {}, this);
            },

            init: function (index, itemdata, quizhelper) {
                this.itemdata = itemdata;
                this.register_events(index, itemdata, quizhelper);
            },

            prepare_html: function (itemdata) {
                //do something
            },

            register_events: function (index, itemdata, quizhelper) {
                var self = this;
                //When click next button , report and leave it up to parent to eal with it.
                $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton").on('click', function (e) {
                    var stepdata = {};
                    stepdata.index = index;
                    stepdata.hasgrade = false;
                    stepdata.totalitems = 0;
                    stepdata.correctitems = 0;
                    stepdata.grade = 0;
                    quizhelper.do_next(stepdata);
                });
                $("#" + itemdata.uniqueid + "_container").on("showElement", async(e) => {
                    if (!self.instance) {
                        self.instance = await RevealImplement.init(e.target.querySelector('.reveal'), itemdata.region, itemdata.selectedtheme);
                        self.instance.initialize();
                    }

                    if (itemdata.fullscreen) {
                        const btn = document.getElementById('toggle-fs-' + itemdata.uniqueid);
                        if (btn) {
                            btn.addEventListener('click', (e) => {
                                e.preventDefault();
                                const isFS = !!document.fullscreenElement || !!document.webkitFullscreenElement;

                                if (isFS) {
                                    const exitMethod = document.exitFullscreen ||
                                        document.webkitExitFullscreen ||
                                        document.mozCancelFullScreen ||
                                        document.msExitFullscreen;
                                    if (exitMethod) {
                                        exitMethod.apply(document);
                                    }
                                } else {
                                    // Dispatch synthetic F key to trigger Reveal's native fullscreen
                                    const event = new KeyboardEvent('keydown', {
                                        key: 'f',
                                        keyCode: 70,
                                        which: 70,
                                        bubbles: true,
                                        cancelable: true
                                    });
                                    document.dispatchEvent(event);
                                }
                            });

                            const updateButtonUI = () => {
                                const isFS = !!document.fullscreenElement || !!document.webkitFullscreenElement;
                                btn.classList.toggle('is-fullscreen', isFS);
                                btn.innerHTML = isFS
                                    ? '<i class="fa fa-compress"></i>'
                                    : '<i class="fa fa-expand"></i>';
                            };
                            document.addEventListener('fullscreenchange', updateButtonUI);
                            document.addEventListener('webkitfullscreenchange', updateButtonUI);
                        }
                    }



                    if (itemdata.timelimit > 0) {
                        $("#" + itemdata.uniqueid + "_container .progress-container").show();
                        $("#" + itemdata.uniqueid + "_container .progress-container i").show();
                        $("#" + itemdata.uniqueid + "_container .progress-container #progresstimer").progressTimer({
                            height: '5px',
                            timeLimit: itemdata.timelimit,
                            onFinish: function () {
                                $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton").trigger('click');
                            }
                        });
                    }
                });

                if ($("#" + itemdata.uniqueid + "_container").is(':visible')) {
                    $("#" + itemdata.uniqueid + "_container").trigger('showElement');
                }
            },

            register_previewbutton: function (buttonid, region = 'default') {
                var previewbtn = document.getElementById(buttonid);
                if (!previewbtn) {
                    return;
                }
                previewbtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    var form = previewbtn.form;
                    if (!form) {
                        return;
                    }
                    ModalFactory.create({
                        type: ModalFactory.types.CANCEL,
                        large: true,
                        removeOnClose: true,
                        templateContext: {
                            classes: 'minilesson-slides-preview-modal'
                        },
                        title: Str.get_string('slides:previewmodaltitle', 'mod_minilesson'),
                        body: Fragment.loadFragment('mod_minilesson', 'preview_slides', M.cfg.contextid, {
                            formdata: new URLSearchParams([...new FormData(form).entries()]).toString()
                        })
                    }).then(function (modal) {
                        modal.getFooter().addClass('d-none');
                        modal.show();
                    });
                    return;
                });
                if (previewbtn.form) {
                    const themeselect = previewbtn.form.querySelector('[data-control="theme"]');
                    if (themeselect) {
                        themeselect.addEventListener('change', function (e) {
                            RevealImplement.setTheme(e.target.value);
                        });
                        RevealImplement.setTheme(themeselect.value);
                    }
                }
            }
        }; //end of return value
    }
);