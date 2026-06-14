// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @module     minilessonitem_cards/itemtype
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'mod_minilesson/progresstimer'], function ($) {
    return {
        index: 0,
        quizhelper: null,
        itemdata: {},
        container: null,

        clone: function () {
            return $.extend(true, { correctitems: 0, totalitems: 0 }, this);
        },

        next_question: function () {
            const stepdata = {};
            stepdata.index = this.index;
            stepdata.hasgrade = false;
            stepdata.totalitems = this.totalitems;
            stepdata.correctitems = this.correctitems;
            stepdata.grade = this.totalitems > 0 ? 100 * this.correctitems / this.totalitems : 0;
            this.quizhelper.do_next(stepdata);
        },

        init: function (index, itemdata, quizhelper) {
            this.index = index;
            this.itemdata = itemdata;
            this.quizhelper = quizhelper;
            this.container = document.querySelector(`#${itemdata.uniqueid}_container`);
            this.$container = $(this.container);
            this.nextbutton = this.container.querySelector('.minilesson_nextbutton');
            this.register_events();
            return this;
        },

        register_events() {
            this.nextbutton.addEventListener('click', e => {
                e.preventDefault();
                this.next_question();
            });
            this.$container.on('showElement', () => {
                this.load_swiper(Swiper => this.init_swiper(Swiper));
            });
        },

        /**
         * Lazy-load the Swiper library (and its CSS) only when a cards item is shown.
         *
         * Swiper is a >100kb MIT library vendored with the plugin (amd/src/external/swiper-lazy.js)
         * and its CSS shipped locally (item/cards/css/swiper-bundle.min.css), rather than pulled from
         * a CDN. This keeps it off the critical path and working on networks that cannot reach
         * jsdelivr/cdnjs (e.g. China).
         *
         * @param {Function} callback called with the Swiper constructor once it (and CSS) are ready.
         */
        load_swiper(callback) {
            this.ensure_swiper_css();
            require(['mod_minilesson/external/swiper-lazy'], function(Swiper) {
                callback(Swiper.default || Swiper);
            });
        },

        /**
         * Inject the local Swiper stylesheet once (shared across all cards items on the page).
         */
        ensure_swiper_css() {
            const id = 'minilessonitem_cards_swiper_css';
            if (document.getElementById(id)) {
                return;
            }
            const wwwroot = (window.M && window.M.cfg && window.M.cfg.wwwroot) || '';
            const link = document.createElement('link');
            link.id = id;
            link.rel = 'stylesheet';
            link.href = wwwroot + '/mod/minilesson/item/cards/css/swiper-bundle.min.css';
            document.head.appendChild(link);
        },

        init_swiper(Swiper) {
            const swiperel = this.container.querySelector('.swiper');
            const swiper = new Swiper(swiperel, {
                direction: 'horizontal',
                loop: false,
                autoHeight: true,
                pagination: {
                    el: '.swiper-pagination',
                    type: 'fraction',
                },
                navigation: {
                    addIcons: false,
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                on: {
                    slideChange: () => {
                        const previousIndex = swiper.previousIndex;
                        const currentIndex = swiper.activeIndex;

                        const previousSlideEl = swiper.slides[previousIndex];
                        const currentSlideEl = swiper.slides[currentIndex];

                        this.settleAudioPlayback(previousSlideEl, false);
                        this.settleAudioPlayback(currentSlideEl, true);
                    },
                }
            });
            this.settleAudioPlayback(swiper.slides[swiper.activeIndex], true);
            if (this.itemdata.timelimit > 0) {
                this.$container.find(".progress-container").show();
                this.$container.find(".progress-container i").show();
                this.$container.find(".progress-container #progresstimer").progressTimer({
                    height: '5px',
                    timeLimit: this.itemdata.timelimit,
                    onFinish: () => {
                        this.nextbutton.click();
                    }
                });
            }
        },

        settleAudioPlayback(slideEl, play) {
            const audio = slideEl.querySelector('audio');
            if (!audio) {
                return;
            }
            if (!play) {
                audio.pause();
                return;
            }
            if (audio.dataset.autoplay === '1') {
                audio.play();
                return;
            }
        }
    };
});