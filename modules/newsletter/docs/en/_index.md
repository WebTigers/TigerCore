<!-- tiger:doc
title: Newsletter
order: 46
visibility: admin
-->

# Newsletter

Collect newsletter subscribers on your site and see them under **Marketing → Newsletter**.

## Adding a signup form to a page

Drop the shortcode into any page or post:

```
[newsletter_form]
```

It renders a name + email form. You can set your own wording:

```
[newsletter_form title="Join the list" intro="One email a week, no spam." button="Count me in"]
```

The `source` attribute (e.g. `[newsletter_form source="footer"]`) is stored on each subscriber so you can
tell where they signed up.

## How subscribing works (double opt-in)

Signing up does **not** immediately add someone to your list. It:

1. stores the person as **Pending**, and
2. emails them a confirmation link.

Only when they click that link do they become **Confirmed**. This is *consent-first*: it proves the person
owns the address and actually wants your newsletter. Every subscriber can unsubscribe with one click from
the link in their confirmation email.

## The subscriber list

The list shows everyone who signed up, with their status:

- **Pending** — signed up but has not confirmed yet.
- **Confirmed** — clicked the confirmation link; a real opt-in.
- **Unsubscribed** — opted out.

Filter by status with the dropdown at the top.

## Sending to your subscribers

This module *collects* subscribers; it does not send email. If you have an email tool such as **TigerList**
installed, your **confirmed** subscribers appear there as the **Newsletter subscribers** audience — pick it
as the target of a campaign. Your email tool still applies its own consent and unsubscribe rules before
anything is sent.
