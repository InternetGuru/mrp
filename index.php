<?php

## SET CONSTANTS AND VARIABLES

define("DOMAIN", "http://www.usccb.org");
define("URL_PATH", "/bible/readings");
define("URL", DOMAIN.URL_PATH."/%s.cfm");
define("URL_DATE_FORMAT", "mdy");
define("DISPLAY_DATE_FORMAT", "l jS \of F Y");
define("DIV_CLASS", "bibleReadingsWrapper");
define("SPECIAL_CONTENT", "special");
$date = isset($_GET['date']) ? $_GET['date'] : date(URL_DATE_FORMAT);
$content = isset($_GET['content']) ? $_GET['content'] : 'default';
$url = sprintf(URL, $date);
$startTime = round(microtime(true) * 1000);
$special_url = null;

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
$h1 = $body->appendChild($doc->createElement("h1", "Mass Readings Project"));

## FUNCTIONS

function dateReformat($date, $srcFormat = 'Y-m-d', $destFormat = 'ymd') {
  $d = DateTime::createFromFormat($srcFormat, $date);
  if ($d && $d->format($srcFormat) == $date) return $d->format($destFormat);
  throw new Exception(sprintf("Invalid date parameter (must conform '%s' format)", URL_DATE_FORMAT));
}

function getRemoteContent($url) {
  $curl = curl_init();
  try {
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_URL => $url));
    $content = curl_exec($curl);
    $response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if($response_code != 200) {
      throw new Exception(sprintf("Unable to get content from URL (status code %s).", $response_code));
    }

    $src_dom = new DOMDocument('1.0', 'UTF-8');
    if(!@$src_dom->loadHTML($content)) {
      throw new Exception("Unable to parse HTML.");
    }
    return $src_dom;
  } catch (Exception $e) {
    throw $e;
  } finally {
    curl_close($curl);
  }
}

function getClassDiv (DOMDocument $doc, $class = DIV_CLASS) {
  $array = [];
  $divs = $doc->getElementsByTagName("div");
  foreach($divs as $div) {
    if($div->getAttribute("class") == $class) {
      $array[] = $div;
    }
  }
  return $array;
}

## GET THE SOURCE CONTENT

try {

  // get source content
  $longDate = dateReformat($date, URL_DATE_FORMAT, DISPLAY_DATE_FORMAT);
  $h1->nodeValue = sprintf("Mass Readings for %s", $longDate);
  $src_dom = getRemoteContent($url);
  $classDivs = getClassDiv($src_dom);

  // looking for special or alternative masses for the day
  $chunk = $src_dom->saveHTML(current($classDivs));
  if(strpos($chunk, "<ul>\n<li><h3><a class=") !== false) {
    # get urls
    preg_match_all("/href=\"(.+?)\"/", $chunk, $matches);
    if (count($matches[1]) < 2) {
      throw new Exception("Special day links not found.");
    }
    $default_url = DOMAIN . $matches[1][0];
    $special_url = DOMAIN . $matches[1][1];
    $src_dom = getRemoteContent($content == SPECIAL_CONTENT ? $special_url : $default_url);
    $classDivs = getClassDiv($src_dom);
  }

  // process source content
  foreach ($classDivs as $div) {
    $chunk = $src_dom->saveHTML($div);
    #$chunk = preg_replace("/\s*<a href=.+?<\/a>\s*/s", '', $chunk); // remove links
    $chunk = str_replace('<a href="/', '<a href="'.DOMAIN."/", $chunk);
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
    if (!@$chunk_dom->loadHTML($chunk)) {
      throw new Exception("Unable to parse HTML");
    }
    foreach ($chunk_dom->getElementsByTagName("body")->item(0)->childNodes as $node) {
      $body->appendChild($doc->importNode($node, true));
    }
  }

} catch(Exception $e) {

  $code = $e->getCode() ? $e->getCode() : 500;
  $title->nodeValue = sprintf("Error %d", $code);
  $h1->nodeValue = sprintf("Error %d", $code);
  $p = $body->appendChild($doc->createElement("p"));
  $p->nodeValue = $e->getMessage();

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