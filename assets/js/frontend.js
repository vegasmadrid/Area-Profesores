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
    });

})(jQuery);
