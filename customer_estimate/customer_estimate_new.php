<?php
  require_once("../login/include_login.php");

  // DB connection
  $core = Core::getInstance();

  // Set date
  $date = date("U");

  // Create new estimate
  if($_POST) {
    $customerId         = $_POST['customer_ID'];
    $shipToId           = $_POST['ship_to_ID'];
    $salesTaxScheduleId = $_POST['sales_tax_schedule_ID'];
    $shipMethodId       = $_POST['ship_method_ID'];
    $dateRequired       = 0;

    $customer = new customer;
    $hullId   = $customer->default_hull_ID($customerId);

    // Convert date format from xx/xx/xx to Unix Epoch
    if($_POST['date_required']) {
      $dateRequired = date_parse($_POST['date_required']);
      $dateRequired = date("U", mktime(0, 0, 0, $dateRequired['month'], $dateRequired['day'], $dateRequired['year']));
    }

    // Get sales tax rate
    $sql  = 'SELECT sales_tax_rate FROM sales_tax_schedule WHERE ID = :salesTaxScheduleId';
    $stmt = $core->dbh->prepare($sql);
    $stmt->bindParam(':salesTaxScheduleId', $salesTaxScheduleId);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $salesTaxRate = $row['sales_tax_rate'];

    $sql  = 'INSERT INTO `customer_estimate` (`ID`) VALUES ("")';
    $stmt = $core->dbh->prepare($sql);
    $stmt->execute();
    $estimateId = $dbh->lastInsertId();

    $sql = 'UPDATE
              customer_estimate
            SET
              `created_by` = :createdBy,
              `created_on` = :createdOn,
              `updated_by` = :updatedBy,
              `updated_on` = :updatedOn,
              `customer_ID` = :customerId,
              `ship_to_ID` = :shipToId,
              `hull_ID` = :hullId,
              `sales_tax_schedule_ID` = :salesTaxScheduleId,
              `sales_tax_rate` = :salesTaxRate,
              `ship_method_ID` = :shipMethodId,
              `date_required` = :dateRequired
            WHERE
              ID = :estimateId';

    $stmt = $core->dbh->prepare($sql);
    $stmt->execute(
      array(
        ':createdBy'          => $_SESSION['current_user']->ID,
        ':createdOn'          => $date,
        ':updatedBy'          => $_SESSION['current_user']->ID,
        ':updatedOn'          => $date,
        ':customerId'         => $customerId,
        ':shipToId'           => $shipToId,
        ':hullId'             => $hullId,
        ':salesTaxScheduleId' => $salesTaxScheduleId,
        ':salesTaxRate'       => $salesTaxRate,
        ':shipMethodId'       => $shipMethodId,
        ':dateRequired'       => $dateRequired,
        ':estimateId'         => $estimateId
      )
    );

    // Preloads customer estimate with slip rent or merchandise product group based upon certain users
    if($_SESSION['current_user']->ID == 119 || $_SESSION['current_user']->ID == 63 || $_SESSION['current_user']->ID == 83 || $_SESSION['current_user']->ID == 138) {
      $userId = $_SESSION['current_user']->ID;

      // Choose correct title
      if($userId == 119) {
        $billItemTypeId = 7;
        $title          = "Slip Rent";
      } else {
        $billItemTypeId = 8;
        $title          = "Merchandise Order";
      }

      $sql  = 'INSERT INTO `customer_bill_item` (`ID`) VALUES ("")';
      $stmt = $core->dbh->prepare($sql);
      $stmt->execute();
      $customerBillItemId = $dbh->lastInsertId();

      $sql = 'UPDATE
		customer_bill_item
	      SET
		`created_by` = :createdBy,
		`created_on` = :createdOn,
		`updated_by` = :updatedBy,
		`updated_on` = :updatedOn,
		`customer_estimate_ID` = :estimateId,
          	`estimate_type_ID` = :estimateTypeId,
          	`type_ID` = :billItemTypeId,
          	`title` = :title
	       WHERE
		ID = :customerBillItemId';

      $stmt = $core->dbh->prepare($sql);
      $stmt->execute(
        array(
          ':createdBy'          => $_SESSION['current_user']->ID,
          ':createdOn'          => $date,
          ':updatedBy'          => $_SESSION['current_user']->ID,
          ':updatedOn'          => $date,
          ':customerEstimateId' => $estimateId,
          ':estimateTypeId'     => 1,
          ':billItemtypeId'     => $billItemTypeId,
          ':title'              => $title,
          ':customerBillItemId' => $customerBillItemId
        )
      );
    }
  }
