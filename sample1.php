#!/usr/bin/php 
<?php
// read the README

require "phpcluster/k-means.php";

$c = new Kmeans;
$c->setCentroids(1000);
$c->setThreshold(50);

echo "Loading entries\n";
$foo = 0;
$title = array();
foreach (glob("data-abc/*") as $file) {
    $rss = unserialize(file_get_contents($file));
    if (!isset($rss['link']) && isset($rss['guid'])) {
        $rss['link'] = $rss['guid'];
    }
    if (!isset($rss['title']) || !isset($rss['link']) || !isset($rss['description'])) {
        continue;
    }

    if (trim($rss['title'])=="") {
        continue;
    }


    if (isset($title[$rss['title']])) {
        continue;
    }
    $title[$rss['title']] = true;

    /* transform id to link */
    $id = substr(trim($rss['link']),5);
    $id = substr($id,0,strlen($id) - 5);
    $link = "http://www.abc.com.py/imprimir.php?pid=$id";

    /* split the calc in chunks of 20,000 news is a */
    /* a good idea, since large files will take a lot */
    /* of time */
    if (++$foo == 20000) break;

    $c->addElement($link, $rss['title']);
}
echo "Organizing\n";
$clusters = $c->doCluster();
foreach ($clusters as $id => $links) {
    echo "    * Cluster $id\n";
    foreach ($links as $link => $text) {
        echo "        * [[$link|{$text[0]}]] ({$text[1]})\n";
    }
}
?>
