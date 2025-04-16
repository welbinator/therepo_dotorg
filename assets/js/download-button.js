document.addEventListener("DOMContentLoaded",(function(){const e=document.getElementById("plugin-repo-grid");e?e.addEventListener("click",(function(e){e.stopPropagation();const t=e.target.closest(".github-download-button a");if(!t)return;const o=t.id;if(o.startsWith("https://api.github.com/repos")){e.preventDefault();const t=`/wp-admin/admin-ajax.php?action=get_release_data&url=${encodeURIComponent(o)}`;fetch(t).then((e=>{if(!e.ok)throw new Error(`Network error: ${e.statusText}`);return e.json()})).then((e=>{e.success&&e.data?.download_url?window.location.href=e.data.download_url:(alert("No downloadable assets found in the latest release."),console.error("Debug: No assets available in the response data."))})).catch((e=>{console.error("Debug: Error fetching release data:",e),alert("Failed to fetch release information. Please try again later.")}))}else e.preventDefault(),t.href=o,window.location.href=o})):console.error("Debug: Grid element not found!")}));