/* Área Profesores: Portería books a service for a family ("Reservas" tab). */
(function () {
    var form = document.getElementById('ap-rp-form');
    if (!form) { return; }
    var fams = JSON.parse(form.getAttribute('data-familias') || '[]');
    var servs = JSON.parse(form.getAttribute('data-servicios') || '{}');
    var old = JSON.parse(form.getAttribute('data-old') || '[]') || {};
    var T = JSON.parse(form.getAttribute('data-textos') || '{}');
    var q = form.querySelector('.ap-rp-q'), res = form.querySelector('.ap-rp-resultados');
    var boxFam = form.querySelector('.ap-rp-familia'), hijosEl = form.querySelector('.ap-rp-hijos');
    var norm = function (t) { return (t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
    var esc = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
    var famActual = null;

    // Pupils (one entry per pupil and family).
    var items = [];
    fams.forEach(function (f) {
        f.hijos.forEach(function (h) {
            items.push({ f: f, h: h, txt: norm(h.nombre + ' ' + f.nombre + ' ' + f.emails.join(' ')) });
        });
    });

    var elegir = function (f, marcados) {
        famActual = f;
        form.familia_id.value = f.id;
        form.querySelector('.ap-rp-familia-nombre').textContent = T.t0 + ' ' + f.nombre;
        hijosEl.innerHTML = f.hijos.map(function (h) {
            return '<label class="ap-rp-hijo" data-etapa="' + esc(h.etapa) + '"><input type="checkbox" name="alumnos[]" value="' + h.id + '"' + (marcados.indexOf(String(h.id)) > -1 ? ' checked' : '') + '> ' + esc(h.nombre) + ' <small>' + esc(h.curso) + '</small></label>';
        }).join('');
        form.querySelector('.ap-rp-emails').textContent = f.emails.length ? T.t1 + ' ' + f.emails.join(', ') : T.t2;
        boxFam.hidden = false;
        form.querySelector('.ap-rp-error').hidden = true;
        q.parentNode.hidden = true;
        res.hidden = true;
        actualizar();
    };

    q.addEventListener('input', function () {
        var t = norm(q.value).trim();
        if (t.length < 2) { res.hidden = true; return; }
        var partes = t.split(/\s+/);
        var m = items.filter(function (it) { return partes.every(function (p) { return it.txt.indexOf(p) > -1; }); }).slice(0, 12);
        res.innerHTML = m.length ? m.map(function (it, i) {
            return '<li><button type="button" data-i="' + items.indexOf(it) + '"><strong>' + esc(it.h.nombre) + '</strong> · ' + esc(it.h.curso) + ' <small>' + esc(it.f.nombre) + '</small></button></li>';
        }).join('') : '<li class="ap-rp-nada">' + T.t3 + '</li>';
        res.hidden = false;
    });
    res.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-i]');
        if (!b) { return; }
        var it = items[+b.getAttribute('data-i')];
        elegir(it.f, [String(it.h.id)]);
    });
    form.querySelector('.ap-rp-cambiar').addEventListener('click', function () {
        famActual = null; form.familia_id.value = ''; boxFam.hidden = true; q.parentNode.hidden = false; q.value = ''; q.focus();
    });

    var mostrar = function (sel, si) {
        form.querySelectorAll(sel).forEach(function (el) { el.hidden = !si; });
    };
    var actualizar = function () {
        var k = (form.querySelector('[name="servicio"]:checked') || {}).value || '';
        var s = servs[k] || null;
        var varios = !!(s && s.varios);
        if (!varios) { form.querySelector('[name="modo"][value="dia"]').checked = true; }
        var modo = form.querySelector('[name="modo"]:checked').value;
        mostrar('[data-si="varios"]', varios);
        mostrar('[data-modo="dia"]', modo === 'dia');
        mostrar('[data-modo="varios"]', modo === 'varios');
        mostrar('[data-si="hora"]', k === 'salida' || k === 'retraso');
        form.querySelector('.ap-rp-hora-txt').textContent = k === 'retraso' ? T.t4 : T.t5;
        mostrar('[data-si="desayuno"]', k === 'ampliacion' && s && s.desayuno);
        mostrar('[data-si="dieta_sn"]', k === 'comedor' && s && s.dieta);
        var dieta = k === 'dieta' || (k === 'comedor' && s && s.dieta && (form.querySelector('[name="dieta"]:checked') || {}).value === 'si');
        mostrar('[data-si="dieta"]', dieta);
        mostrar('[data-si="recoge"]', k === 'salida' && s && s.recoge);
        mostrar('[data-si="motivo"]', dieta || k === 'salida' || k === 'retraso');
        // Children for whom the service is not available.
        hijosEl.querySelectorAll('.ap-rp-hijo').forEach(function (l) {
            var no = s && s.etapas.length && s.etapas.indexOf(l.getAttribute('data-etapa')) < 0;
            l.classList.toggle('is-no', !!no);
            l.title = no ? T.t6 : '';
        });
    };
    form.addEventListener('change', function () {
        form.querySelector('.ap-rp-error').hidden = true;
        actualizar();
    });

    form.addEventListener('submit', function (e) {
        var err = '';
        if (!form.familia_id.value) { err = T.t7; }
        else if (!form.querySelector('[name="alumnos[]"]:checked')) { err = T.t8; }
        else if (!form.querySelector('[name="servicio"]:checked')) { err = T.t9; }
        var box = form.querySelector('.ap-rp-error');
        box.textContent = err; box.hidden = !err;
        if (err) { e.preventDefault(); box.scrollIntoView({ block: 'center' }); }
    });

    // After an error, the form keeps what was written.
    if (old && old.familia_id) {
        Object.keys(old).forEach(function (n) {
            var v = old[n];
            if (n === 'familia_id' || n === 'alumnos' || n === 'dias') { return; }
            form.querySelectorAll('[name="' + n + '"]').forEach(function (el) {
                if (el.type === 'radio' || el.type === 'checkbox') { el.checked = (el.value === v); }
                else { el.value = v; }
            });
        });
        if (old.dias) { form.querySelectorAll('[name="dias[]"]').forEach(function (el) { el.checked = old.dias.indexOf(el.value) > -1; }); }
        form.querySelector('[name="confirmar"]').checked = !!old.confirmar;
        fams.forEach(function (f) { if (String(f.id) === String(old.familia_id)) { elegir(f, (old.alumnos || []).map(String)); } });
    }
    actualizar();
})();
