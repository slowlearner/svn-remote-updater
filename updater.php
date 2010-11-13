<?php 
function run_cmd($ssh_host, $port, $user_name, $pubkey, $privkey, $ssh_command) {
    $methods = array();
    $callbacks = array();
    $connection = ssh2_connect($ssh_host, $port, $methods, $callbacks);
    if (!$connection) {
        throw new Exception("Connection Failed");
    }
    
    if(!is_readable($pubkey)) {
        throw new Exception("Could not find Public key:".$pubkey);
    }
    
    if(!is_readable($privkey)) {
        throw new Exception("Could not find Private key:".$privkey);
    }

    if (!ssh2_auth_pubkey_file($connection, $user_name, $pubkey, $privkey, '')) {
        throw new Exception("Could not authenticate");
    }
    
    $stream = ssh2_exec($connection, $ssh_command);
    stream_set_blocking($stream, true);
    $out = stream_get_contents($stream);
    fclose($stream);
    return $out;
} 



class Svn_Remote_Updater {
    private $clients = null;
    public function __construct() {
    }
    public function load_config($config_file = null) {
        if(!is_readable($config_file)) {
            throw new Exception("Config file is not readable");
        }
        $this->clients = $this->clients = parse_ini_file($config_file, true);
    }
    public function update_all($rev='HEAD') {
        foreach($this->clients as $k => $val) {
            $this->update($k, $rev);
        }
    }
    public function update($id, $rev) {
        try {
            if(!isset($this->clients[$id])) {
                throw new Exception("No config found for the repo");
            }
            $conf = $this->clients[$id];
            $cmd = sprintf('svn up "%s" --username=%s --password=%s --no-auth-cache -r%s', 
                $conf['ssh_remote_dir'], 
                $conf['svn_username'], 
                $conf['svn_password'],
                $rev
                );
            
            
            $pubkey = dirname(__FILE__).'/client_keys/'.$conf['ssh_pubkey'];
            $privkey = dirname(__FILE__).'/client_keys/'.$conf['ssh_privkey'];
            
            echo "Updating client [$id] ...\n";
            echo "$cmd ...\n";
            $out = run_cmd($conf['ssh_host'], $conf['ssh_port'], $conf['ssh_username'], 
            $pubkey, $privkey, $cmd);
            
            echo $out ."\n";
            flush();
            if(empty($out)) {
                throw new Exception("SVN update failed");
            }
        } catch (Exception $e) {
            echo "$id failed updating: ".$e->getMessage()."\n";
        }
        
    }
}

$updater = new Svn_Remote_Updater();
$updater->load_config(dirname(__FILE__).'/config.ini');

if(!$argc > 1) {
    exit();
}


unset($argv[0]);

$params = array();
foreach($argv as $v) {
    list($k, $v) = explode('=', $v, 2);
    $params[$k] = $v;
}


// Defaults to HEAD revision and Updates all installs
$rev = 'HEAD';
$id = 'ALL';

if(isset($params['r']) && !empty($params['r'])) {
    $rev = $params['r'];
}

if(isset($params['id']) && !empty($params['id'])) {
    $id = $params['id'];
}

if($id == 'ALL') {
    $updater->update_all($rev);
    exit();
}
$updater->update($id, $rev);

