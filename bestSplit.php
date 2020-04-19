<?php

include 'streamModel.php';
$verbose = 0;
$stepSize = 10;  // meters (default step size)
$windowSize = 1000;  // meters (default window size)
define('PARAM_ORDER' , 'hfswv');

array_shift($argv);  // ignore 0 = filename

$args = args2kv($argv);

sortArgs();
setup();
loadFile($filename);

try {
    $stream = new Stream( $contents );
} catch (\Exception $e) {
    echo("An error occured attempting to process file\n");
    echo "({$e->getMessage()})\n";
    exit(1);
}

if($verbose){
    $stream->setVerbosity($verbose);
}

$stream->setStepSize($stepSize);
$stream->getFastestWindow($windowSize);


/**
* Processes arguments as either k=v or -k v
* @param array $args
* @param string $p the previous key
*/
function args2kv( $args, $p = null ) :array {


    static $tmp = [];
    if(count($args)===0){
        return $tmp;
    }

    if($p===null && $args[0][0]!=='-'){
        foreach ($args as $a => $arg) {
            $kv = explode('=',$arg);
            if(count($kv)===2){
                $tmp[$kv[0]] = $kv[1];
            }else{
                echo "malformed parameters\n";
                exit(1);
            }
        }
        return $tmp;
    }

    $k = array_shift($args);

    if(substr($k, 0, 2) === '--'){
        // e.g. --help
        $k = substr($k,2);
        if(strlen($k)==1){
            $k = "--$k";  //
        }else{
            $k = "-$k";
            $tmp[$k] = 1;
        }
    }elseif( '-' === $k[0] && !is_numeric($k) ){
        // $flags = $k,1 // multiple flags e.g. -opt
        $k = substr($k,1);
        $k = trim( preg_replace( '/([a-z])/', '-$1 ', $k ) );
        $k = explode( " ", $k );
        $k = array_fill_keys($k,1);
        $tmp = array_merge($tmp,$k);
        $k = key(array_slice($k,-1));  // If subsequent value, pair with last
    }else{
        // It's the value, pair with last iff that is a key
        if( (isset($p)) && ($p[0]==='-' && !is_numeric($p)) && (is_numeric($k) || $k[0]!=='-') ){
            $tmp[$p] = $k;
        }else{
            echo "malformed parameters\n";
            exit(1);
        }
    }

    args2kv($args,$k);

    return $tmp;
}


/**
* order args so, for example, if --help is present then that will be processed first
*/
function sortArgs() :void {
    global $args;
    uksort($args,
      /**
      * @param string $a
      * @param string $b
      */
      function( $a, $b ) :int {
          $posA = strpos(PARAM_ORDER, ($a[0]) );
          $posB = strpos(PARAM_ORDER, ($b[0]) );
          if( false === $posA ) return 1;
          if( false === $posB ) return -1;
          return ( $posA > $posB ? 1 : -1 );
      });
}


/**
* initialise provided parameters
*/
function setup() :void {
    global $args;
    global $verbose, $windowSize, $stepSize, $filename;

    foreach ($args as $a => $arg) {
        switch (substr($a,1)) {
          case 'u': case 'url':
          case 'f': case 'file':
              // set filename
              $filename = $arg;
            break;

          case 'v': case 'verbose':
              // set verbosity
              if('0' !== $arg){
                  $verbose = 1;
              }
            break;

          case 'w': case 'window':
              // set window size
              $windowSize = $arg;
            break;

          case 's': case 'stepsize':
              // set verbosity
              $stepSize = $arg;
            break;

          case 'h': case 'help':
              // show help
              echo "Options:\n -f filename\n -s step size (default = 10)\n -v verbosity (default = 0)\n -h help\n\n";
              exit(0);
            break;

          default:
              // unknown option. Ignore
              echo "Notice: unknown parameter $a\n";
            break;
        }
    }

}


/**
* find possible JSON files
*/
function getFile() :string {
    $files = array_filter( scandir('.') ,
        function($i) :bool { return ( substr($i,-5) === '.json' ); }
      );
    switch (count($files)) {
      case 0:
          echo("No JSON files found\n");
          exit(1);
        break;
      case 1:
          $filename = array_shift($files);
          return $filename;
        break;
      default:
          echo("Multiple JSON files found. Specify filename using -f\n");
          exit(1);
        break;
    }
}


/**
* load JSON file
* @param string $filename
*/
function loadFile( $filename = null ) :void {

    global $contents;

    if(empty($filename)){
        $filename = getFile();
        echo "$filename\n";
    }

    //if it's a URL:
    if(substr($filename,0,4) === 'http'){
        $endpoint = "https://nene.strava.com/flyby/stream_compare/{id}/{id}";
        preg_match('~/activities/(\d+)/?~', $filename, $id);
        $id=$id[1];
        $endpoint = preg_replace ( '~\{id\}~' , $id , $endpoint );

        $ccn = curl_init();
        curl_setopt($ccn, CURLOPT_URL, $endpoint);
        curl_setopt($ccn, CURLOPT_RETURNTRANSFER, true);
        $contents = curl_exec($ccn);
        curl_close($ccn);
    }else{
        $contents = @file_get_contents($filename);
    }
    if( false === $contents ){
        echo("An error occured attempting to open file\n");
        exit(1);
    };

}
