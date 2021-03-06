<?php
/**
 * Purchase Request Processing class
 *
 * This class is used to:
 *  - Determine whether or not a user has submitted a purchase request.
 *  - Validate all incoming data.
 *  - Take the desired book off the market, log it as a purchase, and 
 *    remove the entry from IndexDen.
 *
 * @author    Oliver Spryn
 * @copyright Copyright (c) 2013 and Onwards, ForwardFour Innovations
 * @extends   FFI\BE\Processor_Base
 * @license   MIT
 * @namespace FFI\BE
 * @package   lib.processing
 * @since     3.0.0
*/

namespace FFI\BE;

require_once(dirname(__FILE__) . "/Processor_Base.php");
require_once(dirname(dirname(__FILE__)) . "/APIs/IndexDen.php");
require_once(dirname(dirname(__FILE__)) . "/APIs/Cloudinary.php");
require_once(dirname(dirname(__FILE__)) . "/display/Book.php");
require_once(dirname(dirname(__FILE__)) . "/emails/Email_Buyer.php");
require_once(dirname(dirname(__FILE__)) . "/emails/Email_Merchant.php");
require_once(dirname(dirname(__FILE__)) . "/exceptions/Login_Failed.php");
require_once(dirname(dirname(__FILE__)) . "/exceptions/Validation_Failed.php");
require_once(dirname(dirname(__FILE__)) . "/third-party/Indextank/Exception/HttpException.php");
require_once(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . "/wp-blog-header.php");
	
class Purchase_Process extends Processor_Base {
/**
 * Hold the data for the book which is being purchased
 * 
 * @access private
 * @type   object
*/
	
	private $book;
	
/**
 * Hold the data about the buyer
 *
 * @access private
 * @type   WP_User
*/
	
	private $buyer;
	
/**
 * Hold the comments from the buyer
 *
 * @access private
 * @type   string
*/
	
	private $comments;
	
	
/**
 * Hold the ID of the book
 *
 * @access private
 * @type   int
*/
	
	private $ID;

/**
 * Hold the data about the merchant
 *
 * @access private
 * @type   bool|WP_User
*/
	
	private $merchant;
	
/**
 * CONSTRUCTOR
 *
 * This method will call helper methods to:
 *  - Determine whether or not a user has submitted a purchase request.
 *  - Validate all incoming data.
 *  - Take the desired book off the market, log it as a purchase, and
 *    remove the entry from IndexDen.
 * 
 * @access public
 * @return void
 * @since  3.0.0
 * @throws Indextank_Exception_HttpException [Bubbled up] Thrown in the event of an IndexDen communication error
*/
	
	public function __construct() {
		parent::__construct();
	
	//Check to see if the user has submitted the form
		if ($this->userSubmittedForm()) {
			$this->login();
			$this->fetchSettings("ffi_be_settings");
			$this->validateAndRetain();
			IndexDen::delete($this->ID);
			$this->sendEmails();
			$this->updateLocal();
		}
	}
	
/**
 * Determine whether or not the user has submitted the form by
 * checking to see if all required data is present (but not
 * necessarily valid).
 *
 * @access private
 * @return bool     Whether or not the user has submitted the form
 * @since  3.0.0
*/
	
	private function userSubmittedForm() {
		if (is_array($_POST) && count($_POST) &&
			isset($_POST['id']) && ($this->loggedIn || (!$this->loggedIn && isset($_POST['username']) && isset($_POST['password']))) &&
			is_numeric($_POST['id']) && ($this->loggedIn || (!$this->loggedIn && !empty($_POST['username']) && !empty($_POST['password'])))) {
			return true;
		}
		
		return false;
	}
	
/**
 * Determine whether or not all of the required information has been
 * submitted and is completely valid. If validation has succeeded, then
 * store the data within the class for later database entry.
 *
 * @access private
 * @return void
 * @since  3.0.0
 * @throws Validation_Failed Thrown when ANY portion of the validation process fails
*/

