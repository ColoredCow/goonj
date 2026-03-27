import threading
import requests
import hmac
import hashlib

NGROK_URL = "https://3c0d-157-119-83-207.ngrok-free.app/civicrm/payment/ipn/civirazorpay?processor_id=7"
WEBHOOK_SECRET = "LGS75qEToFNE2o6UIHjqFwvb"

import time as _time
import random as _random
import string as _string

def _fresh_id(prefix, length=14):
    chars = _string.ascii_letters + _string.digits
    return prefix + ''.join(_random.choices(chars, k=length))

# Generate fresh unique IDs each run so CiviCRM never sees them as duplicates
pay1  = _fresh_id('pay_')
ord1  = _fresh_id('order_')
inv1  = _fresh_id('inv_')

pay2  = _fresh_id('pay_')
ord2  = _fresh_id('order_')
inv2  = _fresh_id('inv_')

ts = int(_time.time())

# sub_SWCrhhXGhyxaNz
payload1 = (
    '{"entity":"event","account_id":"acc_AJH6dbLbo2PKMU","event":"subscription.charged",'
    '"contains":["subscription","payment"],"payload":{"subscription":{"entity":{'
    '"id":"sub_SWCrhhXGhyxaNz","entity":"subscription","plan_id":"plan_SWCrgorycYdooY",'
    '"customer_id":null,"customer_email":"tarun.joshi@coloredcow.com",'
    '"customer_contact":"+918445400778","status":"active","type":1,'
    '"current_start":1782498600,"current_end":1785090600,"ended_at":null,"quantity":1,'
    '"notes":{"mobile":"9027121667","purpose":"Team 5000","name":"Tarun","identity_type":"",'
    '"donor_email":"tarun.joshi@coloredcow.com","address":"184 SPRINGBROOK CT","pin":"30549",'
    '"country":"India","state":"Chandigarh","city":"JEFFERSON","contribution_recur_id":"6059",'
    '"contact_id":"363486","source":"CiviCRM Recurring Contribution"},'
    '"charge_at":1785090600,"start_at":1774605040,"end_at":1993055400,"auth_attempts":0,'
    '"total_count":84,"paid_count":4,"customer_notify":false,"created_at":1774605016,'
    '"expire_by":null,"short_url":null,"has_scheduled_changes":false,'
    '"change_scheduled_at":null,"source":"api","payment_method":"card","offer_id":null,'
    '"remaining_count":80}},"payment":{"entity":{'
    f'"id":"{pay1}","entity":"payment","amount":300000,"currency":"INR","status":"captured",'
    f'"order_id":"{ord1}","invoice_id":"{inv1}",'
    '"international":false,"method":"card","amount_refunded":0,"amount_transferred":0,'
    '"refund_status":null,"captured":"1","description":"Recurring Payment via Subscription",'
    '"card_id":"card_SWCs6ICUjTyqhF","bank":null,"wallet":null,"vpa":null,'
    '"email":"tarun.joshi@coloredcow.com","contact":"+918445400778","customer_id":null,'
    '"token_id":"token_SWCs6WWh9gV4bg","notes":[],"fee":9274,"tax":1414,'
    '"error_code":null,"error_description":null,"acquirer_data":{"auth_code":null},'
    f'"created_at":{ts}' + '}}},"created_at":' + f'{ts}' + '}'
)

# sub_SVN9nL2hxjQj0V
payload2 = (
    '{"entity":"event","account_id":"acc_AJH6dbLbo2PKMU","event":"subscription.charged",'
    '"contains":["subscription","payment"],"payload":{"subscription":{"entity":{'
    '"id":"sub_SVN9nL2hxjQj0V","entity":"subscription","plan_id":"plan_SVN9maK72ZZk0u",'
    '"customer_id":null,"customer_email":"tarun.joshi@coloredcow.com",'
    '"customer_contact":"+918445400778","status":"active","type":1,'
    '"current_start":1790274600,"current_end":1792866600,"ended_at":null,"quantity":1,'
    '"notes":{"mobile":"9027121667","purpose":"Team 5000","name":"Tarun","identity_type":"",'
    '"donor_email":"tarun.joshi@coloredcow.com","address":"184 SPRINGBROOK CT","pin":"30549",'
    '"country":"India","state":"Chandigarh","city":"JEFFERSON","contribution_recur_id":"6057",'
    '"contact_id":"363486","source":"CiviCRM Recurring Contribution"},'
    '"charge_at":1792866600,"start_at":1774422939,"end_at":1803493800,"auth_attempts":0,'
    '"total_count":12,"paid_count":7,"customer_notify":false,"created_at":1774422920,'
    '"expire_by":null,"short_url":null,"has_scheduled_changes":false,'
    '"change_scheduled_at":null,"source":"api","payment_method":"card","offer_id":null,'
    '"remaining_count":5}},"payment":{"entity":{'
    f'"id":"{pay2}","entity":"payment","amount":300000,"currency":"INR","status":"captured",'
    f'"order_id":"{ord2}","invoice_id":"{inv2}",'
    '"international":false,"method":"card","amount_refunded":0,"amount_transferred":0,'
    '"refund_status":null,"captured":"1","description":"Recurring Payment via Subscription",'
    '"card_id":"card_SVNA81uufhiBXG","bank":null,"wallet":null,"vpa":null,'
    '"email":"tarun.joshi@coloredcow.com","contact":"+918445400778","customer_id":null,'
    '"token_id":"token_SVNA8EGTBdFcSo","notes":[],"fee":9274,"tax":1414,'
    '"error_code":null,"error_description":null,"acquirer_data":{"auth_code":null},'
    f'"created_at":{ts}' + '}}},"created_at":' + f'{ts}' + '}'
)

def sign(body):
    return hmac.new(WEBHOOK_SECRET.encode(), body.encode(), hashlib.sha256).hexdigest()

barrier = threading.Barrier(2)

def send(label, body):
    barrier.wait()
    r = requests.post(NGROK_URL, data=body, headers={
        "Content-Type": "application/json",
        "X-Razorpay-Signature": sign(body),
    })
    print(f"[{label}] status={r.status_code}")

t1 = threading.Thread(target=send, args=("sub_SWCrhhXGhyxaNz", payload1))
t2 = threading.Thread(target=send, args=("sub_SVN9nL2hxjQj0V", payload2))
t1.start()
t2.start()
t1.join()
t2.join()
