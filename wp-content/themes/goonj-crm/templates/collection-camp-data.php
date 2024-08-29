<?php
// Retrieve the recentCamp data from the session
$recentCampData = $_SESSION['recentCampData'] ?? null;
$contactId = $_SESSION['contactId'] ?? null;
$displayName = $_SESSION['displayName'] ?? null;
$contactNumber = $_SESSION['contactNumber'] ?? null;

$redirectBaseUrl = get_home_url() . "/collection-camp/intent/#?";

$queryParams = [
	'Collection_Camp_Core_Details.Contact_Id' => $recentCampData['Collection_Camp_Core_Details.Contact_Id'] ?? '',
	'Collection_Camp_Intent_Details.District' => $recentCampData['Collection_Camp_Intent_Details.District'] ?? '',
	'Collection_Camp_Intent_Details.State' => $recentCampData['Collection_Camp_Intent_Details.State'] ?? '',
	'Collection_Camp_Intent_Details.Location_Area_of_camp' => $recentCampData['Collection_Camp_Intent_Details.Location_Area_of_camp'] ?? '',
	'Collection_Camp_Intent_Details.Name' => $displayName,
	'Collection_Camp_Intent_Details.Contact_Number' => $contactNumber,
	'Collection_Camp_Intent_Details.You_wish_to_register_as' => $recentCampData['Collection_Camp_Intent_Details.You_wish_to_register_as'] ?? '',
	'Collection_Camp_Intent_Details.City' => $recentCampData['Collection_Camp_Intent_Details.City'] ?? '',
	'Collection_Camp_Intent_Details.Pin_Code' => $recentCampData['Collection_Camp_Intent_Details.Pin_Code'] ?? '',
];

$redirectUrl = $redirectBaseUrl . http_build_query($queryParams);
$noDetailsRedirectUrl = get_home_url() . "/collection-camp/intent/#?" . http_build_query([
	'Collection_Camp_Core_Details.Contact_Id' => $contactId,
	'message' => 'collection-camp-page',
	'Collection_Camp_Intent_Details.Name' => $displayName,
	'Collection_Camp_Intent_Details.Contact_Number' => $contactNumber,
]);

?>

<div class="m-auto w-520 pl-27">
	<a href="<?php echo esc_url($redirectUrl); ?>">
		<button	button class="button button-primary w-520 mb-12 border-none font-sans fz-16 br-4 fw-600">
			Yes, use details from last camp
		</button>
	</a>
	<a href="<?php echo esc_url($noDetailsRedirectUrl); ?>">
		<button class="button button-primary w-520 border-none font-sans fz-16 bg-white br-4 red-border fw-600 text-light-red">
			No, the details are different
		</button>
	</a>
</div>

<?php
?>
