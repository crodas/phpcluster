<?php
require_once "XML/RSS.php";

$blogs = file_get_contents("list.txt");

foreach (explode("\n", $blogs) as $blog) {
    echo "crawling $blog\n";
    $rss =& new XML_RSS($blog);
    $rss->parse();
    foreach ( $rss->getItems() as $item) {
        $raw = serialize($item);
        $id  = sha1($raw);
        if (is_file("data/$id")) continue;
        file_put_contents("data/$id",gzcompress($raw);
    }
}
?>
