<?php

/**
 * Calculates the great-circle distance between two points, with
 * the Haversine formula.
 * @param float $latitudeFrom Latitude of start point in [deg decimal]
 * @param float $longitudeFrom Longitude of start point in [deg decimal]
 * @param float $latitudeTo Latitude of target point in [deg decimal]
 * @param float $longitudeTo Longitude of target point in [deg decimal]
 * @param float $earthRadius Mean earth radius in [m]
 * @return float Distance between points in [m] (same as earthRadius)
 */
function haversineGreatCircleDistance( $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000 ){
  // convert from degrees to radians
  $latFrom = deg2rad($latitudeFrom);
  $lonFrom = deg2rad($longitudeFrom);
  $latTo = deg2rad($latitudeTo);
  $lonTo = deg2rad($longitudeTo);

  $latDelta = $latTo - $latFrom;
  $lonDelta = $lonTo - $lonFrom;

  $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
    cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
  return $angle * $earthRadius;
}


/**
* find the min total consecutive values using Kadane's algorithm
* @param array $arr = simple array
* @param int $k = window size (array elements)
*/
function minSum( $arr, $k ) :array {
    $p = 0;

    if (count($arr) < $k){
        return array();
    }

    // Compute sum of first window of size k
    $res = 0;
    for($i = 0; $i < $k; $i++){
        $res += $arr[$i];
    }

    // Compute sums of remaining windows by:
    //   removing first element of previous window and
    //   adding last element of current window.
    $curr_sum = $res;
    for($i = $k; $i < count($arr); $i++)
    {
        $curr_sum += $arr[$i] - $arr[$i - $k];
        if($res > $curr_sum){
            $p = $i;
        }
        $res = min($res, $curr_sum);
    }

    return[
        'indexStart' => ( $p===0 ? 0 : $p - $k ),
        'indexEnd' => ( $p===0 ? $p + $k : $p ),
        'duration'=> $res
      ];
}


/**
* find the closest match to a value within an array using recursion.
* if an exact match is found return that index else, return the negated lower limit
* @param float $x = the needle
* @param array $arr = the array
* @param int $l = left pointer
* @param int $r = right pointer
*/
function binarySearch( $x, $arr, $l = 0, $r = null ) :int {

    if(null === $r){
        $r = count($arr)-1;
    }

    if ($r >= $l){
        $mid = (int)ceil($l + ($r - $l) / 2);

        // element is present at the middle itself
        if ($arr[$mid] == $x){
            return (int)floor($mid);
        }

        // element is smaller than mid, then search left subarray
        if ($arr[$mid] > $x){
            return binarySearch($x, $arr, $l, $mid - 1);
        }

        // else search right subarray
        return binarySearch($x, $arr, $mid + 1, $r);
    }

    // Exact element is not present, return negated index of closest lower limit
    return -$r;

}
