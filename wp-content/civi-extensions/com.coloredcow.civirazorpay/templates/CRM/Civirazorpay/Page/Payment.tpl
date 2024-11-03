<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<div id="razorpay-button"
     data-key="{$apiKey}"
     data-amount="{$amount}"
     data-currency="{$currency}"
     data-order-id="{$orderId}"
     data-qf-key="{$qfKey}"
     data-email="{$email}">
</div>

{literal}
<script type="text/javascript">
  document.addEventListener("DOMContentLoaded", function() {
    var razorpayButton = document.getElementById('razorpay-button');
    var options = {
      key: razorpayButton.getAttribute('data-key'),
      amount: razorpayButton.getAttribute('data-amount'),
      currency: razorpayButton.getAttribute('data-currency'),
      name: "Your Organization",
      description: "Contribution Payment",
      order_id: razorpayButton.getAttribute('data-order-id'),
      handler: function(response) {
        window.location.href = "/civicrm/contribute/transact/?_qf_ThankYou_display=1&qfKey=" + razorpayButton.getAttribute('data-qf-key') + "&payment_id=" + response.razorpay_payment_id + "&order_id=" + response.razorpay_order_id;
      },
      prefill: {
        email: razorpayButton.getAttribute('data-email')
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
