/* =====================================================================
   SIGI - assets/js/app.js
   Comportamiento general de la interfaz.
   ===================================================================== */

$(document).ready(function () {

    // Confirmacion para acciones destructivas.
    // Se activa con data-confirm="mensaje" en el elemento.
    $('[data-confirm]').on('click', function (e) {
        if (!confirm($(this).data('confirm'))) {
            e.preventDefault();
        }
    });

    // Marca visualmente la fila de la tabla al hacer clic.
    $('.table-clickable tbody tr').on('click', function () {
        var href = $(this).data('href');
        if (href) {
            window.location = href;
        }
    });

    // Filtro rapido en tablas de listado.
    $('#tableFilter').on('keyup', function () {
        var needle = $(this).val().toLowerCase();
        $('table.filterable tbody tr').each(function () {
            var row = $(this).text().toLowerCase();
            $(this).toggle(row.indexOf(needle) !== -1);
        });
    });

    // Evita el doble envio de formularios de alta/edicion.
    $('form[data-once]').on('submit', function () {
        $(this).find('button[type=submit]').prop('disabled', true).addClass('disabled');
    });

    // Menu lateral en pantallas pequenas.
    $('#sidebarToggle').on('click', function () {
        $('#sidebar').toggleClass('show');
    });

    // Alerta Hide de Bootstrap 5.
    $('[data-bs-toggle="alert"]').on('click', function (e) {
        e.preventDefault();
        $(this).closest('.alert').fadeOut(200);
    });
});