	private function validateAndRetain() {
	//Calculate the book's expiration
		$timezone = new \DateTimeZone($this->settings[0]->TimeZone);
		$uploaded = new \DateTime($this->book[0]->Upload, $timezone);
		$expires = $uploaded->add(new \DateInterval("P" . $this->settings[0]->BookExpireMonths . "M"));
		$now = new \DateTime();

	//Fetch, validate, and retain the book data
	
		$this->ID = $_POST['id'];
		$this->book = Book::details($this->ID);
		
		if (!count($this->book)) {
			throw new Validation_Failed("This book does not exist");
		}
		
		if ($this->book[0]->Sold == 1) {
			throw new Validation_Failed("This book has already been purchased");
		}
		
		if ($expires->getTimestamp() < $now->getTimestamp()) {
			throw new Validation_Failed("This book has expired");
		}
		
	//Fetch, validate, and retain the merchant data
		$this->buyer = &$this->user;
		$this->merchant = get_userdata($this->book[0]->MerchantID);
		
		if (!$this->merchant) {
			throw new Validation_Failed("This book's merchant does not exist");
		}

		if ($this->buyer->ID == $this->merchant->ID) {
			throw new Validation_Failed("You cannot buy your own book");
		}
		
	//Retain the comments
		$this->comments = $_POST['comments'];
	}

/**
 * Send an email to the merchant notifying them of the purchase
 * and another email to the buyer, with their receipt.
 *
 * @access private
 * @return void
 * @since  3.0.0
*/
	
	private function sendEmails() {
	//Send the buyer an email
		$emailBuyer = new Email_Buyer();
		$emailBuyer->fromEmail = $this->settings[0]->EmailAddress;
		$emailBuyer->fromName = $this->settings[0]->EmailName;
		$emailBuyer->subject = "ORDER SUCCESS: " . $this->book[0]->Title;
		$emailBuyer->toEmail = $this->buyer->user_email;
		$emailBuyer->toName = $this->buyer->first_name . " " . $this->buyer->last_name;

		$emailBuyer->imageURL = Cloudinary::cover($this->book[0]->ImageID);
		$emailBuyer->merchant = $this->merchant->first_name . " " . $this->merchant->last_name;
		$emailBuyer->merchantFirstName = $this->merchant->first_name;
		$emailBuyer->price = $this->book[0]->Price;
		$emailBuyer->title = $this->book[0]->Title;

		$emailBuyer->buildBody();
		$emailBuyer->send();

	//Send the merchant an email
		$emailMerchant = new Email_Merchant();
		$emailMerchant->fromEmail = $this->buyer->user_email;
		$emailMerchant->fromName = $this->buyer->first_name . " " . $this->buyer->last_name;
		$emailMerchant->subject = "SOLD: " . $this->book[0]->Title;
		$emailMerchant->toEmail = $this->merchant->user_email;
		$emailMerchant->toName = $this->merchant->first_name . " " . $this->merchant->last_name;
		
		$emailMerchant->buyer = $this->buyer->first_name . " " . $this->buyer->last_name;
		$emailMerchant->buyerFirstName = $this->buyer->first_name;
		$emailMerchant->comments = $this->comments;
		$emailMerchant->imageURL = Cloudinary::cover($this->book[0]->ImageID);
		$emailMerchant->price = $this->book[0]->Price;
		$emailMerchant->title = $this->book[0]->Title;
		
		$emailMerchant->buildBody();
		$emailMerchant->send();
	}

/**
 * Update the local database to mark the book as "Sold" and log
 * the purchase.
 *
 * @access private
 * @return void
 * @since  3.0.0
*/

	private function updateLocal() {
		global $wpdb;

	//Mark the book as "Sold"
		$wpdb->update("ffi_be_sale", array (
			"Sold" => "1"
		), array (
			"SaleID" => $this->ID
		));
		

	//Log the purchase
		$timezone = new \DateTimeZone($this->settings[0]->TimeZone);
		$timestamp = new \DateTime("now", $timezone);
		
		$wpdb->insert("ffi_be_purchases", array (
			"PurchaseID" => NULL,
			"BookID"     => $this->book[0]->BookID,
			"Price"      => $this->book[0]->Price,
			"BuyerID"    => $this->buyer->ID,
			"MerchantID" => $this->merchant->ID,
			"Time"       => $timestamp->format("Y-m-d H:i:s")
		), array (
			"%d", "%d", "%d", "%d", "%d", "%s"
		));
	}
}
?>