/**
 * Área Profesores - Backend Admin JavaScript
 * Handles quick AJAX saving of the etapa/tutoría and Dirección of each teacher.
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        function saveTeacherAjax(userId, asignacion, $statusElem) {
            $statusElem.text('Guardando...').css('color', '#64748b').show();

            $.ajax({
                url: AreaProfesoresAdminData.ajax_url,
                type: 'POST',
                data: {
                    action: 'ap_admin_save_teacher',
                    nonce: AreaProfesoresAdminData.nonce,
                    user_id: userId,
                    asignacion: asignacion
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

        // "Come en el colegio" (auto-save)
        $('.ap-come-check').on('change', function() {
            var userId = $(this).data('user-id');
            var $st = $('#status-come-' + userId);
            $st.stop(true, true).show().text('…').css('color', '#64748b');
            $.post(AreaProfesoresAdminData.ajax_url, {
                action: 'ap_admin_save_teacher',
                nonce: AreaProfesoresAdminData.nonce,
                user_id: userId,
                come: this.checked ? '1' : '0'
            }, function(r) {
                $st.text(r && r.success ? '✓' : 'Error').css('color', r && r.success ? '#10b981' : '#ef4444');
                setTimeout(function() { $st.fadeOut(400); }, 1500);
            }, 'json').fail(function() { $st.text('Error de red').css('color', '#ef4444'); });
        });

        // Etapa / tutoría select change (auto-save)
        $('.ap-tutoria-select').on('change', function() {
            var userId = $(this).data('user-id');
            saveTeacherAjax(userId, $(this).val(), $('#status-' + userId));
        });

        // "Secretaría" toggle (recibe los avisos de las familias y ve a todos los alumnos)
        $('.ap-secretaria-toggle').on('change', function() {
            var $toggle = $(this);
            var userId = $toggle.data('user-id');
            var $status = $('#status-sec-' + userId);
            $status.text('Guardando...').css('color', '#64748b').show();
            $.post(AreaProfesoresAdminData.ajax_url, {
                action: 'ap_admin_save_secretaria',
                nonce: AreaProfesoresAdminData.nonce,
                user_id: userId,
                is_secretaria: $toggle.is(':checked') ? 1 : 0
            }, null, 'json').done(function(r) {
                $status.text(r && r.success ? '✓ Guardado' : 'Error').css('color', r && r.success ? '#10b981' : '#ef4444');
                if (r && r.success) { setTimeout(function() { $status.fadeOut(400); }, 2000); }
            }).fail(function() {
                $status.text('Error de red').css('color', '#ef4444');
            });
        });

        // "Dirección" toggle (puede publicar comunicados en Google Chat)
        $('.ap-direccion-toggle').on('change', function() {
            var $toggle = $(this);
            var userId = $toggle.data('user-id');
            var $status = $('#status-dir-' + userId);

            $status.text('Guardando...').css('color', '#64748b').show();
            $.post(AreaProfesoresAdminData.ajax_url, {
                action: 'ap_admin_save_direccion',
                nonce: AreaProfesoresAdminData.nonce,
                user_id: userId,
                is_direccion: $toggle.is(':checked') ? 1 : 0
            }, null, 'json').done(function(response) {
                if (response && response.success) {
                    $status.text('✓ Guardado').css('color', '#10b981');
                    setTimeout(function() { $status.fadeOut(400); }, 2000);
                } else {
                    $status.text('Error').css('color', '#ef4444');
                }
            }).fail(function() {
                $status.text('Error de red').css('color', '#ef4444');
            });
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

/* Tablas ordenables */
(function () {
    // Sortable tables (.ap-sortable): click a column header to sort by it; click again to reverse.
    // A cell can give its own sort value in data-sort (e.g. the course order). Empty values go last.
    (function () {
        var valor = function (row, i) {
            var td = row.children[i];
            if (!td) { return ''; }
            return td.hasAttribute('data-sort') ? td.getAttribute('data-sort') : td.textContent.replace(/\s+/g, ' ').trim();
        };
        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.ap-sortable .ap-sort-btn') : null;
            if (!btn) { return; }
            var th = btn.closest('th'), table = th.closest('table'), tbody = table.tBodies[0];
            if (!tbody) { return; }
            var i = Array.prototype.indexOf.call(th.parentNode.children, th);
            var asc = th.getAttribute('aria-sort') !== 'ascending';
            Array.prototype.forEach.call(th.parentNode.children, function (h) { if (h.hasAttribute('aria-sort')) { h.setAttribute('aria-sort', 'none'); } });
            th.setAttribute('aria-sort', asc ? 'ascending' : 'descending');
            var filas = Array.prototype.slice.call(tbody.rows);
            filas.sort(function (a, b) {
                var x = valor(a, i), y = valor(b, i);
                var vx = x === '' || x === '—', vy = y === '' || y === '—';
                if (vx !== vy) { return vx ? 1 : -1; }
                var c = x.localeCompare(y, 'es', { numeric: true, sensitivity: 'base' });
                return asc ? c : -c;
            });
            filas.forEach(function (f) { tbody.appendChild(f); });
        });
    })();
})();
