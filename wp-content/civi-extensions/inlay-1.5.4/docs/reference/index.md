**Reference**: is written plainly about what is available and how to work with it. APIs etc.

## HTTP API

The `civicrm/inlay-api` http end point is primarily there for Javascript use,
but at times you may need to use it in another language. Example: the
[grassroots petition](https://github.com/artfulrobot/grassrootspetition) inlay
uses this to pre-render a list of petitions.

### POST vs GET requests

POST should be used whenever the request has side effects, e.g. changes data. GET is useful for fetching cachable resources.

Both require passing an `Origin:` header with a configured allowed origin.

Both pass data as a JSON object which must contain the `publicID` key to
identify the inlay intance that should handle the request. The rest of the
request body is up to the inlay in question.

### Using httpie

[httpie](https://github.com/httpie/) is a great command line interface for http requests. e.g. for a POST request you can specify simple key:value pairs as arguments and they will be converted to a JSON object in the body.

```sh
http https://example.org/civicrm/inlay-api Origin:https://allowed.example.org publicID=4cd37771f769 other=data
```

GET request...

```sh
inlayJSON='{"publicID": "4cd37771f769", "other":"data"}'
http GET https://example.org/civicrm/inlay-api Origin:https://allowed.example.org  inlayJSON=="$inlayJSON"
```

### Using Guzzle

```php
<?php
use GuzzleHttp\Client;

$body = [
  'publicID' => 'ea601bdb6105', // your inlay's ID
  // Whataever else your inlay expects/supports.
];

$g = new Client([
    'base_uri'    => 'https://example.org/civicrm/inlay-api',
    'http_errors' => FALSE, // don't get exceptions from guzzle requests.
  ]);

// GET request...
$response = $g->request('get', '', [
  'query' => ['inlayJSON' => json_encode($body)],
  'headers' => [
    'Origin' => 'https://allowed.example.org',
  ],
]);
// POST request...
$response = $g->request('post', '', [
  'body' => json_encode($body),
  'headers' => [
    'Origin' => 'https://allowed.example.org',
  ],
]);

$json_returned = ($response->hasHeader('Content-Type')
  && preg_match('@^application/(problem\+)?json\b@i', $response->getHeader('Content-Type')[0]));

if ($json_returned) {
  // OK
  $data = json_decode($response->getBody(), TRUE);
  // Do something with $data
}
else {
  // Handle error.
}
```

