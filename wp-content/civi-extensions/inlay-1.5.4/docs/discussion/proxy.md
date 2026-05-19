# Proxying Inlay requests

Should you want to keep CiviCRM on a private network, you will need to *reverse proxy* requests to Inlay through a front-facing domain. This might be the domain your website runs on or one set up just for the purpose.

The core extension (i.e. this one) will require the following URLs:

- `/civicrm/inlay-api`
- `/sites/default/files/civicrm/` - specifically the `inlay-xxxxxx.js` files within that but possibly more.

**Specific Inlay Types may require more.**

When Inlay generates the 'bundle' scripts that you embed on your external site, it needs to embed the endpoint URL it can use. By default, it assumes that your CiviCRM server is public facing, and uses CiviCRM's core utilities to generate these URLs. However, if your CiviCRM is not public facing, you'll need to provide limited access via a URL that is public facing. Visit **Administer » Inlays** and open up the **Proxy support**. In there you can place a "Base URL" that you want to use for proxying.

If you enter `https://example.org` as your base URL, then Inlay will use:

- `https://example.org/civicrm/inlay-api` is its endpoint, and that its bundles can be accessed at...
- `https://example.org/sites/default/files/civicrm/inlay-xxxxxx.js`

