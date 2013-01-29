<?php
/**
 * Book Exchange page essentials class
 *
 * This class is used at the top of every Book Exchange script.
 * Its abilities are central to the structing and security of
 * each page. Its abilities include:
 *  - User access control
 *  - Provide quick access to the current user's information
 *  - Importing necessary PHP scripts
 *  - Setting the page title
 *  - Including CSS or JS files
 * 
 * @author    Oliver Spryn
 * @copyright Copyright (c) 2013 and Onwards, ForwardFour Innovations
 * @license   MIT
 * @package   includes
 * @since     v2.0 Dev
*/

class FFI_BE_Essentials {
/**
 * Hold the current user's information
 *
 * @access public
 * @type   object|boolean
*/
	
	public $user = false;
	
/**
 * Hold a private reference to the title of the page for the filter
 * hook to grab.
 *
 * @access private
 * @type   string
*/

	private $title;
	
/**
 * Hold a private reference to the requests for the CSS stylesheets for 
 * the filter hook to grab.
 *
 * @access private
 * @type   string[]
*/

	private $CSS = array();
	
/**
 * Hold a private reference to the requests for the JS scriptss for the 
 * filter hook to grab.
 *
 * @access private
 * @type   string[]
*/

	private $JS = array();
	
/**
 * CONSTRUCTOR
 *
 * There is nothing to do here... just live!
 * 
 * @access public
 * @return void
 * @since  v2.0 Dev
*/

	public function __construct() {
		//Nothing to do!
	}

/**
 * Check if the user is logged in. If so, then grant access to 
 * this page, otherwise, redirect to the login page.
 *
 * All users will have access to the features of the Book Exchange,
 * so there is no reason to check for certain privileges.
 *
 * This method will also obtain access the the current user's 
 * information, if they are logged in.
 * 
 * @access public
 * @return void
 * @since  v2.0 Dev
*/

	public function requireLogin() {
		if (!is_user_logged_in()) {
			wp_redirect(get_site_url() . "/wp-login.php?redirect_to=" . urlencode($_SERVER['REQUEST_URI']));
		} else {
			global $current_user;
			get_currentuserinfo();
			
			$this->user = $current_user;
		}
	}
	
/**
 * Set the <title> of the HTML page.
 * 
 * @access public
 * @param  string   $title The title of the HTML page
 * @return void
 * @since  v2.0 Dev
*/

	public function setTitle($title) {
		$this->title = $title;
		
		add_filter("wp_title", function($title) {
			return $this->title;
		});
	}
	
/**
 * Include the requested stylesheet in the <head> section of the page.
 * Local stylesheets are requested with respect to the "app" folder.
 * So a request such as "system/stylesheet/sell.css" would include the 
 * stylesheet like so: .../book-exchange/app/system/stylesheet/sell.css,
 * regardless of the address of the PHP file which requested the script.
 *
 * External stylesheets must be prefixed with a "//" for this class to 
 * know the request is for an external CSS stylesheet.
 * 
 * Since this method may be called multiple times, each address must be 
 * stored in the $this->CSS variable, since the stylesheet isn't added 
 * right away, but during the construction of the <head> section of the 
 * template. These addresses are stored in an array and are later added
 * to the template in the order they were requested.
 *
 * @access public
 * @param  string   $address The absolute URL of the external stylesheet or the URL with respect to the "app" folder.
 * @return void
 * @since  v2.0 Dev
*/

	public function includeCSS($address) {
	//Store this address for later
		array_push($this->CSS, $address);
		
		add_action("wp_print_styles", function() {
			$styleName = "FFI_BE_STYLE_ID_" . mt_rand();
			
		//Local stylesheets will need their address modified
		//The adderss for external stylesheets begin with "//"
			if (substr($this->CSS[0], 0, 2) != "//") {
				$this->CSS[0] = FFI_BE_REAL_ADDR . "app/" . $this->CSS[0];
			}
			
			wp_register_style($styleName, $this->CSS[0], array(), NULL); //NULL removes the ?ver from the URL
        	wp_enqueue_style($styleName);
			array_shift($this->CSS); //We're done with this stylesheet, so shift it off the front of the array
		});
	}
	
/**
 * Include the requested script in the <head> section of the page.
 * Local scripts are requested with respect to the "app" folder.
 * So a request such as "system/javascripts/interface.js" would include
 * the script like so: .../book-exchange/app/system/javascripts/interface.js,
 * regardless of the address of the PHP file which requested the script.
 *
 * External scripts must be prefixed with a "//" for this class to 
 * know the request is for an external JS file.
 * 
 * Since this method may be called multiple times, each address must be 
 * stored in the $this->JS variable, since the script isn't added 
 * right away, but during the construction of the <head> section of the 
 * template. These addresses are stored in an array and are later added
 * to the template in the order they were requested.
 *
 * @access public
 * @param  string   $address The absolute URL of the external script or the URL with respect to the "app" folder.
 * @return void
 * @since  v2.0 Dev
*/

	public function includeJS($address) {
	//Store this address for later
		array_push($this->JS, $address);
		
		add_action("wp_enqueue_scripts", function() {
			$styleName = "FFI_BE_SCRIPT_ID_" . mt_rand();
			
		//Local scripts will need their address modified
		//The adderss for external scripts begin with "//"
			if (substr($this->JS[0], 0, 2) != "//") {
				$this->JS[0] = FFI_BE_REAL_ADDR . "app/" . $this->JS[0];
			}
			
			wp_register_script($styleName, $this->JS[0], array(), NULL); //NULL removes the ?ver from the URL
        	wp_enqueue_script($styleName);
			array_shift($this->JS); //We're done with this script, so shift it off the front of the array
		});
	}
}
?>