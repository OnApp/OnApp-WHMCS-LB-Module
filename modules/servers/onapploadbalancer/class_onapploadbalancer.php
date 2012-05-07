<?php

if ( ! function_exists('emailtpl_template') ){
    require_once realpath( dirname( __FILE__ ).'/../../../includes/functions.php');
}

class OnAppLoadBalancer {

    private $salt   = "ec457d0a974c48d5685a7efa03d137dc8bbde7e3";
    private $hostname;
    private $serviceid;
    private $created;
    private $server;
    private $user;
    private $onapp;

    public  $error;

    function __construct( $serviceid = null ) {
        $this->hostname = $_SERVER['HTTP_HOST'];
        
        if ( is_null( $serviceid ) )
            $serviceid = self::get_value('id');
        if ( ! is_numeric ( $serviceid ) )
            die('Invalid token');
            
        $this->serviceid = $serviceid;
    }

/**
 * Get GET or POST value
 */
    public static function get_value($value) {
        return $_GET[$value] ? $_GET[$value] : ( $_POST[$value] ? $_POST[$value] : null );
    }

/**
 * Init OnApp PHP wrapper
 */
    public static function init_wrapper() {
        if ( ! defined('ONAPP_FILE_NAME') )
            define("ONAPP_FILE_NAME", "onappcdn.php");

        if ( ! defined('ONAPP_WRAPPER_INIT') )
            define('ONAPP_WRAPPER_INIT', dirname(__FILE__).'/../../../includes/wrapper/OnAppInit.php');

        if ( file_exists( ONAPP_WRAPPER_INIT ) ) {
            require_once ONAPP_WRAPPER_INIT;
        }
        else {
            return false;
        }

        return true;
    }

/**
 * Load $_LANG from language file
 */
    public static function loadloadbalancer_language() {
        global $_LANG;
              
        $dir = dirname(__FILE__).'/lang/';

        if(! file_exists($dir))
           return;

        $dh = opendir ($dir);

        while (false !== $file2 = readdir ($dh)) {
            if (!is_dir ('' . 'lang/' . $file2) ) {
                $pieces = explode ('.', $file2);
                if ($pieces[1] == 'txt') {
                    $arrayoflanguagefiles[] = $pieces[0];
                    continue;
                }
                continue;
            }
        };

        closedir ($dh);

        if ( !isset($_SESSION['Language']) ) {
            $_SESSION['Language'] = 'English';
        }
        
        
        $language = $_SESSION['Language'];

        if( ! in_array ($language, $arrayoflanguagefiles) )
            $language =  "English";

        if( file_exists( dirname(__FILE__) . "/lang/$language.txt" ) ) {
            ob_start ();
            include dirname(__FILE__) . "/lang/$language.txt";
            $templang = ob_get_contents ();
            ob_end_clean ();
            eval ($templang);
        }
    }

/**
 * Create base OnApp Load Balancer module tables structure
 */
    public static function createTables() {
        global $_LANG, $whmcsmysql;

        define ("CREATE_TABLE_ONAPPLBSERVICES", "CREATE TABLE IF NOT EXISTS `tblonapplbservices` (
            `service_id` int(11) NOT NULL,
            `cluster_id` int(11) NOT NULL,
            `balancer_id` int(11) NOT NULL,
            `ports` varchar(255) NOT NULL,
            `port_speed` int(11) NOT NULL,
            `cluster_type` varchar(150) NOT NULL,
            `node_ids` varchar(255) DEFAULT NULL,
            `template_id` int(11) DEFAULT NULL,
            `min_node_amount` int(11) DEFAULT NULL,
            `max_node_amount` int(11) DEFAULT NULL,
            `memory` int(11) DEFAULT NULL,
            `cpus` int(11) DEFAULT NULL,
            `cpu_shares` int(11) DEFAULT NULL,
            `rate_limit` int(11) DEFAULT NULL,
            `auto_scaling_out_cpu_attributes_value` int(11) DEFAULT NULL,
            `auto_scaling_out_cpu_attributes_units` int(11) DEFAULT NULL,
            `auto_scaling_out_cpu_attributes_for_minutes` int(11) DEFAULT NULL,
            `auto_scaling_out_memory_attributes_value` int(11) DEFAULT NULL,
            `auto_scaling_out_memory_attributes_units` int(11) DEFAULT NULL,
            `auto_scaling_out_memory_attributes_for_minutes` int(11) DEFAULT NULL,
            `auto_scaling_in_cpu_attributes_value` int(11) DEFAULT NULL,
            `auto_scaling_in_cpu_attributes_units` int(11) DEFAULT NULL,
            `auto_scaling_in_cpu_attributes_for_minutes` int(11) DEFAULT NULL,
            `auto_scaling_in_memory_attributes_value` int(11) DEFAULT NULL,
            `auto_scaling_in_memory_attributes_units` int(11) DEFAULT NULL,
            `auto_scaling_in_memory_attributes_for_minutes` int(11) DEFAULT NULL
            ) DEFAULT CHARSET=utf8;");
        
        define("CREATE_TABLE_ONAPPLBCLIENTS", "CREATE TABLE IF NOT EXISTS `tblonapplbclients` (
            `service_id` int(11) NOT NULL,
            `client_id` int(11) NOT NULL,
            `onapp_user_id` int(11) NOT NULL,
            `password` varchar(255) NOT NULL,
            `email` varchar(255) NOT NULL
            ) DEFAULT CHARSET=utf8;");        

        if ( ! full_query( CREATE_TABLE_ONAPPLBCLIENTS, $whmcsmysql ) )
            return array(
                "error" => sprintf($_LANG["onappcdnerrtablecreate"], 'onappcdnclients')
            );
        
       
        if ( ! full_query( CREATE_TABLE_ONAPPLBSERVICES, $whmcsmysql ) )
            return array(
                "error" => sprintf($_LANG["onappcdnerrtablecreate"], 'onappcdnclients')
            );        

        return;
    }

/**
 * Get list of onapp cdn servers
 */
    public static function getservers() {
        $product_id = self::get_value("id");
          
        $sql = "SELECT
    tblservers.*, 
    tblservergroupsrel.groupid,
    tblproducts.servergroup
FROM 
    tblservers 
    LEFT JOIN tblservergroupsrel ON 
        tblservers.id = tblservergroupsrel.serverid
    LEFT JOIN tblproducts ON
        tblproducts.id = $product_id 
WHERE
    tblservers.disabled = 0
    AND tblservers.type = 'onapploadbalancer'";

        $sql_servers_result = full_query($sql);      

        $servers = array();
        while ( $server = mysql_fetch_assoc($sql_servers_result)) {
            if ( is_null($server['groupid']) )   
                $server['groupid'] = 0;
            $server['password'] = decrypt($server['password']);

            $servers[$server['id']] = $server;
        }

        return $servers;
    }

