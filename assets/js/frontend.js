/**
 * Área Profesores - Frontend JavaScript
 * Handles tab navigation, interactive form inputs, AJAX submission and records management.
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // =========================================================================
        // Tab Navigation
        // =========================================================================
        $('.ap-tab-btn').on('click', function(e) {
            e.preventDefault();
            var $this = $(this);
            if ($this.hasClass('is-disabled')) {
                return;
            }

            var targetTab = $this.data('target');
            $('.ap-tab-btn').removeClass('is-active');
            $this.addClass('is-active');

            $('.ap-tab-content').removeClass('is-active');
            $('#' + targetTab).addClass('is-active');
        });

        // Open a tab from the URL hash (e.g. /area-profesores/#enlaces)
        var abrirTabHash = function() {
            var tabFromHash = (window.location.hash || '').replace('#', '');
            if (tabFromHash && /^[\w-]+$/.test(tabFromHash)) {
                var $hashTab = $('.ap-tab-btn[data-target="tab-' + tabFromHash + '"]');
                if ($hashTab.length) {
                    $hashTab.trigger('click');
                }
            }
        };
        abrirTabHash();
        $(window).on('hashchange', abrirTabHash);

        // =========================================================================
        // Enlaces Útiles: live search
        // =========================================================================
        $('#ap-enlaces-search-input').on('input', function() {
            var normalize = function(str) {
                return (str || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            };
            var term = normalize($(this).val()).trim();
            var $container = $('#tab-enlaces');
            var visible = 0;

            $container.find('.ap-enlace-card').each(function() {
                var match = term === '' || normalize($(this).data('search')).indexOf(term) !== -1;
                $(this).toggle(match);
                if (match) { visible++; }
            });

            $container.find('.ap-enlaces-group').each(function() {
                var shown = $(this).find('.ap-enlace-card').filter(function() {
                    return this.style.display !== 'none';
                }).length;
                $(this).toggle(shown > 0);
            });

            $container.find('.ap-enlaces-no-results').toggle(visible === 0);
        });

        // =========================================================================
        // Espacios de aula: search ("3 ep" → 3º EP A/B; also by tutor/a)
        // =========================================================================
        $('#ap-ea-buscar').on('input', function() {
            var norm = function(str) {
                return (str || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[ºª]/g, '');
            };
            var words = norm($(this).val()).split(/[^a-z0-9]+/).filter(Boolean);
            var total = 0;
            var $tab = $('#tab-espacios');

            $tab.find('.ap-ea-grupo').each(function() {
                var visible = 0;
                $(this).find('.ap-ea-card').each(function() {
                    var tokens = norm($(this).data('search')).split(/[^a-z0-9]+/);
                    var ok = words.every(function(w) {
                        return tokens.some(function(t) { return t.indexOf(w) === 0; });
                    });
                    this.hidden = !ok;
                    if (ok) { visible++; }
                });
                this.hidden = visible === 0;
                // Open "Promociones anteriores" when the search finds something there.
                if (words.length && visible && this.tagName === 'DETAILS') { this.open = true; }
                total += visible;
            });
            $('#ap-ea-vacio').prop('hidden', total > 0);
        });

        // =========================================================================
        // Mis alumnos: course selector, search and print
        // =========================================================================
        var $ma = $('#tab-alumnos');
        if ($ma.length) {
            $ma.on('click', '.ap-ma-curso', function() {
                var curso = $(this).data('curso');
                $ma.find('.ap-ma-curso').removeClass('is-active');
                $(this).addClass('is-active');
                $ma.find('.ap-ma-lista').each(function() {
                    this.hidden = $(this).data('curso') !== curso;
                });
                $('#ap-ma-buscar').val('').trigger('input');
            });
            $('#ap-ma-buscar').on('input', function() {
                var norm = function(str) {
                    return (str || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                };
                var q = norm($(this).val()).trim();
                // When searching, look in every course.
                $ma.find('.ap-ma-lista').each(function() {
                    var $lista = $(this), visibles = 0;
                    $lista.find('tbody tr').each(function() {
                        var ok = q === '' || norm($(this).data('search')).indexOf(q) !== -1;
                        this.hidden = !ok;
                        if (ok) { visibles++; }
                    });
                    if (q !== '') {
                        this.hidden = visibles === 0;
                    } else {
                        var activo = $ma.find('.ap-ma-curso.is-active').data('curso');
                        this.hidden = activo !== undefined && $lista.data('curso') !== activo;
                    }
                });
            });
            // Edit a pupil's data (allergies, parents' names and phones).
            var dlg = document.getElementById('ap-ma-dialog');
            if (dlg && typeof dlg.showModal === 'function') {
                var $f = $(dlg).find('form');
                var campo = function(label, name, valor, tipo) {
                    return $('<label/>').append($('<span/>').text(label))
                        .append($('<input/>', { type: tipo || 'text', name: name }).val(valor || ''));
                };
                $ma.on('click', '.ap-ma-editar', function() {
                    var d = $(this).data('alumno');
                    $f[0].reset();
                    $f.find('[name="alumno_id"]').val(d.id);
                    $('#ap-ma-dialog-titulo').text('Editar datos de ' + d.nombre);
                    $f.find('[name="alergias_alimentarias"]').val(d.alim);
                    $f.find('[name="alergias_otras"]').val(d.otras);
                    var $fams = $f.find('.ap-ma-dialog-familias').empty();
                    $.each(d.familias || [], function(i, fam) {
                        var $fs = $('<fieldset/>').append($('<legend/>').text('Familia ' + fam.nombre));
                        $.each(fam.contactos, function(j, c) {
                            $fs.append($('<div class="ap-ma-dialog-fila"/>')
                                .append(campo('Nombre', 'ct[' + c.id + '][nombre]', c.nombre))
                                .append(campo('Teléfono de emergencia', 'ct[' + c.id + '][telefono]', c.telefono, 'tel')));
                        });
                        var $nuevo = $('<details class="ap-ma-dialog-nuevo"/>').append($('<summary/>').text('Añadir otro padre, madre o tutor'))
                            .append($('<div class="ap-ma-dialog-fila"/>')
                                .append(campo('Nombre', 'nuevo[' + fam.id + '][nombre]', ''))
                                .append(campo('Teléfono de emergencia', 'nuevo[' + fam.id + '][telefono]', '', 'tel')));
                        if (!fam.contactos.length) { $nuevo.attr('open', true); }
                        $fs.append($nuevo);
                        $fams.append($fs);
                    });
                    if (!(d.familias || []).length) {
                        $fams.append($('<p class="ap-ma-vacio"/>').text('Este alumno no tiene familia asignada: Secretaría la añade desde el backend.'));
                    }
                    dlg.showModal();
                });
                $(dlg).on('click', '[data-cerrar]', function() { dlg.close(); });
                $f.on('submit', function() {
                    var $b = $f.find('[type="submit"]');
                    setTimeout(function() { $b.prop('disabled', true).text('Guardando…'); }, 0);
                });
            } else {
                $ma.find('.ap-ma-editar').remove();
            }

            $('#ap-ma-imprimir').on('click', function() {
                $('body').addClass('ap-imprimiendo-alumnos');
                window.print();
                setTimeout(function() { $('body').removeClass('ap-imprimiendo-alumnos'); }, 500);
            });
        }

        // Switch to form button from empty state
        $(document).on('click', '.ap-switch-to-form', function() {
            $('.ap-tab-btn[data-target="tab-comedor"]').trigger('click');
        });

        // =========================================================================
        // Radio Chip Selection for Clases
        // =========================================================================
        $('.ap-radio-chip input[type="radio"]').on('change', function() {
            $('.ap-radio-chip').removeClass('is-selected');
            if ($(this).is(':checked')) {
                $(this).closest('.ap-radio-chip').addClass('is-selected');
                // The etapa follows the selected course.
                var etapaFromCourse = $(this).data('etapa');
                if (etapaFromCourse) {
                    $('input[name="etapa"]').filter(function() { return this.value === String(etapaFromCourse); }).prop('checked', true);
                }
            }
        });

        // Comedor: show courses from other etapas
        $('.ap-comedor-show-all').on('click', function() {
            $('.ap-comedor-etapa.is-other').prop('hidden', false);
            $(this).remove();
        });

        // =========================================================================
        // Number Stepper (+ / -)
        // =========================================================================
        $('#ap-count-minus').on('click', function() {
            var $input = $('#ap-num-alumnos');
            var val = parseInt($input.val(), 10);
            if (isNaN(val) || val <= 0) {
                $input.val(0);
            } else {
                $input.val(val - 1);
            }
        });

        $('#ap-count-plus').on('click', function() {
            var $input = $('#ap-num-alumnos');
            var val = parseInt($input.val(), 10);
            if (isNaN(val)) {
                $input.val(1);
            } else {
                $input.val(val + 1);
            }
        });

        // =========================================================================
        // AJAX Form Submission
        // =========================================================================
        var $form = $('#ap-comedor-form');
        var $alert = $('#ap-form-alert');
        var $submitBtn = $('#ap-submit-btn');
        var $resetBtn = $('#ap-reset-btn');

        function showAlert(type, message) {
            $alert.removeClass('ap-alert-success ap-alert-error')
                  .addClass(type === 'success' ? 'ap-alert-success' : 'ap-alert-error')
                  .html(message)
                  .slideDown(200);

            // Auto dismiss if success
            if (type === 'success') {
                setTimeout(function() {
                    $alert.slideUp(200);
                }, 5000);
            }
        }

        $form.on('submit', function(e) {
            e.preventDefault();

            var clase = $('input[name="clase"]:checked').val();
            var etapa = $('input[name="etapa"]:checked').val();
            var numAlumnos = $('#ap-num-alumnos').val();

            if (!clase) {
                showAlert('error', AreaProfesoresData.i18n.fill_required + ' (Selecciona la Clase)');
                return;
            }

            if (!etapa) {
                showAlert('error', AreaProfesoresData.i18n.fill_required + ' (Selecciona la Etapa)');
                return;
            }

            if (numAlumnos === '' || parseInt(numAlumnos, 10) < 0) {
                showAlert('error', 'Por favor, indica cuántos alumnos se quedan a comer.');
                $('#ap-num-alumnos').focus();
                return;
            }

            // Disable button and show spinner
            $submitBtn.prop('disabled', true);
            $submitBtn.find('.ap-btn-text').hide();
            $submitBtn.find('.ap-btn-loading').show();
            $alert.slideUp(100);

            var formData = $form.serializeArray();
            formData.push({ name: 'action', value: 'ap_save_comedor_record' });
            formData.push({ name: 'nonce', value: AreaProfesoresData.nonce });

            $.ajax({
                url: AreaProfesoresData.ajax_url,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    $submitBtn.prop('disabled', false);
                    $submitBtn.find('.ap-btn-text').show();
                    $submitBtn.find('.ap-btn-loading').hide();

                    if (response.success) {
                        showAlert('success', '✓ ' + response.data.message);
                        $resetBtn.show();

                        // Render updated records list
                        if (response.data.records) {
                            renderRecordsList(response.data.records);
                        }

                        // Scroll to top of card smoothly
                        $('html, body').animate({
                            scrollTop: $('.ap-form-card').offset().top - 40
                        }, 300);
                    } else {
                        showAlert('error', response.data.message || AreaProfesoresData.i18n.error);
                    }
                },
                error: function() {
                    $submitBtn.prop('disabled', false);
                    $submitBtn.find('.ap-btn-text').show();
                    $submitBtn.find('.ap-btn-loading').hide();
                    showAlert('error', AreaProfesoresData.i18n.error);
                }
            });
        });

        // Reset form for another submission
        $resetBtn.on('click', function() {
            resetForm();
            showAlert('success', 'Formulario listo para registrar otra clase.');
        });

        function resetForm() {
            $('#ap-field-record-id').val('');
            $('#ap-num-alumnos').val('');
            $('#ap-dietas').val('');
            $('#ap-alergicos-faltan').val('');
            $submitBtn.find('.ap-btn-text').html(
                '<svg class="ap-btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>' +
                ' Guardar y Enviar a Cocina'
            );
            $resetBtn.hide();
        }

        // =========================================================================
        // Edit and Delete Records Handlers
        // =========================================================================
        $(document).on('click', '.ap-edit-record', function() {
            var $btn = $(this);
            var id = $btn.data('id');
            var clase = $btn.data('clase');
            var etapa = $btn.data('etapa');
            var alumnos = $btn.data('alumnos');
            var dietas = $btn.data('dietas');
            var alergicos = $btn.data('alergicos');

            // Populate form
            $('#ap-field-record-id').val(id);
            $('#ap-num-alumnos').val(alumnos);
            $('#ap-dietas').val(dietas);
            $('#ap-alergicos-faltan').val(alergicos);

            // Select class chip
            $('.ap-radio-chip input[name="clase"]').each(function() {
                if ($(this).val() === String(clase)) {
                    $(this).closest('.ap-comedor-etapa').prop('hidden', false);
                    $(this).prop('checked', true).trigger('change');
                }
            });

            // Select etapa
            $('input[name="etapa"][value="' + etapa + '"]').prop('checked', true);

            // Change button text
            $submitBtn.find('.ap-btn-text').html(
                '<svg class="ap-btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>' +
                ' Actualizar Registro de ' + clase
            );
            $resetBtn.show();

            // Switch to form tab
            $('.ap-tab-btn[data-target="tab-comedor"]').trigger('click');

            showAlert('success', 'Modificando registro de ' + clase + '. Guarda los cambios para enviar la actualización.');
        });

        $(document).on('click', '.ap-delete-record', function() {
            if (!confirm(AreaProfesoresData.i18n.confirm_delete)) {
                return;
            }

            var $btn = $(this);
            var recordId = $btn.data('id');
            var $item = $btn.closest('.ap-record-item');

            $.ajax({
                url: AreaProfesoresData.ajax_url,
                type: 'POST',
                data: {
                    action: 'ap_delete_comedor_record',
                    nonce: AreaProfesoresData.nonce,
                    record_id: recordId
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        $item.fadeOut(250, function() {
                            $(this).remove();
                            var remaining = $('.ap-record-item').length;
                            $('#ap-records-count').text(remaining);

                            if (remaining === 0) {
                                $('#ap-my-records-container').html(
                                    '<div class="ap-empty-state">' +
                                    '<div class="ap-empty-icon">🍽️</div>' +
                                    '<h3>Aún no has enviado registros para hoy</h3>' +
                                    '<p>Rellena el formulario de "Apuntar Comedor" para notificar los comensales y dietas.</p>' +
                                    '<button type="button" class="ap-btn ap-btn-primary ap-btn-sm ap-switch-to-form">Ir al formulario</button>' +
                                    '</div>'
                                );
                            }
                        });
                    } else {
                        alert(res.data.message || AreaProfesoresData.i18n.error);
                    }
                }
            });
        });

        // Function to rebuild records list dynamically
        function renderRecordsList(records) {
            $('#ap-records-count').text(records.length);

            if (records.length === 0) {
                return;
            }

            var html = '<div class="ap-records-list">';
            records.forEach(function(rec) {
                var dietasHtml = rec.dietas ? '<div class="ap-record-detail-row"><strong>Dietas:</strong> <span>' + escapeHtml(rec.dietas) + '</span></div>' : '';
                var alergicosHtml = rec.alergicos_faltan ? '<div class="ap-record-detail-row ap-text-warning"><strong>Faltan / Obs:</strong> <span>' + escapeHtml(rec.alergicos_faltan) + '</span></div>' : '';

                html += '<article class="ap-record-item" data-record-id="' + rec.id + '">' +
                    '<div class="ap-record-item-main">' +
                        '<div class="ap-record-item-badge">' +
                            '<span class="ap-badge-class">' + escapeHtml(rec.clase) + '</span>' +
                            '<span class="ap-badge-stage">' + escapeHtml(rec.etapa) + '</span>' +
                        '</div>' +
                        '<div class="ap-record-item-stats">' +
                            '<span class="ap-stat-highlight"><strong>' + escapeHtml(rec.num_alumnos) + '</strong> alumnos estándar</span>' +
                            dietasHtml +
                            alergicosHtml +
                        '</div>' +
                    '</div>' +
                    '<div class="ap-record-item-actions">' +
                        '<button type="button" class="ap-btn ap-btn-sm ap-btn-outline ap-edit-record" ' +
                            'data-id="' + rec.id + '" ' +
                            'data-clase="' + escapeHtml(rec.clase) + '" ' +
                            'data-etapa="' + escapeHtml(rec.etapa) + '" ' +
                            'data-alumnos="' + escapeHtml(rec.num_alumnos) + '" ' +
                            'data-dietas="' + escapeHtml(rec.dietas || '') + '" ' +
                            'data-alergicos="' + escapeHtml(rec.alergicos_faltan || '') + '">Editar</button>' +
                        '<button type="button" class="ap-btn ap-btn-sm ap-btn-subtle-danger ap-delete-record" data-id="' + rec.id + '">Eliminar</button>' +
                    '</div>' +
                '</article>';
            });
            html += '</div>';

            $('#ap-my-records-container').html(html);
        }

        function escapeHtml(text) {
            if (!text) return '';
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        // =========================================================================
        // Login & Access Request Handlers ([loginprofesores])
        // =========================================================================
        $('.ap-login-tab-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var target = $btn.data('login-target');

            $('.ap-login-tab-btn').removeClass('is-active');
            $btn.addClass('is-active');

            $('.ap-login-tab-panel').removeClass('is-active');
            $('#' + target).addClass('is-active');
        });

        // Quick button to switch to Request tab
        $(document).on('click', '.ap-switch-to-request', function(e) {
            e.preventDefault();
            $('.ap-login-tab-btn[data-login-target="tab-solicitar"]').trigger('click');
        });

        // Quick button to switch to Login tab
        $(document).on('click', '.ap-switch-to-login', function(e) {
            e.preventDefault();
            $('.ap-login-tab-btn[data-login-target="tab-entrar"]').trigger('click');
        });

        // AJAX Submission of Solicitud de Acceso
        var $solicitudForm = $('#ap-solicitud-form');
        var $solicitudAlert = $('#ap-solicitud-alert');
        var $solicitudSubmitBtn = $('#ap-submit-solicitud-btn');

        $solicitudForm.on('submit', function(e) {
            e.preventDefault();

            var nombre = $.trim($('#ap-req-nombre').val());
            var apellidos = $.trim($('#ap-req-apellidos').val());
            var email = $.trim($('#ap-req-email').val());

            if (!nombre || !apellidos || !email) {
                showSolicitudAlert('error', AreaProfesoresData.i18n.fill_required);
                return;
            }

            // Button loading state
            $solicitudSubmitBtn.prop('disabled', true);
            $solicitudSubmitBtn.find('.ap-btn-text').hide();
            $solicitudSubmitBtn.find('.ap-btn-loading').show();
            $solicitudAlert.slideUp(100);

            var formData = $solicitudForm.serializeArray();
            formData.push({ name: 'action', value: 'ap_submit_solicitud' });
            formData.push({ name: 'nonce', value: AreaProfesoresData.nonce });

            $.ajax({
                url: AreaProfesoresData.ajax_url,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(res) {
                    $solicitudSubmitBtn.prop('disabled', false);
                    $solicitudSubmitBtn.find('.ap-btn-text').show();
                    $solicitudSubmitBtn.find('.ap-btn-loading').hide();

                    if (res.success) {
                        showSolicitudAlert('success', res.data.message);
                        $solicitudForm[0].reset();
                    } else {
                        showSolicitudAlert('error', res.data.message || AreaProfesoresData.i18n.error);
                    }
                },
                error: function() {
                    $solicitudSubmitBtn.prop('disabled', false);
                    $solicitudSubmitBtn.find('.ap-btn-text').show();
                    $solicitudSubmitBtn.find('.ap-btn-loading').hide();
                    showSolicitudAlert('error', AreaProfesoresData.i18n.error);
                }
            });
        });

        function showSolicitudAlert(type, message) {
            $solicitudAlert.removeClass('ap-alert-success ap-alert-error')
                           .addClass(type === 'success' ? 'ap-alert-success' : 'ap-alert-error')
                           .html(message)
                           .slideDown(200);
        }

        // =========================================================================
        // Comunicados a familias (Google Chat) - Dirección y Comunicación
        // =========================================================================
        var $comForm = $('#ap-comunicado-form');
        if ($('#tab-comunicados').length) {
            var i18n = AreaProfesoresData.i18n;
            var $comMsg = $('#ap-com-mensaje');
            var $comTitle = $('#ap-com-titulo');
            var $comFile = $('#ap-com-imagen');
            var $comSubmit = $('#ap-com-submit');
            var $comAlert = $('#ap-comunicado-alert');
            var $comResult = $('#ap-com-result');
            var $replaceId = $('#ap-com-replace-id');
            var $reuseImage = $('#ap-com-reuse-image');
            var $fecha = $('#ap-com-fecha');
            var $hora = $('#ap-com-hora');
            var lastComunicadoId = 0;
            var lastFailed = [];
            var previewUrl = null;

            var fmt = function(str) {
                var args = Array.prototype.slice.call(arguments, 1);
                var i = 0;
                return String(str).replace(/%(\d+\$)?[ds]/g, function(m, pos) {
                    return pos ? args[parseInt(pos, 10) - 1] : args[i++];
                });
            };
            var escapeHtml = function(s) {
                return $('<div>').text(s || '').html();
            };
            var pad = function(n) { return (n < 10 ? '0' : '') + n; };
            var ymd = function(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };

            // Google Chat-like formatting for the preview.
            var chatFormat = function(text) {
                var html = escapeHtml(text);
                html = html.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
                html = html.replace(/(^|[\s(])\*([^*\n]+)\*(?=$|[\s.,;:!?)])/g, '$1<strong>$2</strong>');
                html = html.replace(/(^|[\s(])_([^_\n]+)_(?=$|[\s.,;:!?)])/g, '$1<em>$2</em>');
                html = html.replace(/(^|[\s(])~([^~\n]+)~(?=$|[\s.,;:!?)])/g, '$1<s>$2</s>');
                return html.replace(/\n/g, '<br>');
            };

            var isScheduled = function() {
                return $comForm.find('input[name="modo"]:checked').val() === 'programar';
            };

            var scheduledDate = function() {
                var f = $fecha.val(), h = $hora.val();
                if (!f || !h) { return null; }
                var p = f.split('-'), t = h.split(':');
                return new Date(+p[0], +p[1] - 1, +p[2], +t[0], +t[1]);
            };

            var updatePreview = function() {
                var title = $.trim($comTitle.val() || '');
                var msg = $comMsg.val() || '';
                var text = (title ? '*' + title + '*\n\n' : '') + msg;
                $('#ap-com-count').text((title ? title.length + 2 : 0) + msg.length);
                $('#ap-com-preview-text').html($.trim(text) ? chatFormat($.trim(text)) : '<em>' + escapeHtml(i18n.com_placeholder) + '</em>');
            };

            var selectedSpaces = function() {
                return $comForm.find('input[name="espacios[]"]:checked');
            };

            var updateSchedule = function() {
                var prog = isScheduled();
                $('#ap-com-programar').toggle(prog);
                $comForm.find('.ap-com-modo .ap-com-chip').each(function() {
                    $(this).toggleClass('is-selected', $(this).find('input').is(':checked'));
                });
                var d = scheduledDate();
                var $txt = $('#ap-com-programar-text');
                if (prog && d) {
                    var label = d.toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' }) + ' ' + i18n.com_at + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
                    $txt.text(fmt(i18n.com_will_publish, label)).toggleClass('is-error', d.getTime() < Date.now() + 60000);
                } else {
                    $txt.text('');
                }
            };

            var updateSelection = function() {
                var n = selectedSpaces().length;
                var editing = !!$replaceId.val();
                var label = editing ? i18n.com_save_changes : (isScheduled() ? i18n.com_schedule_btn : i18n.com_publish);
                $comSubmit.prop('disabled', n === 0);
                $comSubmit.find('.ap-com-submit-label').text(fmt(label, n));
                $('#ap-com-selected').text(n ? fmt(i18n.com_selected, n) : i18n.com_none);

                $comForm.find('.ap-com-chips .ap-com-chip').each(function() {
                    $(this).toggleClass('is-selected', $(this).find('input').is(':checked'));
                });
                $comForm.find('.ap-com-grupo').each(function() {
                    var $boxes = $(this).find('input[name="espacios[]"]');
                    var checked = $boxes.filter(':checked').length;
                    var $g = $(this).find('.ap-com-grupo-check');
                    $g.prop('checked', checked > 0 && checked === $boxes.length);
                    $g.prop('indeterminate', checked > 0 && checked < $boxes.length);
                });
                updateSchedule();
            };

            $comMsg.add($comTitle).on('input', updatePreview);
            $comForm.on('change', 'input[name="espacios[]"]', updateSelection);
            $comForm.on('change', 'input[name="modo"]', updateSelection);
            $fecha.add($hora).on('change input', updateSchedule);
            $comForm.on('change', '.ap-com-grupo-check', function() {
                $(this).closest('.ap-com-grupo').find('input[name="espacios[]"]').prop('checked', $(this).is(':checked'));
                updateSelection();
            });
            $comForm.on('click', '[data-ap-select]', function() {
                $comForm.find('input[name="espacios[]"]').prop('checked', $(this).data('ap-select') === 'all');
                updateSelection();
            });
            $comForm.on('click', '[data-ap-when]', function() {
                var d = new Date();
                var when = $(this).data('ap-when');
                var hour = '08:00';
                if (when === 'tomorrow') {
                    d.setDate(d.getDate() + 1);
                } else if (when === 'monday') {
                    d.setDate(d.getDate() + (((8 - d.getDay()) % 7) || 7));
                } else {
                    hour = '17:00';
                }
                $fecha.val(ymd(d));
                $hora.val(hour);
                updateSchedule();
            });

            // Image selection + preview
            var setImagePreview = function(url) {
                $('#ap-com-image-preview').show().find('img').attr('src', url).show();
                $('#ap-com-pdf-nombre, #ap-com-preview-pdf, #ap-com-pdf-nota').hide();
                $('#ap-com-dropzone').hide();
                $('#ap-com-preview-img').attr('src', url).show();
            };
            // PDF (e.g. the menu): sent as a card with a button that opens it.
            var setPdfPreview = function(nombre, thumb) {
                var $p = $('#ap-com-image-preview').show();
                if (thumb) { $p.find('img').attr('src', thumb).show(); } else { $p.find('img').hide().attr('src', ''); }
                $('#ap-com-pdf-nombre').text('📄 ' + nombre).show();
                $('#ap-com-dropzone').hide();
                $('#ap-com-preview-img').hide().attr('src', '');
                $('#ap-com-preview-pdf').show().find('.ap-chat-pdf-nombre').text('📄 ' + nombre);
                $('#ap-com-pdf-nota').show();
            };
            var clearImage = function() {
                $comFile.val('');
                $reuseImage.val('');
                if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
                $('#ap-com-image-preview').hide();
                $('#ap-com-pdf-nombre, #ap-com-preview-pdf, #ap-com-pdf-nota').hide();
                $('#ap-com-dropzone').show();
                $('#ap-com-preview-img').hide().attr('src', '');
            };
            var showImage = function(file) {
                var esPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name || '');
                var okType = esPdf || /^image\/(jpeg|png|gif)$/.test(file.type);
                if (!okType || file.size > (esPdf ? 20 : 8) * 1024 * 1024) {
                    clearImage();
                    showComAlert('error', i18n.com_bad_image);
                    return;
                }
                $comAlert.hide();
                $reuseImage.val('');
                if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
                if (esPdf) {
                    setPdfPreview(file.name, '');
                    return;
                }
                previewUrl = URL.createObjectURL(file);
                setImagePreview(previewUrl);
            };
            $comFile.on('change', function() {
                if (this.files && this.files[0]) { showImage(this.files[0]); } else { clearImage(); }
            });
            $('#ap-com-image-remove').on('click', clearImage);
            $('#ap-com-dropzone').on('dragover dragenter', function(e) {
                e.preventDefault(); $(this).addClass('is-dragover');
            }).on('dragleave drop', function(e) {
                e.preventDefault(); $(this).removeClass('is-dragover');
                if (e.type === 'drop' && e.originalEvent.dataTransfer.files.length) {
                    try { $comFile[0].files = e.originalEvent.dataTransfer.files; } catch (err) { return; }
                    showImage(e.originalEvent.dataTransfer.files[0]);
                }
            });

            function showComAlert(type, message) {
                $comAlert.removeClass('ap-alert-success ap-alert-error')
                         .addClass(type === 'success' ? 'ap-alert-success' : 'ap-alert-error')
                         .text(message).show();
                $('html, body').animate({ scrollTop: $comAlert.offset().top - 80 }, 200);
            }

            function refreshLists(data) {
                if (data.historial_html !== undefined) { $('#ap-com-historial').html(data.historial_html); }
                if (data.programados_html !== undefined) { $('#ap-com-programados').html(data.programados_html); }
            }

            function showResultPanel() {
                $comForm.hide();
                $comResult.show();
                $('html, body').animate({ scrollTop: $comResult.offset().top - 80 }, 200);
            }

            function renderResult(data) {
                refreshLists(data);
                var $list = $comResult.find('.ap-com-result-list').empty();
                var $title = $comResult.find('.ap-com-result-title').removeClass('is-error is-scheduled');
                var $text = $comResult.find('.ap-com-result-text').text('').hide();

                if (data.programado) {
                    lastFailed = [];
                    $title.text(fmt(data.editado ? i18n.com_sched_updated : i18n.com_sched_ok, data.programado_texto)).addClass('is-scheduled');
                    $text.text(fmt(i18n.com_sched_spaces, data.total) + ' ' + data.espacios.join(', ') + '.').show();
                    $('#ap-com-retry').hide();
                    showResultPanel();
                    return;
                }

                lastComunicadoId = data.comunicado_id;
                lastFailed = [];
                $.each(data.resultados, function(_, r) {
                    if (!r.ok) { lastFailed.push(r.id); }
                    $('<li>').addClass(r.ok ? 'is-ok' : 'is-error')
                        .append($('<strong>').text((r.ok ? '✓ ' : '✗ ') + r.nombre))
                        .append($('<span>').text(' — ' + (r.ok ? i18n.com_ok : r.error)))
                        .appendTo($list);
                });
                $title.text(
                    data.ok === data.total ? fmt(i18n.com_all_ok, data.total) : fmt(i18n.com_some_fail, data.ok, data.total)
                ).toggleClass('is-error', data.ok !== data.total);
                $('#ap-com-retry').toggle(lastFailed.length > 0);
                showResultPanel();
            }

            function ajaxCall(formData, $btn, onSuccess) {
                var original = $btn.html();
                $btn.prop('disabled', true).html('<span class="ap-spinner"></span>' + escapeHtml(i18n.com_sending));
                formData.append('nonce', AreaProfesoresData.nonce);

                $.ajax({
                    url: AreaProfesoresData.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    timeout: 180000
                }).done(function(res) {
                    if (res && res.success) {
                        onSuccess(res.data);
                    } else {
                        showComAlert('error', (res && res.data && res.data.message) || i18n.error);
                    }
                }).fail(function(xhr) {
                    var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
                    showComAlert('error', msg || i18n.com_net_error);
                }).always(function() {
                    if ($.contains(document, $btn[0])) {
                        $btn.prop('disabled', false).html(original);
                    }
                    updateSelection();
                });
            }

            function resetComForm() {
                if (!$comForm.length) { return; }
                $comForm[0].reset();
                clearImage();
                $replaceId.val('');
                $('#ap-com-editing').hide();
                updatePreview();
                updateSelection();
                $comAlert.hide();
            }

            // Fill the form from a stored announcement (edit or template).
            function fillForm(data, editId) {
                resetComForm();
                $comResult.hide();
                $comForm.show();
                $comTitle.val(data.titulo || '');
                $comMsg.val(data.mensaje || '');
                $comForm.find('input[name="espacios[]"]').each(function() {
                    $(this).prop('checked', $.inArray($(this).val(), data.espacios || []) !== -1);
                });
                if (data.image_id && data.image_url) {
                    $reuseImage.val(data.image_id);
                    if (data.es_pdf) { setPdfPreview(data.archivo, data.thumb_url || ''); } else { setImagePreview(data.image_url); }
                }
                if (editId) {
                    $replaceId.val(editId);
                    $comForm.find('input[name="modo"][value="programar"]').prop('checked', true);
                    if (data.fecha) { $fecha.val(data.fecha); }
                    if (data.hora) { $hora.val(data.hora); }
                    $('#ap-com-editing-text').text(i18n.com_editing);
                    $('#ap-com-editing').show();
                }
                updatePreview();
                updateSelection();
                $('.ap-tab-btn[data-target="tab-comunicados"]').trigger('click');
                $('html, body').animate({ scrollTop: $('#tab-comunicados').offset().top - 40 }, 200);
            }

            $comForm.on('submit', function(e) {
                e.preventDefault();
                var hasFile = $comFile[0].files && $comFile[0].files.length > 0;
                if (!$.trim($comMsg.val()) && !hasFile && !$reuseImage.val()) {
                    showComAlert('error', i18n.com_empty);
                    return;
                }
                var n = selectedSpaces().length;
                if (!n) { return; }

                var confirmText;
                if (isScheduled()) {
                    var d = scheduledDate();
                    if (!d || d.getTime() < Date.now() + 60000) {
                        showComAlert('error', i18n.com_past);
                        return;
                    }
                    confirmText = fmt(i18n.com_confirm_sched, n, $('#ap-com-programar-text').text());
                } else {
                    confirmText = fmt(i18n.com_confirm, n);
                }
                if (!window.confirm(confirmText)) { return; }

                var fd = new FormData(this);
                fd.append('action', 'ap_chat_publicar');
                ajaxCall(fd, $comSubmit, function(data) {
                    $replaceId.val('');
                    $('#ap-com-editing').hide();
                    renderResult(data);
                });
            });

            function retry(comunicadoId, spaces, $btn) {
                if (!window.confirm(fmt(i18n.com_confirm, spaces.length))) { return; }
                var fd = new FormData();
                fd.append('action', 'ap_chat_publicar');
                fd.append('comunicado_id', comunicadoId);
                $.each(spaces, function(_, id) { fd.append('espacios[]', id); });
                ajaxCall(fd, $btn, renderResult);
            }

            $('#ap-com-retry').on('click', function() {
                retry(lastComunicadoId, lastFailed, $(this));
            });
            $(document).on('click', '.ap-com-retry-old', function() {
                retry($(this).data('comunicado'), $(this).data('espacios') || [], $(this));
            });

            $(document).on('click', '.ap-com-edit', function() {
                fillForm($(this).data('form') || {}, $(this).data('comunicado'));
            });
            $(document).on('click', '.ap-com-template', function() {
                var data = $.extend({}, $(this).data('form') || {});
                delete data.fecha; delete data.hora;
                fillForm(data, 0);
            });
            $('#ap-com-editing-cancel').on('click', resetComForm);

            $(document).on('click', '.ap-com-cancel', function() {
                var id = $(this).data('comunicado');
                if (!window.confirm(i18n.com_confirm_cancel)) { return; }
                var fd = new FormData();
                fd.append('action', 'ap_chat_cancelar');
                fd.append('comunicado_id', id);
                ajaxCall(fd, $(this), function(data) {
                    refreshLists(data);
                    if (String($replaceId.val()) === String(id)) { resetComForm(); }
                });
            });

            $(document).on('click', '.ap-com-send-now', function() {
                var id = $(this).data('comunicado');
                if (!window.confirm(fmt(i18n.com_confirm, $(this).data('total')))) { return; }
                var fd = new FormData();
                fd.append('action', 'ap_chat_enviar_ahora');
                fd.append('comunicado_id', id);
                ajaxCall(fd, $(this), function(data) {
                    if (String($replaceId.val()) === String(id)) { resetComForm(); }
                    renderResult(data);
                });
            });

            $('#ap-com-new').on('click', function() {
                resetComForm();
                $comResult.hide();
                $comForm.show();
            });

            updatePreview();
            updateSelection();
        }
    });

})(jQuery);
