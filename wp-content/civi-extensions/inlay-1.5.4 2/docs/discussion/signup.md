# Towards a generic signup Inlay

This a **discussion**. Albeit one between myself and ... well, maybe you
now!

On the one hand: many people need a quick and easy way to add a signup
form to their site. An Inlay could provide this and they'd be happy. An
MVP would be: ask for first, last names, email. Config would include
a mailing group and a thank you msg tpl. Anti spam, and basic validation.
Contact found using 'Unsupervised' dedupe rule or created new.

On the other hand: most orgs want something more specific. Or they're more
or less anxious about duplicate contacts. Or they have particular GDPR
implementations to accommodate. Or different ways to process the info. Or
they want the form to popup rather than be a static part of the page. Etc.
And basically, Inlay lets me create bespoke forms that do exactly what's
needed really easily. Trying to convince a client to accept what can be
achieved with a generic solution - when the costs of a bespoke one are low
anyway, might be difficult.

But for the moment, this page is my thoughts on how to genericise the
process.

## What input data do we handle from the user?

From the user, a minimum of first and last names, email. Other commonly needed data:

- The URL of the page that the Inlay was on. This is super-useful as it
  can contain `utm_*` parameters.

- Phone

- Country / or full address

- Organisation / Employer

But potentially other data: maybe they want a consent box to be ticked;
maybe they want some custom fields? Maybe a profile, with all that
potentially entails.

It's very easy for mission creep here. What is a signup, what is a contact
form, what is a petition, what is a survey? Once you start to genericise,
they all merge.


## How do we process it?

- How to validate the data

- How to achieve "find or create" (e.g. I use XCM a lot, maybe could fall
  back to Unsupervised de-dupe rule)

- How do we store/merge the data supplied. This gets stupidly complex at
  times. e.g. if they've provided a country, but they already have an
  address or two, which may or may not be in said country! Is 'Org' the
  same as 'Employer', or does that cause problems because you get lots of
  misspelt duplicate Organisations?

- How do we record what just happened? Typically but not always: add them
  to a group, overriding any previous removals/unsubscribes. Do we un-hold
  their email if on hold? Do we remove No Bulk Mail if they've just signed
  up again? Oftentimes: record an activity - good to store other data in
  here. Should that be a particular activity type? Do we have to record
  GDPR stuff using that extension?

- How do we thank them? Is there an email to send? What about text to
  display in browser?

## What does the form look like to the users?

This gets exponentially more complex as you add more fields. But maybe we
start with a flexbox layout that puts first, last name fields on one line,
and email on the next and submit below that. Good class names can help
a themer do their stuff.

What variables do we need here: titles, intro, text before, on, and after
submit button, etc.

Does it pop-up? How's that triggered?

## What config do we need?

From config, a minimum of a title of what they're signing up *for*. But
plus whatever is needed to accommodate the above.

## Common denominators?

### Config:

UX:

- public title
- title element: h1..h3
- intro HTML
- submit button text
- thank you message for web
- social share options?
- additional profile?
- pop-up on exit-intent | static form | pop-up on button.signup click?
  (that would need adding to page separately)

Processing:

- optional select: Group to add to
- checkbox: remove On-Hold
- checkbox: remove No Bulk Emails
- checkbox: use GDPR extension's record SLA acceptance
- checkbox: use GDPR extension's record comms update activity
- record 'signup inlay' activity type? This could be an opinionated
  activity type that includes:

    * subject is name of Inlay
    * location contains the URL
    * details contains a copy of most of the data.

### Processing

- Call hook: civi.signupinlay.getorcreate. If that fails to return
  a contact, if XCM available, use default profile to find the person.
  Fallback to unsupervised de-dupe rule.

- Call hook: civi.signupinlay.validate. Check first, last, email too.
  Bounce errors back in response.

- on-hold / no bulk emails / gdpr / activity things if configured.

- is there an API to submit a profile?

- add to group, if configured.

- send thank you email, if configured

- fire CiviRules trigger?

- Call hook: civi.signupinlay.process.

- Return success.

### UX presentation

Some JS.


## Questions

I wonder how useful Profiles are here.


