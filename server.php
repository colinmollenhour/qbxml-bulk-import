<?php

$configPath = __DIR__.'/config.json';

if (isset($_GET['qwc'])) {
  ?><<??>?xml version="1.0"?>
<QBWCXML>
	<AppName>QuickBooks XML Importer</AppName>
	<AppID></AppID>
	<AppURL>http://localhost.colin-virtual/server.php</AppURL>
	<AppDescription>Import huge QBXML files in chunks.</AppDescription>
	<AppSupport>http://localhost.colin-virtual/README.txt</AppSupport>
	<UserName>username</UserName>
	<OwnerID>{90A44FB7-33D9-4815-AC85-AC86A7E7D1EB}</OwnerID>
	<FileID>{57F3B9B6-86F1-4FCC-B1FF-967DE1813D20}</FileID>
	<QBType>QBFS</QBType>
	<Scheduler>
		<RunEveryNMinutes>2</RunEveryNMinutes>
	</Scheduler>
	<IsReadOnly>false</IsReadOnly>
</QBWCXML><?
  exit;
}

function debug($msg) { error_log(date('r').' '.$msg."\n", 3, __DIR__.'/debug.log'); }
function error($msg) { error_log(date('r').' '.$msg."\n", 3, __DIR__.'/error.log'); }

class QBServ {

  protected $fatalError;
  protected $config;

  public function __construct($configPath)
  {
    if ( ! file_exists($configPath)) {
      $this->fatalError = 'Could not load config.json';
      return;
    }
    $configStr = file_get_contents($configPath);
    $config = @json_decode($configStr, true);
    if ( ! $config) {
      $this->fatalError = 'Could not parse config.json (path,start,end)';
      return;
    }
    $this->config = $config;
    $path = $this->_getFile($this->config->start);
    if ( ! is_readable($path)) {
      $this->fatalError = "Cannot read $path.";
      return;
    }
    $path = $this->_getFile('-STATUS');
    if ( ! touch($path)) {
      $this->fatalError = "Cannot write to status file: $path";
      return;
    }
  }

  public function authenticate($username, $password)
  {
    return array(md5(time().$username.$password), '');
  }

  public function clientVersion($version)
  {
    debug('clientVersion: '.$version);
    return '';
  }

  public function closeConnection($ticket)
  {
    $this->_checkTicket($ticket);
    return 'QBServ says goodbye!';
  }

  public function connectionError($ticket, $hresult, $message)
  {
    $this->_checkTicket($ticket);
    error("connectionError: $message ($hresult)");
    return 'done';
  }

  public function getInteractiveURL($ticket)
  {
    $this->_checkTicket($ticket);
    error("getInteractiveURL not supported");
    return 'http://twitter.com/';
  }

  public function getLastError($ticket)
  {
    $this->_checkTicket($ticket);
    $errfile = __DIR__.'/last_error-'.$ticket.'.txt';
    if (file_exists($errfile)) {
      $err = file_get_contents($errfile);
      unlink($errfile);
      return $err;
    } else {
      return 'NoOp';
    }
  }

  public function getServerVersion($ticket)
  {
    $this->_checkTicket($ticket);
    return '0.1.WTF';
  }

  public function interactiveDone($ticket) { $this->_checkTicket($ticket); return 'Done'; }
  public function interactiveRejected($ticket) { $this->_checkTicket($ticket); return 'WAT?'; }

  public function receiveResponseXML($ticket, $response, $hresult, $message)
  {
    $this->_checkTicket($ticket);
    if ($hresult || $message) {
      error("receiveResponseXML: $message ($hresult)");
      $this->_setLastError($ticket, "Aborting due to received response indicating Quickbooks error: $message");
      return -1;
    }

    // TODO handle $response
    return 100;
  }

  public function sendRequestXML(
    $ticket,
    $strHCPResponse,
    $strCompanyFileName,
    $qbXMLCountry,
    $qbXMLMajorVers,
    $qbXMLMinorVers
  )
  {
    return '';
    // TODO - NoOp for error
    // TODO - QBXML string
  }

  protected function _checkTicket($ticket)
  {
    if ( ! preg_match('/^\w+$/', $ticket)) die('Bad ticket.');
  }

  protected function _setLastError($ticket, $message)
  {
    $errfile = __DIR__.'/last_error-'.$ticket.'.txt';
    file_put_contents($errfile, $message) or error("Could not write to $errfile");
  }

  protected function _getFile($num)
  {
    if ( ! $this->config->path) die("No path");
    return str_replace('*', $num, $this->config->path);
  }

}

try {
  $qbserv = new QBServ($config);
  $server = new SoapServer(__DIR__.'/QBWebConnectorSvc.wsdl');
  $server->setObject($qbserv);
  $server->handle();
} catch (Exception $e) {
  echo $e->getMessage();
  error(date('r')." {$e->getMessage()}\n$e");
}
