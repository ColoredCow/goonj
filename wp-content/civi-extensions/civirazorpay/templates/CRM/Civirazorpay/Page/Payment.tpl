<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<div id="razorpay-options"
     data-key="{$apiKey}"
     data-amount="{$amount}"
     data-currency="{$currency}"
     data-order-id="{$orderId}"
     data-email="{$email}"
     data-organization-name="{$organizationName}"
     data-qf-key="{$qfKey}"
     {if $isRecur}
       data-subscription-id="{$orderId}"
       data-description="Subscription Plan"
     {else}
       data-description="Contribution Payment"
     {/if}>
</div>

{literal}
<script type="text/javascript">
  document.addEventListener("DOMContentLoaded", function() {
    var razorpayOptions = document.getElementById('razorpay-options');
    var options = {
      key: razorpayOptions.getAttribute('data-key'),
      currency: razorpayOptions.getAttribute('data-currency'),
      name: razorpayOptions.getAttribute('data-organization-name'),
      description: razorpayOptions.getAttribute('data-description'),
      prefill: {
        email: razorpayOptions.getAttribute('data-email')
      },
      theme: {
        color: "#528FF0"
      },
    };

    // Handle one-time payment
    if (!razorpayOptions.hasAttribute('data-subscription-id')) {
      options.amount = razorpayOptions.getAttribute('data-amount');
      options.order_id = razorpayOptions.getAttribute('data-order-id');
      options.handler = function(response) {
        console.log('redirectURL === ');
        console.log("/civicrm/contribute/transact/?_qf_ThankYou_display=1&qfKey=" + razorpayOptions.getAttribute('data-qf-key') + "&payment_id=" + response.razorpay_payment_id + "&order_id=" + response.razorpay_order_id);
        // window.location.href = "/civicrm/contribute/transact/?_qf_ThankYou_display=1&qfKey=" + razorpayOptions.getAttribute('data-qf-key') + "&payment_id=" + response.razorpay_payment_id + "&order_id=" + response.razorpay_order_id;
        window.location.href = '/civicrm/event/register/?_qf_ThankYou_display=1&qfKey=' + razorpayOptions.getAttribute('data-qf-key') + "&payment_id=" + response.razorpay_payment_id + "&order_id=" + response.razorpay_order_id;
      };
    }
    // Handle recurring payment
    else {
      options.subscription_id = razorpayOptions.getAttribute('data-subscription-id');
      options.handler = function(response) {
        window.location.href = "/civicrm/contribute/transact/?_qf_ThankYou_display=1&qfKey=" + razorpayOptions.getAttribute('data-qf-key') + "&payment_id=" + response.razorpay_payment_id + "&subscription_id=" + response.razorpay_subscription_id;
      };
    }

    var rzp = new Razorpay(options);
    rzp.open();
  });
</script>
{/literal}
