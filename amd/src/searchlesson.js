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
 * Lesson bank search with infinite scroll.
 *
 * @module     mod_minilesson/searchlesson
 * @copyright  2025 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Ajax from 'core/ajax';
import * as Notification from 'core/notification';
import Templates from 'core/templates';
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import * as Str from 'core/str';
import Fragment from 'core/fragment';


const component = 'mod_minilesson';
const PERPAGE = 10;
const SKELETON_COUNT = 2;

export const registerFilter = (opts) => {
    const localnativelang = opts.nativelang;
    const itemtypeiconmap = opts.itemtypeiconmap || {};
    const form = document.querySelector('#lessonbank_filters');
    const cardsContainer = document.querySelector('[data-region="cards-container"]');
    const gridlayoutbtn = document.querySelector('.gridlayoutbtn');
    const listlayoutbtn = document.querySelector('.listlayoutbtn');
    const countcontainer = document.querySelector('.countcontainer');

    let currentPage = 1;
    let isLoading = false;
    let hasMore = true;
    let observer = null;

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

    const getSentinel = () => {
        let sentinel = cardsContainer.querySelector('.lbsf-sentinel');
        if (!sentinel) {
            sentinel = document.createElement('div');
            sentinel.className = 'lbsf-sentinel';
            cardsContainer.appendChild(sentinel);
        }
        return sentinel;
    };

    const setupObserver = () => {
        if (observer) {
            observer.disconnect();
        }
        observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !isLoading && hasMore) {
                loadMoreCards();
            }
        }, {rootMargin: '200px'});
        observer.observe(getSentinel());
    };

    const insertSkeletons = () => {
        const skeletonTemplate = isListLayout()
            ? `${component}/lessonbank_skeleton_row`
            : `${component}/lessonbank_skeleton`;
        const promises = [];
        for (let i = 0; i < SKELETON_COUNT; i++) {
            promises.push(Templates.render(skeletonTemplate, {}));
        }
        if (isListLayout()) {
            const tbody = cardsContainer.querySelector('tbody');
            if (tbody) {
                return Promise.all(promises).then(htmlArray => {
                    tbody.insertAdjacentHTML('beforeend', htmlArray.join(''));
                });
            }
            return Promise.resolve();
        } else {
            const sentinel = getSentinel();
            return Promise.all(promises).then(htmlArray => {
                sentinel.insertAdjacentHTML('beforebegin', htmlArray.join(''));
            });
        }
    };

    const removeSkeletons = () => {
        cardsContainer.querySelectorAll('.lbsf-skeleton-card').forEach(el => el.remove());
    };

    const buildParams = () => {
        const params = new URLSearchParams();
        var targetlanguage = '';
        if (form.elements['searchgroup[language]']) {
            targetlanguage = form.elements['searchgroup[language]'].value;
            params.append('language', targetlanguage);
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
        if (form.elements['skill[]']) {
            const selectedOptions = form.elements['skill[]'].selectedOptions;
            Array.from(selectedOptions).forEach((option, index) => {
                params.append(`skill[${index}]`, option.value);
            });
        }
        if (form.elements['topic[]']) {
            const selectedOptions = form.elements['topic[]'].selectedOptions;
            Array.from(selectedOptions).forEach((option, index) => {
                params.append(`topic[${index}]`, option.value);
            });
        }
        if (form.elements['itemtype[]']) {
            const selectedOptions = form.elements['itemtype[]'].selectedOptions;
            Array.from(selectedOptions).forEach((option, index) => {
                params.append(`itemtypes[${index}]`, option.value);
            });
        }
        return {params, targetlanguage};
    };

    const enrichLessons = (lessons, targetlanguage) => {
        if (lessons.lessonitems) {
            lessons.lessonitems.forEach(lessonitem => {
                if (lessonitem.nativelanguage === targetlanguage) {
                    lessonitem.nativelanguage = false;
                }
                if (lessonitem.nativelanguage && (lessonitem.nativelanguage !== localnativelang)) {
                    lessonitem.showtranslate = true;
                }
                if (lessonitem.itemtypes) {
                    lessonitem.itemtypes.forEach(it => {
                        it.iconurl = itemtypeiconmap[it.text] || '';
                    });
                }
            });
        }
    };

    const isListLayout = () => cardsContainer.classList.contains('listlayout');

    const fetchPage = (page) => {
        const {params, targetlanguage} = buildParams();
        params.append('page', page);
        params.append('perpage', PERPAGE);

        const args = {
            function: 'local_lessonbank_list_minilessons',
            args: params.toString(),
        };

        return Ajax.call([{
            methodname: `${component}_lessonbank`,
            args: args,
        }])[0].then(rawlessons => {
            var lessons = JSON.parse(rawlessons.data);
            if (!lessons) {
                lessons = {};
                lessons.totalitems = 0;
            }
            enrichLessons(lessons, targetlanguage);
            return lessons;
        });
    };

    const renderRows = (lessons) => {
        const promises = (lessons.lessonitems || []).map(item => {
            return Templates.render(`${component}/lessonbank_listrow`, item);
        });
        return Promise.all(promises).then(htmlArray => htmlArray.join(''));
    };

    const renderCards = (lessons) => {
        const promises = (lessons.lessonitems || []).map(item => {
            return Templates.render(`${component}/lessonbankitem`, item);
        });
        return Promise.all(promises).then(htmlArray => htmlArray.join(''));
    };

    const buildContainerStructure = () => {
        cardsContainer.innerHTML = '';
        if (isListLayout()) {
            // Create table with thead once; tbody receives rows incrementally
            const table = document.createElement('table');
            table.className = 'table table-bordered table-striped listtable';
            // Thead will be rendered via Str, but we build it with known columns
            table.innerHTML =
                '<thead><tr>' +
                '<th></th><th></th><th class="langcolumn"></th>' +
                '<th class="levelcolumn"></th><th class="keywordcolumn"></th>' +
                '<th class="listdescription"></th><th></th><th></th>' +
                '</tr></thead><tbody></tbody>';
            cardsContainer.appendChild(table);
            // Fill header labels
            const ths = table.querySelectorAll('thead th');
            const headerKeys = [
                null, 'title', 'targetlang', 'level', 'keyword', null, 'items', null
            ];
            headerKeys.forEach((key, i) => {
                if (key) {
                    Str.get_string(key, 'mod_minilesson').then(s => { ths[i].textContent = s; });
                }
            });
            Str.get_string('description').then(s => { ths[5].textContent = s; });
            Str.get_string('actions').then(s => { ths[7].textContent = s; });
        } else {
            const wrapper = document.createElement('div');
            wrapper.className = 'searchbox row no-gutters justify-content-between';
            cardsContainer.appendChild(wrapper);
            // Sentinel inside the wrapper so cards stay within .searchbox.row
            const sentinel = document.createElement('div');
            sentinel.className = 'lbsf-sentinel';
            wrapper.appendChild(sentinel);
            return;
        }
        // Sentinel outside the table for list layout
        const sentinel = document.createElement('div');
        sentinel.className = 'lbsf-sentinel';
        cardsContainer.appendChild(sentinel);
    };

    const insertContent = (html) => {
        if (isListLayout()) {
            const tbody = cardsContainer.querySelector('tbody');
            if (tbody) {
                tbody.insertAdjacentHTML('beforeend', html);
            }
        } else {
            const sentinel = cardsContainer.querySelector('.lbsf-sentinel');
            sentinel.insertAdjacentHTML('beforebegin', html);
        }
    };

    const searchFilter = () => {
        currentPage = 1;
        hasMore = true;
        isLoading = true;

        buildContainerStructure();
        const skeletonsReady = insertSkeletons();

        Promise.all([skeletonsReady, fetchPage(currentPage)]).then(([, lessons]) => {
            if (countcontainer) {
                Str.get_string('foundlessons', 'mod_minilesson', lessons.totalitems).then((langstr) => {
                    countcontainer.textContent = langstr;
                });
            }

            hasMore = !!lessons.hasnextbutton;
            removeSkeletons();

            if (!lessons.lessonitems || lessons.lessonitems.length === 0) {
                const noItems = document.createElement('p');
                noItems.className = 'bg-secondary p-3 text-center w-100';
                noItems.innerHTML = '<span></span>';
                Str.get_string('nolessonitemfound', 'mod_minilesson').then((langstr) => {
                    noItems.querySelector('span').textContent = langstr;
                });
                const sentinel = cardsContainer.querySelector('.lbsf-sentinel');
                sentinel.insertAdjacentElement('beforebegin', noItems);
                isLoading = false;
                return;
            }

            const render = isListLayout() ? renderRows(lessons) : renderCards(lessons);
            return render.then(html => {
                insertContent(html);
                isLoading = false;
                setupObserver();

                document.querySelector('#region-main')?.scrollIntoView({
                    behavior: "smooth",
                    block: "start"
                });
            });
        }).catch(err => {
            isLoading = false;
            Notification.exception(err);
        });
    };

    const loadMoreCards = () => {
        if (isLoading || !hasMore) {
            return;
        }
        isLoading = true;
        currentPage++;

        const skeletonsReady = insertSkeletons();

        Promise.all([skeletonsReady, fetchPage(currentPage)]).then(([, lessons]) => {
            hasMore = !!lessons.hasnextbutton;
            removeSkeletons();

            if (!lessons.lessonitems || lessons.lessonitems.length === 0) {
                isLoading = false;
                if (observer) {
                    observer.disconnect();
                }
                return;
            }

            const render = isListLayout() ? renderRows(lessons) : renderCards(lessons);
            return render.then(html => {
                insertContent(html);
                isLoading = false;

                if (!hasMore && observer) {
                    observer.disconnect();
                }
            });
        }).catch(err => {
            isLoading = false;
            Notification.exception(err);
        });
    };

    gridlayoutbtn?.addEventListener('click', e => {
        e.preventDefault();
        cardsContainer.classList.remove('listlayout');
        gridlayoutbtn.classList.add('active');
        listlayoutbtn.classList.remove('active');
        searchFilter();
    });

    listlayoutbtn?.addEventListener('click', e => {
        e.preventDefault();
        cardsContainer.classList.add('listlayout');
        listlayoutbtn.classList.add('active');
        gridlayoutbtn.classList.remove('active');
        searchFilter();
    });

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
            Modal.create({
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

            Modal.create({
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
    form?.addEventListener('submit', e => {
        e.preventDefault();
        searchFilter();
    });
    if (form) {
        searchFilter();
    }
};
