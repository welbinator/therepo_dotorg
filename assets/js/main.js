document.addEventListener('DOMContentLoaded', function () {
    // Attach an event listener to the parent container
    const grid = document.getElementById('plugin-repo-grid');

    grid.addEventListener('click', function (event) {
        // Look for the <a> element inside the .github-download-button wrapper
        const button = event.target.closest('.github-download-button a');

        if (button) {
            event.preventDefault(); // Prevent the default link behavior

            const apiUrl = button.id;

            // Debugging
            console.log("Debug: Button clicked.");
            console.log("Debug: API URL from button ID: ", apiUrl);

            // Check if the API URL is valid
            if (!apiUrl.startsWith('https://api.github.com/repos')) {
                alert('Invalid GitHub API URL.');
                console.error('Debug: Invalid API URL detected.');
                return;
            }

            // Fetch the latest release details from GitHub
            fetch(apiUrl, {
                headers: {
                    'Accept': 'application/vnd.github.v3+json',
                    'User-Agent': 'GitHub-Latest-Release-Fetcher'
                }
            })
            .then(response => {
                console.log("Debug: Fetch response status: ", response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                console.log("Debug: GitHub API response data: ", data);

                if (data && data.assets && data.assets.length > 0) {
                    // Get the first asset's download URL
                    const downloadUrl = data.assets[0].browser_download_url;

                    console.log("Debug: Browser download URL: ", downloadUrl);

                    // Trigger the download
                    window.location.href = downloadUrl;
                } else {
                    alert('No downloadable assets found in the latest release.');
                    console.error("Debug: No assets available in the release data.");
                }
            })
            .catch(error => {
                console.error('Debug: Error fetching release data:', error);
                alert('Failed to fetch release information. Please try again later.');
            });
        }
    });
});