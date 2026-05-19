window.CiviCRMInlay = window.CiviCRMInlay || {
  app: null,
  inlays: {},
  // Generate a temporary ID which can be used to group requests from this
  // page-load together, which is very useful for debugging on an inlay
  // that makes multiple requests.
  runId: self.crypto?.randomUUID(),
  debug: () => {}
};

// Define the app once.
if (!window.CiviCRMInlay.app) {
  (() => {
    if (localStorage && localStorage.getItem("inlayDebug")) {
      window.CiviCRMInlay.debug = console.debug;
      window.CiviCRMInlay.xdebug = true;
    }
    let debug = window.CiviCRMInlay.debug;
    debug("Defining CiviCRMInlay.app");
    var inlay = window.CiviCRMInlay;
    inlay.app = {
      // Reports to console the bundle ID and its age.
      bundleInfo(bundleCreateTime, bundleId) {
        let age = Math.floor((new Date().getTime() - bundleCreateTime) / 1000),
          unit = "s",
          facility = age < 300 ? console.info : console.log;
        if (age > 120) {
          age = Math.floor(age / 60);
          unit = "mins";
        } else if (age > 24 * 60) {
          age = Math.floor(age / 24 / 60);
          unit = "days";
        } else if (age > 60) {
          age = Math.floor(age / 60);
          unit = "hrs";
        }
        facility(`Inlay bundle ${bundleId} created ${age} ${unit} ago`);
      },
      bootWhenReady() {
        // Typically we have to wait for DOMContentLoaded, but this might have already happened.
        if (document.readyState === "complete") {
          debug("Document is already ready, booting any new inlays.");
          inlay.app.boot();
        } else {
          debug("Waiting for DOMContentLoaded to boot any new inlays.");
          document.addEventListener("DOMContentLoaded", inlay.app.boot);
        }
      },
      boot() {
        debug("Inlay app booting");
        // Boot inlays that have not been booted yet.
        let found = [];
        [].forEach.call(
          document.querySelectorAll("script[data-inlay-id]"),
          script => {
            found.push(script);
            const publicID = script.dataset.inlayId;
            if (script.inlayBooted) {
              debug(`Inlay ${publicID}: already booted`);
              return;
            }
            // Script needs booting.
            if (!(publicID in inlay.inlays)) {
              console.warn(
                `Inlay ${publicID}: Failed to instantiate from script:`
              );
              return;
            }
            debug(`Inlay ${publicID}: booting`);
            // Remember we've booted it.
            script.inlayBooted = true;
            /**
             * Boot the inlay now.
             *
             * The inlay object has the following properties:
             * - initData object of data served along with the bundle script.
             * - publicID string for the inlay instance on the server. Nb. you may have
             *   multiple instances of that instance(!) on a web page.
             * - script   DOM node of the script tag that has caused us to be loaded,
             *            e.g. useful for positioning our UI after it, or extracting
             *            locally specified data- attributes.
             * - request(fetchParams)
             *            method providing fetch() wrapper for all Inlay-related
             *            requests. The URL is fixed, so you only provide the params
             *            object.
             */
            if (
              inlay.inlays[publicID] &&
              inlay.inlays[publicID].init in window
            ) {
              window[inlay.inlays[publicID].init]({
                initData: inlay.inlays[publicID],
                publicID,
                script,
                // Here we provide a simplified .request method for our inlays to use.
                // It will add in the publicID of the Inlay, the URL to the inlay endpoint etc.
                // Using .then(r) gives direct access to the JSON returned, as
                // well as r.responseStatus which is the http status, e.g. 200,
                // and r.responseOk which is a shortcut to 200 <=
                // r.responseStatus <299
                request: fetchParams =>
                  inlay.app.request(publicID, fetchParams).catch(e => {
                    // Note: Errors here will be things like:
                    // - network errors
                    // - server error that meant the response output was not parsable JSON.
                    // It is assumed that inlays do not want to handle these errors, so
                    // we just inform the user with a generic error message.
                    console.error("Inlay caught unexpected " + typeof e, e);
                    alert("Sorry, an error occurred.");
                    return {
                      error: e,
                      responseOk: false,
                      responseStatus: 500 // probably.
                    };
                  })
              });
            } else {
              console.error(
                `Failed to boot inlay ${publicID}: missing data or init function`
              );
            }
          }
        );
        debug("All inlays booted", found);
      },
      request(publicID, fetchParams) {
        if (!publicID) {
          console.warn("Inlay broken: missing publicID in call to request()");
          return Promise.resolve({
            error: "Configuration problem on website. Code Inlay1"
          });
        }
        if (!inlay.inlays[publicID]) {
          console.warn("Inlay broken: invalid publicID in call to request()");
          return Promise.resolve({
            error: "Configuration problem on website. Code Inlay2"
          });
        }

        if (typeof fetchParams.body !== "object") {
          console.warn(
            "Inlay broken: request must be called with object for body"
          );
          return Promise.resolve({
            error: "Configuration problem on website. Code Inlay3"
          });
        }

        // Take a deep copy of the fetchParams since we need to be able to make changes
        // but do not want to change an extenal object.
        fetchParams = JSON.parse(JSON.stringify(fetchParams));

        if (!fetchParams.headers || !fetchParams.headers["Content-Type"]) {
          // Provide default content type json header, and ensure body is stringified.
          fetchParams.headers = fetchParams.headers || {};
          fetchParams.headers["Content-Type"] = "application/json";
        }

        // Add the publicID to the payload.
        fetchParams.body.publicID = publicID;
        fetchParams.body.runID = CiviCRMInlay.runId;
        // JSONify payload.
        fetchParams.body = JSON.stringify(fetchParams.body);

        // Prepare the URL.
        var url = inlay.inlays[publicID].inlayEndpoint;

        // Support xdebug for debugging/dev. Make a request with an 'xdebug'
        // property on the fetch params, the value of this should be the string
        // expected by your IDE.
        if ("xdebug" in fetchParams || window.CiviCRMInlay.xdebug) {
          url += (url.indexOf("?") > -1 ? "&" : "?") + "XDEBUG_TRIGGER=1";
          delete fetchParams.xdebug;
        }

        // If method is GET, then we send the json in the query string under inlayJSON
        if (fetchParams.method === "get") {
          url +=
            (url.indexOf("?") > -1 ? "&" : "?") +
            "inlayJSON=" +
            encodeURIComponent(fetchParams.body);
          delete fetchParams.body;
        }

        // Provide some default params.
        fetchParams = Object.assign({
          mode: "cors", // no-cors, cors, same-origin
          cache: "no-cache", // default, no-cache, reload, force-cache, only-if-cached
          credentials: "omit", // include, same-origin, omit
          redirect: "error", // manual, follow, error
          referrerPolicy: "origin-when-cross-origin" // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
        }, fetchParams);

        return fetch(url, fetchParams).then(r => {
          const contentType = r.headers.get("content-type");
          if (!contentType || !contentType.includes("application/json")) {
            throw new TypeError("Server Error: JSON response required.");
          }

          // r.json() returns a Promise that resolves /to/ the parsed object.
          return r.json().then(d => {
            // Copy the status code to the object we pass on.
            d.responseStatus = r.status;
            d.responseOk = r.ok;
            // Pass on the JSON object.
            return d;
          });
        });
      }
    };
  })();
}
