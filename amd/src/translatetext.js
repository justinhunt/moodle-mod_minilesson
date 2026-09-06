/**
 * Wires up the mod_minilesson/translatetext template: a translate icon that fills a panel
 * with the text translated into the learner's native language.
 *
 * @module mod_minilesson/translatetext
 * @copyright 2026 Justin Hunt <justin@poodll.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str', 'mod_minilesson/translate'], function ($, str, translate) {
    "use strict";

    return {

        /**
         * Wire up every translate icon inside the given container. Safe to call again on
         * the same container; each icon is only wired once.
         *
         * @param {object} container A jQuery object (or selector) to look inside.
         */
        init: function (container) {
            var that = this;
            var $container = $(container);
            if (!$container.length) {
                return;
            }
            $container.find('.ml_translate').each(function () {
                var $wrapper = $(this);
                if ($wrapper.data('mltranslateinit')) {
                    return;
                }
                $wrapper.data('mltranslateinit', true);
                $wrapper.on('click', '.ml_translate_btn', function (e) {
                    e.preventDefault();
                    that.toggle($wrapper, $(this));
                });
            });
        },

        /**
         * Show, hide, or fetch the translation for one translate icon.
         *
         * @param {object} wrapper The .ml_translate element, holding the translation context.
         * @param {object} btn The clicked .ml_translate_btn element.
         */
        toggle: function (wrapper, btn) {
            var panel = wrapper.find('.ml_translate_panel');

            // Already translated: just show or hide what we have.
            if (wrapper.data('translated')) {
                panel.toggleClass('d-none');
                btn.toggleClass('ml_translate_btn_active', !panel.hasClass('d-none'));
                return;
            }

            var cmid = parseInt(wrapper.data('cmid'), 10);
            var itemid = parseInt(wrapper.data('itemid'), 10);
            var sourceLang = String(wrapper.data('sourcelang') || '');
            var destLang = String(wrapper.data('destlang') || '');
            var text = String(btn.data('translatetext') || '');
            if (!text) {
                return;
            }

            panel.removeClass('d-none');
            btn.addClass('ml_translate_btn_active');
            str.get_string('translating', 'mod_minilesson').done(function (translating) {
                if (!wrapper.data('translated')) {
                    panel.html('<em>' + translating + '</em>');
                }
            });

            // The web service resolves the target language from the activity, so it needs
            // to know which item (and activity) this text came from.
            translate.init(cmid, itemid);
            translate.do_translate(sourceLang, destLang, text, function (translation, isprogress) {
                panel.html(translation);
                if (translation && !isprogress) {
                    wrapper.data('translated', true);
                }
            });
        }
    };
});
