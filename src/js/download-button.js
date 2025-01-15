document.addEventListener('DOMContentLoaded', function () {
    const grid = document.getElementById('plugin-repo-grid');
    if (!grid) {
        console.error('Debug: Grid element not found!');
        return;
    }

    grid.addEventListener('click', function (event) {
        event.stopPropagation();

        const button = event.target.closest('.github-download-button a');
        if (!button) return;

        const apiUrl = button.id;

        if (apiUrl.startsWith('https://api.github.com/repos')) {
            event.preventDefault();

            fetch(apiUrl, {
                headers: {
                    Accept: 'application/vnd.github.v3+json',
                    'User-Agent': 'GitHub-Latest-Release-Fetcher',
                },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`Network error: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then((data) => {
                    if (data?.assets?.length > 0) {
                        const downloadUrl = data.assets[0].browser_download_url;
                        window.location.href = downloadUrl;
                    } else {
                        alert('No downloadable assets found in the latest release.');
                        console.error('Debug: No assets available in the release data.');
                    }
                })
                .catch((error) => {
                    console.error('Debug: Error fetching release data:', error);
                    alert('Failed to fetch release information. Please try again later.');
                });
        } else {
            // Handle non-GitHub-hosted plugins (direct download URL)
            event.preventDefault();
            button.href = apiUrl; // Update the href
            window.location.href = apiUrl; // Redirect to the download URL
        }
    });
});
