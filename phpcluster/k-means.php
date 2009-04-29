<?php
/**
 *  K-means implementation.
 *
 *  PHP Version 5
 *
 *  @category Text
 *  @package  PHPClustering
 *  @author   César Rodas <crodas@member.fsf.org>
 *  @license  http://www.php.net/license/3_01.txt PHP License 3.01
 *  @link     http://cesar.la/cluster
 */

require_once dirname(__FILE__)."/base.php";

/**
 *  Array Merge 
 *
 *  @param array &$array Target array
 *  @param array $array1 Array to merge with $array
 *
 *  @return void
 */
function Array_Merge_ex(&$array, $array1)
{
    foreach ($array1 as $key => $value) {
        if (!isset($array[$key])) {
            $array[$key] = 0;
        }
        $array[$key] += $value;
    }
}

/**
 *  This class imeplements K-means clustering algorithm.
 *
 *  @category Text
 *  @package  PHPClustering
 *  @author   César Rodas <crodas@member.fsf.org>
 *  @license  http://www.php.net/license/3_01.txt PHP License 3.01
 *  @link     http://cesar.la/cluster
 */
class Kmeans extends Cluster_base
{
    private $_centroid   = 100;
    protected $threshold = 0.5;

    // {{{ setThreshold
    /**
     *  Centroid members minimum score threshold. 
     *  
     *  The threshold value must be > 1 and < 100, greatest
     *  values means better results and more iterations.
     *  
     *  The default value is 50%.
     *
     *  @param integer $threshold Threshold value.
     *
     *  @return bool
     */
    final function setThreshold($threshold)
    {
        if ($threshold > 100 or $threshold < 1) {
            return false;
        }
        $this->threshold = $threshold/100;
        return true;
    }
    // }}}

    // {{{ setCentroids
    /**
     *  Set the number of centroids.
     *
     *  The centroid value is the number of clusters
     *  or groups where the documents will be grouped. 
     *
     *  This value need to be less than half of number
     *  of documents to categorize.
     *
     *  @param int $number Number of centroids.
     *
     *  @return bool
     */
    final function setCentroids($number)
    {
        if (!is_integer($number)) {
            return false;
        }
        $this->_centroid = $number;
        return true;
    }
    // }}} 

    // {{{ _narray
    /**
     *  Auxiliar function to generar N arrays of arrays/
     *
     *  @param int $ncur Number of elements.
     *
     *  @return array
     */
    final private function _narray($ncur)
    {
        $arr = array();
        for ($i=0; $i < $ncur; $i++) {
            $arr[$i] = array();
        }
        return $arr;
    }
    // }}} 

    //  mainCluster  {{{
    /**
     *  The K-means algorithm implementation.
     *
     *  K-means clustering is a method of cluster analysis which 
     *  aims to partition n observations into k clusters in which 
     *  each observation belongs to the cluster with the nearest mean. 
     *
     *  It is similar to the expectation-maximization algorithm for mixtures
     *  of Gaussians in that they both attempt to find the centers of 
     *  natural clusters in the data.
     *  
     *  @param int   $iteration Maximum number of iterations.
     *  @param array &$node     Array of objects to categorize
     *
     *  @return array Array of centroids and elements IDs.
     */
    final protected function mainCluster($iteration,&$node)
    {   
        $max       = count($node);
        $threshold = 1 - $this->threshold;
        $ncentroid = $this->_centroid;
        $centroid  = array();

        /* initialize first centroids  {{{ */
        $temp = array();
        if ($ncentroid > ($max/2)) {
            $ncentroid = $max/2 -1;
        }

        for ($i=0; $i < $ncentroid; $i++) {
            do {
                $id = rand(0, $max);
            } while (isset($temp[$id]));
            $temp [$id] = true;
            $centroid[] = $node[$id];
        }
        unset($temp);
        /* }}} */

        /* main loop */
        $oldmatches = array();
        for ($ite = 0; $ite < $iteration; $ite++) {
            $this->doLog("Iteration $ite");
            $bmatches = $this->_narray($ncentroid);

            /* find a centroid for every element {{{ */
            for ($i=0; $i < $max; $i++) {
                $row        = & $node[$i];
                $bmatch_val = 2;
                for ($e = 0; $e < $ncentroid; $e++) {
                    if ($centroid[$e] === null) {
                        continue;
                    }
                    $d = $this->distance($centroid[$e], $row);
                    if ($d < $bmatch_val) {
                        $bmatch     = $e;
                        $bmatch_val = $this->distance($centroid[$bmatch], $row);
                    }
                }
                /* we just want good results, that's why there */
                /* is a threshold                              */
                if ($bmatch_val < $threshold) {
                    $bmatches[$bmatch][] = $i;
                    $xmatches[$bmatch][] = $bmatch_val;
                }
            }
            /* }}} */

            if ($bmatches === $oldmatches) {
                break; /* we got a perfect clustering */
            }
            $oldmatches = $bmatches;

            /* merge all the features per centroid {{{ */
            $free = 0;
            for ($i=0; $i < $ncentroid; $i++) {
                $nnodes = count($bmatches[$i]);
                if ($nnodes <= 1 || $centroid[$i] == null) {
                    /* empty centroid or only one match*/
                    $free++;
                    $centroid[$i] = null;
                    continue;
                }
                /* merging all features in every node */
                $wcount = array();
                $avg    = array();
                for ($e=0; $e < $nnodes; $e++) {
                    $nid = $bmatches[$i][$e];
                    array_merge_ex($avg, $node[$nid]->features);
                }

                /* saving only the average */
                foreach (array_keys($avg) as $wid) {
                    $avg[$wid] = ceil($avg[$wid] / $nnodes);
                    if ($avg[$wid] <= 0) {
                        unset($avg[$wid]);
                    }
                }

                /* add the new centroid value and prepare */
                $centroid[$i]->features = $avg;
                $this->distance_init($centroid[$i]);
            }
            /* }}} */

            $this->doLog("\t$free free clusters");
        }

        /* now from out $bmatches get the key */
        /* put into an array and return it {{{  */
        $clusters = array();
        for ($i=0; $i < $ncentroid; $i++) {
            if ($bmatches[$i] === null || count($bmatches[$i]) == 1) {
                continue;
            }
            $cluster  = array();
            $elements = & $bmatches[$i];
            $nelement = count($elements);
            for ($e=0; $e < $nelement; $e++) {
                $id           = $node[$elements[$e]]->id;
                $cluster[$id] = array($this->text[$id], $xmatches[$i][$e]);
            }
            if (count($cluster)==0) {
                continue;
            }
            $clusters[] = $cluster;
        }
        /*  }}} */

        return $clusters;
    }
    // }}}

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
