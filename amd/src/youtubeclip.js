define(['jquery','core/log'], function($,log) {
    "use strict"; // jshint ;_;


    log.debug('MiniLesson YT Clip Player: initialising');

    return{

        initVideo: function(container) {
            var that = this;
            if (typeof(YT) == 'undefined' || typeof(YT.Player) == 'undefined') {
                if (typeof(window.deferredYTClips) == 'undefined') {
                    window.deferredYTClips=[];
                }
                window.deferredYTClips.push(container);
                window.onYouTubeIframeAPIReady = function() {
                    for(var i=0;i<window.deferredYTClips.length && i>-1;i++){
                        that.loadPlayer(window.deferredYTClips[i]);
                    }
                };

                $.getScript('//www.youtube.com/iframe_api');
            } else {
                that.loadPlayer(container);
            }
        }, //end of init video

        loadPlayer: function(container) {
            var that = this;
            var theDiv = $("#" + container);
            var startSeconds = theDiv.data('start');
            var endSeconds  = theDiv.data('end');
            var thePlayer = new YT.Player(container, {
                width: theDiv.data('width'),
                height: theDiv.data('height'),
                // For a list of all parameters, see:
                // https://developers.google.com/youtube/player_parameters
                playerVars: {
                    autoplay: 0,
                    controls: 1,
                    modestbranding: 0,
                    rel: 0,
                    showinfo: 0
                },
                events: {
                    onReady: function (e) {
                        var videocue = {videoId: theDiv.data('video')};
                        if(!isNaN(startSeconds) && startSeconds > 0){
                            videocue.startSeconds = startSeconds
                        }else{
                            startSeconds = 0;
                        }
                        if(!isNaN(endSeconds) && endSeconds > 0){videocue.endSeconds = endSeconds;}
                        e.target.cueVideoById(videocue);
                    },
                    onStateChange: function (e) {
                        switch(e.data) {
                            case YT.PlayerState.ENDED:
                                thePlayer.seekTo(startSeconds);
                                thePlayer.pauseVideo();
                                break;
                            case YT.PlayerState.PAUSED:
                            case YT.PlayerState.PLAYING:
                            default:

                        }
                    },
                },
            });
        } //end of load player

    };//end of return value
});