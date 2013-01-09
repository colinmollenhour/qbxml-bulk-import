<?php

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

    $xml = simplexml_load_string('<root>'.$contents.'</root>'); /* @var $xml SimpleXMLElement */

    $addNodes = array();
    $renamed = FALSE;
    foreach ($xml->children() as $request) { /* @var $request SimpleXMLElement */
      switch ($request->getName())
      {
        // Delete all customers that use email as name
        case 'CustomerAddRq':
          $customerName = "{$request->CustomerAdd->Name}";
          if (strpos($customerName, '@')) {
            $request = FALSE;
          }
          if ( ! isset($removedCustomers[$customerName])) {
            $removedCustomers[$customerName] = true;
            $removedCustomersUpdated = true;
            $request = FALSE;
          }
          break;

        // Delete duplicate requests
        case 'ItemServiceAddRq':
          if (isset($this->itemServices["{$request->ItemServiceAdd->Name}"])) {
            $request = FALSE;
          }
          $this->itemServices["{$request->ItemServiceAdd->Name}"] = true;
          break;

        // Replace customer name in SalesReceipts
        case 'SalesReceiptAddRq':
          $customerName = "{$request->SalesReceiptAdd->CustomerRef->FullName}";
          if ($customerName && strpos($customerName, '@') || isset($removedCustomers[$customerName])) {
            $request->SalesReceiptAdd->CustomerRef->FullName = self::GENERIC_CUSTOMER_NAME;
            $renamed = TRUE;
          }
          break;

        // Replace customer name in CreditMemos
        case 'CreditMemoAddRq':
          $customerName = "{$request->CreditMemoAdd->CustomerRef->FullName}";
          if ($customerName && strpos($customerName, '@') || isset($removedCustomers[$customerName])) {
            $request->CreditMemoAdd->CustomerRef->FullName = self::GENERIC_CUSTOMER_NAME;
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
      $contents .= trim(preg_replace('#^<\?[^?]+\?>#', '', $node->asXML()))."\n";
    }
    if ($removedCustomersUpdated) {
      file_put_contents($removedCustomersFile, implode("\n", $removedCustomers));
    }
    return $contents;
  }

  public function getGenericCustomerAdd()
  {
    $xml = new SimpleXMLElement('
    <CustomerAddRq requestID="110111">
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

}
