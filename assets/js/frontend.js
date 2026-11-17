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
        var tabFromHash = (window.location.hash || '').replace('#', '');
        if (tabFromHash) {
            var $hashTab = $('.ap-tab-btn[data-target="tab-' + tabFromHash + '"]');
            if ($hashTab.length) {
                $hashTab.trigger('click');
            }
        }

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
            }
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
        // Comunicados a familias (Google Chat) - solo Dirección
        // =========================================================================
        var $comForm = $('#ap-comunicado-form');
        if ($comForm.length) {
            var i18n = AreaProfesoresData.i18n;
            var $comMsg = $('#ap-com-mensaje');
            var $comTitle = $('#ap-com-titulo');
            var $comFile = $('#ap-com-imagen');
            var $comSubmit = $('#ap-com-submit');
            var $comAlert = $('#ap-comunicado-alert');
            var $comResult = $('#ap-com-result');
            var lastComunicadoId = 0;
            var lastFailed = [];
            var previewUrl = null;
            var fmt = function(str) {
                var args = Array.prototype.slice.call(arguments, 1);
                var i = 0;
                return String(str).replace(/%(\d+\$)?d/g, function(m, pos) {
                    return pos ? args[parseInt(pos, 10) - 1] : args[i++];
                });
            };
            var escapeHtml = function(s) {
                return $('<div>').text(s || '').html();
            };

            // Google Chat-like formatting for the preview.
            var chatFormat = function(text) {
                var html = escapeHtml(text);
                html = html.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');
                html = html.replace(/(^|[\s(])\*([^*\n]+)\*(?=$|[\s.,;:!?)])/g, '$1<strong>$2</strong>');
                html = html.replace(/(^|[\s(])_([^_\n]+)_(?=$|[\s.,;:!?)])/g, '$1<em>$2</em>');
                html = html.replace(/(^|[\s(])~([^~\n]+)~(?=$|[\s.,;:!?)])/g, '$1<s>$2</s>');
                return html.replace(/\n/g, '<br>');
            };

            var updatePreview = function() {
                var title = $.trim($comTitle.val());
                var msg = $comMsg.val();
                var text = (title ? '*' + title + '*\n\n' : '') + msg;
                $('#ap-com-count').text((title ? title.length + 2 : 0) + msg.length);
                $('#ap-com-preview-text').html($.trim(text) ? chatFormat($.trim(text)) : '<em>' + escapeHtml(i18n.com_placeholder) + '</em>');
            };

            var selectedSpaces = function() {
                return $comForm.find('input[name="espacios[]"]:checked');
            };

            var updateSelection = function() {
                var n = selectedSpaces().length;
                $comSubmit.prop('disabled', n === 0);
                $comSubmit.find('.ap-com-submit-label').text(n ? fmt(i18n.com_publish, n) : fmt(i18n.com_publish, 0));
                $('#ap-com-selected').text(n ? fmt(i18n.com_selected, n) : i18n.com_none);

                $comForm.find('.ap-com-chip').each(function() {
                    $(this).toggleClass('is-selected', $(this).find('input').is(':checked'));
                });
                $comForm.find('.ap-com-grupo').each(function() {
                    var $boxes = $(this).find('input[name="espacios[]"]');
                    var checked = $boxes.filter(':checked').length;
                    var $g = $(this).find('.ap-com-grupo-check');
                    $g.prop('checked', checked > 0 && checked === $boxes.length);
                    $g.prop('indeterminate', checked > 0 && checked < $boxes.length);
                });
            };

            $comMsg.add($comTitle).on('input', updatePreview);
            $comForm.on('change', 'input[name="espacios[]"]', updateSelection);
            $comForm.on('change', '.ap-com-grupo-check', function() {
                $(this).closest('.ap-com-grupo').find('input[name="espacios[]"]').prop('checked', $(this).is(':checked'));
                updateSelection();
            });
            $comForm.on('click', '[data-ap-select]', function() {
                $comForm.find('input[name="espacios[]"]').prop('checked', $(this).data('ap-select') === 'all');
                updateSelection();
            });

            // Image selection + preview
            var clearImage = function() {
                $comFile.val('');
                if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
                $('#ap-com-image-preview').hide();
                $('#ap-com-dropzone').show();
                $('#ap-com-preview-img').hide().attr('src', '');
            };
            var showImage = function(file) {
                var okType = /^image\/(jpeg|png|gif)$/.test(file.type);
                if (!okType || file.size > 8 * 1024 * 1024) {
                    clearImage();
                    showComAlert('error', i18n.com_bad_image);
                    return;
                }
                $comAlert.hide();
                if (previewUrl) { URL.revokeObjectURL(previewUrl); }
                previewUrl = URL.createObjectURL(file);
                $('#ap-com-image-preview').show().find('img').attr('src', previewUrl);
                $('#ap-com-dropzone').hide();
                $('#ap-com-preview-img').attr('src', previewUrl).show();
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

            function renderResult(data) {
                lastComunicadoId = data.comunicado_id;
                lastFailed = [];
                var $list = $comResult.find('.ap-com-result-list').empty();
                $.each(data.resultados, function(_, r) {
                    if (!r.ok) { lastFailed.push(r.id); }
                    $('<li>').addClass(r.ok ? 'is-ok' : 'is-error')
                        .append($('<strong>').text((r.ok ? '✓ ' : '✗ ') + r.nombre))
                        .append(r.ok ? $('<span>').text(' — ' + i18n.com_ok) : $('<span>').text(' — ' + r.error))
                        .appendTo($list);
                });
                $comResult.find('.ap-com-result-title').text(
                    data.ok === data.total ? fmt(i18n.com_all_ok, data.total) : fmt(i18n.com_some_fail, data.ok, data.total)
                ).toggleClass('is-error', data.ok !== data.total);
                $('#ap-com-retry').toggle(lastFailed.length > 0);
                if (data.historial_html) { $('#ap-com-historial').html(data.historial_html); }
                $comForm.hide();
                $comResult.show();
                $('html, body').animate({ scrollTop: $comResult.offset().top - 80 }, 200);
            }

            function publish(formData, $btn, count) {
                if (!window.confirm(fmt(i18n.com_confirm, count))) { return; }
                var original = $btn.html();
                $btn.prop('disabled', true).html('<span class="ap-spinner"></span>' + escapeHtml(i18n.com_sending));
                formData.append('action', 'ap_chat_publicar');
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
                        renderResult(res.data);
                    } else {
                        showComAlert('error', (res && res.data && res.data.message) || i18n.error);
                    }
                }).fail(function(xhr) {
                    var msg = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
                    showComAlert('error', msg || i18n.com_net_error);
                }).always(function() {
                    $btn.prop('disabled', false).html(original);
                    updateSelection();
                });
            }

            $comForm.on('submit', function(e) {
                e.preventDefault();
                var hasFile = $comFile[0].files && $comFile[0].files.length > 0;
                if (!$.trim($comMsg.val()) && !hasFile) {
                    showComAlert('error', i18n.com_empty);
                    return;
                }
                var n = selectedSpaces().length;
                if (!n) { return; }
                publish(new FormData(this), $comSubmit, n);
            });

            function retry(comunicadoId, spaces, $btn) {
                var fd = new FormData();
                fd.append('comunicado_id', comunicadoId);
                $.each(spaces, function(_, id) { fd.append('espacios[]', id); });
                publish(fd, $btn, spaces.length);
            }

            $('#ap-com-retry').on('click', function() {
                retry(lastComunicadoId, lastFailed, $(this));
            });
            $(document).on('click', '.ap-com-retry-old', function() {
                retry($(this).data('comunicado'), $(this).data('espacios') || [], $(this));
            });

            $('#ap-com-new').on('click', function() {
                $comForm[0].reset();
                clearImage();
                updatePreview();
                updateSelection();
                $comAlert.hide();
                $comResult.hide();
                $comForm.show();
            });

            updatePreview();
            updateSelection();
        }
    });

})(jQuery);
