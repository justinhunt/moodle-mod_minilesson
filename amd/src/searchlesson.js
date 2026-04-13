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

export const registerFilter = (opts) => {
    const localnativelang = opts.nativelang;
    const form = document.querySelector('#lessonbank_filters');
    const cardsContainer = document.querySelector('[data-region="cards-container"]');
    const gridlayoutbtn = document.querySelector('.gridlayoutbtn');
    const listlayoutbtn = document.querySelector('.listlayoutbtn');
    const countcontainer = document.querySelector('.countcontainer');
    const pagination = document.querySelector('[name="perpageselection"]');

    const getSpinner = () => {
        const template = document.getElementById('mod_minilesson-spinner');
        if (template) {
            return template.content.cloneNode(true);
        }
        return '';
    };

    const getPreviewIframe = (url) => {
        const template = document.getElementById('mod_minilesson-preview-iframe');
        if (template) {
            const content = template.content.cloneNode(true);
            const iframe = content.querySelector('iframe');
            if (iframe) {
                iframe.src = url;
            }
            return content;
        }
        return '';
    };

    gridlayoutbtn?.addEventListener('click', e => {
        e.preventDefault();
        cardsContainer.classList.remove('listlayout');
        gridlayoutbtn.classList.add('active');
        listlayoutbtn.classList.remove('active');
        searchFilter(form);
    });

    listlayoutbtn?.addEventListener('click', e => {
        e.preventDefault();
        cardsContainer.classList.add('listlayout');
        listlayoutbtn.classList.add('active');
        gridlayoutbtn.classList.remove('active');
        searchFilter(form);
    });

    const searchFilter = form => {
        // The remote function we will call to search and list the lessons
        const functionname = 'local_lessonbank_list_minilessons';
        // Build our search params
        const params = new URLSearchParams();
        var targetlanguage = '';
        // Target Language  
        if (form.elements['searchgroup[language]']) {
            targetlanguage = form.elements['searchgroup[language]'].value;
            params.append('language', targetlanguage);
        }
        // Keywords
        if (form.elements['searchgroup[keyword]']) {
            params.append('keywords', form.elements['searchgroup[keyword]'].value);
        }
        // Level
        if (form.elements['level[]']) {
            const selectedOptions = form.elements['level[]'].selectedOptions;
            Array.from(selectedOptions).forEach((option, index) => {
                params.append(`level[${index}]`, option.value);
            });
        }
        // Skills
        if (form.elements['skill[]']) {
            const selectedOptions = form.elements['skill[]'].selectedOptions;
            Array.from(selectedOptions).forEach((option, index) => {
                params.append(`skill[${index}]`, option.value);
            });
        }
        // Topic
        if (form.elements['topic[]']) {
            const selectedOptions = form.elements['topic[]'].selectedOptions;
            Array.from(selectedOptions).forEach((option, index) => {
                params.append(`topic[${index}]`, option.value);
            });
        }
        // Item Type
        if (form.elements['itemtype[]']) {
            const selectedOptions = form.elements['itemtype[]'].selectedOptions;
            Array.from(selectedOptions).forEach((option, index) => {
                params.append(`itemtypes[${index}]`, option.value);
            });
        }
        // Page
        if (form.elements.page) {
            params.append('page', form.elements.page.value);
        }
        // Per page
        if (form.elements.perpage) {
            params.append('perpage', form.elements.perpage.value);
        }
        // Prepare the arguments for the ajax call
        const args = {
            function: functionname,
            args: params.toString(),
        };
        // Make the ajax call
        Ajax.call([{
            methodname: `${component}_lessonbank`,
            args: args,
        }])[0].then(rawlessons => {
            var lessons = JSON.parse(rawlessons.data);
            // If items is null or false, probably an error occurred. We just show no items.
            if (!lessons) {
                lessons = {};
                // Items here is a misnomer, it really means totallessons 
                lessons.totalitems = 0;
            }

            // If there are lessons.lessonitems then check the nativelang and set showtranslate
            if (lessons.lessonitems) {
                lessons.lessonitems.forEach(lessonitem => {
                    // If the native language of the activity and the native language of the lesson are different
                    // AND the lesson has a different  target language to native language, then it can be translated
                    if (lessonitem.nativelanguage === targetlanguage) {
                        lessonitem.nativelanguage = false;
                    }
                    if (lessonitem.nativelanguage && (lessonitem.nativelanguage !== localnativelang)) {
                        lessonitem.showtranslate = true;
                    }
                });
            }


            // Set the layout flag
            lessons.islistlayot = cardsContainer.classList.contains('listlayout') ? true : false;
            // Update the count
            if (countcontainer) {
                Str.get_string('foundlessons', 'mod_minilesson', lessons.totalitems).then((langstr) => {
                    countcontainer.textContent = langstr;
                });
            }
            // Render the lessons  (again the lessonbankitems really means lessonbank lessons)
            Templates.render(`${component}/lessonbankitems`, lessons)
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
        const dirbtn = e.target.closest('[data-action="previousbtn"],[data-action="nextbtn"]');
        if (dirbtn) {
            const pageno = dirbtn.getAttribute('data-page');
            const perpage = dirbtn.getAttribute('data-perpage');
            const pagevalue = parseInt(pageno, 10) + (dirbtn.dataset.action === 'previousbtn' ? -1 : 1);
            if (form) {
                form.elements.page.value = pagevalue;
                form.elements.perpage.value = perpage;
                searchFilter(form);
            }
        }
        const downloadbtn = e.target.closest('[data-action="download"]');
        if (downloadbtn) {
            if (!downloadbtn.dataset.id) {
                return;
            }
            if (downloadbtn.classList.contains('ml_loading')) {
                return;
            }
            downloadbtn.classList.add('ml_loading');
            downloadbtn.innerHTML = '';
            downloadbtn.appendChild(getSpinner());
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
                    response.html,
                    js
                )))
            }).then(function (modal) {
                modal.hideFooter();
                modal.getRoot().on('submit ' + ModalEvents.save, function (e) {
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
    cardsContainer.addEventListener('click', e => {
        if (e.target.href) {
            return;
        }
        e.preventDefault();
        const previewbtn = e.target.closest('[data-action="preview"]');
        if (previewbtn) {

            if (!previewbtn.dataset.id && !previewbtn.dataset.viewurl) {
                return;
            }
            if (previewbtn.classList.contains('ml_loading')) {
                return;
            }
            const url = previewbtn.dataset.viewurl;
            const title = previewbtn.dataset.title;
            const originalHTML = previewbtn.innerHTML;
            previewbtn.classList.add('ml_loading');
            previewbtn.innerHTML = '';
            previewbtn.appendChild(getSpinner());

            ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                title: title,
                body: getPreviewIframe(url),
                large: true,
                removeOnClose: true
            }).then(modal => {
                modal.show();
                previewbtn.classList.remove('ml_loading');
                previewbtn.innerHTML = originalHTML;
                return modal;
            }).catch(Notification.exception);
        }
    });
    if (pagination) {
        pagination.addEventListener('change', e => {
            const perpagevalue = e.target.value;
            if (form) {
                form.elements.page.value = 1;
                form.elements.perpage.value = perpagevalue;
                searchFilter(form);
            }
        });
    }
    form?.addEventListener('submit', e => {
        e.preventDefault();
        form.querySelector('[name="page"]').value = 1;
        searchFilter(form);
    });
    if (form) {
        searchFilter(form);
    }
};