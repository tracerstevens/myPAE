<?php
  /*
   * Estimate Edit Class
   *
   * Allows user to edit core information about the customer estimate
   */
  class estimateEdit
  {
    public function __construct($customerEstimateId)
    {
      $this->checkPost($customerEstimateId);
    }

    /*
     * Save user input into db
     */
    private function checkPost($customerEstimateId)
    {
      // DB connection
      $core = Core::getInstance();

      if($_POST['edit_estimate']) {
        $statusId           = $_POST['customer_estimate_status_ID'];
        $customerId         = $_POST['customer_ID'];
        $hullId             = $_POST['hull_ID'];
        $shipToId           = $_POST['ship_to_ID'];
        $shipMethodId       = $_POST['ship_method'];
        $salesTaxScheduleId = $_POST['sales_tax_schedule_ID'];
        $salesTaxAmount     = $_POST['sales_tax_amount'];
        $attention          = htmlspecialchars($_POST['attention']);
        $depositRequired    = $_POST['deposit_required'];
        $laborRate          = $_POST['labor_rate'];


        // Convert date format from xx/xx/xx to Unix Epoch
        if($_POST['date_required']) {
          $dateRequired = date_parse($_POST['date_required']);
          $dateRequired = date("U", mktime(0, 0, 0, $dateRequired[month], $dateRequired[day], $dateRequired[year]));
        }

        // Get sales tax schedule information before update
        $sql  = 'SELECT sales_tax_schedule_ID, sales_tax_amount FROM customer_estimate WHERE ID = :customerEstimateId';
        $stmt = $core->dbh->prepare($sql);
        $stmt->bindParam(':customerEstimateId', $customerEstimateId);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $salesTaxScheduleIdPrevious = $row['sales_tax_schedule_ID'];
        $salesTaxAmountPrevious     = $row['sales_tax_amount'];

        // Get sales tax rate before update
        $sql  = 'SELECT sales_tax_rate FROM sales_tax_schedule WHERE ID = :salesTaxScheduleId';
        $stmt = $core->dbh->prepare($sql);
        $stmt->bindParam(':salesTaxScheduleId', $salesTaxScheduleId);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $salesTaxRate = $row['sales_tax_rate'];

        // Check if user input sales tax amount is different than stored sales tax amount
        // If different update sales tax rate
        if($salesTaxAmountPrevious != $salesTaxAmount) {
          $customerEstimate = new customerEstimate($customerEstimateId);
          $salesTaxRate     = round(($salesTaxAmount / $customerEstimate->displaySubTotal()) * 100, 3);
        }

        // Update db with user input
        $sql = 'UPDATE
                customer_estimate
              SET
                status_ID = :statusId,
                customer_ID = :customerId,
                hull_ID = :hullId,
                ship_to_ID = :shipToId,
                ship_method_ID = :shipMethodId,
                sales_tax_schedule_ID = :salesTaxScheduleId,
                sales_tax_rate = :salesTaxRate,
                sales_tax_amount = :salesTaxAmount,
                date_required = :dateRequired,
                deposit_required = :depositRequired,
                attention = :attention,
                labor_rate = :laborRate
              WHERE
                ID = :customerEstimateId';

        $stmt = $core->dbh->prepare($sql);
        $stmt->execute(
          array(
            ':statusId'           => $statusId,
            ':customerId'         => $customerId,
            ':hullId'             => $hullId,
            ':shipToId'           => $shipToId,
            ':shipMethodId'       => $shipMethodId,
            ':salesTaxScheduleId' => $salesTaxScheduleId,
            ':salesTaxRate'       => $salesTaxRate,
            ':salesTaxAmount'     => $salesTaxAmount,
            ':dateRequired'       => $dateRequired,
            ':depositRequired'    => $depositRequired,
            ':attention'          => $attention,
            ':laborRate'          => $laborRate,
            ':customerEstimateId' => $customerEstimateId
          )
        );

        // Update sales tax if estimate ship to has changed country state
        if($salesTaxScheduleId <> $salesTaxScheduleIdPrevious) {
          updateCustomerEstimateSalesTax($customerEstimateId);
        }
      }
    }

    /*
     * Displays input fields for user to edit
     */
    public function displayReport()
    {
      global $customerEstimate;

      // DB connection
      $core = Core::getInstance();

      // Get estimate status
      $sql = 'SELECT * FROM customer_estimate_status WHERE hide = 0 ORDER BY sort_order ASC';
      $stmt = $core->dbh->prepare($sql);
      $stmt->execute();
      $estimateStatuses = $stmt->fetchAll();
      ?>

      <script src="../javascript/CalendarPopup.js"></script>

      <table class="ribbon_new" width="100%">
        <tr>
          <td>
            <hr style="height: 1px; width: 1px; visibility: hidden">
          </td>
        </tr>
      </table>

      <form name="form1" action="<?php echo $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']; ?>" method="post">
        <input type="hidden" name="edit_estimate" value="1">
        <input type="hidden" name="estimate_ID" value="<?php echo $customerEstimate->ID; ?>">

        <table width="100%" class="text_s_black" style="font-weight: bold" cellspacing="6">
          <TR>
            <TD align="right" width="150">Labor Rate</TD>
            <TD><INPUT TYPE="TEXT" NAME="labor_rate" VALUE="<?php echo $customerEstimate->laborRate; ?>"
                       CLASS="text_r" SIZE="8"></TD>
          </TR>
          <tr class="text_r">
            <td style="padding: 0px;">
              <hr style="margin: 1px 0pt;" color="#bbbbbb" noshade="noshade" size="1">
            </td>
          </tr>

          <tr>
            <td align="right" width="150">Status</td>
            <td>
              <SELECT name="customer_estimate_status_ID" class="text_r">
                <?php
                  foreach($estimateStatuses AS $row) {
                    echo '<option value="' . $row['ID'] . '" ' . ($customerEstimate->statusId == $row['ID'] ? 'SELECTED' : false) . '>' . $row['title'] . '</option>';
                  }
                ?>
              </SELECT>
          </tr>
          <tr class="text_r">
            <td style="padding: 0px;">
              <hr style="margin: 1px 0pt;" color="#bbb" noshade="noshade" size="1">
            </td>
          </tr>
          <tr>
            <td align="right">Sales Tax Schedule</td>
            <td><?php showSalesTaxScheduleSelect($customerEstimate->salesTaxScheduleId, 'text_r'); ?></td>
          </tr>
          <tr>
            <td align="right">Sales Tax Amount</td>
            <td>
              <input type="text" name="sales_tax_amount" class="text_r"
                     value="<?php echo $customerEstimate->salesTaxAmount; ?>" size="8">
          </tr>
          <tr class="text_r">
            <td style="padding: 0px;">
              <hr style="margin: 1px 0pt;" color="#bbb" noshade="noshade" size="1">
            </td>
          </tr>
          <tr>
            <td align="right">Customer</td>
            <td><?php showCustomerSelect($customerEstimate->customerId, 'text_r'); ?></td>
          </tr>
          <tr>
            <td align="right">Hull ID</td>
            <td><?php showHullNumberSelect($customerEstimate->hullId, 'text_r'); ?></td>
          </tr>
          <tr>
            <td align="right" style="vertical-align: top; padding-top: 6px">Ship To</td>
            <td><?php showShipToSelect($customerEstimate->shipToId, 'text_r'); ?></td>
          </tr>
          <tr>
            <td align="right">Ship Via</td>
            <td><?php showShipMethodSelect($customerEstimate->shipMethodId, 'text_r'); ?></td>
          </tr>
          <tr class="text_r">
            <td style="padding: 0px;">
              <hr style="margin: 1px 0pt;" color="#bbb" noshade="noshade" size="1">
            </td>
          </tr>
          <tr>
            <td align="right">Date Required</td>
            <td>
              <SCRIPT LANGUAGE="JavaScript">
                var cal1 = new CalendarPopup();
              </SCRIPT>
              <INPUT TYPE="TEXT" CLASS="text_r" NAME="date_required" size="8" readonly="readonly"
                     style="text-align: center"
                     value="<?php echo $customerEstimate->dateRequired ? date("m/d/Y", $customerEstimate->dateRequired) : false; ?>">
              <A HREF="#"
                 onClick="cal1.select(document.forms['form1'].date_required,'anchor1','MM/dd/yyyy'); return false;"
                 NAME="anchor1" ID="anchor1"><IMG SRC="../javascript/popup_calendar/dlcalendar_2.gif"
                                                  style="border: 0px;"></A>
              <A HREF="#" onClick="document.forms['form1'].date_required.value = ''; return false;"><IMG
                  SRC="../images/icons/erase.png" style="border: 0px;"></A>
            </td>
          </tr>
          <tr>
            <td align="right">Deposit Required</td>
            <td>
              <input type="text" name="deposit_required" class="text_r"
                     value="<?php echo $customerEstimate->depositRequired; ?>" size="8">
          </tr>
          <tr class="text_r">
            <td style="padding: 0px;">
              <hr style="margin: 1px 0pt;" color="#bbb" noshade="noshade" size="1">
            </td>
          </tr>
          <tr>
            <td align="right">Attention</td>
            <td>
              <input type="text" name="attention" class="text_r" value="<?php echo $customerEstimate->attention; ?>"
                     size="25">
          </tr>
          <tr>
            <td></td>
            <td style="padding-top: 10px"><input type="submit" value="Save" class="menu_section_button">
          </tr>
        </table>
      </form>

    <?php
    }
  }

?>
