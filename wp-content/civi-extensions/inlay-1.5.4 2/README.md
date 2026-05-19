# Inlay

![Screenshot](docs/images/inlay-explained.svg)


A system to enable developers to make different types of forms (or presentations of data) on external websites with/from CiviCRM.

**You’ll probably get a better overview/intro/detailed knowledge by reading the [documentation](https://docs.civicrm.org/inlay/en/latest/) instead of this README**

![Screenshot](docs/images/inlay-list.jpg)

The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Try it out

Bit of a pain to try it out as you need an implementation and configuration to
go with. You could try the Signup Form from
https://github.com/artfulrobot/inlaysignup which is fairly generic and will
give you an idea.

You should find the main Inlays admin screen at **Administer » Inlays**. You'll
need the `Administer Inlays` permission.


--------------------------

Original idea below

## Why do we need another way to connect your site?

There are various ways sites can connect. You can use the API directly
(from your webserver side, with secret API key); you can do so through
CMRF which adds nice logging and caching; you can send people to your
CiviCRM site or IFRAME it; you can use the Remoteform extension; you can
use webform, views; form processor.

So why this new aproach? Because I regularly find I have needs that these
don't quite meet. Specifically:

1. **The remote website technology is unknown.** You can't use CMRF if the
   website is on Python, for example. I need a solution that can work with
   any CMS (and even no CMS). Also, it's often helpful in terms of
   iterating fast to not need to negotiate deployment changes on the
   website end since this can slow things down, or increase costs.

2. **The design UX is really important.** You can make lots out of Lego
   but it still has square edges that might not be acceptable. You can do
   some nice things with Javascipt and CSS, and remote form even lets you
   template different HTML for form elements, but often clients
   have really specific requirements for forms that simply need a bit of
   custom love. Only offer gift aid options if geoip says they're in the
   UK? Popup a "how about £10/month" if they do a one off over £20? Ask
   them to share on social media after signup with a bespoke button? etc.

3. **It must be able to scale efficiently.** Several of my clients run
   petitions that might attract a lot of people. How quickly the form
   loads and can be processed is critical to being able to keep the server
   alive. So, for example, if each website page load causes a request
   to the CiviCRM site which takes 500ms to process, that's only 120 users
   per minute you can serve (per core, roughly, ignoring everything else)
   just to *display* the form; even before you've started handling
   submissions. We can always say "get a bigger server" but we can write
   efficient code and save some emissions first. Caching layers do help in
   some existing solutions while other existing solutions can't be cached,
   e.g. by using POST requests.

4. **Processing must be customisable.** How to validate data? What
   anti-spam to use? How to identify contacts (certain de-dupe rule? use
   XCM? something else?) and what to do with duplicates? Send confirmation
   email - based on data received? Add to group(s)? What if they're
   already there? Add activity, unless they already have one? Complex
   email journey logic (e.g. [Chassé](docs.civicrm.org/chasse/) journey
   selection) etc. etc.

5. **(Proxyable)**. It should be possible that the CiviCRM server itself
   is not on a public facing IP. I put this one in brackets because many
   of the existing solutions provide solutions for this.

## Architecture

- CiviCRM "Inlay" Extension (this)

- Provides an API so that other extensions can register different Inlay
  types.

- Provides admins a way to create instances of the registered Inlay types,
  deferring to the implementation for the configuration screens.

- Generates (and re-generates) a static Javascipt file (which therefore
  allows for caching at the server level and can be served with minimal
  server load and at max speed) which includes:

   1. some helper/wrapper methods

   2. the custom inlay type app code. This can be written in anything you
      like, e.g. plain, typescript, VueJs, Angluar99... As long as the
      app's production bundle can be safely concatenated with the shared
      app code and the JSON data object, anything goes.

   3. a JSON object of initial data. This might include certain parts of
      the instance's configuration that are suitable for public use (e.g.
      a thank you message, but not an external API secret key!) as well as
      snapshot data, e.g. the number of people who have signed to-date.

