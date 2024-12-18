document.addEventListener('DOMContentLoaded', function () {
    // Attach an event listener to the parent container
    const grid = document.getElementById('plugin-repo-grid');

    if (grid) {
        grid.addEventListener('click', function (event) {
            // Prevent event bubbling
            event.stopPropagation();
        
            // Look for the <a> element inside the .github-download-button wrapper
            const button = event.target.closest('.github-download-button a');
        
            // Exit if no button was clicked
            if (!button) {
                return;
            }
        
            console.log('Debug: Button clicked.');
        
            const apiUrl = button.id;
        
            // Check if the API URL starts with GitHub's API prefix
            if (apiUrl.startsWith('https://api.github.com/repos')) {
                event.preventDefault(); // Prevent the default link behavior
                console.log('Debug: Valid GitHub API URL detected:', apiUrl);
        
                // Fetch the latest release details from GitHub
                fetch(apiUrl, {
                    headers: {
                        'Accept': 'application/vnd.github.v3+json',
                        'User-Agent': 'GitHub-Latest-Release-Fetcher',
                    },
                })
                    .then((response) => {
                        console.log('Debug: Fetch response status: ', response.status);
                        if (!response.ok) {
                            throw new Error(
                                'Network response was not ok: ' + response.statusText
                            );
                        }
                        return response.json();
                    })
                    .then((data) => {
                        console.log('Debug: GitHub API response data: ', data);
                        if (data && data.assets && data.assets.length > 0) {
                            // Get the first asset's download URL
                            const downloadUrl = data.assets[0].browser_download_url;
        
                            console.log('Debug: Browser download URL: ', downloadUrl);
        
                            // Trigger the download
                            window.location.href = downloadUrl;
                        } else {
                            alert(
                                'No downloadable assets found in the latest release.'
                            );
                            console.error(
                                'Debug: No assets available in the release data.'
                            );
                        }
                    })
                    .catch((error) => {
                        console.error('Debug: Error fetching release data:', error);
                        alert(
                            'Failed to fetch release information. Please try again later.'
                        );
                    });
            } else {
                console.log('Debug: Invalid API URL detected:', apiUrl);
                // If the URL is not a GitHub API URL, update the href and let it function normally
                button.setAttribute('href', apiUrl);
            }
        });
        
    }

    // Suggestions and categories functionality
    const categoriesInput = document.getElementById('categories');
    if (categoriesInput) {
        const suggestionsContainer = document.createElement('div');
        suggestionsContainer.id = 'categories-suggestions';
        suggestionsContainer.style.position = 'absolute';
        suggestionsContainer.style.backgroundColor = '#fff';
        suggestionsContainer.style.border = '1px solid #ccc';
        suggestionsContainer.style.zIndex = '1000';
        suggestionsContainer.style.display = 'none';
        document.body.appendChild(suggestionsContainer);

        let activeSuggestions = [];

        function fetchSuggestions(query) {
            if (!query.trim()) {
                suggestionsContainer.style.display = 'none';
                return;
            }

            fetch(`/wp-json/wp/v2/plugin-category?search=${encodeURIComponent(query)}`)
                .then((response) => response.json())
                .then((data) => {
                    activeSuggestions = data.map((item) => item.name);
                    renderSuggestions();
                })
                .catch((error) => {
                    console.error('Error fetching suggestions:', error);
                    suggestionsContainer.style.display = 'none';
                });
        }

        function renderSuggestions() {
            suggestionsContainer.innerHTML = '';
            if (activeSuggestions.length > 0) {
                activeSuggestions.forEach((suggestion) => {
                    const suggestionElement = document.createElement('div');
                    suggestionElement.textContent = suggestion;
                    suggestionElement.style.padding = '8px';
                    suggestionElement.style.cursor = 'pointer';
                    suggestionElement.addEventListener('mousedown', function () {
                        addCategory(suggestion);
                    });
                    suggestionsContainer.appendChild(suggestionElement);
                });
                const rect = categoriesInput.getBoundingClientRect();
                suggestionsContainer.style.left = `${rect.left + window.pageXOffset}px`;
                suggestionsContainer.style.top = `${rect.bottom + window.pageYOffset}px`;
                suggestionsContainer.style.width = `${rect.width}px`;
                suggestionsContainer.style.display = 'block';
            } else {
                suggestionsContainer.style.display = 'none';
            }
        }

        function addCategory(category) {
            const existingCategories = categoriesInput.value
                .split(',')
                .map((item) => item.trim());
            if (!existingCategories.includes(category)) {
                existingCategories.push(category);
                categoriesInput.value = existingCategories
                    .filter((item) => item)
                    .join(', ');
            }
            suggestionsContainer.style.display = 'none';
        }

        categoriesInput.addEventListener('input', function () {
            fetchSuggestions(categoriesInput.value);
        });

        document.addEventListener('click', function (event) {
            if (!suggestionsContainer.contains(event.target) && event.target !== categoriesInput) {
                suggestionsContainer.style.display = 'none';
            }
        });
    }

    // Event listeners for search and filters
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
                }
            };
            xhr.send();
        }

        searchField.addEventListener('input', updateGrid);
        typeFilter.addEventListener('change', updateGrid);
        categoryFilter.addEventListener('change', updateGrid);
    }
});
