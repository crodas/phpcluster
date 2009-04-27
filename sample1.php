<?php

require "phpcluster/k-means.php";

$c = new Kmeans;
$c->setCentroids(200);
$c->setThreshold(50);

foreach (glob("data/*") as $file) {
    $rss = unserialize(gzuncompress(file_get_contents($file)));
    if (!isset($rss['link']) && isset($rss['guid'])) {
        $rss['link'] = $rss['guid'];
    }
    if (!isset($rss['title']) || !isset($rss['link']) || !isset($rss['description'])) {
        continue;
    }
    if (strncmp("http://www.perfil.com/",$rss['link'],22)==0) {
        continue;
    }

    if (strpos($rss['link'],'.py') === false) {
        continue;
    }
    //$description = strip_tags(utf8_decode($rss['description']));
    $c->addPeer(trim($rss['link']), utf8_decode($rss['title']));
}

$clusters = $c->doCluster();
foreach ($clusters as $id => $links) {
    echo "    * Cluster $id\n";
    foreach ($links as $link => $text) {
        echo "        * [[$link|{$text[0]}]] ({$text[1]})\n";
    }
}
?>
