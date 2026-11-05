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

    });

})(jQuery);
