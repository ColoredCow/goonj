<?php
// Retrieve the recentCamp data from the session
$recentCampData = $_SESSION['recentCampData'] ?? null;
$contactId = $_SESSION['contactId'] ?? null;

$redirectBaseUrl = get_home_url() . "/collection-camp-form/#?";

$queryParams = [
	'source_contact_id' => $recentCampData['source_contact_id'] ?? '',
	'Collection_Camp_Intent.District' => $recentCampData['custom_72'] ?? '',
	'Collection_Camp_Intent.State' => $recentCampData['custom_71'] ?? '',
	'Collection_Camp_Intent.Start_Date' => $recentCampData['custom_73'] ?? '',
	'Collection_Camp_Intent.End_Date' => $recentCampData['custom_74'] ?? '',
	'Collection_Camp_Intent.Location_Area_of_camp' => $recentCampData['custom_69'] ?? '',
	'message' => 'collection-camp-page'
];

$redirectUrl = $redirectBaseUrl . http_build_query($queryParams);
$noDetailsRedirectUrl = get_home_url() . "/collection-camp-form/#?" . http_build_query([
	'source_contact_id' => $contactId,
	'message' => 'collection-camp-page'
]);

?>

<div class="m-auto w-520 pl-27">
	<button class="button button-primary w-520 mb-12 border-none font-sans fz-16 br-4 fw-600">
		<a href="<?php echo esc_url($redirectUrl); ?>" class="text-white text-decoration-none">
			Yes, use details from last camp
		</a>
	</button>
	<button class="button button-primary w-520 border-none font-sans fz-16 bg-white br-4 red-border fw-600">
		<a href="<?php echo esc_url($noDetailsRedirectUrl); ?>" class="text-light-red text-decoration-none">
			No, the details are different
		</a>
	</button>
</div>

<?php
?>
