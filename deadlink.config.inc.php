<?php
    //Create a file in the same directory as this on named deadlink.config.local.inc.php and copy the stuff below.
    
    //Activate this to run the bot on a specific page for debugging purposes.
    $debug = false;
    $debugPage = array( 'title'=>"", 'pageid'=>0 );
    $debugStyle = 20;   //Use an int to run through a limited amount of articles.  Use "test" to run the test pages.

    //Set the bot's UA
    $userAgent = '';

    //Location of memory files to be saved
    $dlaaLocation = '';

    //Experimental multithreading (not ready)
    $multithread = false;
    $workers = false;
    $workerLimit = 3;
    $useQueuedArchiving = false;
    $useQueuedEditing = false;
    
    //Set Wiki to run on, define this before this gets called, to run on a different wiki.
    define( 'WIKIPEDIA', "enwiki" );
    
    //Wiki connection setup.  Uses the defined constant WIKIPEDIA.
    switch( WIKIPEDIA ) {
        default:
        $apiURL = "https://en.wikipedia.org/w/api.php";
        $username = "";
        $password = "";
        $runpage = "";
        $taskname = "";
        $nobots = false;
        break;
    }
    
    //DB connection setup
    $useLocalDB = false;
    $host = "";
    $port = "";
    $user = "";
    $pass = "";
    $db = "";
    
    //Wikipedia DB setup
    $useWikiDB = false;
    $wikihost = "";
    $wikiport = "";
    $wikiuser = "";
    $wikipass = "";
    $wikidb = "";
    
    
    //Don't copy any of this below.
    if( file_exists( 'deadlink.config.local.inc.php' ) ) require_once( 'deadlink.config.local.inc.php' );
    define( 'USERAGENT', $userAgent );
    define( 'COOKIE', $username.WIKIPEDIA.$taskname );
    define( 'API', $apiURL );
    define( 'NOBOTS', true );
    define( 'USERNAME', $username );
    define( 'TASKNAME', $taskname );
    define( 'DLAA', $dlaaLocation );
    define( 'RUNPAGE', $runpage );
    define( 'MULTITHREAD', $multithread );
    define( 'WORKERS', $workers );
?>
