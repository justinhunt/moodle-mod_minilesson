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
 * TODO describe module searchlesson
 *
 * @module     mod_minilesson/searchlesson
 * @copyright  2025 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Ajax from 'core/ajax';
import * as Notification from 'core/notification';
import Templates from 'core/templates';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import * as Str from 'core/str';
import Fragment from 'core/fragment';


const component = 'mod_minilesson';

export const registerFilter = () => {
    const form = document.querySelector('#lessonbank_filters');
    const cardsContainer = document.querySelector('[data-region="cards-container"]');
    const searchFilter = form => {

        const functionname = 'local_lessonbank_list_minilessons';
        const params = new URLSearchParams();
        if (form.elements['searchgroup[language]']) {
            params.append('language', form.elements['searchgroup[language]'].value);
        }
        if (form.elements['searchgroup[keyword]']) {
            params.append('keywords', form.elements['searchgroup[keyword]'].value);
        }
        if (form.elements['level[]']) {
            const selectedOptions = form.elements['level[]'].selectedOptions;
            Array.from(selectedOptions).forEach((option, index) => {
                params.append(`level[${index}]`, option.value);
            });
        }
        const args = {
            function: functionname,
            args: params.toString(),
        };
        Ajax.call([{
            methodname: `${component}_lessonbank`,
            args: args,
        }])[0].then(items => {
            items = JSON.parse(items.data);
            Templates.render(`${component}/lessonbankitems`, {items})
            .then((html, js) => {
                return Templates.replaceNodeContents(cardsContainer, html, js);
            }).then(() => {
                document.querySelector('#region-main')?.scrollIntoView({
                    behavior: "smooth",
                    block: "start"
                });
            });
            return null;
        })
        .catch(Notification.exception);
    };
    cardsContainer.addEventListener('click', e => {
        if (e.target.href) {
            return;
        }
        e.preventDefault();
        const downloadbtn = e.target.closest('[data-action="download"]');
        if (downloadbtn) {
            if (!downloadbtn.dataset.id) {
                return;
            }
            const id = Number(downloadbtn.dataset.id);
            const url = new URL(window.location.href);
            url.searchParams.set('restore', id);
            url.searchParams.set('sesskey', M.cfg.sesskey);
            window.location.href = url.toString();
        }
        const showtextbtn = e.target.closest('[data-action="showtext"]');
        if (showtextbtn) {
            const wrapper = showtextbtn.parentElement;
            const titlehtml = wrapper.firstElementChild.cloneNode(true).outerHTML;
            wrapper.innerHTML = titlehtml + wrapper.dataset.text;
        }
    });
    cardsContainer.addEventListener('click', e => {
        if (e.target.href) {
            return;
        }
        e.preventDefault();
        const translatebtn = e.target.closest('[data-action="translate"]');
        if (translatebtn) {
            const callFragment = data => {
                return Fragment.loadFragment(component, 'translatetoimport', M.cfg.contextid, {
                    params: data
                })
                .then((response, js) => new Promise(resolve => {
                    response = JSON.parse(response);
                    return resolve(
                        response,
                        js
                    );
                }));
            };
            ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                large: false,
                removeOnClose: true,
                title: Str.get_string('translatetoimport', component),
                body: callFragment(
                    new URLSearchParams([...Object.entries(translatebtn.dataset)]).toString()
                ).then((response, js) => new Promise(resolve => resolve(
                    response.html, js
                )))
            }).then(function (modal) {
                modal.hideFooter();
                modal.getRoot().on('submit ' + ModalEvents.save, function(e) {
                    e.preventDefault();
                    var form = this.querySelector('form');
                    modal.setBody(
                        callFragment(new URLSearchParams(new FormData(form)).toString())
                        .then((response, js) => new Promise(resolve => {
                            if (response.redirecturl) {
                                location.href = response.redirecturl;
                                resolve('', js);
                                return;
                            }
                            resolve(response.html, js);
                        }))
                    );
                });
                modal.show();
            });
        }
    });
    form?.addEventListener('submit', e => {
        e.preventDefault();
        searchFilter(form);
    });
    if (form) {
        searchFilter(form);
    }
};