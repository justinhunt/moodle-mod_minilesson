
define(['jquery', 'core/log','https://player.vimeo.com/api/player.js'], function($, log, Vimeo) {
    "use strict";

    //user statements like this to send log output to console
    log.debug('MiniLesson VIMEO Clip Player: initialising');

    return {
        initVideo: function(container) {
            var that = this;
            that.loadPlayer(container);
        },

        loadPlayer: function(container) {
            var that = this;
            var theDiv = $("#" + container);
            var startSeconds = theDiv.data('start');
            var endSeconds = theDiv.data('end');

            var options = {
                id: theDiv.data('video'),
                width: theDiv.data('width'),
                height: theDiv.data('height'),
                autoplay: false,
                loop: false,
                byline: false,
                title: false,
                portrait: false,
                api: true,
                player_id: container
            };

            log.debug('Creating XXXX Vimeo player with options:', options);
            var thePlayer = new Vimeo(container, options);

            thePlayer.ready().then(function() {
                log.debug('Vimeo player is ready');
                thePlayer.setVolume(0);
                if (!isNaN(startSeconds) && startSeconds > 0) {
                    thePlayer.setCurrentTime(startSeconds);
                }

                thePlayer.on('timeupdate', function(data) {
                    if (endSeconds && data.seconds >= endSeconds) {
                        thePlayer.setCurrentTime(startSeconds).then(function () {
                            thePlayer.pause();
                        }).catch(function(error) {
                            log.error('Error rewinding the video', error);
                        });
                    }
                });
            }).catch(function(error) {
                log.error('Error initializing Vimeo player:', error);
            });
        }
    };
});
