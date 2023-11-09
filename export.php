<?php
/**
 * @package		LanceCMS - Large Mysql Database Export and Import - Export File
 * @author		Somnath Ghosh (SomLance)
 * @link        https://somlance.com
 * @version		Version 1.0.0
 */

// export database configurations
$servername             = "localhost";
$username               = "username";
$password               = "password";
$database               = "database";
$debug                  = true;

// debug
if ( $debug ) {
    error_reporting ( E_ALL );
    ini_set ( 'display_errors', 'on' );
}

// json header
header ( 'Content-Type: application/json; charset=utf-8' );

// create connection
$conn                   = new mysqli ( $servername, $username, $password, $database );

// check connection
if ( $conn->connect_error ) { die ( "Connection failed: " . $conn->connect_error ); }

// to get the action
$action                 = empty ( $_GET['action'] ) ? '' : $_GET['action'];

// validation
if ( ! in_array ( $action, ['fetch-table-names','fetch-table-structure','fetch-data'] ) ) {
    
    // output
    echo json_encode ([
        'request'       => 'failed',
        'reason'        => 'invalid_action',
        'message'       => 'Invalid action.'
    ]);
    exit;
}

// ----------
// now taking actions accordingly
// ----------

// step 01
// to fetch table names
if ( $action == 'fetch-table-names' ) {
    
    // to fetch tables
    $result             = mysqli_query ( $conn, "show tables" );
    $tables             = [];
    while ( $row = mysqli_fetch_array ( $result ) ) { $tables[] = $row[0]; }

    // output
    echo json_encode ([
        'request'       => 'success',
        'tables'        => $tables
    ]);
    exit;
}

// step 02
// to fetch table structure
// for that this script will return structure of specific table
else if ( $action == 'fetch-table-structure' ) {
    
    // table name
    $table              = empty ( $_GET['table'] ) ? '' : $_GET['table'];
    
    // table name is mandatory
    if ( empty ( $table ) ) {
        
        // output
        echo json_encode ([
            'request'   => 'failed',
            'reason'    => 'table_name_missing',
            'message'   => 'Table name is mandatory.'
        ]);
        exit;
    }
    
    // to fetch tables
    $result             = mysqli_query ( $conn, "show tables" );
    $tables             = [];
    while ( $row = mysqli_fetch_array ( $result ) ) { $tables[] = $row[0]; }
    
    // to check whether table exists or not
    if ( ! in_array ( $table,$tables ) ) {
        
        // output
        echo json_encode ([
            'request'   => 'failed',
            'reason'    => 'table_missing',
            'message'   => 'Table does not exist.'
        ]);
        exit;
    }
    
    // to fetch the table structure
    $result             = mysqli_query ( $conn, "DESCRIBE {$table}" );
    $structure          = [];
    while ( $row = mysqli_fetch_array ( $result, MYSQLI_ASSOC ) ) { 
        $structure[]    = $row; 
    }
    
    // output
    echo json_encode ([
        'request'       => 'success',
        'structure'     => $structure
    ]);
    exit;
}

// step 03
// to fetch data from specific table
// we will paginate automatically
else if ( $action == 'fetch-data' ) {
    
    // table name
    $table              = empty ( $_GET['table'] ) ? '' : $_GET['table'];
    $page               = empty ( $_GET['page'] ) ? 1 : $_GET['page'];
    $page               = (int)$page;
    $page               = empty ( $page ) ? 1 : $page;
    $perpage            = 20;
    $start              = 0 + ( $page - 1 ) * $perpage;
    
    // table name is mandatory
    if ( empty ( $table ) ) {
        
        // output
        echo json_encode ([
            'request'   => 'failed',
            'reason'    => 'table_name_missing',
            'message'   => 'Table name is mandatory.'
        ]);
        exit;
    }
    
    // to fetch tables
    $result             = mysqli_query ( $conn, "show tables" );
    $tables             = [];
    while ( $row = mysqli_fetch_array ( $result ) ) { $tables[] = $row[0]; }
    
    // to check whether table exists or not
    if ( ! in_array ( $table,$tables ) ) {
        
        // output
        echo json_encode ([
            'request'   => 'failed',
            'reason'    => 'table_missing',
            'message'   => 'Table does not exist.'
        ]);
        exit;
    }
    
    // to fetch data
    $result             = mysqli_query ( $conn, "SELECT * FROM {$table} LIMIT {$start},{$perpage}" );
    $data               = [];
    while ( $row = mysqli_fetch_array ( $result, MYSQLI_ASSOC ) ) { $data[] = $row; }
    
    // output
    echo json_encode ([
        'request'       => 'success',
        'data'          => $data
    ]);
    exit;
}