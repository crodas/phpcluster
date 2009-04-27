<?php

require_once dirname(__FILE__)."/base.php";

function array_merge_v2(&$array, $array1)
{
    foreach($array1 as $key => $value) {
        if (!isset($array[$key])){
            $array[$key] = 0;
        }
        $array[$key] += $value;
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
                $wcount = array();
                $avg    = array();
                for ($e=0; $e < $nnodes; $e++) {
                    $nid = $bmatches[$i][$e];
                    array_merge_v2($avg, $node[$nid]->features);
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


?>
