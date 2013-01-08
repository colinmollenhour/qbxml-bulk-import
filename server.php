<?php
if (isset($_GET['pi'])) { echo phpinfo(); exit; }
define('BP', __DIR__);
define('VAR_DIR', __DIR__.'/var');

$proto = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on" ? 'https' : 'http');
$host = $_SERVER['HTTP_HOST'];
$uri = $_SERVER['PHP_SELF'];
define('BASE_URL', "$proto://$host$uri");

function debug($msg) { error_log(date('c').' '.$msg."\n", 3, VAR_DIR.'/debug.log'); }
function error($msg) { error_log(date('c').' '.$msg."\n", 3, VAR_DIR.'/error.log'); }

class QBServ {

  const VERSION = 'v0.1.WTF';

  protected $fatalError;
  protected $config;

  public function __construct()
  {
    try {
      $configPath = BP.'/config.txt';
      if ( ! is_readable($configPath)) {
        throw new Exception('Could not load config.txt');
      }
      $configStr = trim(file_get_contents($configPath));
      list($path, $start, $end) = explode('|', $configStr);
      if ( ! strlen($path) || ! strlen($start) || ! strlen($end)) {
        throw new Exception('Could not parse config.txt. Expected format: /path/to/files*.xml|1|523');
      }
      $this->config = (object) array('path' => $path, 'start' => $start, 'end' => $end);
      $path = $this->_getFile($this->config->start);
      if ( ! is_readable($path)) {
        throw new Exception("Cannot read $path.");
      }
      $path = $this->_getFile();
      if ( ! touch($path)) {
        throw new Exception("Cannot write to status file: $path");
      }
    } catch(Exception $e) {
      $this->fatalError = $e->getMessage();
    }
    if (isset($_GET['qwc'])) {
      if ($this->fatalError) {
        die("<h3>{$this->fatalError}</h3>");
      }
      header('Content-Disposition: attachment; filename=qbserv.qwc');
      header('Content-Type: text/xml');
      ?><<??>?xml version="1.0"?>
<QBWCXML>
  <AppName>QuickBooks XML Importer</AppName>
  <AppID></AppID>
  <AppURL><?= BASE_URL ?></AppURL>
  <AppDescription>Import huge QBXML files in chunks.</AppDescription>
  <AppSupport><?= str_replace('server.php', 'README.txt', BASE_URL) ?></AppSupport>
  <UserName>username</UserName>
  <OwnerID>{90A44FB7-33D9-4815-AC85-AC86A7E7D1EB}</OwnerID>
  <FileID>{57F3B9B6-86F1-4FCC-B1FF-967DE1813D20}</FileID>
  <QBType>QBFS</QBType>
  <Scheduler>
    <RunEveryNMinutes>0</RunEveryNMinutes>
  </Scheduler>
  <IsReadOnly>false</IsReadOnly>
</QBWCXML><?
      exit;
    }
  }

  public function serverVersion($params)
  {
    debug("serverVersion");
    return $this->_wrapResult(__FUNCTION__, self::VERSION);
  }

  public function clientVersion($params)
  {
    debug('clientVersion: '.$params->strVersion);
    if ($this->fatalError) {
      return $this->_wrapResult(__FUNCTION__, 'E:'.$this->fatalError);
    }
    return $this->_wrapResult(__FUNCTION__, '');
  }

  public function authenticate($params)
  {
    debug("authenticate: $params->strUsername : $params->strPassword");
    return $this->_wrapResult(__FUNCTION__, array(md5(time().$params->strUsername.$params->strPassword), ''));
  }

  public function closeConnection($params)
  {
    $this->_checkTicket($params->ticket);
    if ($this->fatalError) {
      return $this->_wrapResult(__FUNCTION__, 'FATAL ERROR: '.$this->fatalError);
    }
    return $this->_wrapResult(__FUNCTION__, 'QBServ says goodbye!');
  }

  public function connectionError($params)
  {
    $this->_checkTicket($params->ticket);
    error("connectionError: $params->message ($params->hresult)");
    return $this->_wrapResult(__FUNCTION__, 'done');
  }

  public function getInteractiveURL($params)
  {
    $this->_checkTicket($params->ticket);
    error("getInteractiveURL not supported");
    return $this->_wrapResult(__FUNCTION__, BASE_URL.'?pi');
  }

