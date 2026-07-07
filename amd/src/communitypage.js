define(['jquery', 'core/log', 'core/ajax', 'core/templates'],
    function ($, log, Ajax, templates) {
        "use strict"; // jshint ;_;

        /*
        This file manages a community page instance: fetching the shared
        submissions for an item and rendering them into a container, plus the
        sort/like/consent interactions. It is used from the free speaking item
        (between the results page and the next item) and from the quiz
        finished page.
         */

        log.debug('MiniLesson community page: initialising');

        return {

            sort: 'date',

            //for making multiple instances
            clone: function () {
                return $.extend(true, {}, this);
            },

            /**
             * @param {jQuery|string} container where to render the community page
             * @param {number} itemid the lesson item id
             * @param {Object} opts optional: localcanshare (bool) force-show the
             *   consent toggle (used in-quiz where the step may not be saved
             *   server side yet), onunavailable (function) called when the
             *   community page cannot be shown, showmore (bool) show a "more"
             *   link that expands the shortened transcript to the full one,
             *   localentry (function) returns the viewer's own not-yet-saved
             *   submission as an entry object (or null); it is re-evaluated on
             *   every render and shown, pinned first, while consent is on
             */
            init: function (container, itemid, opts) {
                this.container = $(container);
                this.itemid = itemid;
                this.opts = opts || {};
                this.register_events();
            },

            register_events: function () {
                var self = this;
                // The viewer's own share-this toggle.
                self.container.on('change', '.ml_cpage_myconsent', function (e) {
                    Ajax.call([{
                        methodname: 'mod_minilesson_set_cpage_consent',
                        args: {itemid: self.itemid, consent: e.target.checked}
                    }])[0].then(function () {
                        return self.load(self.sort);
                    }).catch(function (err) {
                        log.debug(err);
                    });
                });
                // Sorting.
                self.container.on('change', '.ml_cpage_sortselect', function (e) {
                    self.load(e.target.value);
                });
                // Expand a shortened transcript to the full one.
                self.container.on('click', '.ml_cpage_transcript_morelink', function (e) {
                    e.preventDefault();
                    var wrapper = $(e.currentTarget).closest('.ml_cpage_entry_transcript');
                    wrapper.find('.ml_cpage_entry_transcript_short').hide();
                    wrapper.find('.ml_cpage_entry_transcript_full').show();
                    $(e.currentTarget).hide();
                });
                // Likes.
                self.container.on('click', '.ml_cpage_likebtn', function (e) {
                    var btn = $(e.currentTarget);
                    var submissionid = btn.closest('.ml_cpage_entry').data('submissionid');
                    Ajax.call([{
                        methodname: 'mod_minilesson_toggle_cpage_like',
                        args: {submissionid: submissionid}
                    }])[0].then(function (response) {
                        if (response.success) {
                            btn.toggleClass('active', response.liked);
                            btn.find('.ml_cpage_likecount').text(response.likes);
                        }
                        return response;
                    }).catch(function (err) {
                        log.debug(err);
                    });
                });
            },

            load: function (sort) {
                var self = this;
                self.sort = sort || self.sort;
                return Ajax.call([{
                    methodname: 'mod_minilesson_get_cpage_submissions',
                    args: {itemid: self.itemid, sort: self.sort}
                }])[0].then(function (response) {
                    if (!response.success) {
                        //the feature is off - nothing to show
                        if (self.opts.onunavailable) {
                            self.opts.onunavailable();
                        }
                        return response;
                    }
                    var entries = response.entries;
                    // The viewer's own fresh submission, from browser data,
                    // shown while their step is not yet saved server side.
                    if (typeof self.opts.localentry === 'function' && response.myconsent) {
                        var localentry = self.opts.localentry();
                        if (localentry) {
                            // It supersedes any own entry from a previous attempt.
                            entries = entries.filter(function (entry) {
                                return !entry.isowner;
                            });
                            localentry.isowner = true;
                            // Pin it at the top regardless of sort order.
                            entries = [localentry].concat(entries);
                        }
                    }
                    var templatedata = {
                        itemid: self.itemid,
                        likesenabled: response.likesenabled,
                        entries: entries,
                        hasentries: entries.length > 0,
                        sortdate: self.sort !== 'likes',
                        sortlikes: self.sort === 'likes',
                        showconsent: response.canshare || self.opts.localcanshare === true,
                        myconsent: response.myconsent,
                        //the server says whether transcripts are expandable (written item types);
                        //opts.showmore remains as a per-instance override
                        showmore: response.canexpand === true || self.opts.showmore === true
                    };
                    return templates.render('mod_minilesson/communitypage', templatedata).then(
                        function (html, js) {
                            self.container.html(html);
                            self.container.show();
                            templates.runTemplateJS(js);
                        }
                    );
                }).catch(function (err) {
                    log.debug(err);
                    if (self.opts.onunavailable) {
                        self.opts.onunavailable();
                    }
                });
            },
        };
    }
);
