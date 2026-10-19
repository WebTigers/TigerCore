<!-- tiger:doc title="SMS" visibility="admin" -->
# SMS

Tiger sends text messages through a provider-agnostic layer — the SMS sibling of the email system.
Pick a provider, add its credentials, and any part of the platform can send an SMS (one-time codes,
2FA, operational alerts) with a single call.

## Set it up

**Admin → Settings → SMS.** Choose a provider, enter its credentials, **Save**, then use **Send a
test** to confirm it end to end.

### Twilio
The industry-standard SMS API. You need:
- **Account SID** and **Auth Token** (Twilio Console → Account).
- Either a **From number** (a number you own in Twilio, in E.164 like `+15551234567`) **or** a
  **Messaging Service SID** (a Twilio number pool).

### Amazon SNS
Native to an AWS-hosted Tiger. You need:
- An **AWS region** (e.g. `us-east-1`).
- An **origination number** as the sender — a 10DLC or toll-free number (required for US A2P SMS).
- **Access/secret keys** — *or leave them blank* on an EC2 instance that has a role with `sns:Publish`,
  and Tiger uses the instance role automatically (no keys to manage, like the fleet's email).

> A brand-new AWS account is in the **SMS sandbox** ($1/month, verified destinations only) until you
> request production access. In the sandbox you can still send to verified test numbers.

## Numbers

Destinations are normalized to **E.164** (`+<country><number>`) automatically — a bare US 10-digit
number becomes `+1…`. An un-parseable number fails cleanly rather than sending.

## For developers

```php
$res = Tiger_Sms::send('+14075551234', 'Your code is 123456');
// ['ok' => true, 'id' => '…', 'provider' => 'twilio', 'to' => '+14075551234']
```

Unconfigured or on a provider error it returns `['ok' => false, 'error' => '…']` — it never throws.
Add another provider by extending `Tiger_Sms_Adapter_Abstract` and `Tiger_Sms::register('name', Class)`.

## Compliance

SMS carries strict consent rules (TCPA, US 10DLC registration, opt-in + STOP handling). Transactional
messages (codes, alerts) and marketing differ — only send where you have consent.
