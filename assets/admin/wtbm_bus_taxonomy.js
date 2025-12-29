(function ($) {
    $(document).ready(function () {

        function closeTermModal() {
            $('#mpcrbm_term_condition_modal').hide();
            $('#mpcrbm_term_condition_key').val('');
            $('#mpcrbm_term_condition_title').val('');
            if (tinymce.get('mpcrbm_term_condition_answer_editor')) {
                tinymce.get('mpcrbm_term_condition_answer_editor').setContent('');
            } else {
                $('#mpcrbm_term_condition_answer_editor').val('');
            }
        }

        $(document).on('click', '#mpcrbm_add_term_condition_btn',function() {
            $('#mpcrbm_term_modal_title').text('Add Term & Condition');
            closeTermModal();

            let targetBtn = $('#mpcrbm_save_term_condition_btn');

            if (targetBtn.length) {
                targetBtn.attr('id', 'mpcrbm_save_term_condition_btn');
            }
            $('#mpcrbm_term_condition_modal').show();
        });

        $(document).on( 'click', '#mpcrbm_cancel_term_condition_btn', function() {
            closeTermModal();
        });

        $(document).on('click', '.mpcrbm_edit_term', function() {
            const row = $(this).closest('tr');
            $('#mpcrbm_term_condition_key').val(row.data('key'));
            $('#mpcrbm_term_condition_title').val(row.find('.faq-title').text());
            $('#mpcrbm_term_modal_title').text('Edit Term & Condition');
            $('#mpcrbm_term_condition_modal').show();

            let targetBtn = $('#mpcrbm_save_term_condition_btn');

            if (targetBtn.length) {
                targetBtn.attr('id', 'mpcrbm_save_term_condition_btn');
            }

            const answer = row.find('.faq-answer').text();
            setTimeout(() => {
                if (tinymce.get('mpcrbm_term_condition_answer_editor')) {
                    tinymce.get('mpcrbm_term_condition_answer_editor').setContent(answer);
                } else {
                    $('#mpcrbm_term_condition_answer_editor').val(answer);
                }
            }, 300);
        });

        $(document).on( 'click','#mpcrbm_save_term_condition_btn', function( e ) {
            alert("ok");
            e.preventDefault();
            const title = $('#mpcrbm_term_condition_title').val().trim();
            let answer = '';
            if (tinymce.get('mpcrbm_term_condition_answer_editor')) {
                answer = tinymce.get('mpcrbm_term_condition_answer_editor').getContent();
            } else {
                answer = $('#mpcrbm_term_condition_answer_editor').val();
            }
            const key = $('#mpcrbm_term_condition_key').val();

            if (title === '' || answer === '') {
                alert('Please fill all fields.');
                return;
            }

            $.post( wbtm_admin_var.url, {
                action: 'mpcrbm_save_term_and_condition',
                title: title,
                answer: answer,
                key: key,
                nonce: wbtm_admin_var.nonce
            }, function(response){
                if (response.success) location.reload();
                else alert(response.data);
            });
        });

        $(document).on('click', '.mpcrbm_delete_term', function() {
            if (!confirm('Are you sure you want to delete this FAQ?')) return;
            const key = $(this).closest('tr').data('key');
            $.post( wbtm_admin_var.url, {
                action: 'mpcrbm_delete_term',
                key: key,
                nonce: wbtm_admin_var.nonce
            }, function(response){
                if (response.success) location.reload();
                else alert(response.data);
            });
        });


        $(document).on('click', '.mpcrbm_remove_term_condition', function() {
            let $this = $(this);
            $this.text( 'removing...');
            let item = $(this).closest('.mpcrbm_selected_item');
            let key = item.data('key');
            let title = item.data('title');

            let html = `
            <div class="mpcrbm_faq_item" 
            data-key="${key}"
            data-key="${title}"
            >
                <div class="mpcrbm_faq_title">${title}</div>
                <button type="button" class="button button-small mpcrbm_add_faq">Add</button>
            </div>`;

            updateTermMeta( $this, item, 'remove', 'mpcrbm_all_term_condition', html  );

        });

        function updateTermMeta( clickBtn, item, action, append_section, html ) {

            let post_id = $('[name="mpcrbm_post_id"]').val();
            let data = [];

            $('.mpcrbm_selected_item').each(function() {
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

            let faq_keys = JSON.stringify(data);
            $('#mpcrbm_added_faq_input').val(JSON.stringify( faq_keys ));

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'mpcrbm_save_added_term_condition',
                    post_id: post_id,
                    mpcrbm_added_term: faq_keys,
                    nonce: mpcrbm_admin_nonce.nonce
                },
                success: function (response) {
                    if (response.success) {
                        // alert('✅ FAQs saved successfully');

                        console.log(append_section);
                        clickBtn.text( action );
                        $('.'+append_section).append(html);
                        item.remove();
                    } else {
                        alert('❌ Error saving FAQs:', response.data.message);
                    }
                }
            });
        }

    });
})(jQuery);