    public function show () {
        die('You need to define function show in class' . __CLASS__);
    }
//
//    /**
// * Renders clientarea view
//     *
//     * @global <type> $_LANG
//     * @global <type> $breadcrumbnav
//     * @global <type> $smartyvalues
//     * @global <type> $CONFIG
//     * @param <type> $templatefile
//     * @param <type> $values
//     */
//    protected function show_template($templatefile, $values) {
//        global $_LANG, $smartyvalues, $CONFIG;
//        self::loadcdn_language();
//
//        $id          = self::get_value('id');
//        $page        = self::get_value('page');
//
//        $breadcrumbnav  = ' <a href="index.php">'.$_LANG["globalsystemname"].'</a>';
//        $breadcrumbnav .= ' &gt; <a href="clientarea.php">'.$_LANG["clientareatitle"].'</a>';
//        $breadcrumbnav .= ' &gt; <a href="' . ONAPP_FILE_NAME . '?page=resources&id=' . $id . '">'.$_LANG["onappcdnresources"].'</a>';
//        if ( $page != 'resources')
//            $breadcrumbnav .=
//                ' &gt; <a href="' . ONAPP_FILE_NAME . '?page='. $page. '&id=' .
//                $id . '&resource_id='. self::get_value('resource_id') .'">'
//                .$_LANG['onappcdn' . str_replace('_', '', $page )].'</a>';
//
//
//        $pagetitle = $_LANG["clientareatitle"];
//        $pageicon = "images/support/clientarea.gif";
//        $values['_LANG'] = $_LANG;
//        initialiseClientArea( $pagetitle, $pageicon, $breadcrumbnav);
//
//        $smartyvalues = $values;
//
//        if ($CONFIG['SystemSSLURL'])
//            $smartyvalues['systemurl'] = $CONFIG['SystemSSLURL'] . '/';
//        else if ($CONFIG['SystemURL'] != 'http://www.yourdomain.com/whmcs')
//            /* Do not change this URL!!! - Otherwise WHMCS Failed ! */
//            $smartyvalues['systemurl'] = $CONFIG['SystemURL'] . '/';
//        
//        outputClientArea($templatefile);
//    }
//
//    /**
//     * Gets Services Ids of currently loged in user
//     *
//     * @return array User's servises Ids
//     */
//    public function getUserServisesIds () {
//        $userid = $_SESSION['uid'];
//        
//        if ( ! isset( $userid ) || ! is_numeric( $userid ) )
//            return array();
//        
//        $sql = "
//            SELECT
//                h.id
//            FROM
//                tblhosting as h
//            LEFT JOIN
//                tblproducts as p ON h.packageid = p.id
//            WHERE
//                h.userid = $userid AND
//                p.servertype = 'onappcdn'
//        ";
//
//        $result = full_query( $sql );
//
//        if ( ! $result && mysql_num_rows($result) < 1 )
//            return array();
//
//        $servicesIds = array();
//        while ( $row = mysql_fetch_assoc($result) )
//            $servicesIds[] = $row['id'];
//
//        return $servicesIds;
//    }
//    
//    /**
//     * Launchs particular action
//     *
//     * @param string $action
//     */
//    public function runAction( $action ) {
//        $this->$action();
//    }
//
//    /**
//     * Gets OnApp class instance
//     *
//     * @return object OnApp Instance
//     */
//    protected function getOnAppInstance() {
//        $user   = $this->get_user();
//        $server = $this->getServer();
//        
//        $this->onapp = new OnApp_Factory(
//           $server['address'],
//           $user['email'],
//           $user['password']
//        );
//        
//        if ( $this->onapp->getErrorsAsArray() ) {
//            die('<b>Get OnApp Version Permission Error: </b>' . implode( PHP_EOL , $this->onapp->getErrorsAsArray() )); 
//        }
//        
//        return $this->onapp;
//    }
//
//    /**
//     *
//     */
//    public function getServiceId () {
//        return $this->serviceid;
//    }
//
//    /**
//     * Redirect to another page
//     *
//     * @param string $url redirection url
//     */
//    public function redirect($url) {
//        if (!headers_sent()) {
//                    header('Location: '.$url);
//                    exit;
//            }
//        else {
//            echo '<script type="text/javascript">';
//            echo 'window.location.href="'.$url.'";';
//            echo '</script>';
//            echo '<noscript>';
//            echo '<meta http-equiv="refresh" content="0;url='.$url.'" />';
//            echo '</noscript>'; exit;
//        };
//    }
//    
//    /**
//     *  Verify if user have access to particular cdn_resource
//     *  
//     *  @param integer $resource_id CDN resource id
//     */
//    public function ifHaveAccessToResource ( $resource_id ) {
//        $onapp = $this->getOnAppInstance();    
//        
//        $resources= $onapp->factory('CDNResource');
//        $list = $resources->getList();
//        
//        if ( $resources->getErrorsAsArray() ) {
//            die('<b>Getting Resource Error: </b>' . implode( PHP_EOL , $resources->getErrorsAsArray() ) );
//        }         
//        
//        $resource_ids = array();
//        foreach ( $list as $resource) {
//            $resource_ids[] = $resource->_id;
//        }
//
//        if ( ! in_array( $resource_id ,$resource_ids ) ) {
//            die( 'Invalid Token ( code : 5 )' );
//        }
//        
//        return true;
//    }
//    
//    public function get_user() {
//        if (! is_null( $this->user ) )
//            return $this->user;
//
//        $sql      = "select * from tblonappcdnclients where service_id = " . $this->serviceid;
//        $resource = full_query( $sql );
//        $user     = mysql_fetch_assoc( $resource );
//
//        $this->user = $user;
//        return $user;
//    }
//
//    protected function getWhmcsClientDetails () {
//        $sql = "
//            SELECT
//                h.userid as clientid,
//                c.currency,
//                currencies.prefix as currencyprefix,
//                currencies.code as currencycode,
//                currencies.rate as currencyrate,
//                currencies.default as ifdefaultcurrency
//            FROM
//                tblhosting as h
//            LEFT JOIN
//                tblclients as c
//                ON h.userid = c.id
//            LEFT JOIN
//                tblcurrencies as currencies
//                ON currencies.id = c.currency
//            WHERE
//                h.id = $this->serviceid
//        ";
//
//        $result = full_query( $sql );
//        $client_details = mysql_fetch_assoc( $result );
//
//        return $client_details;
//    }
//
//    protected function getServer() {
//        if (! is_null($this->server) )
//            return $this->server;
//        $sql = "SELECT
//                tblservers.*
//            FROM
//                tblhosting
//                LEFT JOIN tblproducts ON tblproducts.id = packageid
//                LEFT JOIN tblservers ON tblproducts.configoption1 = tblservers.id
//            WHERE
//                tblhosting.id = " . $this->serviceid;
//        $resource = full_query( $sql );
//        $server   = mysql_fetch_assoc( $resource );
//
//
//        if ( !is_null($server) ) {
//            $server['address'] = $server['ipaddress'] != '' ? $server['ipaddress'] : $server['hostname'];
//
//            if ($server["secure"] == "on")
//                $server['address'] = 'https://'.$server['address'];
//
//            $server['password'] = decrypt($server['password']);
//
//            $this->server = $server;
//            return $this->server;
//        } else
//            die( 'OnApp CDN server for service ' . $this->serviceid . ' not found.' );
//    }
//
//    public function create_user() {
//
//        $server = $this->getServer();
//
//        $service_id = $this->serviceid;
//
//        $onapp = new OnApp_Factory(
//            $server['address'], 
//            $server['username'],
//            $server['password']
//        );
//
//        if( ! $onapp->_is_auth ) {
//            return "Can not login as '".$server['username']."' on '".$server['address'];
//        } else if ( $onapp->getErrorsAsArray() ) {
//            return '<b>Getting OnApp Version Error: </b>' . implode("\n", $onapp->getErrorsAsArray());
//        } else {
//            $sql = "SELECT
//                tblproducts.*
//            FROM
//                tblhosting
//                LEFT JOIN tblproducts ON tblproducts.id = packageid
//            WHERE
//                tblhosting.id = " . $this->serviceid;
//
//            $resource = full_query( $sql );
//            $hosting   = mysql_fetch_assoc( $resource );
//
//            if( ! $hosting )
//                return "Can't execute SQL query \"$sql\"";
//
//            if ( ! strpos('.', $this->hostname ) ){
//                $this->hostname .= '.com'; 
//            }
//            
//            $email    = 'cdnuser'.$this->serviceid.'@'.$this->hostname;
//            
//            $password = md5($this->serviceid . $this->salt . date('h-i-s, j-m-y') );
//
//            $user = $onapp->factory( 'User', true );
//
//            $user->_email           = $email;
//            $user->_password        = $password;
//            $user->_login           = $email;
//            $user->_first_name      = 'WHMCS CDN User';
//            $user->_last_name       = '#'.$this->serviceid;
//
//            $user->_role_ids        = $hosting["configoption2"];
//            $user->_user_group_id   = $hosting["configoption4"];
//            $user->_billing_plan_id = $hosting["configoption3"];
//            $user->_time_zone       = $hosting["configoption5"];
//
//            $user->save();
//
//            if( $user->error )
//                return implode( "\n", $user->error );
//            else {
//                $user_id = $user->_id;
//
//                $sql = "REPLACE tblonappcdnclients SET
//                    service_id    = '$service_id' ,
//                    server_id     = '".$server['id']."',
//                    email         = '$email',
//                    password      = '$password',
//                    onapp_user_id = '$user_id'";
//
//                if( ! full_query($sql) )
//                    return "Can't execute SQL query \"$sql\"";
//                else {
//                    $this->created = true;
//                    return 'success';
//                }
//            }
//        }
//
//        return 'Something went wrong';
//    }
//
//    public function delete_user() {
//        $server = $this->getServer();
//
//        $onapp = new OnApp_Factory(
//            $server['address'],
//            $server['username'],
//            $server['password']
//        );
//
//        if( ! $onapp->_is_auth ) {
//            return "Can not login as '".$server['username']."' on '".$server['address'];
//        } else if ( $onapp->getErrorsAsArray() ) {
//            return '<b>Getting OnApp Version Error: </b>' . implode("\n", $onapp->getErrorsAsArray());
//        } else {
//            $user = $this->get_user();
//
//            $onapp_user = $onapp->factory( 'User', true );
//
//            $onapp_user->load($user["onapp_user_id"]);
//
//            $onapp_user->delete();
//
//            $onapp_user->delete();
//
//            if( $onapp_user->error )
//                return implode( "\n", $onapp_user->error );
//            else {
//                $sql = "DELETE FROM tblonappcdnclients WHERE service_id = " . $this->serviceid;
//
//                if( ! full_query($sql) )
//                    return "Can't execute SQL query \"$sql\"";
//                else
//                    return 'success';
//            }
//        }
//
//        return 'Something went wrong';
//    }
//
//    public function suspend_user() {
//        $server = $this->getServer();
//
//        $onapp = new OnApp_Factory(
//            $server['address'],
//            $server['username'],
//            $server['password']
//        );
//
//        if( ! $onapp->_is_auth ) {
//            return "Can not login as '".$server['username']."' on '".$server['address'];
//        } else if ( $onapp->getErrorsAsArray() ) {
//            return '<b>Getting OnApp Version Error: </b>' . implode("\n", $onapp->getErrorsAsArray());
//        } else {
//            $user = $this->get_user();
//
//            $onapp_user = $onapp->factory( 'User', true );
//
//            $onapp_user->load($user["onapp_user_id"]);
//
//            if( $onapp_user->_obj->_status == 'suspended')
//                return "User is already suspended";
//
//            $onapp_user->suspend();
//
//            if( $onapp_user->error )
//                return implode( "\n", $onapp_user->error );
//            else
//                return 'success';
//        }
//        return 'Something went wrong';
//    }
//
//    public function unsuspend_user() {
//        $server = $this->getServer();
//
//        $onapp = new OnApp_Factory(
//            $server['address'],
//            $server['username'],
//            $server['password']
//        );
//
//        if( ! $onapp->_is_auth ) {
//            return "Can not login as '".$server['username']."' on '".$server['address'];
//        } else if ( $onapp->getErrorsAsArray() ) {
//            return '<b>Getting OnApp Version Error: </b>' . implode("\n", $onapp->getErrorsAsArray());
//        } else {
//            $user = $this->get_user();
//
//            $onapp_user = $onapp->factory( 'User', true );
//
//            $onapp_user->load($user["onapp_user_id"]);
//
//            if( $onapp_user->_obj->_status != 'suspended')
//                return "User is not suspended";
//
//            $onapp_user->activate_user();
//
//            if( $onapp_user->error )
//                return implode( "\n", $onapp_user->error );
//            else
//                return 'success';
//        }
//        return 'Something went wrong';
//    }
    
}
