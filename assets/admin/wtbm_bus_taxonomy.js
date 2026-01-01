(function ($) {
    $(document).ready(function () {

        function closeTermModal() {
            $('#wtbm_term_condition_modal').hide();
            $('#wtbm_term_condition_key').val('');
            $('#wtbm_term_condition_title').val('');
            if (tinymce.get('wtbm_term_condition_answer_editor')) {
                tinymce.get('wtbm_term_condition_answer_editor').setContent('');
            } else {
                $('#wtbm_term_condition_answer_editor').val('');
            }
        }

        $(document).on('click', '#wtbm_add_term_condition_btn',function() {
            $('#wtbm_term_modal_title').text('Add Term & Condition');
            closeTermModal();

            let targetBtn = $('#wtbm_save_term_condition_btn');

            if (targetBtn.length) {
                targetBtn.attr('id', 'wtbm_save_term_condition_btn');
            }
            $('#wtbm_term_condition_modal').show();
        });

        $(document).on( 'click', '#wtbm_cancel_term_condition_btn', function() {
            closeTermModal();
        });

        $(document).on('click', '.wtbm_edit_term', function() {
            const row = $(this).closest('tr');
            $('#wtbm_term_condition_key').val(row.data('key'));
            $('#wtbm_term_condition_title').val(row.find('.term-title').text());
            $('#wtbm_term_modal_title').text('Edit Term & Condition');
            $('#wtbm_term_condition_modal').show();

            let targetBtn = $('#wtbm_save_term_condition_btn');

            if (targetBtn.length) {
                targetBtn.attr('id', 'wtbm_save_term_condition_btn');
            }

            const answer = row.find('.term-answer').text();
            setTimeout(() => {
                if (tinymce.get('wtbm_term_condition_answer_editor')) {
                    tinymce.get('wtbm_term_condition_answer_editor').setContent(answer);
                } else {
                    $('#wtbm_term_condition_answer_editor').val(answer);
                }
            }, 300);
        });

        $(document).on( 'click','#wtbm_save_term_condition_btn', function( e ) {
            e.preventDefault();
            const title = $('#wtbm_term_condition_title').val().trim();
            let answer = '';
            if (tinymce.get('wtbm_term_condition_answer_editor')) {
                answer = tinymce.get('wtbm_term_condition_answer_editor').getContent();
            } else {
                answer = $('#wtbm_term_condition_answer_editor').val();
            }
            const key = $('#wtbm_term_condition_key').val();

            if (title === '' || answer === '') {
                alert('Please fill all fields.');
                return;
            }

            $.post( wbtm_admin_var.url, {
                action: 'wtbm_save_term_and_condition',
                title: title,
                answer: answer,
                key: key,
                nonce: wbtm_admin_var.nonce
            }, function(response){
                if (response.success) location.reload();
                else alert(response.data);
            });
        });

        $(document).on('click', '.wtbm_delete_term', function() {
            if (!confirm('Are you sure you want to delete this FAQ?')) return;
            const key = $(this).closest('tr').data('key');
            $.post( wbtm_admin_var.url, {
                action: 'wtbm_delete_term',
                key: key,
                nonce: wbtm_admin_var.nonce
            }, function(response){
                if (response.success) location.reload();
                else alert(response.data);
            });
        });


        // ===== ADD TERM =====
        $(document).on('click', '.wtbm_add_term_condition', function() {
            let item = $(this).closest('.wtbm_term_item');
            let $this = $(this);
            $this.text( 'adding...');
            let key = item.data('key');
            let title = item.data('title');

            let html = `
            <div class="wtbm_selected_item" 
            data-key="${key}"
            data-title="${title}"
            >
                <div class="wtbm_term_title">${title}</div>
                <button type="button" class="button button-small  wtbm_remove_term_condition">Remove</button>
            </div>`;

            updateTermMeta( $this, item, 'add', 'wtbm_selected_term_condition', html );

        });
        // ===== REMOVE TERM =====
        $(document).on('click', '.wtbm_remove_term_condition', function() {
            let $this = $(this);
            $this.text( 'removing...');
            let item = $(this).closest('.wtbm_selected_item');
            let key = item.data('key');
            let title = item.data('title');

            let html = `
            <div class="wtbm_term_item" 
            data-key="${key}"
            data-title="${title}"
            >
                <div class="wtbm_term_title">${title}</div>
                <button type="button" class="button button-small wtbm_add_term_condition">Add</button>
            </div>`;

            updateTermMeta( $this, item, 'remove', 'wtbm_all_term_condition', html  );

        });

        function updateTermMeta( clickBtn, item, action, append_section, html ) {

            let post_id = $('[name="wbtm_post_id"]').val();
            let data = [];

            $('.wtbm_selected_item').each(function() {
                let key = $(this).data('key');
                data.push(key);
            });
            let key = $(item).data('key');

            if ( action === 'add' ) {
                if (!data.includes(key)) {
                    data.push(key);
                }
            } else if (action === 'remove') {
                let index = data.indexOf(key);
                if (index !== -1) {
                    data.splice(index, 1);
                }
            }

            let term_keys = JSON.stringify(data);
            $('#wtbm_added_term_input').val(JSON.stringify( term_keys ));

            $.ajax({
                url: wbtm_ajax_url,
                method: 'POST',
                data: {
                    action: 'wtbm_save_added_term_condition',
                    post_id: post_id,
                    wtbm_added_term: term_keys,
                    nonce: wbtm_nonce
                },
                success: function (response) {
                    if (response.success) {
                        // alert('✅ FAQs saved successfully');

                        // console.log(append_section);
                        clickBtn.text( action );
                        $('.'+append_section).append(html);
                        item.remove();
                    } else {
                        alert('❌ Error saving FAQs:', response.data.message);
                    }
                }
            });
        }

        function updateHiddenField() {
            let selected = [];
            let post_id = $('[name="wbtm_post_id"]').val();

            $('.wtbm_bus_feature_checkbox:checked').each(function () {
                selected.push($(this).data('term-id'));
            });

            $('#wtbm_added_feature').val(selected.join(','));

            jQuery.ajax({
                url: wbtm_ajax_url,
                type: 'POST',
                data: {
                    action: 'wtbm_save_bus_features',
                    post_id: post_id,
                    nonce: wbtm_nonce,
                    features: jQuery('#wtbm_added_feature').val(),
                },
                success: function (response) {
                    console.log(response);
                }
            });
        }

        $(document).on('change', '.wtbm_bus_feature_checkbox', function () {
            updateHiddenField();
        });




    });
})(jQuery);