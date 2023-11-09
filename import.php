<?php
/**
 * @package		LanceCMS - Large Mysql Database Export and Import - Import File
 * @author		Somnath Ghosh (SomLance)
 * @link        https://somlance.com
 * @version		Version 1.0.0
 */

// import database configurations
$servername             = "localhost";
$username               = "username";
$password               = "password";
$database               = "database";
$table_prefix           = "im_";
$debug                  = true;
$export_url             = "https://www.your-old-website-url.com/export.php";
$import_url             = "https://www.your-new-website-url.com/import.php";

// debug
if ( $debug ) {
    error_reporting ( E_ALL );
    ini_set ( 'display_errors', 'on' );
}

// create connection
$conn                   = new mysqli ( $servername, $username, $password, $database );

// check connection
if ( $conn->connect_error ) { die ( "Connection failed: " . $conn->connect_error ); }

// to get the action
$action                 = empty ( $_GET['action'] ) ? '' : $_GET['action'];
$action                 = empty ( $action ) ? 'configuration' : $action;

// validation
if ( ! in_array ( $action, ['configuration','fetch-table-names','fetch-table-structure','fetch-data'] ) ) {
    
    // json header
    header ( 'Content-Type: application/json; charset=utf-8' );

    // output
    echo json_encode ([
        'request'       => 'failed',
        'reason'        => 'invalid_action',
        'message'       => 'Invalid action.'
    ]);
    exit;
}

// html start
echo "<html><head><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"><title>SomLance DB Import Script</title></head><body style=\"background:#000;color:#fff;font-family:arial\">";

