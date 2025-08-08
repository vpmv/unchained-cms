let ietfLocales = {
    az: 'az-AZ',
    bs: 'bs-BA',
    zh: 'zh-HA',
    nl: 'nl-NL',
    en: 'en-GB',
    fr: 'fr-FR',
    de: 'de-DE',
    it: 'it-IT',
    no: 'no-NO',
    pt: 'pt-PT',
    sr: 'sr-SP',
    es: 'es-ES',
    sv: 'sv-SE',
    uz: 'uz-CR',
};

let locale = document.documentElement.lang;
if (ietfLocales[locale] !== undefined) {
    locale = ietfLocales[locale];
}


$(function () {
    let tableInfo = function (settings, start, end, max, total, pre) {
        let $table = $(settings.nTable).closest('.dt-container');
        $('.dt-info', $table).text(pre);
        if (start === 1 && end >= max) {
            $('.pagination', $table).closest('.row').remove();
            $('.dt-length', $table).remove();
        }
    };

    let table = $('.table.dashboard').DataTable(
        {
            order:        [],
            responsive:   true,
            infoCallback: tableInfo,
            bAutoWidth:   false,
            language:     {
                url: `https://cdn.datatables.net/plug-ins/2.3.2/i18n/${locale}.json`
            }
        }
    );

    // collapse child
    table.on('responsive-display', function (e, datatable, row) {
        let child = row.child();
        $('.dtr-data:empty', child).each(function () {
            $(this).closest('li').remove();
        });

        if ($('li', child).length === 0) {
            child.remove();
            $(row.node()).addClass('no-child').find('td:first-child').off('click.dtr mousedown.dtr mouseup.dtr'); // fixme: not working
        }
    });

    screen.orientation.addEventListener("change", (event) => {
        table.draw();
        table.columns().draw();
    });

    if (window.location.search) {
        queryPart = window.location.search.substring(window.location.search.indexOf('?') + 1);
        query     = new URLSearchParams(queryPart);

        let reference = query.get('reference');
        if (reference) {
            table.search(decodeURI(reference));
        }
    }
});