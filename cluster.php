<?php

/**
 *  Cluster base
 *
 *  This abstract class provides an enviroment to 
 *  implement any text clustering algorithm. 
 */
abstract class Cluster_Base 
{
    protected $data  = array();
    protected $text  = array();
    protected $index = array();
    protected $word_count = 0;
    private $_wordcount = array();
    private $_maxfeaturesfreq = 100;

    final public function addPeer($id, $raw_text)
    {
        $text = $this->filterPeer($raw_text);
        if (!is_string($text)) {
            return false;
        }
        $features = $this->getFeatures($text);
        if (is_array($features)) { 
            $this->text[$id] = $raw_text;
            $data   = & $this->data[$id];
            $index  = & $this->index;
            $wcount = & $this->_wordcount; 
            $isize  = count($index);
            foreach ($features as $feature => $count) {
                if (!isset($index[$feature])) {
                    $index[$feature]  = $isize++;
                    $wcount[$feature] = 0;
                }
                $data[$index[$feature]] = $count;
                $wcount[$feature]++;

            }
            $this->word_count = count($index)/2;
            return true;
        }
        return false;
    }

    protected function filterPeer($text)
    {
        return strtolower($text);
    }

    protected function getFeatures($text)
    {
        $words = array();
        foreach(preg_split("/[^a-zραινσϊνσϊό]/i",$text) as $word) {
            if (strlen($word) > 2) {
                if (!isset($words[$word])) {
                    $words[$word] = 0;
                }
                $words[$word]++;
            }
        }
        return $words;
    }


    final private function pearson_pow($number, $exp=2)
    {
        return pow($number, $exp);
    }

    protected function distance_init(&$node)
    {
        $features  = & $node->features; 
        $node->sum = array_sum($features);
        $seq       = array_sum(array_map(array(&$this,"pearson_pow"),$features));
        $node->den = $seq - pow($node->sum,2) / $this->word_count; 
    }

    protected function distance(&$node1, &$node2)
    {
        if (!$node1 instanceof stdclass) {
            throw new exception("error");
        }
        if (!$node2 instanceof stdclass) {
            throw new exception("error");
        }
        $v1 = & $node1->features;
        $v2 = & $node2->features;

        if (count($v1) > count($v2)) {
            $min = & $v2;
            $max = & $v1;
        } else {
            $min = & $v1;
            $max = & $v2;
        }

        $sum1       = $node1->sum;
        $sum2       = $node2->sum;
        $word_count = $this->word_count;

        $pSum = 0;
        foreach ($min as $id => $count) {
            if (!isset($max[$id])) {
                continue;
            }
            $pSum += $count * $max[$id]; 
        }
        $num = $pSum - ($sum1 * $sum2 / $word_count);
        $den = sqrt($node1->den * $node2->den);
        if ($den == 0) {
            return 0;
        }
        return 1-($num/$den);
    }


    final function setFeaturesFreqThreshold($max=100) {
        if ($max > 100 or $max < 1) {
            return false;
        }
        $this->_maxfeaturesfreq = $max/100;
        return true;
    }

    final function doCluster($iteration=10)
    {
        $nodes    = array();
        $raw_data = & $this->data;

        if ($this->_maxfeaturesfreq != 100) {
            $wcount = & $this->_wordcount;
            $index  = & $this->index;
            $lndata = count($raw_data);
            $thold  = $this->_maxfeaturesfreq * $lndata;
            $todel  = array();
            arsort($wcount);

            foreach ($wcount as $word => $count) {
                if ($count < $thold) {
                    break;
                }
                $todel[] = $word;
                echo "Deleting common word $word\n";
            }

            foreach ($todel as $word) {
                $id = $index[$word];
                foreach (array_keys($raw_data) as $lid) {
                    if (isset($raw_data[$lid][$id])) {
                        unset($raw_data[$lid][$id]);
                    }
                }
                unset($index[$word]);
            }
        }

        foreach (array_keys($raw_data) as $id) {
            $anode           = new stdClass;
            $anode->id       = $id;
            $anode->features = & $raw_data[$id];
            $anode->left     = 0;
            $anode->right    = 0;
            $anode->distance = 0;
            $this->distance_init($anode);
            $nodes[] = $anode;
        }

        return $this->_doCluster($iteration,$nodes);
    }

    abstract protected function _doCluster($iteration=10, &$data);
}

class BiCluster extends Cluster_Base
{
    private $_nodes;