- Provides `<script>` snippet for the website.

- Provides a public XHR/ajax endpoint for the client's use, deferring to
  the various classes for the processing, and providing a common structure
  for response and request. There may be various common helper methods
  that might be useful here, e.g. CSRF tokens; calling Form Processor; but
  the emphasis for those implementing Inlay types should be to limit any
  exposed API surfaces - allowing only very specific API calls.


## Use cases

Instances of "Inlays" could be used to:

- Expose a profile (like remote form does)

- Create a signup form with various configuration options. This could be
  forked and extended by orgs with particular needs.

- Present a petition with a totaliser graphic, "latest signed by",
  petition text, etc.

- Present a list of (public-safe) data, fetched from the CRM.

- Donation forms (much more complex).

- Contact Us forms.

Inlay type code could be extended or forked - e.g. you could extend
someone's Contact Form Inlay for your own use.

## CORS

CORS origins are configured as an OptionValue. Add a value with the origin
(e.g. `https://example.org`) as its `value` and set it as active.

## Requirements

* PHP v7.0+
* CiviCRM 5.29+

## Installation (Web UI)

This extension has not yet been published for installation via the web UI.

## Installation (CLI, Zip)

Sysadmins and developers may download the `.zip` file for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
cd <extension-dir>
cv dl inlay@https://github.com/FIXME/inlay/archive/master.zip
```

## Installation (CLI, Git)

Sysadmins and developers may clone the [Git](https://en.wikipedia.org/wiki/Git) repo for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
git clone https://github.com/FIXME/inlay.git
cv en inlay
```

## Usage

To-do


## Known Issues

See issue queue.

## How inlays get booted.

Each inlay instance creates a Javascript file which contains:

1. The core Inlay code (common to all inlays) which does:

   1. Ensure we have a skeletal CiviCRMInlay object defined (if you have 2+ on
      one page this will already be defined).
   2. If CiviCRMInlay.app is not yet defined, define it, and call app.boot when
      `DOMContentLoaded`

2. The Inlay's own code (common to all inlays of that type) which defines
   a global boot function for that type of inlay, unless already defined.

3. The particular Inlay's configuration data is stored at
   `CiviCRMInlay.inlays[publicID]`.

4. When `DOMContentLoaded` fires, the core inlay code finds all relevant
   `<script>` tags and boots each app in turn with that Inlay type's boot
   function by passing in it's own configuration.

## XHR requests

Inlay instances are passed an `inlay` object which includes
a `inlay.request(method, fetchParams)` function. The `method` should be
`get` or `post` (other methods not supported yet) and the `fetchParams` must
include `body`, being an Object. The body will be passed to `JSON.stringify()`
to form the payload of the request.

Most requests will be post requests. However, sometimes a 'get' request might
have advantages (e.g. the request could be cached by the server). The calling
Inlay just needs to pass `inlay.request('get', {body: {...}})` as with a post
request, but behind the scenes, the stringified payload is added to an
`inlayJSON` query parameter. When processing at the server end, the data will
be found with `getBody()` exactly as it would have with a post request.

### Response objects

These are normally the JSON response from the server (from your inlay's code),
**plus** two extra properties, `responseOk` and `responseStatus` (e.g. 200,
401...). Normally these are copied from the
[Response](https://developer.mozilla.org/en-US/docs/Web/API/Response) object,
but where an error was caught they're faked to `false` and `500` respectively.

You should check for a successful/expected response yourself, e.g. check
`responseOk`; check for the existence of `.error`; check you got what you
expected.

You might want to emit objects with an `.error` property from your PHP code,
so you can handle all errors the same way.

Network errors, and non-JSON responses (which is considered a server error) are
caught by Inlay and passed on as an object with and `error` property.

Therefore you will never have to implement a `.catch()`, unless you program
a `throw` yourself in your response handler javascript.

