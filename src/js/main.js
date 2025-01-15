document.addEventListener('DOMContentLoaded', function () {
    // Attach event listener to the plugin-repo grid
    const grid = document.getElementById('plugin-repo-grid');
    if (!grid) {
        console.error('Debug: Grid element not found!');
        return;
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

    // Form toggle functionality
    const hostedOnGitHubRadios = document.querySelectorAll('input[name="hosted_on_github"]');
    const githubFields = document.getElementById('github-fields');
    const downloadUrlField = document.getElementById('download-url-field');

    const hostedOnGitHub = document.getElementById('hosted_on_github');

    function toggleFields() {
        const selectedValue = hostedOnGitHub.value;

        if (selectedValue === 'yes') {
            githubFields.style.display = 'flex';
            downloadUrlField.style.display = 'none';
            document.getElementById('github_username').setAttribute('required', 'required');
            document.getElementById('github_repo').setAttribute('required', 'required');
            document.getElementById('download_url').removeAttribute('required');
        } else if (selectedValue === 'no') {
            githubFields.style.display = 'none';
            downloadUrlField.style.display = 'block';
            document.getElementById('github_username').removeAttribute('required');
            document.getElementById('github_repo').removeAttribute('required');
            document.getElementById('download_url').setAttribute('required', 'required');
        }
    }

    hostedOnGitHubRadios.forEach(function (radio) {
        radio.addEventListener('change', toggleFields);
    });

    toggleFields();
});
