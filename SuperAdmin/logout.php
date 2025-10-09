<?php
    // session start at the top of file 
    session_start();

    //unsetting different session variables during logging out 
    session_unset();

    //destroying the session set during different stages
    session_destroy();

    //redirecting to login page after logging out
    header("Location: login.php");

    //exiting this logout.php file and making sure no files are run
    exit;
?>
