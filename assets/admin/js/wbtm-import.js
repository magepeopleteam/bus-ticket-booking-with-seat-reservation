jQuery(document).ready(function($) {
    // File input validation
    $('#wbtm_import_file').on('change', function() {
        var fileName = $(this).val();
        if (fileName) {
            var extension = fileName.substring(fileName.lastIndexOf('.') + 1).toLowerCase();
            if (extension !== 'csv') {
                alert('Please select a CSV file.');
                $(this).val('');
            }
        }
    });

    // Form submission confirmation
    $('form').on('submit', function(e) {
        if (!$('#wbtm_import_file').val()) {
            e.preventDefault();
            alert('Please select a CSV file to import.');
            return false;
        }
        
        if (!confirm('Are you sure you want to import buses? This process cannot be undone.')) {
            e.preventDefault();
            return false;
        }
    });
});
