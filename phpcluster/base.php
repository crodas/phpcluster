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

?>
