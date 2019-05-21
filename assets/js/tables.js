$(function() {
    let tableInfo = function(settings, start, end, max, total, pre) {
        let $table = $(settings.nTable).closest('.dataTables_wrapper');
        $('.dataTables_info', $table).text(pre);
        if (start === 1 && end >= max) {
            $('.pagination', $table).closest('.row').remove();
            $('.dataTables_length', $table).remove();
        }
    };

    let table = $('.table.dashboard').DataTable({
            order:        [],
            responsive:   true,
            infoCallback: tableInfo,
            bAutoWidth:   false,
        }
    );

    table
        .on('responsive-display', function(e, datatable, row) {
            let child = row.child();
            $('.dtr-data:empty', child).each(function() {
                $(this).closest('li').remove();
            });

            if ($('li', child).length === 0) {
                child.remove();
                $(row.node()).addClass('no-child').find('td:first-child').off('click.dtr mousedown.dtr mouseup.dtr'); // fixme: not working
            }
        });

    $(window).on('resize', function() {
        table.draw();
        table.columns().draw();
    });

    if (window.location.hash) {
        search = decodeURI(window.location.hash.substr(1));
        table.search(search).draw();
    }
});