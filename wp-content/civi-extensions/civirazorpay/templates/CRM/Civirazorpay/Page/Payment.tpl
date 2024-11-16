<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<div id="razorpay-options"
     data-key="{$apiKey}"
     data-amount="{$amount}"
     data-currency="{$currency}"
     data-order-id="{$orderId}"
     data-email="{$email}"
     data-organization-name="{$organizationName}"
     data-qf-key="{$qfKey}">
</div>

{literal}
<script type="text/javascript">
  document.addEventListener("DOMContentLoaded", function() {
    var razorpayOptions = document.getElementById('razorpay-options');
    var options = {
      key: razorpayOptions.getAttribute('data-key'),
      amount: razorpayOptions.getAttribute('data-amount'),
      currency: razorpayOptions.getAttribute('data-currency'),
      name: razorpayOptions.getAttribute('data-organization-name'),
      description: "Contribution Payment",
      order_id: razorpayOptions.getAttribute('data-order-id'),
      handler: function(response) {
        window.location.href = "/civicrm/contribute/transact/?_qf_ThankYou_display=1&qfKey=" + razorpayOptions.getAttribute('data-qf-key') + "&payment_id=" + response.razorpay_payment_id + "&order_id=" + response.razorpay_order_id;
      },
      prefill: {
        email: razorpayOptions.getAttribute('data-email')
      },
      theme: {
        color: "#528FF0"
      }
    };

    var rzp = new Razorpay(options);
    rzp.open();
  });
</script>
{/literal}
