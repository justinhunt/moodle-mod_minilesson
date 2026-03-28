define(['jquery'], function ($) {
    return {
        init: function (containerId, btnId) {
            const container = document.getElementById(containerId);
            const btn = document.getElementById(btnId);

            if (!container || !btn) return;

            // Check for native support
            const supportsNative = !!(container.requestFullscreen || container.webkitRequestFullscreen);

            const updateButtonUI = () => {
                const isFsNative = !!document.fullscreenElement || !!document.webkitFullscreenElement;
                const isFsPseudo = container.classList.contains('is-pseudo-fullscreen');
                const isFS = isFsNative || isFsPseudo;

                btn.classList.toggle('is-fullscreen', isFS);
                // Update icon and text for clarity
                btn.innerHTML = isFS
                    ? '<i class="fa fa-compress"></i>'
                    : '<i class="fa fa-expand"></i>';
                    
                // Dispatch event so layout handlers can run after dom repaints
                setTimeout(() => {
                    container.dispatchEvent(new CustomEvent('minilesson:fullscreenchange', { detail: { isFullscreen: isFS } }));
                    window.dispatchEvent(new Event('resize'));
                }, 100);
            };

            const toggleFullscreen = () => {
                if (supportsNative) {
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
                } else {
                    // Pseudo-fullscreen fallback for iOS Safari
                    container.classList.toggle('is-pseudo-fullscreen');
                    updateButtonUI();
                }
            };

            btn.addEventListener('click', (e) => {
                e.preventDefault();
                toggleFullscreen();
            });

            btn.addEventListener('keydown', (e) => {
                if (e.key === ' ') {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleFullscreen();
                }
            });

            // Handle browser-level changes (Esc key, orientation change)
            document.addEventListener('fullscreenchange', updateButtonUI);
            document.addEventListener('webkitfullscreenchange', updateButtonUI);
        }
    };
});