    final protected function _doCluster($iteration=10,&$nodes)
    {   
        $node_count = count($nodes);
        $distances  = array();
        $curClusId  = -1;
        $toProcess  = $node_count;

        /* big and almost ended loop ;) */
        $cache = array();
        while ($toProcess >= 1) {
            $lowestpairs = array(0, 1);
            $closest     = -1 ;//$this->distance($nodes[0], $nodes[1]);

            for ($i=0; $i < $node_count; $i++) {
                fwrite(STDERR,"$i\n");
                for ($e=$i+1; $e < $node_count; $e++) {
                    $d = $this->distance($nodes[$i], $nodes[$e]);
                    if ($d > 1.001 || $d < 0) {
                        die("fails $i $e $d");
                    }
                    if ($d  > $closest) {
                        $closest     = $d;
                        $lowestpairs = array($i,$e); 
                    }
                }
            }
            /* now we got the best pair */
            list($x, $y)        = $lowestpairs;
            /* info */
            echo "find best clustering $x $y with a distance of $closest\n";
            printf("\t%s\n\t%s\n\n",$nodes[$x]->id,$nodes[$y]->id);

            /* create a new node (the cluster bet. best match) */
            $nodeA              = & $nodes[$x];
            $nodeB              = & $nodes[$y];
            $new_node           = new stdclass;
            $new_node->left     = clone $nodeA;
            $new_node->id       = clone $nodeA;
            $new_node->right    = clone $nodeB;
            $new_node->distance = $closest;
            /* merge features */
            $features  = $nodeA->features;
            foreach ($nodeB->features as $feature => $count ) {
                if (!isset($nodeA->features[$feature])) {
                    $features[$feature] = 0;
                }
                $features[$feature] += $count;
            }
            $new_node->features = $features;
            /* prepare it for calc. */
            $this->distance_init($new_node);

            /* delete nodes and add the new cluster */
            unset($nodes[$x]);
            unset($nodes[$y]);
            $nodes[] = $new_node;

            /* recreating the node array */
            $nodes      = array_values($nodes);
            $node_count = count($nodes);
            $toProcess -= 2;
        }
        $nodes = array_values($nodes);
        print_r($nodes);
        file_put_contents("out",serialize($nodes));
    }
}

class Kmeans extends Cluster_base
{
    private $_centroid = 100;
    protected $threshold=0.5;

    final function setThreshold($threshold)
    {
        if ($threshold > 100 or $threshold < 1) {
            return false;
        }
        $this->threshold = $threshold/100;
        return true;
    }

    function setCentroids($number)
    {
        if (!is_integer($number)) {
            return false;
        }
        $this->_centroid = $number;
        return true;
    }

    final private function _narray($ncur)
    {
        $arr = array();
        for ($i=0; $i < $ncur; $i++) {
            $arr[$i] = array();
        }
        return $arr;
    }

    final protected function _doCluster($iteration=10,&$node)
    {   
        $threshold = 1 - $this->threshold;
        $ncentroid = $this->_centroid;
        $centroid  = array();
        $max       = max(array_keys($node));
        $temp      = array();
        for ($i=0; $i < $ncentroid; $i++) {
            do {
                $id = rand(0, $max);
            } while (isset($temp[$id]));
            $temp [$id] = true;
            $centroid[] = $node[$id];
        }
        unset($temp);

        /* main loop */
        $oldmatches = array();
        for($ite = 0; $ite < $iteration; $ite++) {
            fwrite(STDOUT, "Iteration $ite\n");
            $bmatches = $this->_narray($ncentroid);
            /* find a centroid for every node */
            for($i=0; $i < $max; $i++) {
                $row        = $node[$i];
                $bmatch_val = 2;
                for ($e = 0; $e < $ncentroid; $e++) {
                    if ($centroid[$e] === null) continue;
                    $d = $this->distance($centroid[$e], $row);
                    if ($d < $bmatch_val) {
                        $bmatch     = $e;
                        $bmatch_val = $this->distance($centroid[$bmatch], $row);
                    }
                }
                if ($bmatch_val < $threshold) {
                    $bmatches[$bmatch][] = $i;
                    $xmatches[$bmatch][] = $bmatch_val;
                }
            }
            if ($bmatches === $oldmatches) {
                break; /* we got a perfect clustering */
            }
            $oldmatches = $bmatches;

            /* merge all the features per centroid */
            for ($i=0; $i < $ncentroid; $i++) {
                $nnodes = count($bmatches[$i]);
                if ($nnodes <= 1 || $centroid[$i] == null) {
                    /* empty centroid or only one match*/
                    $centroid[$i] = null;
                    continue;
                }
                /* merging all features in every node */
                $avg = array();
                for ($e=0; $e < $nnodes; $e++) {
                    $nid = $bmatches[$i][$e];
                    array_merge_v2($avg, $node[$nid]->features);
                }
                /* saving only the average */
                foreach (array_keys($avg) as $wid) {
                    $avg[$wid] = (int) ($avg[$wid] / $nnodes);
                    if ($avg[$wid] <= 0) {
                        unset($avg[$wid]);
                    }
                }
                /* add the new centroid value and prepare */
                $centroid[$i]->features = $avg;
                $this->distance_init($centroid[$i]);
            }
        }
        /* now from out $bmatches get the key */
        /* put into an array and return it */
        $clusters = array();
        for ($i=0; $i < $ncentroid; $i++) {
            if ($bmatches[$i] === null) {
                continue;
            }
            $cluster  = array();
            $elements = & $bmatches[$i];
            $nelement = count($elements);
            for($e=0; $e < $nelement; $e++) {
                $id           = $node[$elements[$e]]->id;
                $cluster[$id] = array($this->text[$id], $xmatches[$i][$e]);
            }
            if (count($cluster)==0) {
                continue;
            }
            $clusters[] = $cluster;
        }
        return $clusters;
    }
}

function array_merge_v2(&$array, $array1)
{
    foreach($array1 as $key => $value) {
        if (!isset($array[$key])) {
            $array[$key] = 0;
        }
        $array[$key] += $value;
    }
}

$c = new Kmeans;
$c->setCentroids(1000);
$c->setThreshold(70);
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

    //if (strpos($rss['link'],'.py') === false) {
    //    continue;
    //}
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
