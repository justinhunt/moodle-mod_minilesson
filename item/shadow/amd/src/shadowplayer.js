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
 * YouTube player wrapper for the Video Shadowing item type.
 *
 * Wraps the YouTube IFrame API with the seek/play/pause/poll controls the
 * shadowing state machine needs. The API script is loaded once and shared
 * by all instances on the page.
 *
 * @module     minilessonitem_shadow/shadowplayer
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/log'], function($, log) {
    "use strict"; // jshint ;_;

    log.debug('MiniLesson Shadow Player: initialising');

    return {

        player: null,
        ready: false,

        //for making multiple instances
        clone: function() {
            return $.extend(true, {}, this);
        },

        /**
         * Create the player in the given container.
         *
         * @param {string} containerid id of the div to replace with the player iframe
         * @param {Object} opts {videoid, start, end}
         * @param {Object} callbacks {onReady, onPlaying, onPaused, onEnded}
         */
        init: function(containerid, opts, callbacks) {
            var self = this;
            self.opts = opts;
            self.callbacks = callbacks || {};
            this.load_api(function() {
                self.create_player(containerid);
            });
        },

        load_api: function(callback) {
            if (typeof window.YT !== 'undefined' && typeof window.YT.Player !== 'undefined') {
                callback();
                return;
            }
            if (typeof window.mlShadowYTCallbacks === 'undefined') {
                window.mlShadowYTCallbacks = [];
                var previoushandler = window.onYouTubeIframeAPIReady;
                window.onYouTubeIframeAPIReady = function() {
                    if (typeof previoushandler === 'function') {
                        previoushandler();
                    }
                    window.mlShadowYTCallbacks.forEach(function(cb) {
                        cb();
                    });
                    window.mlShadowYTCallbacks = [];
                };
                window.mlShadowYTCallbacks.push(callback);
                $.getScript('https://www.youtube.com/iframe_api');
            } else {
                window.mlShadowYTCallbacks.push(callback);
            }
        },

        create_player: function(containerid) {
            var self = this;
            self.player = new window.YT.Player(containerid, {
                playerVars: {
                    autoplay: 0,
                    controls: 1,
                    rel: 0,
                    playsinline: 1,
                    modestbranding: 1
                },
                events: {
                    onReady: function(e) {
                        var videocue = {videoId: self.opts.videoid};
                        if (self.opts.start > 0) {
                            videocue.startSeconds = self.opts.start;
                        }
                        if (self.opts.end > 0) {
                            videocue.endSeconds = self.opts.end;
                        }
                        e.target.cueVideoById(videocue);
                        self.ready = true;
                        if (self.callbacks.onReady) {
                            self.callbacks.onReady();
                        }
                    },
                    onStateChange: function(e) {
                        switch (e.data) {
                            case window.YT.PlayerState.PLAYING:
                                if (self.callbacks.onPlaying) {
                                    self.callbacks.onPlaying();
                                }
                                break;
                            case window.YT.PlayerState.PAUSED:
                                if (self.callbacks.onPaused) {
                                    self.callbacks.onPaused();
                                }
                                break;
                            case window.YT.PlayerState.ENDED:
                                if (self.callbacks.onEnded) {
                                    self.callbacks.onEnded();
                                }
                                break;
                            default:
                        }
                    }
                }
            });
        },

        seek_to: function(seconds) {
            if (this.ready) {
                this.player.seekTo(seconds, true);
            }
        },

        play: function() {
            if (this.ready) {
                this.player.playVideo();
            }
        },

        pause: function() {
            if (this.ready) {
                this.player.pauseVideo();
            }
        },

        get_current_time: function() {
            if (this.ready && typeof this.player.getCurrentTime === 'function') {
                return this.player.getCurrentTime();
            }
            return 0;
        },

        is_playing: function() {
            return this.ready && typeof this.player.getPlayerState === 'function' &&
                this.player.getPlayerState() === window.YT.PlayerState.PLAYING;
        }

    }; //end of return value
});
