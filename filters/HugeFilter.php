<?php
require __DIR__.'/../lib/QbSimplexmlElement.php';

class HugeFilter
{

  const GENERIC_CUSTOMER_NAME = 'Generic Customer';

  protected $itemServices = array();
  protected $removedCustomers = array();

  public function filterXml($contents, $jobName)
  {
    $removedCustomersFile = VAR_DIR.'/customers-'.$jobName.'.idx';
    $removedCustomers = file_exists($removedCustomersFile) ? explode("\n",file_get_contents($removedCustomersFile)) : array();
    $removedCustomers = array_flip($removedCustomers);
    $removedCustomersUpdated = false;

    $xml = new QbSimplexmlElement('<?xml version="1.0" encoding="US-ASCII"?><root>'.$contents.'</root>'); /* @var $xml QbSimplexmlElement */
    if ( ! $xml) {
      throw new Exception('Error loading contents in SimpleXML. Check PHP error logs.');
    }

    $addNodes = array();
    $renamed = FALSE;
    foreach ($xml->children() as $request) { /* @var $request QbSimplexmlElement */
      $type = substr($request->getName(),0,-2);
      $node = $request->$type; /* @var $node QbSimplexmlElement */
      switch ($request->getName())
      {
        // Truncate names
        case 'AccountAddRq':
          $node->truncate('Name',31);
          $node->truncate('ParentRef/FullName',31,':');
          break;
        case 'BillAddRq':
          $node->truncate('VendorRef/FullName',31);
          $node->truncate('ARAccountRef/FullName',31,':');
          $node->truncate('RefNumber',20);
          $node->truncate('TermsRef/FullName',31);
          $node->truncate('ExpenseLineAdd/AccountRef/FullName',31,':');
          $node->truncate('ExpenseLineAdd/CustomerRef/FullName',41);
          $node->truncate('ExpenseLineAdd/ClassRef/FullName',31,':');
          $node->truncate('ItemLineAdd/ItemRef/FullName',31,':');
          $node->truncate('ItemLineAdd/CustomerRef/FullName',41);
          $node->truncate('ItemLineAdd/ClassRef/FullName',31,':');
          break;
        case 'ClassAddRq':
          $node->truncate('Name',31);
          $node->truncate('ParentRef/FullName',31,':');
          break;
        case 'CreditMemoAddRq':
          $node->truncate('RefNumber',11);
          $node->truncate('ClassRef/FullName',31,':');
          $node->truncate('ARAccountRef/FullName',31,':');
          // Replace customer name in CreditMemos
          $customerName = $node->truncate('CustomerRef/FullName',31);
          if ($customerName && (strpos($customerName, '@') || isset($removedCustomers[$customerName]))) {
            $node->CustomerRef->FullName = self::GENERIC_CUSTOMER_NAME;
            $renamed = TRUE;
          }
          break;


        // Delete all customers that use email as name
        case 'CustomerAddRq':
          $customerName = "{$node->Name}";
          if (strpos($customerName, '@')) {
            $request = FALSE;
          }
          else if ($customerName && ! isset($removedCustomers[$customerName])) {
            $removedCustomers[$customerName] = true;
            $removedCustomersUpdated = true;
            $request = FALSE;
          }
          break;

        // Delete duplicate requests
        case 'ItemServiceAddRq':
          $itemServiceName = $node->truncate('Name',31);
          $node->truncate('ClassRef/FullName',31);
          $node->truncate('ParentRef/FullName',31);
          if (isset($this->itemServices[$itemServiceName])) {
            $request = FALSE;
          }
          $this->itemServices[$itemServiceName] = true;
          break;

        // Replace customer name in SalesReceipts
        case 'SalesReceiptAddRq':
          $customerName = $node->truncate('CustomerRef/FullName',31);
          if ($customerName && (strpos($customerName, '@') || isset($removedCustomers[$customerName]))) {
            $node->CustomerRef->FullName = self::GENERIC_CUSTOMER_NAME;
            $renamed = TRUE;
          }
          break;

        // Replace customer name in JournalEntries
        case 'JournalEntryAddRq':
          foreach ($request->JournalEntryAdd->children() as $child) {
            if (in_array($child->getName(), array('JournalDebitLine','JournalCreditLine'))) {
              $customerName = "{$child->EntityRef->FullName}";
              if (strpos($customerName, '@') || isset($removedCustomers[$customerName])) {
                $child->EntityRef->FullName = self::GENERIC_CUSTOMER_NAME;
                $renamed = TRUE;
              }
            }
          }
          break;
      }
      if ($request) {
        $addNodes[] = $request;
      }
    }
    if ($renamed) {
      array_unshift($addNodes, $this->getGenericCustomerAdd());
    }
    $contents = '';
    foreach ($addNodes as $node) {
      $contents .= '      '.$this->getXmlAscii($node)."\n";
    }
    if ($removedCustomersUpdated) {
      file_put_contents($removedCustomersFile, implode("\n", array_keys($removedCustomers)));
    }
    return $contents;
  }

  public function getGenericCustomerAdd()
  {
    $xml = new QbSimplexmlElement('<?xml version="1.0" encoding="US-ASCII"?>
    <CustomerAddRq requestID="0">
      <CustomerAdd>
        <Name>'.self::GENERIC_CUSTOMER_NAME.'</Name>
        <IsActive>true</IsActive>
        <FirstName>Generic</FirstName>
        <LastName>Customer</LastName>
        <BillAddress>
          <Addr1>Generic Customer</Addr1>
          <Addr2>123 Fiction Way</Addr2>
          <City>Thneedville</City>
          <State>UT</State>
          <PostalCode>99999</PostalCode>
          <Country>US</Country>
        </BillAddress>
        <ShipAddress>
          <Addr1>Generic Customer</Addr1>
          <Addr2>123 Fiction Way</Addr2>
          <City>Thneedville</City>
          <State>UT</State>
          <PostalCode>99999</PostalCode>
          <Country>US</Country>
        </ShipAddress>
        <Phone>1231231234</Phone>
        <Email>user@example.com</Email>
      </CustomerAdd>
    </CustomerAddRq>
    ');
    return $xml;
  }

  public function getXmlAscii(SimpleXMLElement $xml)
  {
    $xml2 = new QbSimplexmlElement('<?xml version="1.0" encoding="US-ASCII"?><'.$xml->getName().'/>');
    $xml2->populate($xml);
    return $xml2->asXml();
    /*return trim(preg_replace('#^<\?[^?]+\?>#', '', $xml2->asNiceXml(null,1)));*/
  }

}
