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
        form.addEventListener('submit', function (e) {
            setTimeout(function () {
                if (e.defaultPrevented) { return; } // the teacher cancelled (e.g. the warning about regular diners)
                boton.disabled = true; boton.textContent = 'Guardando…';
            }, 0);
        });
        actualizar();
    }

    // ---- Reservar dieta desde la lista de la clase ----
    var dlg = document.getElementById('ap-cd-dieta');
    if (dlg && typeof dlg.showModal === 'function') {
        var df = dlg.querySelector('form');
        var modo = function () {
            var m = (df.querySelector('[name="modo"]:checked') || df.querySelector('[name="modo"]') || {}).value || 'dia';
            dlg.querySelector('[data-modo="dia"]').hidden = m !== 'dia';
            dlg.querySelector('[data-modo="varios"]').hidden = m !== 'varios';
        };
        document.querySelectorAll('[data-cd-dieta]').forEach(function (b) {
            b.addEventListener('click', function () {
                var li = b.closest('.ap-cd-alumno');
                var marcado = li ? li.querySelector('input[name="come[]"]').checked : true;
                var emails = b.getAttribute('data-emails') || '';
                df.reset();
                df.alumno_id.value = b.getAttribute('data-cd-dieta');
                dlg.querySelector('.ap-cd-dieta-nombre').textContent = b.getAttribute('data-nombre');
                // Not a diner of today (saved) or unmarked now without validating.
                dlg.querySelector('.is-no-come').hidden = b.getAttribute('data-come') === '1' && marcado;
                dlg.querySelector('.ap-cd-dieta-correo').textContent = 'Al reservar la dieta se avisará a la familia por correo (' + emails + '), igual que si la hubiera reservado ella, y aparecerá en su Área de Familias. También se avisa a cocina.';
                dlg.querySelector('.is-correo').hidden = !emails;
                dlg.querySelector('.is-sin-correo').hidden = !!emails;
                dlg.querySelector('.ap-rp-error').hidden = true;
                modo();
                dlg.showModal();
            });
        });
        df.addEventListener('change', modo);
        dlg.querySelector('[data-cd-dieta-cerrar]').addEventListener('click', function () { dlg.close(); });
        df.addEventListener('submit', function (e) {
            var err = '';
            if (!df.tipo_dieta.value && !df.instrucciones.value.trim()) {
                err = 'Indica qué dieta necesita (el tipo o las instrucciones).';
            } else if ((df.querySelector('[name="modo"]:checked') || {}).value === 'varios' && (!df.hasta.value || !df.querySelector('[name="dias[]"]:checked'))) {
                err = 'Indica el último día y marca al menos un día de la semana.';
            }
            var box = dlg.querySelector('.ap-rp-error');
            box.textContent = err; box.hidden = !err;
            if (err) { e.preventDefault(); return; }
            df.querySelector('[type="submit"]').disabled = true;
        });
    } else if (dlg) {
        dlg.hidden = true;
        document.querySelectorAll('[data-cd-dieta]').forEach(function (b) { b.hidden = true; });
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
        // "Marcar todos" / "Desmarcar todos".
        Array.prototype.forEach.call(document.querySelectorAll('[data-cd-fijos]'), function (b) {
            b.addEventListener('click', function () {
                var marcar = b.getAttribute('data-cd-fijos') === 'todos';
                Array.prototype.forEach.call(tabla.querySelectorAll('input[type="checkbox"]'), function (c) { c.checked = marcar; });
            });
        });
        Array.prototype.forEach.call(tabla.querySelectorAll('.ap-cd-fila'), function (b) {
            b.addEventListener('click', function () {
                toggle(Array.prototype.slice.call(b.closest('tr').querySelectorAll('input[type="checkbox"]')));
            });
        });
    }

    // ---- Sin comensales fijos: todo lo marcado es extra (se cobra) ----
    var cdForm = document.getElementById('ap-cd-form');
    if (cdForm && cdForm.getAttribute('data-sin-fijos') === '1') {
        cdForm.addEventListener('submit', function (e) {
            var n = cdForm.querySelectorAll('input[name="come[]"]:checked').length;
            if (n && !window.confirm('Esta clase no tiene comensales fijos marcados, así que ' + (n === 1 ? 'el alumno marcado contará' : 'los ' + n + ' alumnos marcados contarán') + ' como EXTRA: se avisará a Administración para cobrárselo a las familias y cada familia recibirá un correo.\n\nSi alguno se queda siempre, cancela y márcalo primero en "Comensales fijos de la clase".\n\n¿Validar igualmente?')) {
                e.preventDefault();
            }
        });
    }

    // ---- Mi comida: el texto del día cambia al marcarlo ----
    document.querySelectorAll('.ap-cp-dia input[type="checkbox"]').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var t = cb.closest('.ap-cp-dia').querySelector('.ap-cp-txt');
            if (t) { t.textContent = cb.checked ? 'No como' : 'Como'; }
        });
    });

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
