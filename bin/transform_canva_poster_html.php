<?php

/**
 * @file
 */

/**
 *
 */
function transform_canva_poster_html($url, $outputFile = 'saved_page.html') {
  echo "Retrieving the web page: $url\n";

  $html = @file_get_contents($url);
  if ($html === FALSE) {
    die("Error: Failed to retrieve the web page at $url. Please check the URL and try again.\n");
  }

  echo "Successfully retrieved the web page.\n";

  $dom = new DOMDocument();

  libxml_use_internal_errors(TRUE);
  $dom->loadHTML($html);
  libxml_clear_errors();

  $baseUrl = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);

  $images = $dom->getElementsByTagName('img');
  foreach ($images as $img) {
    $src = $img->getAttribute('src');
    if (!preg_match('#^https?://#', $src)) {
      $absoluteUrl = rtrim($baseUrl, '/') . '/' . ltrim($src, '/');
      $img->setAttribute('src', $absoluteUrl);
    }

    $img->removeAttribute('srcset');
  }

  // Save the modified HTML to a file.
  if (file_put_contents($outputFile, $dom->saveHTML())) {
    echo "Web page successfully saved as $outputFile\n";
  }
  else {
    echo "Error: Failed to save the web page to $outputFile\n";
  }
}

// Command line argument handling.
if ($argc < 2) {
  die("Usage: php transform_canva_poster_html.php <URL> [output_file]\n");
}

$url = $argv[1];
// Optional argument for output file.
$outputFile = $argv[2] ?? 'saved_page.html';

transform_canva_poster_html($url, $outputFile);
