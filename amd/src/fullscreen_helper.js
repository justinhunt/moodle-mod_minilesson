define(['jquery'], function ($) {
    return {
        init: function (containerId, btnId) {
            const container = document.getElementById(containerId);
            const btn = document.getElementById(btnId);

            if (!container || !btn) return;

            const updateButtonUI = () => {
                const isFS = !!document.fullscreenElement || !!document.webkitFullscreenElement;
                btn.classList.toggle('is-fullscreen', isFS);
                // Update icon and text for clarity
                btn.innerHTML = isFS
                    ? '<i class="fa fa-compress"></i>'
                    : '<i class="fa fa-expand"></i>';
            };

            const toggleFullscreen = () => {
                if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                    if (container.requestFullscreen) {
                        container.requestFullscreen();
                    } else if (container.webkitRequestFullscreen) { /* Safari/iOS */
                        container.webkitRequestFullscreen();
                    }
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    } else if (document.webkitExitFullscreen) {
                        document.webkitExitFullscreen();
                    }
                }
            };

            btn.addEventListener('click', (e) => {
                e.preventDefault();
                toggleFullscreen();
            });

            // Handle browser-level changes (Esc key, orientation change)
            document.addEventListener('fullscreenchange', updateButtonUI);
            document.addEventListener('webkitfullscreenchange', updateButtonUI);
        }
    };
});
