document.addEventListener('DOMContentLoaded', function () {
	var hash = window.location.hash.substring(1); // Remove the '#' 
	var params = new URLSearchParams(hash);
	var message = params.get('message');

	if (message) {
		var messageDiv = document.getElementById('custom-message');
		if (messageDiv) {
			if (message === 'not-inducted-volunteer' || message === 'individual-user') {
				messageDiv.innerHTML = `
					<p class="fw-600 fz-16 mb-6">You are not registered as a volunteer with us.</p>
					<p class="fw-400 fz-16 mt-0 mb-24">To set up a collection camp, please take a moment to fill out the volunteer registration form below. We can't wait to have you on board!</p>
				`;
			} else if (message === 'past-collection-data') {
				messageDiv.innerHTML = `
					<div class="w-520 mt-30 m-auto">
						<p class="fw-600 fz-16 mb-11">Goonj Collection Camp</p>
						<p class="fw-400 fz-16 mt-0 mb-24">It seems like you have created collection camps in the past. Would you like to duplicate the location details from your last collection camp?</p>
					</div>
				`;
			}
		}
	}
});
