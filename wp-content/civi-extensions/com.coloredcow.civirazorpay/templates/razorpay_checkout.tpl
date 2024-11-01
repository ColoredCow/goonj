<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<button id="rzp-button1">Pay with Razorpay</button>

{literal}
<script>
  var options = {
    "key": "{$razorpayKey}",
    "amount": "{$amount}",
    "currency": "{$currency}",
    "name": "Your Organization",
    "description": "Contribution",
    "order_id": "{$orderId}",
    "handler": function (response) {
      // Redirect to IPN handler with payment_id and order_id
      window.location.href = "/civicrm/payment/ipn/civirazorpay?payment_id=" + response.razorpay_payment_id + "&order_id=" + response.razorpay_order_id;
    }
  };

  var rzp = new Razorpay(options);

  document.getElementById('rzp-button1').onclick = function(e) {
    rzp.open();
    e.preventDefault();
  }
</script>
{/literal}
