<?php
/**
 *  Base Clustering
 *
 *  PHP Version 5
 *
 *  @category Text
 *  @package  PHPClustering
 *  @author   César Rodas <crodas@member.fsf.org>
 *  @license  http://www.php.net/license/3_01.txt PHP License 3.01
 *  @link     http://cesar.la/cluster
 */

/**
 *  Cluster base
 *
 *  This abstract class provides an enviroment to 
 *  implement any text clustering algorithm. 
 *
 *  @category Text
 *  @package  PHPClustering
 *  @author   César Rodas <crodas@member.fsf.org>
 *  @license  http://www.php.net/license/3_01.txt PHP License 3.01
 *  @link     http://cesar.la/cluster
 */
abstract class Cluster_Base
{
    protected $data           = array();
    protected $text           = array();
    protected $index          = array();
    protected $word_count     = 0;
    private $_wordcount       = array();
    private $_maxfeaturesfreq = 100;

    // addElement {{{
    /**
     *  This function add an element and its ID.
     *
     *  @param string $id       Object id
     *  @param string $raw_text Objects content.
     *
     *  @return bool True if success
     */
    final public function addElement($id, $raw_text)
    {
        $text = $this->filterElement($raw_text);
        if (gettype($text) != gettype($raw_text)) {
            return false;
        }
        $features = $this->getFeatures($text);
        if (is_array($features) && count($features) > 1) { 
            /* saving raw text for future reuse */
            $this->text[$id] = $raw_text;

            $data   = & $this->data[$id];
            $index  = & $this->index;
            $wcount = & $this->_wordcount; 
            $isize  = & $this->word_count;
            foreach ($features as $feature => $count) {
                if (!isset($index[$feature])) {
                    $index[$feature]  = $isize++;
                    $wcount[$feature] = 0;
                }
                $data[$index[$feature]] = $count;
                $wcount[$feature]++;
            }
            return true;
        }
        return false;
    }
    // }}} 

    // filterElement {{{ 
    /**
     *  filterElement
     *
     *  This function is a filter that is applied to every
     *  element. This can be overriden in order to implement
     *  your own filter.
     *
     *  @param string $text Element value.
     *
     *  @return string 
     */
    protected function filterElement($text)
    {
        return strtolower($text);
    }
    // }}}

    // getFeatures {{{
    /** 
     *  getFeatures
     *
     *  This function extracts all the features from 
     *  a given element, it must return an array with
     *  the feature as they key and the count of repetition
     *  as its value.
     *
     *  @param string $text Element's content.
     *
     *  @return array 
     */
    protected function getFeatures($text)
    {
        $words = array();
        foreach (preg_split("/[^a-zñáéíóúíóúü']/i", $text) as $word) {
            if (strlen($word) > 2) {
                if (!isset($words[$word])) {
                    $words[$word] = 0;
                }
                $words[$word]++;
            }
        }
        return $words;
    }
    // }}}

    // _pearsonPow {{{
    /**
     *  Auxiliar function, Pearson Pow
     *
     *  @param int $number Number to pow
     *
     *  @return int
     */
    final private function _pearsonPow($number)
    {
        return pow($number, 2);
    }
    // }}} 

    // distanceInit {{{
    /**
     *  Distance init. (Pearson distance)
     *
     *  This function initializes values needed for distance.
     *
     *  Initialization means calculate values that are unchanged
     *  for every node, such information could be stored as properties
     *  
     *  @param object &$element Element to initialize
     *
     *  @return void
     */
    protected function distanceInit(&$element)
    {
        $features       = & $element->features; 
        $element->sum   = array_sum($features);
        $element->count = count($features);
        $element->keys  = array_keys($features);

        $seq = array_sum(array_map(array(&$this, "_pearsonpow"), $features));

        $element->den = $seq - pow($element->sum, 2) / $this->word_count; 
    }
    // }}}

    // distance {{{
    /** 
     *  Distance (Pearson distance)
     *
     *  This function is the main function, this calculate
     *  the distance (similarity) between two elements. By
     *  default it cames with the pearson distance, but this
     *  can be overriden.
     *
     *  @param object &$element1 Element 
     *  @param object &$element2 Element 
     *
     *  @return int from 0 to 1. 0 means match perfectly
     */
    protected function distance(&$element1, &$element2)
    {
        #if (!$element1 instanceof stdclass) {
        #    throw new exception("error");
        #}
        #if (!$element2 instanceof stdclass) {
        #    throw new exception("error");
        #}

        $v1 = & $element1->features;
        $v2 = & $element2->features;

        $pSum = 0;
        foreach ($element2->keys as &$id) {
            if (!isset($v1[$id])) {
                continue;
            }
            $pSum += $v1[$id] * $v2[$id]; 
        }

        $num = $pSum - ($element1->sum * $element2->sum / $this->word_count);
        $den = sqrt($element1->den * $element2->den);
        if ($den == 0) {
            return 0;
        }
        return 1-($num/$den);
    }
    // }}}

    // setFeaturesFreqThreshold  {{{
    /**
     *  Set Maxiumn features frequency, this is useful
     *  to delete too common features, in the case of 
     *  English texts (the, theses, etc).
     *
     *  @param int $max Threshold (100 ... 0)
     *
     *  @return bool
     *  
     *  @experimental
     *  
     */
    final function setFeaturesFreqThreshold($max=100)
    {
        if ($max > 100 or $max < 1) {
            return false;
        }
        $this->_maxfeaturesfreq = $max/100;
        return true;
    }
    // }}}

    // doCluster {{{
    /**
     *  do Cluster
     *
     *  This function prepare all the features, and call to 
     *  distanceInit(), then it call to the mainCluster() function.
     *
     *  @param int $iteration Maximum iteration.
     *
     *  @return array 
     */
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
                $this->doLog("Deleting common word $word");
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
            $this->distanceInit($anode);
            $nodes[] = $anode;
        }

        return $this->mainCluster($iteration, $nodes);
    }
    // }}}

    // doLog {{{
    /**
     *  Do Log.
     *
     *  @param string $string String to log.
     *
     *  @return void
     */
    protected function doLog($string)
    {
        $date = date("Y-m-d H:i:s");
        fwrite(STDERR, "{$date}: {$string}\n");
    }
    // }}}

    /**
     *  Implementation of the cluster itself algorithm.
     *
     *  @param int   $iteration Maximum iteration
     *  @param array &$data     Array of elements.
     *
     *  @return array
     */
    abstract protected function mainCluster($iteration, &$data);
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */
?>
