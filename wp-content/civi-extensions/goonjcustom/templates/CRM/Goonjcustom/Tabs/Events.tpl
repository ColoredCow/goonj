<crm-angular-js modules="{$tabValue.module}">
  <form id="bootstrap-theme">
    <{$tabValue.directive} options="{ldelim}eventID: {$tabValue.entity.id}{rdelim}"></{$tabValue.directive}>
  </form>
  <script>
    console.log("Tab Value:", {$tabValue|json_encode|escape:'js'});
    console.log("Event ID:", {$tabValue.entity.id|default:'undefined'});
</script>
</crm-angular-js>
