<?php

include 'commonFunctions.php';
define( 'INIT_METHODS' , array('distance','time','distanceIndexed') );

class Stream{

    /** @var array $timeIndexed = data indexed by time (raw) */
    private $timeIndexed;

    /** @var array $distanceIndexed = data indexed by distance */
    private $distanceIndexed = array();

    /** @var float $distance = total activity distance (meters) */
    private $distance = 0;

    /** @var int $epoch = Start time of activity (seconds)  */
    private $epoch = 0;

    /** @var int $verbose */
    private $verbose = 0;

    /** @var float $stepSize = the quantise snap amount */
    private $stepSize = 10;

    /**
    * @param string $data
    */
    public function __construct( $data ){

        $tmp = json_decode($data);

        if($tmp===null){
            throw new Exception("Invalid JSON, could not parse");
        }

        if (!isset($tmp->stream)){
            throw new Exception("missing or malformed stream data");
        }

        $this->timeIndexed = $tmp->stream;
        $this->init(['distance','time']);

    }


    /**
    * @param int $verbosity
    */
    public function setVerbosity($verbosity) :void {
        $this->verbose = 1;
    }


    /**
    * @param float $stepSize
    */
    public function setStepSize($stepSize) :void {
        if($stepSize<=0){
            echo "minimum step size is 1m\n";
            $this->stepSize = 1;
        }else{
            $this->stepSize = $stepSize;
        }
    }


    /**
    * Initialise summary stats
    * @param array $arr = items to Initialise
    */
    private function init( $arr ) :void {
        foreach ($arr as $a) {
            if(in_array($a,INIT_METHODS)){
                call_user_func( [$this, "init".ucfirst($a)] );
            }else{
                echo "unknown method: $a\n";
            }
        }
    }


    /**
    * Set the distance from lat/lng
    */
    private function initDistance() :void {
        $this->timeIndexed[0]->distance = 0;
        $this->timeIndexed[0]->cumulativeDistance = 0;

        $total = 0;
        foreach ( array_slice($this->timeIndexed,1) as $p => $pin) {
            $pin->distance = haversineGreatCircleDistance(
                $this->timeIndexed[$p]->point->lat,
                $this->timeIndexed[$p]->point->lng,
                $pin->point->lat,
                $pin->point->lng
            );
            $total += $pin->distance;
            $pin->cumulativeDistance = $total;

        }

        $this->distance = $total;

    }


    /**
    * Initialise time
    */
    private function initTime() :void {
        $this->epoch = (int)$this->timeIndexed[0]->time;
        $this->timeIndexed[0]->elapsed = 0;
        $this->timeIndexed[0]->timeToLast = 0;

        foreach ( array_slice($this->timeIndexed,1) as $w => $waypoint) {
            $waypoint->elapsed = (int)($waypoint->time - $this->epoch);
            $waypoint->timeToLast = $waypoint->elapsed - $this->timeIndexed[$w]->elapsed;
        }
    }


    /**
    * Find the nearest neighbour within
    * @param float $needle = the search distance
    * @param array $haystack = simple array of cumulativeDistance
    */
    private function nearestNeighbour($needle, $haystack) :int {

        $n = count($haystack);
        $result = binarySearch($needle, $haystack);

        if($result >= 0){
            if(1 === $this->verbose){
                printf("exact match: [%d]",$result);
                echo "\n";
            }
            return $this->timeIndexed[$result]->elapsed;
        }else{
            // approximate match, find the closest neighbour and calculate interpolation weighting
            $lowerLimit = abs($result);

            $d1 = $this->timeIndexed[$lowerLimit]->cumulativeDistance;
            $d2 = $this->timeIndexed[$lowerLimit+1]->cumulativeDistance;

            $distanceRange = $this->timeIndexed[$lowerLimit+1]->cumulativeDistance - $this->timeIndexed[$lowerLimit]->cumulativeDistance;
            $distanceOffset = $needle - $this->timeIndexed[$lowerLimit]->cumulativeDistance;

            $t1 = $this->timeIndexed[$lowerLimit]->elapsed;
            $t2 = $this->timeIndexed[$lowerLimit+1]->elapsed;

            $i = ($t2-$t1);
            $v = $t1 + ($i * $distanceOffset / $distanceRange);
            $skew = round(100 * $distanceOffset / $distanceRange,1);

            if(1 === $this->verbose){
                echo "nearest neighbours:\n";
                printf("d1--d2: %.2fm - %.2fm (%.2fm) [%d,+1]", $d1, $d2 , $distanceRange, $lowerLimit );
                echo "\n";

                echo "skew: $skew%\n";

                printf("t1--t2: %ds - %ds (%ds)", $t1, $t2, $i );
                echo "\n";

                printf("Interpolation: %.2fs", $v );
                echo "\n";
            }

        }

        return $v;

    }