  public function getLastError($params)
  {
    $this->_checkTicket($params->ticket);
    $errfile = VAR_DIR.'/last_error-'.$params->ticket.'.txt';
    if (file_exists($errfile)) {
      $err = file_get_contents($errfile);
      unlink($errfile);
      return $this->_wrapResult(__FUNCTION__, $err);
    } else {
      return $this->_wrapResult(__FUNCTION__, ''); // reply NoOp to pause for 5 seconds
    }
  }

  public function interactiveDone($params)
  {
    $this->_checkTicket($params->ticket);
    return $this->_wrapResult(__FUNCTION__, 'Done');
  }

  public function interactiveRejected($params)
  {
    $this->_checkTicket($params->ticket);
    return $this->_wrapResult(__FUNCTION__, 'WAT?');
  }

  public function sendRequestXML($params)
  {
    $this->_checkTicket($params->ticket);
    if ($params->strHCPResponse) {
      debug("New session: QBXML $params->qbXMLCountry $params->qbXMLMajorVers.$params->qbXMLMinorVers");
      debug("strHCPResponse: $params->strHCPResponse");
      debug("strCompanyFileName: $params->strCompanyFileName");
    }

    $status = $this->_getStatus();
    $part = null;
    if ( ! strlen($status)) {
      $part = $this->config->start;
    } else if (is_numeric($status)) {
      $part = $status + 1;
    }

    // Unrecognized status
    if ($part === null) {
      $this->_setLastError($params->ticket, 'Unrecognized status: '.$status);
      return $this->_wrapResult(__FUNCTION__, 'NoOp');
    }

    // All done!
    if ($part > $this->config->end) {
      return $this->_wrapResult(__FUNCTION__, '');
    }

    // Send a part
    $file = $this->_getFile($part);
    $contents = file_get_contents($file);
    if ( ! $contents) {
      $this->_setLastError($params->ticket, 'Could not get file contents: '.$file);
      return $this->_wrapResult(__FUNCTION__, 'NoOp');
    }
    $this->_setStatus($part);
    $onError = 'onError="continueOnError" responseData="includeNone"';
    $qbxml = <<<XML
<?xml version="1.0" ?>
<?qbxml version="2.1"?>
<QBXML>
  <QBXMLMsgsRq $onError>
$contents  </QBXMLMsgsRq>
</QBXML>
XML;
    return $this->_wrapResult(__FUNCTION__, $qbxml);
  }

  public function receiveResponseXML($params)
  {
    $this->_checkTicket($params->ticket);
    if ($params->hresult || $params->message) {
      error("receiveResponseXML: $params->message ($params->hresult)");
      $this->_setLastError($params->ticket, "Aborting due to received response indicating Quickbooks error: $params->message");
      return $this->_wrapResult(__FUNCTION__, -1);
    }

    $percent = ($this->config->end - $this->config->start) / ($this->_getStatus() - $this->config->start);
    $percent = min(100,max(0, ceil($percent)));
    debug("receiveResponseXML ($percent%):\n$params->response");
    return $this->_wrapResult(__FUNCTION__, $percent);
  }

  protected function _checkTicket($ticket)
  {
    if ( ! preg_match('/^\w+$/', $ticket)) die('Bad ticket.');
  }

  protected function _setLastError($ticket, $message)
  {
    $errfile = VAR_DIR.'/last_error-'.$ticket.'.txt';
    file_put_contents($errfile, $message) or error("Could not write to $errfile");
  }

  protected function _getFile($num = '-STATUS')
  {
    if ( ! $this->config->path) die("No path");
    return str_replace('*', $num, $this->config->path);
  }

  protected function _wrapResult($type, $result)
  {
    #return (object) array($type.'Response' => (object) array($type.'Result' => $result));
    $response = (object) array($type.'Result' => $result);
    debug("RESPONSE ($type) ".print_r($response, true));
    return $response;
  }

  protected function _getStatus()
  {
    return file_get_contents($this->_getFile());
  }

  protected function _setStatus($status)
  {
    return file_put_contents($this->_getFile(), $status);
  }

}

try {
  $qbserv = new QBServ();
  $server = new SoapServer(BP.'/QBWebConnectorSvc.wsdl');
  $server->setObject($qbserv);
  $server->handle();
} catch (Exception $e) {
  echo $e->getMessage();
  error(date('r')." {$e->getMessage()}\n$e");
}
