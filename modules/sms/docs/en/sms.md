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

### Vonage
The classic SMS API (formerly Nexmo). You need:
- An **API key** and **API secret** (Vonage dashboard → *API settings*).
- A **From** — an E.164 number you own, **or** an alphanumeric sender ID (e.g. `MyBrand`) where the
  destination country allows one. Non-GSM text is sent as unicode automatically.
- *For two-way:* a **signature secret** + the matching **signature method** (set both in the dashboard)
  so inbound webhooks are verified — see "Receiving messages" below.

### Plivo
You need:
- An **Auth ID** and **Auth token** (Plivo console → *Account*).
- Either a **From number** (a Plivo number you own, in E.164) **or** a **PowerPack UUID** (a Plivo
  number pool). A message prefers the PowerPack when set.

### Telnyx
You need:
- An **API key** (Telnyx portal → *Auth → API Keys*, begins `KEY…`).
- Either a **From** (an E.164 number or a sender ID) **or** a **Messaging Profile ID** (a number pool).
  Both may be set — the profile picks the pool, the From pins a specific number within it.

> **Which to pick?** Any of them sends the same way through `Tiger_Sms::send()` — the choice is about
> your carrier relationship, pricing, and number inventory. Twilio and Vonage are the broadest; Amazon
> SNS is the zero-key choice on AWS; Plivo and Telnyx are strong on price and programmable-number pools.

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

## Receiving messages (two-way)

Receiving is **application-specific**, so TigerSMS only does the transport — it verifies the webhook,
normalizes the message, and **publishes an event**. *Your* module decides what an inbound text means
(a reply, a STOP/opt-out, a keyword, an order lookup), so the two stay loosely coupled.

1. **Point your provider's inbound webhook** at `https://your-site/sms/inbound/<provider>` — e.g.
   `…/sms/inbound/twilio` (in the Twilio Console, the number's *"A message comes in"* webhook). The
   endpoint verifies the provider's signature and 403s anything it can't authenticate.
2. **Subscribe** from any module's `Bootstrap` — you get the normalized message:

```php
protected function _initSmsInbound()
{
    if (!class_exists('Tiger_Sms')) { return; }
    Tiger_Sms::onInbound(function (array $m) {
        // $m = ['provider','message_id','from','to','body','media'=>[],'received_at','raw']
        if (strtoupper(trim($m['body'])) === 'STOP') { /* your opt-out logic */ }
        // … reply with Tiger_Sms::send($m['from'], '…'), look something up, etc.
    });
}
```

Many modules can subscribe; a listener that throws is isolated so it can't break the webhook or the
others.

**Inbound support by provider** (every one verifies the request before a listener ever sees it):

| Provider | Inbound | How it's verified | Set the webhook to |
|---|---|---|---|
| **Twilio** | ✅ out of the box | `X-Twilio-Signature` (HMAC-SHA1) | the number's *"A message comes in"* webhook |
| **Vonage** | ✅ when signed | the `sig` param (your signature secret + method) | the number's inbound webhook, **method POST** |
| **Plivo** | ✅ out of the box | `X-Plivo-Signature-V3` (HMAC-SHA256 of URL + nonce) | the number's Message URL (POST) |
| **Amazon SNS** | ⏳ follow-up | — | AWS routes inbound through an SNS topic subscription, not a direct webhook |
| **Telnyx** | ⏳ follow-up | — | Telnyx POSTs JSON signed with Ed25519 (a raw-body path, not the form webhook) |

For Vonage, set a **signature secret** in the dashboard and enter it (plus the matching method) in the
SMS settings, and set the inbound webhook to **HTTP POST** — an unsigned request is refused.

## Compliance

SMS carries strict consent rules (TCPA, US 10DLC registration, opt-in + STOP handling). Transactional
messages (codes, alerts) and marketing differ — only send where you have consent.
