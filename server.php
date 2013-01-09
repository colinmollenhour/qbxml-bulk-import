<?php
if (isset($_GET['pi'])) { echo phpinfo(); exit; }
define('BP', __DIR__);
define('VAR_DIR', __DIR__.'/var');

$proto = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on" ? 'https' : 'http');
$host = $_SERVER['HTTP_HOST'];
$uri = $_SERVER['PHP_SELF'];
define('BASE_URL', "$proto://$host$uri");

date_default_timezone_set('America/New_York');
function debug($msg) { error_log(date('c').' '.$msg."\n", 3, VAR_DIR.'/debug.log'); }
function error($msg) { error_log(date('c').' '.$msg."\n", 3, VAR_DIR.'/error.log'); }

class QBServ {

  const VERSION = 'v0.9';

  const STATUS_OK = 0;

  protected $fatalError;
  protected $config;

  public function __construct()
  {
    try {
      if ( ! extension_loaded('soap')) {
        throw new Exception('You must have the SOAP extension installed.');
      }
      $configPath = BP.'/config.txt';
      if ( ! is_readable($configPath)) {
        throw new Exception('Could not load config.txt');
      }
      $configStr = trim(file_get_contents($configPath));
      if ( ! preg_match('/^\w+\|[^|]+\|\d+\|\d+$/', $configStr)) {
        throw new Exception('Could not parse config.txt. Expected format: JobName|/path/to/files*.xml|1|523');
      }
      list($jobName, $path, $start, $end) = explode('|', $configStr);
      if ( ! strpos($path, '*')) {
        throw new Exception('Path to files must contain a replacement placeholder (*).');
      }
      $this->config = (object) array('jobName' => $jobName, 'path' => $path, 'start' => $start, 'end' => $end);
      $path = $this->_getFile($this->config->start);
      if ( ! is_readable($path)) {
        throw new Exception("Cannot read $path.");
      }
      if ( ! $this->_setStatus(null)) {
        throw new Exception("Cannot write to status file: $path");
      }
      if ( ! $this->_setStatus(null)) {
        throw new Exception("Cannot write to status file: $path");
      }
    } catch(Exception $e) {
      $this->fatalError = $e->getMessage();
    }
    if (isset($_GET['test'])) {
      die('<html><body>Tests passed. Download the QWC file here: <a href="'.BASE_URL.'?qwc">qbserv.qwc</a></body></html>');
    }
    if (isset($_GET['qwc'])) {
      if ($this->fatalError) {
        die("<h3>{$this->fatalError}</h3>");
      }
      header('Content-Disposition: attachment; filename=qbserv.qwc');
      header('Content-Type: text/xml');
      $guid = sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
      ?><<?php ?>?xml version="1.0"?>
<QBWCXML>
  <AppName>QuickBooks XML Importer</AppName>
  <AppID></AppID>
  <AppURL><?= BASE_URL ?></AppURL>
  <AppDescription>Import huge QBXML files in chunks.</AppDescription>
  <AppSupport><?= str_replace('server.php', 'README.txt', BASE_URL) ?></AppSupport>
  <UserName>username</UserName>
  <OwnerID>{18602462-953C-4702-83AA-C07C1BF556E2}</OwnerID>
  <FileID>{<?= $guid ?>}</FileID>
  <QBType>QBFS</QBType>
  <IsReadOnly>false</IsReadOnly>
</QBWCXML><?php
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
    $part = $this->_getStatus() ? $this->_getStatus()+1: $this->config->start;
    return $this->_wrapResult(__FUNCTION__, "W:Connection established. Sync will start at $part of {$this->config->start}-{$this->config->end} parts.");
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
    $percent = min(100,max(1, ceil($percent)));
    debug("receiveResponseXML ($percent%):\n$params->response");

    try {
      libxml_use_internal_errors(true);
      $xml = simplexml_load_string($params->response);
      if ( ! $xml) {
        $errors = array();
        foreach (libxml_get_errors() as $error) {
          $errors[] = $error->message;
        }
        throw new Exception("Response contains XML Errors:\n  ".implode("\n  ", $errors), -2);
      }
      if ( ! $xml->QBXMLMsgsRs instanceof SimpleXMLElement) {
        throw new Exception("Response does not contain expected element.");
      }
      foreach ($xml->QBXMLMsgsRs->children() as $node) { /* @var $node SimpleXMLElement */
        if ($node['statusCode'] != self::STATUS_OK) {
          $errString = "{$node['requestID']} {$node['statusSeverity']} ({$node['statusCode']}): {$node['statusMessage']}\n";
          $this->_logError($errString);
        }
      }
    }
    catch (Exception $e) {
      $this->_setLastError($params->ticket, $e->getMessage());
      error($e->getMessage());
      return $this->_wrapResult(__FUNCTION__, $e->getCode());
    }
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

  protected function _getFile($num)
  {
    $path = $this->config->path;
    if ( ! $path) die("No path");
    return str_replace('*', $num, $path);
  }

  protected function _wrapResult($type, $result)
  {
    $response = (object) array($type.'Result' => $result);
    debug("RESPONSE ($type) ".print_r($response, true));
    return $response;
  }

  protected function _getStatus()
  {
    $file = VAR_DIR.'/job-'.$this->config.'.status';
    return file_get_contents($file);
  }

  protected function _setStatus($status)
  {
    $file = VAR_DIR.'/job-'.$this->config->jobName.'.status';
    if ($status === null) {
      return touch($file);
    }
    return file_put_contents($file, $status);
  }

  protected function _logJobMessage($message)
  {
    $file = VAR_DIR.'/job-'.$this->config->jobName.'.log';
    if ($message === null) {
      return touch($file);
    }
    return file_put_contents($file, $message, FILE_APPEND); // TODO - keep file open until done
  }

}

try {
  $qbserv = new QBServ();
  $server = new SoapServer(BP.'/QBWebConnectorSvc.wsdl');
  $server->setObject($qbserv);
  $server->handle();
} catch (Exception $e) {
  echo $e->getMessage();
  error("{$e->getMessage()}\n$e");
}
