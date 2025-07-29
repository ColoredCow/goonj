<script type="text/javascript">
{literal}
CRM.$(function($) {
  // ToDo: Fix this for multi-addresses
  var rowCity = CRM.$("label[for='address_1_city']").closest('tr');
  var rowCounty = CRM.$("label[for='address_1_county_id']").closest('tr');
  var rowState = CRM.$("label[for='address_1_state_province_id']").closest('tr');
  if(rowCounty.length){
    rowCity.insertAfter(rowCounty);
  }
  else{
    rowCity.insertAfter(rowState);
  }
});
{/literal}
</script>
