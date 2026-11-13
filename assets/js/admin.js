/**
 * Área Profesores - Backend Admin JavaScript
 * Handles quick AJAX toggling of teacher status and tutoría assignment.
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        function saveTeacherAjax(userId, isProfesor, tutoria, $statusElem) {
            $statusElem.text('Guardando...').css('color', '#64748b').show();

            $.ajax({
                url: AreaProfesoresAdminData.ajax_url,
                type: 'POST',
                data: {
                    action: 'ap_admin_save_teacher',
                    nonce: AreaProfesoresAdminData.nonce,
                    user_id: userId,
                    is_profesor: isProfesor ? 1 : 0,
                    tutoria: tutoria
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $statusElem.text('✓ Guardado').css('color', '#10b981');
                        setTimeout(function() {
                            $statusElem.fadeOut(400);
                        }, 2000);
                    } else {
                        $statusElem.text('Error').css('color', '#ef4444');
                    }
                },
                error: function() {
                    $statusElem.text('Error de red').css('color', '#ef4444');
                }
            });
        }

        // Teacher toggle switch change
        $('.ap-teacher-toggle').on('change', function() {
            var $toggle = $(this);
            var userId = $toggle.data('user-id');
            var isProfesor = $toggle.is(':checked');
            var $row = $toggle.closest('.ap-user-row');
            var tutoria = $row.find('.ap-tutoria-select').val();
            var $status = $('#status-' + userId);

            if (isProfesor) {
                $row.addClass('is-teacher');
            } else {
                $row.removeClass('is-teacher');
            }

            saveTeacherAjax(userId, isProfesor, tutoria, $status);
        });

        // =========================================================================
        // Solicitudes de Acceso (Aprobar, Rechazar, Eliminar)
        // =========================================================================
        function updateMenuPendingBadge(count) {
            var $menuBadge = $('#toplevel_page_area-profesores .plugin-count');
            if (count > 0) {
                if ($menuBadge.length) {
                    $menuBadge.text(count);
                } else {
                    $('#toplevel_page_area-profesores .wp-menu-name').append(
                        ' <span class="update-plugins count-' + count + '"><span class="plugin-count">' + count + '</span></span>'
                    );
                }
            } else {
                $('#toplevel_page_area-profesores .update-plugins').remove();
            }
        }

        // Aprobar Solicitud
        $(document).on('click', '.ap-btn-approve-solicitud', function() {
            var $btn = $(this);
            var id = $btn.data('id');
            var $row = $btn.closest('.ap-solicitud-row');
            var tutoria = $row.find('.ap-solicitud-tutoria-select').val() || '';

            $btn.prop('disabled', true).text('Aprobando...');

            $.ajax({
                url: AreaProfesoresAdminData.ajax_url,
                type: 'POST',
                data: {
                    action: 'ap_admin_approve_solicitud',
                    nonce: AreaProfesoresAdminData.nonce,
                    solicitud_id: id,
                    tutoria: tutoria
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        $row.removeClass('ap-row-estado-pendiente')
                            .addClass('ap-row-estado-aprobada');
                        $row.find('.ap-status-pill')
                            .removeClass('ap-status-warning')
                            .addClass('ap-status-success')
                            .text('Aprobada');
                        $btn.replaceWith('<span style="color:#059669; font-weight:700;">✓ Aprobada</span>');
                        $row.find('.ap-btn-reject-solicitud').remove();

                        updateMenuPendingBadge(res.data.pending_count);
                    } else {
                        alert(res.data.message || 'Error al aprobar');
                        $btn.prop('disabled', false).text('Aprobar');
                    }
                },
                error: function() {
                    alert('Error de conexión');
                    $btn.prop('disabled', false).text('Aprobar');
                }
            });
        });

        // Rechazar Solicitud
        $(document).on('click', '.ap-btn-reject-solicitud', function() {
            if (!confirm('¿Rechazar esta solicitud de acceso?')) {
                return;
            }

            var $btn = $(this);
            var id = $btn.data('id');
            var $row = $btn.closest('.ap-solicitud-row');

            $btn.prop('disabled', true).text('Rechazando...');

            $.ajax({
                url: AreaProfesoresAdminData.ajax_url,
                type: 'POST',
                data: {
                    action: 'ap_admin_reject_solicitud',
                    nonce: AreaProfesoresAdminData.nonce,
                    solicitud_id: id
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        $row.removeClass('ap-row-estado-pendiente')
                            .addClass('ap-row-estado-rechazada');
                        $row.find('.ap-status-pill')
                            .removeClass('ap-status-warning')
                            .addClass('ap-status-danger')
                            .text('Rechazada');
                        $btn.replaceWith('<span style="color:#991b1b; font-weight:700;">Rechazada</span>');
                        $row.find('.ap-btn-approve-solicitud').remove();

                        updateMenuPendingBadge(res.data.pending_count);
                    } else {
                        alert(res.data.message || 'Error al rechazar');
                        $btn.prop('disabled', false).text('Rechazar');
                    }
                },
                error: function() {
                    alert('Error de conexión');
                    $btn.prop('disabled', false).text('Rechazar');
                }
            });
        });

        // Eliminar Solicitud
        $(document).on('click', '.ap-btn-delete-solicitud', function() {
            if (!confirm('¿Estás seguro de que deseas eliminar esta solicitud permanentemente?')) {
                return;
            }

            var $btn = $(this);
            var id = $btn.data('id');
            var $row = $btn.closest('.ap-solicitud-row');

            $.ajax({
                url: AreaProfesoresAdminData.ajax_url,
                type: 'POST',
                data: {
                    action: 'ap_admin_delete_solicitud',
                    nonce: AreaProfesoresAdminData.nonce,
                    solicitud_id: id
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        $row.fadeOut(250, function() {
                            $(this).remove();
                        });
                        updateMenuPendingBadge(res.data.pending_count);
                    } else {
                        alert(res.data.message || 'Error al eliminar');
                    }
                }
            });
        });

        // Tutoría select change
        $('.ap-tutoria-select').on('change', function() {
            var $select = $(this);
            var userId = $select.data('user-id');
            var tutoria = $select.val();
            var $row = $select.closest('.ap-user-row');
            var isProfesor = $row.find('.ap-teacher-toggle').is(':checked');
            var $status = $('#status-' + userId);

            saveTeacherAjax(userId, isProfesor, tutoria, $status);
        });

        // =========================================================================
        // Solicitudes de Acceso (Aprobar, Rechazar, Eliminar)
        // =========================================================================
        function updateMenuPendingBadge(count) {
            var $menuBadge = $('#toplevel_page_area-profesores .plugin-count');
            if (count > 0) {
                if ($menuBadge.length) {
                    $menuBadge.text(count);
                } else {
                    $('#toplevel_page_area-profesores .wp-menu-name').append(
                        ' <span class="update-plugins count-' + count + '"><span class="plugin-count">' + count + '</span></span>'
                    );
                }
            } else {
                $('#toplevel_page_area-profesores .update-plugins').remove();
            }
        }

        // Aprobar Solicitud
        $(document).on('click', '.ap-btn-approve-solicitud', function() {
            var $btn = $(this);
            var id = $btn.data('id');
            var $row = $btn.closest('.ap-solicitud-row');
            var tutoria = $row.find('.ap-solicitud-tutoria-select').val() || '';

            $btn.prop('disabled', true).text('Aprobando...');

            $.ajax({
                url: AreaProfesoresAdminData.ajax_url,
                type: 'POST',
                data: {
                    action: 'ap_admin_approve_solicitud',
                    nonce: AreaProfesoresAdminData.nonce,
                    solicitud_id: id,
                    tutoria: tutoria
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        $row.removeClass('ap-row-estado-pendiente')
                            .addClass('ap-row-estado-aprobada');
                        $row.find('.ap-status-pill')
                            .removeClass('ap-status-warning')
                            .addClass('ap-status-success')
                            .text('Aprobada');
                        $btn.replaceWith('<span style="color:#059669; font-weight:700;">✓ Aprobada</span>');
                        $row.find('.ap-btn-reject-solicitud').remove();

                        updateMenuPendingBadge(res.data.pending_count);
                    } else {
                        alert(res.data.message || 'Error al aprobar');
                        $btn.prop('disabled', false).text('Aprobar');
                    }
                },
                error: function() {
                    alert('Error de conexión');
                    $btn.prop('disabled', false).text('Aprobar');
                }
            });
        });

        // Rechazar Solicitud
        $(document).on('click', '.ap-btn-reject-solicitud', function() {
            if (!confirm('¿Rechazar esta solicitud de acceso?')) {
                return;
            }

            var $btn = $(this);
            var id = $btn.data('id');
            var $row = $btn.closest('.ap-solicitud-row');

            $btn.prop('disabled', true).text('Rechazando...');

            $.ajax({
                url: AreaProfesoresAdminData.ajax_url,
                type: 'POST',
                data: {
                    action: 'ap_admin_reject_solicitud',
                    nonce: AreaProfesoresAdminData.nonce,
                    solicitud_id: id
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        $row.removeClass('ap-row-estado-pendiente')
                            .addClass('ap-row-estado-rechazada');
                        $row.find('.ap-status-pill')
                            .removeClass('ap-status-warning')
                            .addClass('ap-status-danger')
                            .text('Rechazada');
                        $btn.replaceWith('<span style="color:#991b1b; font-weight:700;">Rechazada</span>');
                        $row.find('.ap-btn-approve-solicitud').remove();

                        updateMenuPendingBadge(res.data.pending_count);
                    } else {
                        alert(res.data.message || 'Error al rechazar');
                        $btn.prop('disabled', false).text('Rechazar');
                    }
                },
                error: function() {
                    alert('Error de conexión');
                    $btn.prop('disabled', false).text('Rechazar');
                }
            });
        });

        // Eliminar Solicitud
        $(document).on('click', '.ap-btn-delete-solicitud', function() {
            if (!confirm('¿Estás seguro de que deseas eliminar esta solicitud permanentemente?')) {
                return;
            }

            var $btn = $(this);
            var id = $btn.data('id');
            var $row = $btn.closest('.ap-solicitud-row');

            $.ajax({
                url: AreaProfesoresAdminData.ajax_url,
                type: 'POST',
                data: {
                    action: 'ap_admin_delete_solicitud',
                    nonce: AreaProfesoresAdminData.nonce,
                    solicitud_id: id
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        $row.fadeOut(250, function() {
                            $(this).remove();
                        });
                        updateMenuPendingBadge(res.data.pending_count);
                    } else {
                        alert(res.data.message || 'Error al eliminar');
                    }
                }
            });
        });

    });

})(jQuery);
