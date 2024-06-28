(function($, ts) {

  $(function() {
    // I think this is required to respond to "onReady", because the above function does not
    // but I'm not 100% sure.

    // If no refresh link ...
    if ($('#refreshall').length == 0) {

      // Add refresh button
      $('.crm-content-block').children().first()
          .before('<span style="float:right;"><button class="btn btn-default" title="' + ts("Refresh all dashlets") + '" id="refreshall"><i class="crm-i fa-refresh"></i> ' + ts("Refresh all dashlets") + '</button></span>');

      // Add click handler
      $('#refreshall').click( function() {
        $('crm-dashlet a.fa-refresh').click();
      });
    }
  });

}(CRM.$, CRM.ts('dashboardrefresh')));
