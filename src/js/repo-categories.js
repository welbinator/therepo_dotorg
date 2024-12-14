import 'select2';
import 'select2/dist/css/select2.min.css';

document.addEventListener('DOMContentLoaded', function () {
    console.log('Document loaded.');

    const categoriesInput = document.getElementById('categories');
    if (!categoriesInput) {
        console.error('Categories input field not found.');
        return;
    }

    console.log('Categories input field found.');

    // Ensure Select2 is available
    if (typeof jQuery.fn.select2 !== 'function') {
        console.error('Select2 is not loaded.');
        return;
    }

    console.log('Select2 is loaded.');

    // Initialize Select2
    jQuery(categoriesInput).select2({
        ajax: {
            url: RepoCategories.ajax_url,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    action: 'get_categories',
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
        placeholder: 'Start typing to search or add categories',
        minimumInputLength: 2,
    });

    console.log('Select2 initialized.');
});
