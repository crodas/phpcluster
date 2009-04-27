<?php

require_once dirname(__FILE__)."/base.php";

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
            $closest     = -1;

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


?>
