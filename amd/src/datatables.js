define(['jquery', 'core/str', 'core/log', 'core/modal_factory', 'core/modal_events', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js'],
    function($, str, log, ModalFactory, ModalEvents) {
    "use strict"; // jshint ;_;

/*
This file contains class and ID definitions.
 */


    log.debug('MiniLesson Teacher Datatables helper: initialising');

    return{
        //pass in config, amd set up table
        init: function(props){
            //pick up opts from html
            var thetable=$('#' + props.tableid);
            this.dt=thetable.DataTable(props.tableprops);
            this.setup_bulkdelete();
        },

        getDataTable: function(tableid){
            return $('#' + tableid).DataTable();
        },

        setup_bulkdelete: function(){
            //get the bulk delete button
            var bulkdelete_btn = $('#mod_minilesson_deleteconfirmation');

            //handle the click event
            bulkdelete_btn.on('click', function(e) {
                var btn = bulkdelete_btn[0];

                ModalFactory.create({
                    type: ModalFactory.types.SAVE_CANCEL,
                    title: str.get_string('confirmation', 'admin'),
                    body: str.get_string('bulkdeletequestion', 'mod_minilesson'),
                    removeOnClose: true
                }).then(function(modal) {
                    modal.setSaveButtonText(str.get_string('yes', 'moodle'));

                    modal.getRoot().on(ModalEvents.save, function() {
                        var form = btn.closest('form');
                        var input = document.createElement('input');
                        input.setAttribute('type', 'hidden');
                        input.setAttribute('name', btn.getAttribute('name'));
                        input.setAttribute('value', btn.getAttribute('value'));
                        form.appendChild(input);
                        if (typeof M.core_formchangechecker !== 'undefined') {
                            M.core_formchangechecker.set_form_submitted();
                        }
                        form.submit();
                    });

                    modal.show();

                    return modal;
                });
            });
        }

    };//end of return value
});