// step 01
// creating configuration
// we will create required tables to store data temporarily
// that will help to execute the full operation
if ( $action == 'configuration' ) {
    
    // creating configuration table structure
    $result             = mysqli_query ( $conn, '
        CREATE TABLE IF NOT EXISTS `'.$table_prefix.'import_config` (
          `id` int(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
          `meta_key` varchar(255) NOT NULL,
          `meta_data` longtext NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;' );
    
    // if success
    if ( $result ) {
        echo '<pre>-- CONFIG TABLE CREATED --</pre>';
        echo '<script> setTimeout ( async function () { window.location="'.$import_url.'?action=fetch-table-names"; }, 1000 ); </script>';
        exit;
    }
    
    // failed
    else {
        echo '<pre>-- UNABLE TO CREATE CONFIG TABLE --</pre>';
        echo '<pre>-- operation stopped --</pre>';
        exit;
    }
}

// step 02
// to fetch table names
else if ( $action == 'fetch-table-names' ) {
    
    // to fetch tables
    $tables             = call_export_url ( $export_url, $action );
    
    // if success
    if ( $tables['request'] == 'success' ) {
        
        // converting into json
        $json_data      = mysqli_real_escape_string ( $conn, json_encode ( $tables['tables'] ) );
        
        // counting tables
        $count_tables   = count ( $tables['tables'] );
        $selected_table = $tables['tables'][0];
        
        // preparing a json of the tables records and keeping them in config table
        mysqli_query ( $conn, "DELETE FROM `{$table_prefix}import_config` WHERE `meta_key`='tables';" );
        mysqli_query ( $conn, "INSERT INTO `{$table_prefix}import_config` (meta_key,meta_data) VALUES ('tables','{$json_data}');" );
        
        mysqli_query ( $conn, "DELETE FROM `{$table_prefix}import_config` WHERE `meta_key`='tables_count';" );
        mysqli_query ( $conn, "INSERT INTO `{$table_prefix}import_config` (meta_key,meta_data) VALUES ('tables_count','{$count_tables}');" );
        
        mysqli_query ( $conn, "DELETE FROM `{$table_prefix}import_config` WHERE `meta_key`='selected_table';" );
        mysqli_query ( $conn, "INSERT INTO `{$table_prefix}import_config` (meta_key,meta_data) VALUES ('selected_table','{$selected_table}');" );
        
        // message
        echo '<pre>-- TABLES FETCHED --</pre>';
        echo '<pre>';
        print_r ( $tables['tables'] );
        echo '<pre>';
        echo '<script> setTimeout ( async function() { window.location="'.$import_url.'?action=fetch-table-structure"; }, 1000 ); </script>';
        exit;
    }
    
    // failed
    else {
        echo '<pre>-- UNABLE TO FETCH TABLES --</pre>';
        echo '<pre>-- operation stopped --</pre>';
        exit;
    }
}

// step 03
// fetch table config
else if ( $action == 'fetch-table-structure' ) {
    
    // to fetch selected table
    $result                     = mysqli_query ( $conn, "SELECT * FROM `{$table_prefix}import_config` WHERE `meta_key`='selected_table' LIMIT 1;" );
    $data                       = [];
    while ( $row = mysqli_fetch_array ( $result, MYSQLI_ASSOC ) ) { $data[] = $row; }
    $selected_table             = empty ( $data[0]['meta_data'] ) ? '' : $data[0]['meta_data'];
    
    // when we have a selected table
    if ( ! empty ( $selected_table ) ) {
        
        // structure
        $fetch                  = call_export_url ( $export_url, $action, "&table={$selected_table}" );
        
        // when it is a success
        if ( $fetch['request'] == 'success' ) {
        
            // processing structure
            $structure          = $fetch['structure'];
            $count_structure    = count ( $structure );
        
            // running into loop to prepare the table create query
            $table_query        = "";
            $got_primary_key    = false;
            for ( $i=0;$i<$count_structure;$i++ ) {
                
                // keeping a check when we get some exception 
                // to improve the code
                $array_keys     = array_keys ( $structure[$i] );
                $compare_keys   = ['Field','Type','Extra','Key','Null','Default'];
                $compare        = array_diff ( $array_keys,$compare_keys );
                
                // when we have something in compare
                if ( !1 && ! empty ( $compare ) ) {
                    echo '<pre>-- WE GOT SOMETHING DIFFERENT</pre>';
                    echo '<pre>';
                    print_r($compare);
                    echo '</pre>';
                    exit;
                }
                
                // when there is a default value
                if ( !1 && ! empty ( $structure[$i]['Default'] ) ) {
                    echo '<pre>-- WE GOT SOMETHING DIFFERENT</pre>';
                    echo '<pre>';
                    print_r([
                        'Default' => $structure[$i]['Default']    
                    ]);
                    echo '</pre>';
                    exit;
                }
                
                // appending
                $table_query    = $table_query . " `{$structure[$i]['Field']}` " . 
                    ( ! empty ( $structure[$i]['Type'] ) ? " {$structure[$i]['Type']} " : '' ) .
                    ( ! empty ( $structure[$i]['Default'] ) ? " DEFAULT ".(($structure[$i]['Default']=='current_timestamp()')? $structure[$i]['Default'] : "'{$structure[$i]['Default']}'" )." " : '' ) .
                    ( ! empty ( $structure[$i]['Extra'] ) ? " {$structure[$i]['Extra']} " : '' ) .
                    ( ( ! empty ( $structure[$i]['Key'] ) &&  $structure[$i]['Key'] == 'PRI' && ! $got_primary_key ) ? " PRIMARY KEY " : '' ) . 
                    ( ( ! empty ( $structure[$i]['Null'] ) &&  $structure[$i]['Null'] == 'NO' ) ? " NOT NULL " : '' ) .
                    ( ( ! empty ( $structure[$i]['Null'] ) &&  $structure[$i]['Null'] == 'YES' ) ? " NULL " : '' ).
                    "," . PHP_EOL;
                    
                // resetting got_primary_key
                if ( $structure[$i]['Key'] == 'PRI' ) { $got_primary_key = true; }
            }
            
            // final processing
            $table_query        = trim ( trim ( $table_query ), ',' );
            
            // debug
            if ( !1 ) {
                echo '<pre>';
                print_r ( $structure );
                echo 'CREATE TABLE IF NOT EXISTS `'.$selected_table.'` ( '.$table_query.' ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;'; 
                echo '<pre>';
                exit;
            }
            
            // creating table structure
            $result             = mysqli_query ( $conn, 'CREATE TABLE IF NOT EXISTS `'.$selected_table.'` ( '.$table_query.' ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;' );
            
            // if success
            if ( $result ) {
                
                // now we will update the selected_table value in config table
                // for this we need to fetch all the table names
                $result         = mysqli_query ( $conn, "SELECT * FROM `{$table_prefix}import_config` WHERE `meta_key`='tables' LIMIT 1;" );
                $data           = [];
                while ( $row = mysqli_fetch_array ( $result, MYSQLI_ASSOC ) ) { $data[] = $row; }
                
                // processing all tables
                // and fetching the next table
                $all_tables     = json_decode ( $data[0]['meta_data'], TRUE );
                $key            = array_search ( $selected_table, $all_tables );
                $key            = empty ( $key ) ? 0 : (int)$key;
                $next_key       = $key + 1;
                $next_table     = empty ( $all_tables[$next_key] ) ? '' : $all_tables[$next_key];
                
                // complete
                if ( empty ( $next_table ) ) {
                    
                    // selecting first table
                    $next_table = empty ( $all_tables[0] ) ? '' : $all_tables[0];
                    
                    // updating the next table as selected
                    mysqli_query ( $conn, "DELETE FROM `{$table_prefix}import_config` WHERE `meta_key`='selected_table';" );
                    mysqli_query ( $conn, "INSERT INTO `{$table_prefix}import_config` (meta_key,meta_data) VALUES ('selected_table','{$next_table}');" );
                    
                    echo '<pre>-- TABLE CREATION COMPLETED --</pre>';
                    echo '<script> setTimeout ( async function () { window.location="'.$import_url.'?action=fetch-data"; }, 1000 ); </script>';
                    exit;
                }
                
                // otherwise
                else {
                    
                    // updating the next table as selected
                    mysqli_query ( $conn, "DELETE FROM `{$table_prefix}import_config` WHERE `meta_key`='selected_table';" );
                    mysqli_query ( $conn, "INSERT INTO `{$table_prefix}import_config` (meta_key,meta_data) VALUES ('selected_table','{$next_table}');" );
                    
                    echo '<pre>-- '.$selected_table.' -- TABLE CREATED --</pre>';
                    echo '<script> setTimeout ( async function () { window.location="'.$import_url.'?action=fetch-table-structure"; }, 1000 ); </script>';
                    exit;
                }
            }
            
            // failed
            else {
                echo '<pre>-- '.$data[0]['meta_data'].' -- UNABLE TO CREATE TABLE --</pre>';
                echo '<pre>-- operation stopped --</pre>';
                exit;
            }
            exit;
            
        }
    }
    
    // failed
    else {
        echo '<pre>-- UNABLE TO SELECT TABLE --</pre>';
        echo '<pre>-- operation stopped --</pre>';
        exit;
    }
    
}

// step 04
// to fetch data one by one
else if ( $action == 'fetch-data' ) {
    
    // to fetch selected table
    $result                     = mysqli_query ( $conn, "SELECT * FROM `{$table_prefix}import_config` WHERE `meta_key`='selected_table' LIMIT 1;" );
    $data                       = [];
    while ( $row = mysqli_fetch_array ( $result, MYSQLI_ASSOC ) ) { $data[] = $row; }
    $selected_table             = empty ( $data[0]['meta_data'] ) ? '' : $data[0]['meta_data'];
    
    // to fetch page
    $result                     = mysqli_query ( $conn, "SELECT * FROM `{$table_prefix}import_config` WHERE `meta_key`='page' LIMIT 1;" );
    $data                       = [];
    while ( $row = mysqli_fetch_array ( $result, MYSQLI_ASSOC ) ) { $data[] = $row; }
    $page                       = empty ( $data[0]['meta_data'] ) ? 1 : (int)$data[0]['meta_data'];
    $next_page                  = $page + 1;
    
    // when we have a selected table
    if ( ! empty ( $selected_table ) ) {
        
        // structure
        $fetch                  = call_export_url ( $export_url, $action, "&table={$selected_table}&page={$page}" );
        
        // if success
        if ( $fetch['request'] == 'success' ) {
            
            // data
            $data               = empty ( $fetch['data'] ) ? [] : $fetch['data'];
            
            // when we have data
            if ( ! empty ( $data ) ) {
                
                // counting data
                $count_data     = count ( $data );
                
                // running into loop to process and insert data
                for ( $i=0;$i<$count_data;$i++ ) {
                    
                    // taking a single row
                    $keys       = array_keys ( $data[$i] );
                    $count_keys = count ( $keys );
                    $keys_str   = "";
                    $values_str = "";
                    
                    // running into loop to prepare keys string
                    for ( $j=0;$j<$count_keys;$j++ ) { 
                        
                        // appending in keys string
                        $keys_str   = $keys_str . $keys[$j] . ","; 
                        
                        // appending in values string
                        $this_value = empty ( $data[$i][$keys[$j]] ) ? '' : $data[$i][$keys[$j]];
                        $values_str = $values_str . "'".mysqli_real_escape_string($conn,$this_value)."',";
                    }
                    
                    // finalising
                    $keys_str   = trim ( trim ( $keys_str ), ',' );
                    $values_str = trim ( trim ( $values_str ), ',' );
                    
                    // insert
                    mysqli_query ( $conn, "INSERT INTO `$selected_table` ({$keys_str}) VALUES ({$values_str});" );
                }
                
                // after we are done 
                // we will update the page value and restart again
                mysqli_query ( $conn, "DELETE FROM `{$table_prefix}import_config` WHERE `meta_key`='page';" );
                mysqli_query ( $conn, "INSERT INTO `{$table_prefix}import_config` (meta_key,meta_data) VALUES ('page','{$next_page}');" );
                
                echo '<pre>-- '.$selected_table.' -- DATA IMPORTED -- page: '.$page.' --</pre>';
                echo '<pre>'; 
                print_r($data);
                echo '</pre>';
                echo '<script> setTimeout ( async function () { window.location="'.$import_url.'?action=fetch-data"; }, 1000 ); </script>';
                exit;
            }
            
            // else move to next table
            else {
                
                // now we will update the selected_table value in config table
                // for this we need to fetch all the table names
                $result         = mysqli_query ( $conn, "SELECT * FROM `{$table_prefix}import_config` WHERE `meta_key`='tables' LIMIT 1;" );
                $data           = [];
                while ( $row = mysqli_fetch_array ( $result, MYSQLI_ASSOC ) ) { $data[] = $row; }
                
                // processing all tables
                // and fetching the next table
                $all_tables     = json_decode ( $data[0]['meta_data'], TRUE );
                $key            = array_search ( $selected_table, $all_tables );
                $key            = empty ( $key ) ? 0 : (int)$key;
                $next_key       = $key + 1;
                $next_table     = empty ( $all_tables[$next_key] ) ? '' : $all_tables[$next_key];
                
                // complete
                if ( empty ( $next_table ) ) {
                    
                    echo '<pre>-- OPERATION COMPLETE --</pre>';
                    exit;
                }
                
                // otherwise
                else {
                    
                    // updating the next table as selected
                    mysqli_query ( $conn, "DELETE FROM `{$table_prefix}import_config` WHERE `meta_key`='selected_table';" );
                    mysqli_query ( $conn, "INSERT INTO `{$table_prefix}import_config` (meta_key,meta_data) VALUES ('selected_table','{$next_table}');" );
                    
                    mysqli_query ( $conn, "DELETE FROM `{$table_prefix}import_config` WHERE `meta_key`='page';" );
                    mysqli_query ( $conn, "INSERT INTO `{$table_prefix}import_config` (meta_key,meta_data) VALUES ('page','1');" );
                    
                    echo '<pre>-- '.$selected_table.' -- DATA IMPORTED --</pre>';
                    echo '<script> setTimeout ( async function () { window.location="'.$import_url.'?action=fetch-data"; }, 1000 ); </script>';
                    exit;
                }
            }
        }
    }
    
    // otherwise 
    else {
        echo '<pre>-- COMPLETED --</pre>';
        exit;
    }
}

// end html
echo "</body></html>";

// defining function to call export url
function call_export_url ( $export_url, $action, $param='' ) {
    
    // initialising curl
    $curl                           = curl_init();
    
    // preparing curl options
    curl_setopt_array ( $curl, [
        CURLOPT_URL                 => $export_url . "?action={$action}{$param}",
        CURLOPT_RETURNTRANSFER      => true,
        CURLOPT_ENCODING            => '',
        CURLOPT_MAXREDIRS           => 10,
        CURLOPT_TIMEOUT             => 0,
        CURLOPT_FOLLOWLOCATION      => true,
        CURLOPT_HTTP_VERSION        => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST       => 'GET',
        CURLOPT_POSTFIELDS          => [],
        CURLOPT_HTTPHEADER          => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
    ]);
    
    // collecting response
    $response                       = curl_exec($curl);
    
    // closing curl
    curl_close($curl);
    
    // output
    return json_decode ( $response, TRUE );
}