    /**
    * Initialise the stream data indexed by distance
    */
    private function initDistanceIndexed() :void {

        $ti = array_map(
            function($i) :int { return $i->distance; },
            $this->timeIndexed
          );

        // $d = array_filter($d); // remove elements with 0 distance

        if(1 === $this->verbose){
            $average = array_sum($ti)/count($ti);
            printf("Average distance between waypoints: %.1f meters", $average);
            echo "\n";
        }

        $ti = array_map(
            function($i) :float { return round($i->cumulativeDistance,2); },
            $this->timeIndexed
          );

        for ( $i = 0; $i <= $this->distance ; $i += $this->stepSize ) {

            $tmp = new stdclass;
            $tmp->distance = $i;
            $tmp->elapsed = $this->nearestNeighbour( $i, $ti );

            $this->distanceIndexed[] = $tmp;

        }

        // A final pass to set the timeToLast
        $this->distanceIndexed[0]->timeToLast = 0;
        foreach ( array_slice($this->distanceIndexed,1) as $w => $waypoint) {
            $waypoint->timeToLast = $waypoint->elapsed - $this->distanceIndexed[$w]->elapsed;
        }

    }


    /**
    * Get the fastest continuous section of the specified distance
    * https://en.wikipedia.org/wiki/Maximum_subarray_problem
    * Convert steps to (quantised) distance
    * Use Kadane's in it's simplest form to find the minimum sum (timeToLast)
    * @param int $k = window size in meters (e.g. 5000)
    */
    public function getFastestWindow( $k ) :void {

        if( $k<=0 || $k > $this->distance ){
            echo "you're joking right?\n";
            echo "this activity distance: ".  floor($this->distance)."m\n";
            exit(1);
        }

        if(!isset($this->distanceIndexed[0])){
            $this->init(['distanceIndexed']); // to the nearest n meters, with interpolation
        };

        // $step = $this->distanceIndexed[1]->distance; // get the distance step size
        $k/=$this->stepSize; // $step;  // If the step size is 10m, then we need 500 samples

        $di = array_map(
            function($i) :int { return $i->timeToLast; },
            $this->distanceIndexed
          );

        // $di = array_filter($di); // remove elements with 0 distance

        $fw = minSum( $di, (int)floor($k) );

        echo "fastest: \n".
          ($this->distanceIndexed[$fw['indexStart']]->distance).
          "m -- ".($this->distanceIndexed[$fw['indexEnd']]->distance)."m\n";

        $t0 = new DateTime();
        $t0->setTime( 0,0, $this->distanceIndexed[$fw['indexStart']]->elapsed );
        $t1 = new DateTime();
        $t1->setTime( 0,0, $this->distanceIndexed[$fw['indexEnd']]->elapsed );

        echo $t0->format('H:i:s')." -- ".$t1->format('H:i:s')."\n\n";

        $p = $fw['indexStart'];
        $chunkSize = (int)floor(1000 / $this->stepSize);

        if( $k * $this->stepSize > 1000 ){
            echo "splits:\n";
            for ($i=1; $i <= ceil( ($k * $this->stepSize)/1000); $i++) {

                $s = array_slice( $this->distanceIndexed, $p, $chunkSize );

                $s = array_map(function($e) :bool {
                      return $e->timeToLast;
                  }, $s);

                $split = (int)floor(array_sum($s));

                $p += $chunkSize;

                $t = new DateTime();
                $t->setTime( 0,0, $split );
                echo "$i  ".$t->format('H:i:s')."\n";

            }
            echo "\n";
        }

        echo "duration:\n";

        //"m -- ".($this->distanceIndexed[$fw['indexEnd']]->distance)."m\n";

        $date = new DateTime();
        $date->setTime( 0,0, $fw['duration'] );

        echo $date->format('H:i:s')."\n";

    }

}