?>
<HTML>
<HEAD>
  <TITLE>Create New Customer Estimate</TITLE>
  <LINK REL="stylesheet" TYPE="text/css" HREF="../style.css">
  <SCRIPT LANGUAGE="JavaScript" SRC="../javascript/CalendarPopup.js"></SCRIPT>
</HEAD>


<BODY style="margin: 0px">

<table width="100%" cellspacing="0" class="page_header">
  <tr>
    <td>
      <img src="http://www.mypae.com/v3.0/images/logo_002.png" style="vertical-align: -4px">
      Customer Estimate
    </td>
  </tr>
</table>

<?php
  if($estimateId) {
    echo '<div style="margin: 50px auto 10px auto; width: 300px; text-align: center">Estimate successfully created</div>';
    echo '<div style="margin: 10px auto 50px auto; width: 150px; text-align: center">
	    <button class="menu_section_button" onclick="window.open(\'../customer_estimate/customer_estimate.php?customer_estimate_ID=' . $estimateId . '&tab=5\',
			 \'customer_estimate_' . $estimateId . '\'); window.close()">
			   Go To Estimate # ' . $estimateId . '
	    </button>
	  </div>';
    exit;
  }
?>

<FORM NAME="form" ACTION="<?php echo $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']; ?>" METHOD="POST">
  <TABLE WIDTH="100%" CLASS="text_s_black" STYLE="font-weight: bold" CELLSPACING="6">
    <TR>
      <TD align="right" width="150">Customer</TD>
      <TD>
        <?php showCustomerSelect(0, 'text_s_black'); ?>
      </TD>
    </TR>
    <TR>
      <TD align="right" style="vertical-align: top; padding-top: 5px">Ship To</TD>
      <TD>
        <?php showShipToSelect(1, 'text_s_black'); ?>
      </TD>
    </TR>
    <TR>
      <TD align="right">Sales Tax Schedule</TD>
      <TD>
        <?php showSalesTaxScheduleSelect(0, 'text_s_black'); ?>
      </TD>
    </TR>
    <TR>
      <TD align="right">Ship Via</TD>
      <TD>
        <SELECT CLASS="text_s_black" name="ship_method_ID">
          <?php showShipViaSelect(1, 'text_s_black'); ?>
        </SELECT></TD>
    </TR>
    <TR>
      <TD align="right">Date Required</TD>
      <TD>
        <SCRIPT LANGUAGE="JavaScript">
          var cal1 = new CalendarPopup();
        </SCRIPT>
        <INPUT TYPE="TEXT" CLASS="text_s_black" NAME="date_required" size="10" readonly="readonly"
               style="text-align: center">
        <A HREF="#" onClick="cal1.select(document.forms['form'].date_required,'anchor1','MM/dd/yyyy'); return false;"
           NAME="anchor1" ID="anchor1"><IMG SRC="../javascript/popup_calendar/dlcalendar_2.gif"
                                            style="border: 0px;"></A>
        <A HREF="#" onClick="document.forms['form'].date_required.value = ''; return false;"><IMG
            SRC="../images/icons/erase.png" style="border: 0px;"></A>
      </TD>
    </TR>
    <tr>
      <td></td>
      <td style="padding-top: 10px"><INPUT TYPE="SUBMIT" VALUE="Create Estimate" CLASS="menu_section_button"></td>
    </tr>
  </TABLE>

</FORM>

</BODY>
</HTML>
