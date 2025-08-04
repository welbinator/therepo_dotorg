<?php

function therepo_social_share_shortcode($atts) {
	

	$share_url   = urlencode(get_permalink());
	$title       = urlencode(get_the_title());

	ob_start();
	?>
	<div class="bg-primary/5 p-6 rounded-xl border border-primary/10 border-gray-200">
		<h3 class="font-medium mb-2 text-center">Share this on social!</h3>
		<br />
		<div class="flex justify-center gap-3">
			<a href="https://twitter.com/intent/tweet?url=<?= $share_url ?>&text=<?= $title ?>"
			   target="_blank" rel="noopener noreferrer"
			   class="inline-flex items-center justify-center gap-2 whitespace-nowrap text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 border border-input bg-background hover:bg-accent hover:text-accent-foreground rounded-full p-3 h-10 w-10">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="blue" viewBox="0 0 24 24">
					<path d="M16.99 0H20.298L13.071 8.26L21.573 19.5H14.916L9.702 12.683L3.736 19.5H0.426L8.156 10.665L0 0H6.826L11.539 6.231L16.99 0ZM15.829 17.52H17.662L5.83 1.876H3.863L15.829 17.52Z"/>
				</svg>
				<span class="sr-only">Share on X</span>
			</a>
			<a href="https://www.facebook.com/sharer/sharer.php?u=<?= $share_url ?>"
			   target="_blank" rel="noopener noreferrer"
			   class="inline-flex items-center justify-center gap-2 whitespace-nowrap text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 border border-input bg-background hover:bg-accent hover:text-accent-foreground rounded-full p-3 h-10 w-10">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="blue" viewBox="0 0 24 24">
					<path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
				</svg>
				<span class="sr-only">Share on Facebook</span>
			</a>
			<a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $share_url ?>"
			   target="_blank" rel="noopener noreferrer"
			   class="inline-flex items-center justify-center gap-2 whitespace-nowrap text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 border border-input bg-background hover:bg-accent hover:text-accent-foreground rounded-full p-3 h-10 w-10">
				<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="blue" viewBox="0 0 24 24">
					<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
				</svg>
				<span class="sr-only">Share on LinkedIn</span>
			</a>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode('therepo_share_buttons', 'therepo_social_share_shortcode');