define([], function() {
    window.requirejs.config({
        paths: {
            'datatables.net': '//cdn.datatables.net/1.13.6/js/jquery.dataTables.min',
            'datatables.net-buttons': '//cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min',
            'datatables.net-buttons-html5': '//cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min',
            'datatables.net-buttons-colVis': '//cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min',
            'datatables.net-buttons-print': '//cdn.datatables.net/buttons/2.4.1/js/buttons.print.min',
        },
        shim: {
            'datatables.net': {exports: 'datatables.net'},
            'datatables.net-buttons': {exports: 'datatables.net-buttons'},
            'datatables.net-buttons-html5': {exports: 'datatables.net-buttons-html5'},
            'datatables.net-buttons-colVis': {exports: 'datatables.net-buttons-colVis'},
            'datatables.net-buttons-print': {exports: 'datatables.net-buttons-print'},
        }
    });
});