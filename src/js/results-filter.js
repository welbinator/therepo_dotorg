// Event listeners for search and filters
const grid = document.getElementById('plugin-repo-grid');
const searchField = document.getElementById('plugin-repo-search');
const typeFilter = document.getElementById('plugin-repo-type-filter');
const categoryFilter = document.getElementById('plugin-repo-category-filter');

if (searchField && typeFilter && categoryFilter && grid) {
    function updateGrid() {
        const search = searchField.value;
        const type = typeFilter.value;
        const category = categoryFilter.value;

        const xhr = new XMLHttpRequest();
        xhr.open(
            'GET',
            `/wp-admin/admin-ajax.php?action=filter_plugins&search=${encodeURIComponent(
                search
            )}&type=${encodeURIComponent(type)}&category=${encodeURIComponent(category)}`,
            true
        );
        xhr.onload = function () {
            if (xhr.status === 200) {
                grid.innerHTML = xhr.responseText;
            } else {
                grid.innerHTML = '<p class="!text-gray-600">Error loading results.</p>';
            }
        };
        xhr.onerror = function () {
            grid.innerHTML = '<p class="!text-gray-600">Error loading results.</p>';
        };
        xhr.send();
    }

    searchField.addEventListener('input', updateGrid);
    typeFilter.addEventListener('change', updateGrid);
    categoryFilter.addEventListener('change', updateGrid);
}
