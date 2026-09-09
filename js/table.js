(function() {
    if (!/problem\.form\.php|change\.form\.php/.test(window.location.pathname)) {
        return;
    }

    function moverTablaFechas() {
        var $tabla = $('table.tab_cadre_fixe th:contains("Dates")').closest('table');
        if ($tabla.length && !$tabla.closest('.dates_timelines').length) {
            $tabla.wrap('<div class="dates_timelines"></div>');
        }
    }

    var observer = new MutationObserver(function() {
        moverTablaFechas();
    });
    observer.observe(document.body, { childList: true, subtree: true });

    moverTablaFechas();
})();