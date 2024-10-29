<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<button id="rzp-button1">Pay with Razorpay</button>

<script>
  var options = {
    "key": "{$razorpayKey}", // Razorpay API Key passed from backend
    "amount": "{$amount}", // Amount in paise
    "currency": "{$currency}",
    "name": "Your Organization Name",
    "description": "Donation",
    "order_id": "{$orderId}", // Razorpay order ID
    "handler": function (response) {
      // Redirect to IPN handler to process payment on server
      window.location.href = "/civicrm/payment/ipn/civirazorpay?payment_id=" + response.razorpay_payment_id + "&order_id=" + response.razorpay_order_id;
    }
  };

  var rzp = new Razorpay(options);

  document.getElementById('rzp-button1').onclick = function(e) {
    rzp.open();
    e.preventDefault();
  }
</script>
