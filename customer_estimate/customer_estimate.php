<?php
  /*
   * This is the view to display a customer estimate, users can edit core information and browse sub menus to
   * add comments, uploads, and products attached to the estimate
   */
  require_once("../login/include_login.php");
  require_once("../tabs/tabs_class.php");
  require_once("../header/header_class.php");
  require_once("customer_estimate_edit_class.php");
  require_once("customer_estimate_status_class.php");

  echo '<link rel="stylesheet" type="text/css" href="../style.css">';
  echo '<body style="margin: 0px">';

  /*
   * Start Bill Item Class
   * Bill Items are groups of products (parts, labor, freight, slip rent, outside service or merchandise)
   * Typically referenced as product groups
   */
  $customerBillItemReport = new customerBillItemReport;
  $customerBillItemReport->customerEstimateId = $_GET['customer_estimate_ID'];
  $customerBillItemReport->check_post();

  /*
   * Start Upload Class
   * Uploads allows the user to upload documents related to the estimate (cost work ups, parts quotes, images, etc.)
   */
  $uploads = new uploads;
  $uploads->customerEstimateId = $_GET['customer_estimate_ID'];
  $uploads->insertUpload();
  $uploads->checkDelete();

  /*
   * Start Estimate Edit Class
   * Gives user the ability to edit the estimate core information (ship to address, due date, etc.)
   */
  $estimateEdit = new estimateEdit($_GET['customer_estimate_ID']);

  /*
   * Start Customer Estimate Class
   * The core class that brings in all customer estimate information
   */
  $customerEstimate = new customerEstimate($_GET['customer_estimate_ID']);

  /*
   * Displays the top ribbon of the estimate, showing core information such as customer name, ship to address
   * billing address, pricing summary, etc.
   */
  echo $customerEstimate->display_ribbon();

  /*
   * Start Tabs Class
   * A simple class that dynamically creates menu tabs horizontally which controls sub menus
   */
  $tabs = new tabs();
  $tabs->tabModule = "customer_estimate_ID=" . $_GET['customer_estimate_ID'];
  $tabs->tabSelected = $_GET['tab'] ? $_GET['tab'] : 5;

  /* Push tabs away from left side */
  $tabs->addTabSpacer(60);

  /* Load Product Groups Menu */
  $billItemsTabsText = "Product Groups (" . totalBillItems('customer_estimate_ID=' . $customerEstimate->ID) . ")";
  $tabs->addTab($billItemsTabsText, 160, 4);
  $tabs->addTabSpacer(2);

  /* Load Edit Menu */
  $tabs->addTab("Edit", 100, 2);
  $tabs->addTabSpacer(20);

  /* Load Discussion Menu */
  $tabs->addTab("Discussion", 100, 1);
  $tabs->addTabSpacer(2);

  /* Load Uploads Menu */
  $uploadsTabsText = "Uploads (" . totalUploads('customer_estimate_ID=' . $customerEstimate->ID) . ")";
  $tabs->addTab($uploadsTabsText, 100, 3);
  $tabs->addTabSpacer(10);

  /* Display loaded tabs */
  $tabs->displayTabs();

  /*
   * Menu Switch
   * Displays current sub menu depending on menu tab selected
   */
  switch($tabs->tabSelected) {
    case 1:
      /*
       * Start Discussion Class
       *  Allows users to add comments to the estimate for internal discussions, these are not shown to the customer
       */
      $discussion                     = new discussion;
      $discussion->customerEstimateId = $_GET['customer_estimate_ID'];
      $discussion->showDiscussion();
      break;

    case 2:
      /*
       *  Displays the edit menu allowing users to change core information
       */
      $estimate_edit->display_report();
      break;

    case 3:
      /*
       *  Displays the uploads menu allowing users to upload supporting documents
       */
      $uploads->show_page();
      break;

    case 4:
      /*
       *  Displays the groups of products attached to the estimate, user must open a group to see individual products
       */

      // Set filter parameters
      $customerBillItemReport->groupByFilter = $_GET['group_by'] ? $_GET['group_by'] : "1";
      $customerBillItemReport->sortByFilter  = $_GET['sort_by'] ? $_GET['sort_by'] : "1";

      // Load query based on filter parameters
      $customerBillItemReport->createSql();

      // Display menu options (Add new product group, etc.)
      $customerBillItemReport->displayMenu();

      // Display shortcut options (Open and close product groups)
      $customerBillItemReport->displayShortcuts();

      // Display list of product groups
      $customerBillItemReport->displayReport();
      break;
  }
?>

