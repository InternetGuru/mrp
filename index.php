<?php

## SET CONSTANTS AND VARIABLES

define("URL", "http://www.usccb.org/bible/readings/%s.cfm");
define("URL_DATE_FORMAT", "mdy");
define("DISPLAY_DATE_FORMAT", "l jS \of F Y");
$url = sprintf(URL, date(URL_DATE_FORMAT));
$startTime = round(microtime(true) * 1000);

## CREATE HTML DOCUMENT

$doc = new DOMDocument('1.0', 'UTF-8');
$root = $doc->createElement('html');
$root->setAttribute("lang", "en");
$root = $doc->appendChild($root);
$head = $doc->createElement('head');
$head = $root->appendChild($head);
$link = $head->appendChild($doc->createElement('link'));
$link->setAttribute('rel', 'stylesheet');
$link->setAttribute('type', 'text/css');
$link->setAttribute('href', 'style.css');
$meta = $head->appendChild($doc->createElement('meta'));
$meta->setAttribute('name', 'viewport');
$meta->setAttribute('content', 'width=device-width, initial-scale=1.0');
$title = $doc->createElement('title', "Church Readings");
$title = $head->appendChild($title);
$body = $doc->createElement('body');
$body = $root->appendChild($body);
$h1 = $body->appendChild($doc->createElement("h1", sprintf("Church Readings for %s", date(DISPLAY_DATE_FORMAT))));

## GET THE SOURCE CONTENT

try {

  // get remote URL content
  $src_string = @file_get_contents($url);
  if(!$src_string) {
    throw new Exception("No HTML content found.");
  }

  // parse source HTML
  $src_dom = new DOMDocument('1.0', 'UTF-8');
  if(!@$src_dom->loadHTML($src_string)) {
    throw new Exception("Unable to parse HTML.");
  }

} catch(Exception $e) {

  $code = $e->getCode() ? $e->getCode() : 500;
  $title->nodeValue = sprintf("Error %d", $code);
  $h1->nodeValue = sprintf("Error %d", $code);
  $p = $body->appendChild($doc->createElement("p"));
  $p->nodeValue = $e->getMessage();

}

## PROCESS THE SOURCE CONTENT

$divs = $src_dom->getElementsByTagName("div");
foreach($divs as $div) {
  if($div->getAttribute("class") != "bibleReadingsWrapper") {
    continue;
  }
  $chunk = $src_dom->saveHTML($div);
  $chunk = preg_replace("/\s*<a href=.+?<\/a>\s*/s", '', $chunk);
  $chunk = preg_replace("/<div.*?>/", '', $chunk);
  $chunk = preg_replace("/<\/div>/", '', $chunk);
  $chunk = str_replace("<h4>", '</p><h2>', $chunk);
  $chunk = str_replace("</h4>", '</h2><p>', $chunk);
  $chunk = str_replace("<br><br>", '</p><p>', '<p>'.$chunk.'</p>');
  $chunk = str_replace("<br><br>", '</p><p>', $chunk);
  $chunk = preg_replace("/\s*<\/p>/", '</p>', $chunk);
  $chunk = preg_replace("/<p>\s*/", "<p>", $chunk);
  $chunk = str_replace("<p></p>", '', $chunk);
  $chunk = str_replace("<br></p>", "</p>", $chunk);
  $chunk_dom = new DOMDocument('1.0', 'UTF-8');
  $chunk_dom->loadHTML($chunk);
  foreach ($chunk_dom->getElementsByTagName("body")->item(0)->childNodes as $node) {
    $body->appendChild($doc->importNode($node, true));
  }
}

## GENERATE FOOTER

$dl = $body->appendChild($doc->createElement("dl"));
$dl->appendChild($doc->createElement("dt", "Source"));
$dl->appendChild($doc->createElement("dd", $url));
$endTime = round(microtime(true) * 1000);
$dl->appendChild($doc->createElement("dt", "Time"));
$dl->appendChild($doc->createElement("dd", $endTime-$startTime." ms"));

## PRINT HTML CONTENT

echo "<!doctype html>";
echo $doc->saveHTML();