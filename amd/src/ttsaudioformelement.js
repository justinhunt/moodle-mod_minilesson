import Log from "core/log";
import Config from "core/config";
import Fragment from "core/fragment";
import Templates from "core/templates";

const regionSelector = regionname => `[data-region="${regionname}"]`;

export const registerElement = ({component, fragmentcallback, elementid}) => {
    const element = document.getElementById(elementid);
    if (element) {
        Log.debug(element);
        element.addEventListener('change', () => {
            const rootelement = element.closest('[data-root="elementwrapper"]');
            const loaderElement = rootelement.querySelector(regionSelector('overlay-icon-container'));
            const params = {...element.dataset};
            const formdata = new FormData;
            params.options = JSON.stringify({...rootelement.dataset});
            rootelement.querySelectorAll('select').forEach(selectel => {
                if (selectel instanceof HTMLSelectElement) {
                    [...selectel.selectedOptions].forEach(option => {
                        formdata.append(selectel.getAttribute('name'), option.value);
                    });
                }
            });
            params.formdata = new URLSearchParams([...formdata.entries()]).toString();
            if (loaderElement) {
                loaderElement.classList.remove('hidden');
            }
            Fragment.loadFragment(component, fragmentcallback, Config.contextid, params)
            .then((html, js) => {
                Templates.replaceNode(rootelement, html, js);
            });
        });
    }
};