define(['jquery', 'core/str', 'core/log', 'core/modal_factory', 'core/modal_events', './datatablesLoader',
    'datatables.net', 'datatables.net-buttons'],
    function($, str, log, ModalFactory, ModalEvents) {
    "use strict"; // jshint ;_;

/*
This file contains class and ID definitions.
 */
    $.fn.dataTable.ext.buttons.bulkdelete = {
        text: M.str.mod_minilesson.bulkdelete,
        className: 'btn btn-primary d-none',
        attr: {
            id: 'deleteconfirmation',
            type:  'submit',
            name: 'action',
            value: 'bulkdelete'
        },
        action: function(e, dt, node) {
            var btn = node.closest('button')[0];

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
        }
    };

    log.debug('MiniLesson Teacher Datatables helper: initialising');

    return{
        //pass in config, amd set up table
        init: function(props){
            //pick up opts from html
            var thetable=$('#' + props.tableid);
            this.dt=thetable.DataTable(props.tableprops);
        },

        getDataTable: function(tableid){
            return $('#' + tableid).DataTable();
        }


    };//end of return value
});