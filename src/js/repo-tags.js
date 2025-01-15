import 'select2';
import 'select2/dist/css/select2.min.css';

document.addEventListener('DOMContentLoaded', function () {
    const tagsInput = document.getElementById('tags');
    if (!tagsInput) {
        console.error('Tags input field not found.');
        return;
    }

    

    if (typeof jQuery.fn.select2 !== 'function') {
        console.error('Select2 is not loaded.');
        return;
    }

    // Initialize Select2
    jQuery(tagsInput).select2({
        ajax: {
            url: RepoCategories.ajax_url,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    action: 'get_tags',
                    q: params.term,
                };
            },
            processResults: function (data) {
                return {
                    results: data,
                };
            },
            cache: true,
        },
        tags: true,
        placeholder: 'Start typing to search or add tags',
        minimumInputLength: 2,
    });

});

