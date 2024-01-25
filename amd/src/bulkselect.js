define([], function () {

    var table = document.querySelector('table#mod_minilesson_qpanel');

    table.addEventListener('change', function () {
        var btn = document.querySelector('#mod_minilesson_deleteconfirmation');
        if (table.querySelectorAll(':checked').length > 0) {
            btn.classList.remove('d-none');
        } else {
            btn.classList.add('d-none');
        }
    });
});