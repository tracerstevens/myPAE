<?php
  /*
   * Customer Estimate Class
   *
   * Returns customer estimate information
   * Returns Subtotal and Grandtotal of customer estimate
   * Displays payment information in pdf format
   * Displays ribbon shown at top of estimate UI
   */
  class customerEstimate
  {
    public function __construct($customerEstimateId)
    {
      $this->loadData($customerEstimateId);
    }

    /*
     * Loads all customer estimate information
     */
    private function loadData($customerEstimateId)
    {
      // DB connection
      $core = Core::getInstance();

      // Get customer estimate information
      $sql = 'SELECT
			  *
			FROM
			  customer_estimate
			WHERE customer_estimate.ID = :customerEstimateId
			LIMIT 1';

      $stmt = $core->dbh->prepare($sql);
      $stmt->bindParam(':customerEstimateId', $customerEstimateId);
      $stmt->execute();

      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      // Load variables
      $this->ID                 = $row['ID'];
      $this->estimateNumber     = str_pad((int)$row['ID'], 4, "0", STR_PAD_LEFT);
      $this->title              = $row['title'];
      $this->description        = $row['description'];
      $this->customerId         = $row['customer_ID'];
      $this->hullId             = $row['hull_ID'];
      $this->shipToId           = $row['ship_to_ID'];
      $this->dateRequired       = $row['date_required'];
      $this->salesTaxScheduleId = $row['sales_tax_schedule_ID'];
      $this->salesTaxRate       = $row['sales_tax_rate'];
      $this->salesTaxAmount     = $row['sales_tax_amount'];
      $this->statusId           = $row['status_ID'];
      $this->attention          = $row['attention'];
      $this->depositRequired    = $row['deposit_required'];
      $this->typeId             = $row['type_ID'];
      $this->laborRate          = $row['labor_rate'];

      // Get customer information
      $customer = new customer();
      $customer->loadData($this->customerIdD);
      $this->customerId          = $customer->ID;
      $this->customerName        = $customer->name;
      $this->customerAddressFull = $customer->addressFull;
      $this->customerPhone1      = $customer->phone1;
      $this->customerFax1        = $customer->fax1;
      $this->customerEmail       = $customer->email;

      // Get ship via information
      $shipMethod            = new shipMethod($row['ship_method_ID']);
      $this->shipMethodId    = $shipMethod->ID;
      $this->shipMethodStyle = $shipMethod->style;
      $this->shipMethodTitle = $shipMethod->title;

      // Get ship to information
      $shipTo = new shipTo;
      $shipTo->loadData($row['ship_to_ID']);
      $this->shipToIdD      = $shipTo->ID;
      $this->shipToName     = $shipTo->companyName;
      $this->shipToAddress1 = $shipTo->address1;
      $this->shipToAddress2 = $shipTo->address2;
      $this->shipToAddress3 = $shipTo->address3;
      $this->shipToPhone1   = $shipTo->phoneNumber;
      $this->shipToFax1     = $shipTo->faxNumber;

      // Get estimate status information
      $customerEstimateStatus            = new customerEstimateStatus($row['status_ID']);
      $this->customerEstimateStatusId    = $customerEstimateStatus->ID;
      $this->customerEstimateStatusStyle = $customerEstimateStatus->style;
      $this->customerEstimateStatusTitle = $customerEstimateStatus->title;

      // Get created by full name and date
      $createdBy       = new user($row['created_by']);
      $this->createdBy = $createdBy->name;
      $this->createdOn = $row['created_on'];

      // Get updated by full name and date
      $updatedBy       = new user($row['updated_by']);
      $this->updatedBy = $updatedBy->name;
      $this->updatedOn = $row['updated_on'];

      // Get sales tax schedule title
      $sql  = 'SELECT title FROM sales_tax_schedule WHERE ID = :salesTaxScheduleId LIMIT 1'; //'" . $this->sales_tax_schedule_ID'
      $stmt = $core->dbh->prepare($sql);
      $stmt->bindParam(':salesTaxScheduleId', $this->salesTaxScheduleId);
      $stmt->execute();
      $row                            = $stmt->fetch(PDO::FETCH_ASSOC);
      $this->sales_tax_schedule_title = $row['title'];

      // Get boat hull number
      $hull             = new hull;
      $this->hullNumber = $hull->displayHullNumber($this->hullId, 'text_s_black');
    }

    /*
     * Calculates sub total of customer estimate
     */
    public function displaySubTotal()
    {
      // DB connection
      $core = Core::getInstance();

      $sql = 'SELECT
              SUM(customer_bill_item_bom.unit_price*customer_bill_item_bom.quantity) AS total_bom_price
            FROM
              customer_bill_item_bom
            LEFT JOIN customer_bill_item ON customer_bill_item.ID=customer_bill_item_bom.customer_bill_item_ID
            WHERE
              customer_bill_item.customer_estimate_ID = :customerEstimateId';

      $stmt = $core->dbh->prepare($sql);
      $stmt->bindParam(':customerEstimateId', $this->Id);
      $stmt->execute();
      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      return round($row['total_bom_price'], 2);
    }

    /*
     * Calculates grand total of customer estimate
     */
    public function displayGrandTotal()
    {
      return ($this->salesTaxAmount + $this->displaySubTotal());
    }

    /*
     * Displays list of payments collected from customer on the pdf version of customer estimate
     */
    public function showPaymentsPdf()
    {
      // DB connection
      $core = Core::getInstance();

      // Buffer used to store html and then output at the end
      $buffer = '';

      $sql = "SELECT
              *,
              payment_method.abbr AS payment_method_abbr
            FROM
              payment_in
            LEFT JOIN payment_method ON payment_method.ID=payment_in.payment_method_ID
            WHERE
              customer_estimate_ID=$this->ID";

      $stmt = $core->dbh->prepare($sql);
      $stmt->bindParam(':customerEstimateId', $this->Id);
      $stmt->execute();
      $results = $stmt->fetchAll();

      $buffer .= '<table style="font-size: 8pt" cellspacing="0" width="100%">';

      foreach($results AS $row) {
        $buffer .= '<tr style="color: #009900">
                      <td align="center" width="80">' . date("m/d/Y", $row['transaction_date']) . '</td>
                      <td align="center" width="50">' . $row['payment_method_abbr'] . '</td>
                      <td align="center"> ' . ($row['transaction_number'] ? ' # ' . $row['transaction_number'] : false) . '</td>
                      <td width="90" align="right" style="font-size: 10pt">$' . number_format($row['amount'], 2) . '</td>
                    </tr>';
      }

      $buffer .= '</table>';

      return $buffer;
    }

    /*
     * Displays useful core information of estimate along the top of the UI
     */
    public function display_ribbon()
    {
      $buffer = '
        <title>Customer Estimate | ' . $this->estimateNumber . '</title>

        <table width="100%" cellspacing="0" class="page_header">
          <tr>
            <td>
              <img src="../images/logo_002.png" style="vertical-align: -4px">
              Customer Estimate <span style="color: #ccc; font-weight: normal">|</span> ' . $this->estimateNumber . '
            </td>
            <td align="right" width="200">
              <input type="button" value="PDF" class="menu_section_button" onclick="javascript: window.open(\'customer_estimate_pdf_selected.php?list=' . $this->Id . '\', \'\', \'left=50, top=50, width=850, height=800\')">
              <input type="button" value="PDF SO" class="menu_section_button" onclick="javascript: window.open(\'customer_estimate_pdf_selected.php?list=' . $this->Id . '&salesOrder=1\', \'\', \'left=50, top=50, width=850, height=800\')">
              <input type="button" value="PDF WOA" class="menu_section_button" onclick="javascript: window.open(\'customer_estimate_pdf_woa.php\', \'\', \'left=50, top=50, width=850, height=800\')">
            </td>
          </tr>
        </table>
	
        <table class="header_group_table">
          <tr valign="top">
            <td class="header_group" width="325">
              <table class="text_s_black" style="padding-left: 2px;" cellspacing="0" width="100%">
                <tr valign="top">
                  <td width="120">Estimate #</td>
                  <td><b>' . $this->estimateNumber . '</b></td>
                </tr>
                <tr>
                  <td>Status</td>
                  <td><span style="' . $this->customerEstimateStatusStyle . '">' . $this->customerEstimateStatusTitle . '</span></td>
                </tr>
                <tr>
                  <td>Sales Tax Schedule</td>
                  <td>' . $this->salesTaxScheduleTitle . '</span></td>
                </tr>
                <tr class="text_r"><td colspan="2" style="padding: 0px;"><hr style="margin: 1px 0pt;" color="#eeeeee" noshade="noshade" size="1"></td></tr>
                <tr valign="top">
                  <td width="100">Created By</td>
                  <td>' . $this->createdBy . ' (' . date("m/d/Y g:i a", $this->createdOn) . ')</td>
                </tr>
                <tr valign="top">
                  <td>Updated By</td>
                  <td>' . $this->updatedBy . ' (' . date("m/d/Y g:i a", $this->updatedOn) . ')</td>
                </tr>
              </table>
		        </td>
		        <td class="header_group" width="300">
              <table class="text_s_black" style="padding-left: 2px;" cellspacing="0" width="100%">
                <tr valign="top">
                  <td width="100">Customer</td>
                  <td><a class="text_s_black" style="font-weight: bold" href="" onclick="window.open(\'../customer/customer.php?customer_ID=' . $this->customerId . '\')">' . $this->customerName . '</a> <BR>
                    ' . $this->customerAddressFull . '
                    <hr style="margin: 1px 0pt;" color="#eeeeee" noshade="noshade" size="1">
                    Ph. ' . $this->customerPhone1 . ' <BR>
                    Fx. ' . $this->customerFax1 . '<BR>
                    E-mail: ' . $this->customerEmail . '
                  </td>
                </tr>
                <tr class="text_r"><td colspan="2" style="padding: 0px;"><hr style="margin: 1px 0pt;" color="#eeeeee" noshade="noshade" size="1"></td></tr>
                <tr valign="top">
                  <td width="100">Hull #</td>
                  <td>' . showHullTitle($this->hullId) . '</td>
                </tr>
              </table>
		        </td>
		        <td class="header_group" width="300">
			        <table class="text_s_black" style="padding-left: 2px;" cellspacing="0" width="100%">
			          <tr valign="top">
				          <td width="100">Ship To</td>
                  <td><b>' . $this->shipToName . '</b> <BR>
                    ' . ($this->attention ? 'Attn: ' . $this->attention . '<br>' : false) . '
                    ' . ($this->shipToAddress1 ? strtoupper($this->shipToAddress1) . '<BR>' : '') . '
                    ' . strtoupper($this->shipToAddress2) . '<BR>
                    ' . strtoupper($this->shipToAddress3) . '
                    <hr style="margin: 1px 0pt;" color="#eeeeee" noshade="noshade" size="1">
                    Ph. ' . $this->shipToPhone1 . ' <BR>
                    Fx. ' . $this->shipToFax1 . '
                  </td>
			          </tr>
			          <tr class="text_r"><td colspan="2" style="padding: 0px;"><hr style="margin: 1px 0pt;" color="#eeeeee" noshade="noshade" size="1"></td></tr>
			          <tr valign="top">
				          <td>Ship Via</td>
				          <td><span style="' . $this->shipMethodStyle . '">' . $this->shipMethodTitle . '</span></td>
                </tr>
                <tr valign="top">
                  <td>Date Required</td>
                  <td>' . ($this->dateRequired ? date("m/d/Y", $this->dateRequired) : '-') . '</td>
                </tr>
              </table>
            </td>
            <td class="header_group" width="300">
              <table class="text_s_black" style="padding-left: 2px;" cellspacing="0" width="100%">
                <tr>
                  <td style="font-weight: bold; text-align:center" colspan="2">Summary</td>
                </tr>
                <tr>
                  <td width="100">Sub Total</td>
                  <td align="right">$' . number_format($this->displaySubTotal(), 2) . '</span></td>
                </tr>
                <tr>
                  <td>Sales Tax (' . $this->salesTaxRate . '%)</td>
                  <td align="right">$' . number_format($this->salesTaxAmount, 2) . '</span></td>
                </tr>
                <tr class="text_r"><td style="padding: 0px;" colspan="2"><hr style="margin: 1px 0pt;" color="#eeeeee" noshade="noshade" size="1"></td></tr>
                <tr>
                  <td>Grand Total</td>
                  <td align="right" style="font-weight: bold">$' . number_format($this->displayGrandTotal(), 2) . '</span></td>
                </tr>';

      if($this->depositRequired <> 0.00) {
        $buffer .= '<tr class="text_r"><td style="padding: 0px;" colspan="2"><hr style="margin: 1px 0pt;" color="#eeeeee" noshade="noshade" size="1"></td></tr>
                    <tr>
                      <td>Deposit Required</td>
                      <td align="right" style="color: #0000ff">$' . number_format($this->depositRequired, 2) . '</span></td>
                    </tr>';
      }

      $buffer .= '</table>
                </td>
                <td></td>
              </tr>
            </table>';

      return $buffer;

    }

  }

?>