/* Área Profesores: comedor diario (pestaña Comedor) y página de cocina en directo. */
(function () {
    'use strict';

    // ---- Pestaña "Comedor" del profesor ----
    var form = document.getElementById('ap-cd-form');
    if (form) {
        var items = Array.prototype.slice.call(form.querySelectorAll('.ap-cd-alumno'));
        var boton = form.querySelector('.ap-cd-validar');
        var resumen = form.querySelector('.ap-cd-resumen');
        var actualizar = function () {
            var n = { total: 0, fijo: 0, extra: 0, familia: 0, no: 0 };
            items.forEach(function (li) {
                var come = li.querySelector('input').checked;
                var fijo = li.getAttribute('data-fijo') === '1';
                var fam = li.getAttribute('data-familia') === '1';
                li.classList.toggle('is-come', come);
                li.classList.toggle('is-extra', come && !fijo && !fam);
                li.classList.toggle('is-no', !come && fijo);
                if (come) {
                    n.total++;
                    if (fijo) { n.fijo++; } else if (fam) { n.familia++; } else { n.extra++; }
                } else if (fijo) {
                    n.no++;
                }
            });
            var partes = [n.fijo + ' fijos'];
            if (n.extra) { partes.push(n.extra + ' extra'); }
            if (n.familia) { partes.push(n.familia + ' por reserva de la familia'); }
            if (n.no) { partes.push(n.no + (n.no === 1 ? ' fijo no se queda' : ' fijos no se quedan')); }
            resumen.textContent = 'Comen hoy: ' + n.total + ' (' + partes.join(', ') + ')';
            boton.textContent = 'Validar el comedor (' + n.total + (n.total === 1 ? ' comensal)' : ' comensales)');
        };
        form.addEventListener('change', actualizar);
        Array.prototype.forEach.call(form.querySelectorAll('[data-cd]'), function (b) {
            b.addEventListener('click', function () {
                var modo = b.getAttribute('data-cd');
                items.forEach(function (li) {
                    li.querySelector('input').checked = modo === 'fijos' ? (li.getAttribute('data-fijo') === '1' || li.getAttribute('data-familia') === '1') : false;
                });
                actualizar();
            });
        });
        form.addEventListener('submit', function () {
            setTimeout(function () { boton.disabled = true; boton.textContent = 'Guardando…'; }, 0);
        });
        actualizar();
    }

    // Change of class ("Poner el comedor de un compañero").
    var sel = document.querySelector('.ap-cd-select');
    if (sel) {
        sel.addEventListener('change', function () {
            if (sel.value) {
                sel.form.action = sel.form.getAttribute('action') + '#comedor';
                sel.form.submit();
            }
        });
    }

    // Fixed diners: whole column / whole row.
    var tabla = document.querySelector('.ap-cd-fijos-tabla');
    if (tabla) {
        var toggle = function (boxes) {
            var all = boxes.every(function (c) { return c.checked; });
            boxes.forEach(function (c) { c.checked = !all; });
        };
        Array.prototype.forEach.call(tabla.querySelectorAll('.ap-cd-col'), function (b) {
            b.addEventListener('click', function () {
                toggle(Array.prototype.slice.call(tabla.querySelectorAll('input[data-dia="' + b.getAttribute('data-dia') + '"]')));
            });
        });
        Array.prototype.forEach.call(tabla.querySelectorAll('.ap-cd-fila'), function (b) {
            b.addEventListener('click', function () {
                toggle(Array.prototype.slice.call(b.closest('tr').querySelectorAll('input[type="checkbox"]')));
            });
        });
    }

    // ---- Página de cocina: se recarga sola cuando cambian los datos ----
    var cocina = document.querySelector('[data-ap-cocina-vivo]');
    if (cocina && window.fetch) {
        var url = cocina.getAttribute('data-ap-cocina-vivo');
        var firma = null;
        var comprobar = function () {
            fetch(url, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
                if (!j || !j.success) { return; }
                if (firma !== null && j.data.firma !== firma) { window.location.reload(); }
                firma = j.data.firma;
            }).catch(function () {});
        };
        comprobar();
        setInterval(comprobar, 60000);
    }
})();
