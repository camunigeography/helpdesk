<?php

# Class to create a helpdesk facility
require_once ('frontControllerApplication.php');
class helpdesk extends frontControllerApplication
{
	# Function to assign defaults additional to the general application defaults
	public function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		$defaults = array (
			'database' => 'helpdesk',
			'table' => 'calls',
			'peopleDatabase' => 'people',
			'emailDomain' => 'cam.ac.uk',
			'completedJobExpiryDays' => 7,
			'listMostRecentFirst' => true,
			'div' => 'helpdesk',
			'h1'					=> NULL,
			'institution'			=> NULL,	// Institution name in "welcome to the %institution helpdesk"
			'type'					=> NULL,	// Used in: "the %type staff" and "some %type problems"
			'busyThreshold' => 25,	// The number of calls above which the Staff are 'busy'
			'administrators' => true,
			'authentication' => true,	// All pages require authentication
			'cols'			=> 55,	// Size of textareas
			'totalRecentSearches' => 7,	// Number of recent searches to display
			'apiUsername'			=> false,		// Optional API access
			'tabUlClass' => 'tabsflat',
		);
		
		# Return the defaults
		return $defaults;
	}
	
	
	# Function to assign additional actions
	public function actions ()
	{
		# Specify additional actions
		$actions = array (
			'home' => array (
				'description' => false,
				'url' => '',
				'tab' => 'Home',
				'icon' => 'house',
			),
			'report' => array (
				'description' => 'Report a problem',
				'tab' => 'Report a problem',
				'icon' => 'add',
			),
			'calls' => array (
				'description' => 'View/edit submitted problems',
				'url' => 'calls/',
				'tab' => 'View/edit submitted problems',
				'icon' => 'application_double',
			),
			'call' => array (
				'description' => 'View/edit call',
				'url' => 'calls/',
				'usetab' => 'calls',
			),
			'search' => array (
				'description' => 'Search calls',
				'usetab' => 'calls',
				'url' => 'search/',
				'administrator' => true,
			),
			'allcalls' => array (
				'description' => 'All calls',
				'usetab' => 'calls',
			),
			'statistics' => array (
				'description' => 'Statistics',
				'url' => 'statistics.html',
				'administrator' => true,
				'parent' => 'admin',
				'subtab' => 'Statistics',
			),
			'data' => array (	// Used for e.g. AJAX calls, etc.
				'description' => 'Data point',
				'url' => 'data.html',
				'export' => true,
				'administrator' => true,
			),
			'categories' => array (
				'description' => 'Categories',
				'url' => 'categories/',
				'administrator' => true,
				'parent' => 'admin',
				'subtab' => 'Categories',
				'icon' => 'text_list_bullets',
			),
		);
		
		# Return the actions
		return $actions;
	}
	
	
	# Define the available status definitions
	var $currentStatusDefinitions = array (
		'submitted'		=> 'not yet scheduled by %type staff',
		'timetabled'	=> '%type staff have read the request and scheduled time to implement it',
		'researching'	=> '%type staff have read the request a solution to the problem is being researched',
		'completed'		=> 'the problem is believed to be resolved',
		'deferred'		=> 'the problem has been scheduled for the longer-term',
	);
	
	# Define the available levels of busyness
	private $busyness = array (
		10		=> 'very low',
		30		=> 'moderate',
		60		=> 'high',
		999999	=> 'very high',		// The integer given is just an arbitrarily high number that should never be reached
	);
	
	# Whether jQuery is loaded
	private $jQueryLoaded = false;
	
	
	# Database structure definition
	public function databaseStructure ()
	{
		return "
			
			-- Administrators
			CREATE TABLE `administrators` (
			  `id` varchar(191) COLLATE utf8mb4_unicode_ci PRIMARY KEY NOT NULL COMMENT 'Username',
			  `active` enum('','Yes','No') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Yes' COMMENT 'Currently active?',
			  `receiveHelpdeskEmail` enum('Yes','No') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Yes',
			  `state` text COLLATE utf8mb4_unicode_ci COMMENT 'Headings expanded'
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Helpdesk administrators';
			
			-- Settings
			CREATE TABLE IF NOT EXISTS `settings` (
			  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY COMMENT 'Automatic key (ignored)',
			  `homepageMessageHtml` TEXT NULL COMMENT 'Homepage message (if any)'
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Settings';
			INSERT INTO settings (id) VALUES (1);
			
			-- Calls
			CREATE TABLE `calls` (
			  `id` int PRIMARY KEY NOT NULL AUTO_INCREMENT COMMENT 'Call number',
			  `subject` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Subject',
			  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'User',
			  `categoryId` int NOT NULL COMMENT 'Category of problem',
			  `details` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Details of problem, or describe what you did',
			  `imageFile` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Optional image (e.g. screenshot)',
			  `timeSubmitted` datetime NOT NULL,
			  `lastUpdated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Time this call was last updated (or created)',
			  `administratorId` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Staff',
			  `currentStatus` enum('submitted','timetabled','researching','completed','deferred') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted' COMMENT 'Status',
			  `reply` text COLLATE utf8mb4_unicode_ci NOT NULL,
			  `timeOpened` datetime DEFAULT NULL,
			  `timeCompleted` datetime DEFAULT NULL,
			  `internalNotes` text COLLATE utf8mb4_unicode_ci
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
			
			-- Categories
			CREATE TABLE `categories` (
			  `id` int PRIMARY KEY NOT NULL AUTO_INCREMENT,
			  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Category',
			  `listpriority` DECIMAL(2,0) NOT NULL DEFAULT '0' COMMENT 'List priority (smaller numbers = earlier)',
			  `hide` TINYINT NULL DEFAULT NULL COMMENT 'Hide for new calls?'
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
			
			-- Searches
			CREATE TABLE `searches` (
			  `id` int PRIMARY KEY NOT NULL AUTO_INCREMENT COMMENT 'Automatic key',
			  `search` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Search phrase',
			  `username` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Username',
			  `datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Automatic timestamp'
			) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Table of searches';
		";
	}
	
	
	# Additional initialisation
	public function main ()
	{
		# Force creation of administrators if none
		if (!$this->administrators) {
			$this->accountDetails ();
			return false;
		}
		
		# Get the user details
		if ($this->action != 'api') {
			if (!$this->userDetails = $this->userDetails ()) {
				echo "<p>Welcome to the {$this->settings['institution']} online helpdesk system for requesting help with {$this->settings['type']} problems.</p>";
				$this->accountDetails ();
				return false;
			}
		}
		
		# Load the list of available categories
		if (!$this->getCategories ()) {
			echo "<p>This system is not yet set up fully. Please check back shortly.</p>";
			if ($this->userIsAdministrator && $this->action != 'categories') {
				echo "As a system administrator, please <a href=\"{$this->baseUrl}/categories/\">add some categories to the database</a>.";
				return false;
			}
		}
		
		# Show the search box throughout
		if ($this->userIsAdministrator) {
			if (!in_array ($this->action, array ('search', 'api', 'data', 'categories'))) {
				$this->searchForm ($float = true);
			}
		}
		
		# Confirm success
		return true;
	}
	
	
	# Function to get the user details or force them to register
	private function userDetails ($user = false)
	{
		# Default to the current user
		if (!$user) {$user = $this->user;}
		
		# Get the list of users
		if (!$userDetails = $this->databaseConnection->select ($this->settings['peopleDatabase'], 'people', array ('username' => $user /*, 'active' => 'Y' */ ))) {
			return false;
		}
		
		# Determine the user's preferred e-mail address
		$userDetails[$user]['_preferredEmail'] = (application::validEmail ($userDetails[$user]['websiteEmail']) ? $userDetails[$user]['websiteEmail'] : (application::validEmail ($userDetails[$user]['email']) ? $userDetails[$user]['email'] : "{$user}@{$this->settings['emailDomain']}"));
		
		# Assemble the user's full name
		$userDetails[$user]['_fullname'] = "{$userDetails[$user]['forename']} {$userDetails[$user]['surname']}";
		
		# Otherwise return the details
		return $userDetails[$user];
	}
	
	
	# Welcome screen
	public function home ()
	{
		# Start the HTML
		$html = '';
		
		# Start the page
		$html .= "\n\n" . "<p>Welcome, {$this->userDetails['forename']}, to the {$this->settings['institution']} online helpdesk system for requesting help with {$this->settings['type']} problems.</p>";
		
		# Add extra message, if enabled
		if ($this->settings['homepageMessageHtml']) {
			$html .= "\n<br />\n" . $this->settings['homepageMessageHtml'];
		}
		
		# Show current problems
		if ($this->userIsAdministrator) {
			$html .= "\n<h2>Unresolved calls <span>[admins only]</span></h2>";
			$count = $this->totalCalls ();
			$html .= "\n<p>Currently <a href=\"{$this->baseUrl}/calls/\" class=\"actions\"><strong>{$count} helpdesk calls</strong></a> outstanding.</p>";
		}
		
		# Show my current calls
		$html .= "\n<h2>My current/recent problems</h2>";
		$html .= $this->showCallRate (false);
		if (!$calls = $this->getCalls ()) {
			$html .= "\n<p>{$this->tick} You do not appear to have any logged {$this->settings['type']} problems outstanding" . ($this->userIsAdministrator ? '  that you submitted for yourself' : '') . '.</p>';
		} else {
			$html .= $this->renderCalls ($calls);
		}
		
		# Show the reporting screen
		$html .= "\n<h2>Report a new problem</h2>";
		$html .= $this->reportForm ();
		
		# Show the HTML
		echo $html;
	}
	
	
	# Admin screen
	public function admin ()
	{
		# Start the HTML
		$html = '';
		
		# Assemble the HTML
		$html .= "\n" . '<ul>';
		$html .= "\n\t" . "<li><a href=\"{$this->baseUrl}/statistics.html\">Call statistics</a></li>";
		$html .= "\n\t" . "<li><a href=\"{$this->baseUrl}/categories/\">Manage categories</a></li>";
		$html .= "\n" . '</ul>';
		
		# Show the HTML
		echo $html;
	}
	
	
	# Override FrontControllerApplication defaults
	public function administrators ($null = NULL, $boxClass = 'graybox', $showFields = array ('active' => 'Active?', 'receiveEmail' => 'Receive e-mail?', 'email' => 'E-mail', 'privilege' => 'privilege', 'name' => 'name', 'forename' => 'forename', 'surname' => 'surname', ))
	{
		# Expose the receiveHelpdeskEmail field
		return parent::administrators (NULL, 'graybox', $showFields = array ('active' => 'Active?', 'email' => 'E-mail', 'privilege' => 'privilege', 'name' => 'name', 'forename' => 'forename', 'surname' => 'surname', 'receiveHelpdeskEmail' => 'Receive helpdesk e-mail?'));
	}
	
	
	# Wrapper function to send the administrator an e-mail listing errors
	public /* public as per frontControllerApplication base class */ function throwError ($errors, $visible = true, $extraInfo = false)
	{
		# Start the HTML
		$html = '';
		
		# Ensure the errors are an array
		$errors = application::ensureArray ($errors);
		
		# Show the errors
		if ($visible) {
			foreach ($errors as $error) {
				$html .= "\n<p class=\"warning\">$error</p>";
			}
		}
		
		# Construct the message
		$introduction = 'The following ' . (count ($errors) == 1 ? 'problem has' : 'problems have') . ' been encountered:';
		$message = "\nDear webserver administrator,\n\n$introduction\n\n" . '- ' . implode ("\n\n- ", $errors) . ($extraInfo ? "\n\nExtra info:\n" . $extraInfo : '');
		#!# Ideally add mysql_error-style database error message to this
		
		# Send the mail
		if (application::sendAdministrativeAlert ($this->settings['administratorEmail'], 'Helpdesk', 'Helpdesk problem', $message)) {
			if ($visible) {
				$html .= "\n" . '<p class="warning">The server administrator has been informed about ' . (count ($errors) == 1 ? 'this error' : 'these errors') . '.</p>';
			}
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to allow the user to update their account details
	private function accountDetails ($firstRun = false)
	{
		# Introduction
		if (!$this->administrators) {
			echo "\n\n" . "<h2>Add first member of {$this->settings['type']} staff</h2>";
		}
		
		# Create the call reporting form
		$form = new form (array (
			'displayRestrictions' => false,
			'formCompleteText' => false,
			'escapeOutput' => true,
			'databaseConnection' => $this->databaseConnection,
			'cols' => $this->settings['cols'],
			'nullText' => false,
		));
		
		# Where specified, Force user creation, or administrator creation for a virgin installation
		if (!$this->administrators) {
			$form->heading ('p', "As the system currently has no members of {$this->settings['type']} staff, you are required to create one.");
		} else {
			$form->heading ('p', '<strong>As this is the first time you have used this system</strong>, you are required to enter your details for the purposes of customisation:');
		}
		
		# Databind the form
		$form->dataBinding (array (
			'database' => $this->settings['peopleDatabase'],
			'table' => 'people',
			'lookupFunction' => array ('database', 'lookup'),
			'lookupFunctionParameters' => array ($showKeys = false),
			'includeOnly' => array ('username', 'title', 'forename', 'surname', 'email', 'websiteEmail', "college__JOIN__{$this->settings['peopleDatabase']}__colleges__reserved"),
			'attributes' => array (
				'username' => array ('title' => 'Username', 'default' => $this->user, 'editable' => false),
				'email' => array ('title' => 'Cambridge e-mail address', 'default' => "{$this->user}@{$this->settings['emailDomain']}", 'editable' => false, 'type' => 'email'),
				'websiteEmail' => array ('title' => 'Preferred e-mail address', 'default' => "{$this->user}@{$this->settings['emailDomain']}", 'type' => 'email'),
			),
		));
		
		# Obtain the result
		if (!$result = $form->process ()) {return false;}
		
		# Insert the new person in the people database
		if (!$this->databaseConnection->insert ($this->settings['peopleDatabase'], 'people', $result)) {
			echo $this->throwError ('There was a problem inserting the new user into the database.', false, application::dumpData ($this->databaseConnection->error (), false, true));
			return false;
		}
		
		# Insert the new person in the administrators database if necessary
		if (!$this->administrators) {
			if (!$this->databaseConnection->insert ($this->settings['database'], 'administrators', array ('id' => $result['username']))) {
				echo $this->throwError ('There was a problem inserting the new administrator into the administators database.');
				return false;
			}
		}
		
		# Confirm success
		echo "\n<p>Many thanks, " . htmlspecialchars ($result['forename']) . ' - your personalisation details have now been successfully stored.</p>';
		echo "\n<p>You can now <a href=\"{$this->baseUrl}/\">return to the front page</a> and/or submit a helpdesk call.</p>";
		
		# Return success
		return true;
	}
	
	
	# Report problem screen
	public function report ()
	{
		# Show the call form
		echo $this->reportForm ();
	}
	
	
	# Report form
	private function reportForm ($editCall = false)
	{
		# Start the HTML
		$html  = '';
		
		# Create the call reporting form
		$this->loadJquery ();
		$form = new form (array (
			'formCompleteText' => false,
			'databaseConnection' => $this->databaseConnection,
			'div' => 'ultimateform horizontalonly helpdeskcall' . ($editCall && $this->userIsAdministrator ? ' editable' : ''),
			'cols' => $this->settings['cols'],
			'unsavedDataProtection' => true,
			'jQuery' => false,
			'uploadThumbnailWidth' => ($editCall ? 300 : 300),
			'uploadThumbnailHeight' => ($editCall ? 300 : 80),
			'displayRestrictions' => false,
		));
		
		# Determine which fields to display; admins should also be able to set the username
		$includeOnly = array ('subject', 'categoryId', 'building', 'room', 'details', 'imageFile', 'location', 'itnumber', );	// Fields like location and itnumber may be installation-specific but will be ignored if not present
		if ($this->userIsAdministrator) {array_unshift ($includeOnly, 'username');}
		if ($editCall) {array_unshift ($includeOnly, 'id');}
		
		# Get the data if in editing mode, even if most of it is not used
		if ($editCall) {
			$data = $this->databaseConnection->select ($this->settings['database'], $this->settings['table'], $data = array ('id' => $editCall));
			$editCall = $data[$editCall];
		}
		
		# In editing mode, instead show all fields for the administrator and pre-fill the data
		$exclude = array ();
		if ($this->userIsAdministrator && $editCall) {
			
			$includeOnly = false;
			$exclude = array ('timeOpened', 'timeCompleted', 'timeSubmitted', 'lastUpdated', );
			
			# Mark the call as having been opened
			if (empty ($editCall['timeOpened'])) {
				$result = $this->databaseConnection->update ($this->settings['database'], $this->settings['table'], $data = array ('timeOpened' => 'NOW()'), $conditions = array ('id' => $editCall['id']));
			}
			
			if (!$editCall['administratorId']) {
				$editCall['administratorId'] = $this->user;
			}
		}
		
		# Define form overloading attributes; some of these are used only in editing mode, but are otherwise ignored if in submission mode
		$attributes = array (
			'id' => array ('editable' => false),
			'subject' => array ('autofocus' => true, ),
			'details' => array ('editable' => (!$editCall || ($editCall && !$this->userIsAdministrator))),
			'location' => array ('disallow' => '(http|https)://', ),
			// 'administratorId' => array ('editable' => false, 'default' => $this->user),
			#!# Support for ultimateForm->select():regexp needed
			'currentStatus' => array ('default' => ($this->userIsAdministrator && $editCall ? ($editCall['currentStatus'] == 'submitted' ? '' : $editCall['currentStatus']) : ''), 'disallow' => ($this->userIsAdministrator && $editCall ? 'submitted' : '')),	// The currentStatus is deliberately wiped so that the admin remembers to change it
			'reply'			=> array (/*'required' => true,*/ 'description' => 'NOTE: making changes in this box will result in an e-mail being sent to the user.'),
			'imageFile' => array ('directory' => $_SERVER['DOCUMENT_ROOT'] . $this->baseUrl . '/images/', 'forcedFileName' => application::generatePassword (8, false), 'allowedExtensions' => array ('jpg', 'jpeg', 'png', 'gif'), 'lowercaseExtension' => true, 'required' => false, 'thumbnail' => true, 'flatten' => true, 'editable' => (!$editCall), 'previewLocationPrefix' => "{$this->baseUrl}/images/", 'thumbnailExpandable' => true, ),
		);
		if ($this->userIsAdministrator) {
			$attributes['username'] = array (
				'type' => 'select',
				'editable' => (!$editCall),
				'values' => $this->userList ($limitToActiveOnly = (!$editCall)),
				'description' => "This box is shown only to {$this->settings['type']} staff.",
			);
			if ($this->userIsAdministrator && !$editCall) {
				$attributes['username']['default'] = $this->user;
			}
		}
		
		# Get categories, ordered by list priority; for new calls, omit categories marked as hidden, and otherwise show all
		$categories = $this->getCategories ($omitHidden = (!$editCall));
		$attributes['categoryId'] = array ('values' => $categories);
		
		# Databind the form
		$form->dataBinding (array (
			'database' => $this->settings['database'],
			'table' => $this->settings['table'],
			'lookupFunction' => array ('database', 'lookup'),
			'lookupFunctionParameters' => array (NULL, false, true, false, $firstOnly = true),
			'includeOnly' => $includeOnly,
			'exclude' => $exclude,
			'attributes' => $attributes,
			'data' => $editCall,	// Will either be false (i.e. submission mode) or contain the data (i.e. editing mode)
			'simpleJoin' => true,
			'intelligence' => true,
		));
		if ($editCall) {
			$form->input (array (
				'name' => 'lastUpdated',
				'title' => 'Time of creation / last update',
				'editable' => false,
				'discard' => true,
				'default' => $editCall['lastUpdated'],
			));
		}
		
		# Return the result
		if (!$result = $form->process ($html)) {return $html;}
		
		# Add in the username
		if (!isSet ($result['username'])) {
			$result['username'] = $this->user;
		}
		
		# Log the call submission time
		if (!$editCall) {
			$result['timeSubmitted'] = 'NOW()';
		}
		
		# Close the call by adding the completion time if required
		if ($this->userIsAdministrator && $editCall && $result['currentStatus'] == 'completed') {
			$result['timeCompleted'] = 'NOW()';
		}
		
		# Add default values
		if (!$editCall) {
			$result['administratorId'] = '';
			$result['reply'] = '';
		}
		
		# Obtain the image filename
		$image = false;
		if (!$editCall) {
			$image = $result['imageFile'];
			unset ($result['imageFile']);
		}
		
		# Insert the new call
		$function = ($editCall ? 'update' : 'insert');
		if (!$this->databaseConnection->$function ($this->settings['database'], $this->settings['table'], $result, ($editCall ? array ('id' => $result['id']) : false), $emptyToNull = false)) {
			$html .= $this->throwError ('There was a problem ' . ($editCall ? 'updating the call' : 'logging the request') . '.');
			var_dump ($this->databaseConnection->error ());
			return $html;
		}
		
		# Confirm the call has been submitted
		$html .= "\n<p>" . ($editCall ? 'Many thanks; the call has been updated.' : '<strong>Many thanks; your details have been submitted.</strong> ' . ucfirst ($this->settings['type']) . ' staff will be in contact in due course.') . '</p>';
		if (!$editCall) {
			$html .= "\n<p>You can use the menu above to perform additional tasks or <a href=\"{$this->baseUrl}/logout.html\">log out</a> if you have finished.</p>";
		}
		
		# Determine the call number
		$callId = ($editCall ? $result['id'] : $this->databaseConnection->getLatestId ());
		
		# Move the image to its final URL
		if ($image) {
			$extension = pathinfo ($image, PATHINFO_EXTENSION);
			$imageFileOriginal = $_SERVER['DOCUMENT_ROOT'] . $this->baseUrl . '/images/' . $image;
			$imageFilenameNew = $callId . '-1' . '.' . $extension;		// e.g. 122-1.png for call #122
			$imageFileNew   = $_SERVER['DOCUMENT_ROOT'] . $this->baseUrl . '/images/' . $imageFilenameNew;
			rename ($imageFileOriginal, $imageFileNew);
			$this->databaseConnection->update ($this->settings['database'], $this->settings['table'], array ('imageFile' => $imageFilenameNew), array ('id' => $callId));
		}
		
		# Determine the call administration URL
		$callDetailsUrl = $_SERVER['_SITE_URL'] . $this->baseUrl . "/calls/{$callId}/";
		
		# If the administrator's reply has entered/changed, e-mail the user
		if ($editCall && $this->userIsAdministrator && ($editCall['reply'] != $result['reply'])) {
			$user = $this->userDetails ($result['username']);
			// $headers  = "From: Helpdesk <{$this->settings['administratorEmail']}>\n";
			// $headers .= "Reply-To: \"{$this->userDetails['forename']} {$this->userDetails['surname']}\" <{$this->userDetails['_preferredEmail']}>\n";
			$headers  = "From: \"{$this->userDetails['forename']} {$this->userDetails['surname']}\" <{$this->userDetails['_preferredEmail']}>\n";
			$headers .= 'Cc: ' . $this->getRecipients ($exclude = $this->user) . "\n";		// Copy the other administrators
			$date = $editCall['timeSubmitted'];
			$message  = "\n" . "On {$date}, {$user['_fullname']} wrote:\n" . application::emailQuoting ($editCall['details']) . "\n\n" . stripslashes ($result['reply']) . "\n\n\n" . $this->userDetails['forename'];
			$message = wordwrap ($message);
			$subject = "Re: [Helpdesk][$callId] " . $result['subject'];
			$to = "\"{$user['_fullname']}\" <{$user['_preferredEmail']}>";
			if (!application::utf8Mail ($to, $subject, $message, $headers)) {
				$html .= $this->throwError ("There was a problem sending an e-mail to alert the {$this->settings['type']} staff to a new call, but the call itself has been logged successfully.");
			}
			$html .= "\n<br /><p>The following e-mail has been sent:</p>\n<hr />";
			$html .= "\n<pre>To: {$user['_preferredEmail']}\n" . htmlspecialchars ($headers) . 'Subject: ' . htmlspecialchars ($subject) . "\n" . htmlspecialchars ($message)  . '</pre>';
			
		# If it is a new call, or the user's submission has changed, e-mail the admin
		} else if (!$editCall || ($editCall && ($editCall['details'] != $result['details']))) {
			
			$headers  = "From: Helpdesk <{$this->settings['administratorEmail']}>\n";
			$headers .= "Reply-To: \"{$this->userDetails['forename']} {$this->userDetails['surname']}\" <{$this->userDetails['_preferredEmail']}>\n";
			$message  = "\nA support call has been " . ($editCall ? 'updated' : 'submitted') . ". The details are online at:\n\n{$callDetailsUrl}\n\n" . stripslashes ($result['details']);
			$message .= "\n\n\n** Please respond to the user using the web interface rather than replying to this e-mail directly. **";
			if (!application::utf8Mail ($this->getRecipients (), ("[Helpdesk][{$callId}] " . $result['subject']) . ($editCall ? ' (updated)' : ''), wordwrap ($message), $headers)) {
				$html .= $this->throwError ("There was a problem sending an e-mail to alert the {$this->settings['type']} staff to the call, but the call details have been logged successfully.");
			}
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to determine the helpdesk e-mail recipients
	private function getRecipients ($exclude = false)
	{
		# Determine the recipients of the helpdesk call
		$recipients = array ();
		foreach ($this->administrators as $user => $attributes) {
			if ($user == $exclude) {continue;}
			if ($attributes['receiveHelpdeskEmail'] == 'Yes') {
				$recipients[] = $attributes['email'];
			}
		}
		if (!$recipients) {$recipients[] = $this->settings['administratorEmail'];}
		
		# Convert to comma-separated string
		$recipients = implode (', ', $recipients);
		
		# Return the recipients
		return $recipients;
	}
	
	
	# Function to show the number of calls
	private function showCallRate ($showHeading = true)
	{
		# Get the number of active calls
		$count = $this->totalCalls ();
		
		# Determine a description
		foreach ($this->busyness as $threshold => $description) {
			if ($count < $threshold) {break;}
		}
		
		# Build the HTML
		$html  = '';
		if ($showHeading) {$html .= "\n<h2>Calls outstanding</h2>";}
		$html .= "\n<p>Note: the current call rate is <strong>{$description}</strong> ({$count} calls).</p>";
		// $html .= "\n<p>Calls are prioritised, and submitting here will be the quickest way of having problems dealt with.</p>";
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to assemble a user list for administrators
	private function userList ($limitToActiveOnly = false)
	{
		# Get the users
		if (!$users = $this->databaseConnection->select ($this->settings['peopleDatabase'], 'people', ($limitToActiveOnly ? array ('active' => 'Y') : array ()), array ('username', 'title', 'forename', 'surname'))) {return false;}
		
		# Create the user list, starting with the current user then list by username
		$userList = array ($this->user => "Myself ({$this->user} - " . ($users[$this->user]['title'] ? "{$users[$this->user]['title']} " : '') . "{$users[$this->user]['forename']} {$users[$this->user]['surname']})");
		unset ($users[$this->user]);
		ksort ($users);
		foreach ($users as $user => $attributes) {
			$userList[$user] = "{$user} (" . ($attributes['title'] ? "{$attributes['title']} " : '') . "{$attributes['forename']} {$attributes['surname']})";
		}
		
		# Return the list of users
		return $userList;
	}
	
	
	# Function to get the list of categories
	private function getCategories ($omitHidden = false)
	{
		$conditions = array ();
		if ($omitHidden) {
			$conditions = array ('hide' => NULL);
		}
		
		# Get the problems, in list priority order
		$problems = $this->databaseConnection->selectPairs ($this->settings['database'], 'categories', $conditions, array ('id', 'category'), true, 'listpriority');
		
		# Return the list
		return $problems;
	}
	
	
	# Page to list all calls
	public function allcalls ()
	{
		# Get the list of calls
		$calls = $this->getCalls (false, $limitDate = false, false, $listMostRecentFirst = true);
		
		# Render the calls
		$html = $this->renderCalls ($calls, false, $limitDate = false, false);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to search for calls
	public function search ()
	{
		# Start the HTML
		$html = '';
		
		# Process the form or end
		if (!$result = $this->searchForm ()) {return false;}
		
		# Get the search term
		$searchTerm = $result['q'];
		
		#!# Currently not working
		# End if no submission
		if (!strlen ($searchTerm)) {
			$html = $this->recentSearches ();
			echo $html;
			return false;
		}
		
		# Log the search term
		$log = array ('search' => $searchTerm, 'username' => $this->user);
		$this->databaseConnection->insert ($this->settings['database'], 'searches', $log);
		
		# Determine if the call dates should be limited, i.e. if only showing unresolved items
		$limitDate = ($result['what'] == 'unresolved');
		
		# Get the calls
		if (!$calls = $this->getCalls (false, $limitDate, $searchTerm)) {
			$html = "\n<p>No matching calls were found.</p>";
			echo $html;
			return false;
		}
		
		# Render the calls
		$html = $this->renderCalls ($calls, false, $limitDate, $searchTerm);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to create the search form
	private function searchForm ($float = false)
	{
		# Cache _GET and remove the action, to avoid ultimateForm thinking the form has been submitted
		#!# This is a bit hacky, but is necessary because we set name=false in the ultimateForm constructor
		$get = $_GET;
		if (isSet ($_GET['action'])) {unset ($_GET['action']);}
		if (isSet ($_GET['item'])) {unset ($_GET['item']);}
		
		# Run the form module
		$form = new form (array (
			'displayRestrictions' => false,
			'get' => true,
			'name' => false,
			'nullText' => false,
			'div' => 'ultimateform search' . ($float ? ' right' : ''),
			'submitTo' => $this->baseUrl . '/' . $this->actions['search']['url'],
			'display'		=> 'template',
			'displayTemplate' => '{[[PROBLEMS]]}<p>{q} {what} {[[SUBMIT]]}</p>',
			'submitButtonText' => 'Search!',
			'submitButtonAccesskey' => false,
			'formCompleteText' => false,
			'requiredFieldIndicator' => false,
			'reappear' => true,
		));
		$form->input (array (
			'name'		=> 'q',
			'size'		=> ($float ? 20 : 40),
			'maxlength'	=> 255,
			'title'		=> 'Search',
			'required'	=> true,
			'placeholder' => 'Search calls',
			'autofocus'	=> ($this->action != 'report'),
		));
		$form->select (array (
			'name'		=> 'what',
			'title'		=> false,
			'values'	=> array ('unresolved' => 'Unresolved', 'all' => 'All'),
			'default'	=> 'unresolved',
			'required'	=> true,
			'nullRequiredDefault' => false,
		));
		$result = $form->process ();
		
		# Reinstate _GET
		$_GET = $get;
		unset ($get);
		
		# Return the result
		return $result;
	}
	
	
	# Function to show recent searches
	private function recentSearches ()
	{
		# Get the data
		$query = "SELECT
			DISTINCT
				search
			FROM {$this->settings['database']}.searches
			WHERE username = :user
			ORDER BY id DESC
			LIMIT {$this->settings['totalRecentSearches']}
		;";
		$preparedStatementValues = array ('username' => $this->user);
		if (!$data = $this->databaseConnection->getPairs ($query, false, $preparedStatementValues)) {return false;}
		
		# Assemble into a list
		$list = array ();
		$list[] = 'Your recent searches:';
		foreach ($data as $index => $search) {
			$list[] = "<a href=\"{$this->baseUrl}/search/?q=" . htmlspecialchars (urlencode ($search)) . '">' . htmlspecialchars ($search) . "</a>";
		}
		
		# Construct the HTML
		$html  = application::htmlUl ($list, 0, 'inline small');
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to show current calls of the user
	public function calls ()
	{
		# Get the calls
		$calls = $this->getCalls ();
		
		# Render the calls
		$html = $this->renderCalls ($calls);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to show a single call
	public function call ($callId)
	{
		# Start the HTML
		$html = '';
		
		# Ensure a supplied call number is numeric
		if (!$callId || !is_numeric ($callId)) {
			$html = "\n<p class=\"warning\">Error: The call number must be numeric.</p>";
			echo $html;
			return false;
		}
		
		# Get the calls
		if (!$calls = $this->getCalls ($callId)) {
			$html = "\n<p>The call you specified is either not valid, resolved a while ago, or you do not have rights to see it.</p>";
			echo $html;
			return false;
		}
		
		# Link back to all calls
		$html .= "\n<p><a href=\"{$this->baseUrl}/calls/" . ($this->userIsAdministrator ? "#call{$callId}" : '') . "\">&laquo; Return to the list of all calls</a></p>";
		
		# Render the call
		$html .= $this->renderCalls ($calls, $callId);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to show a list of jobs or a single job
	private function renderCalls ($calls, $callId = false, $limitDate = true, $searchTerm = false)
	{
		# Start the HTML
		$html = '';
		
		# End, with message, if no calls
		#!# If there are no current calls it is impossible to get previous calls because of the 'else' block here
		if (!$calls) {
			if ($this->userIsAdministrator) {
				$html = "\n<p>{$this->tick} There are no {$this->settings['type']} problems outstanding." . ($this->action == 'calls' ? ' CONGRATULATIONS! Enjoy it while it lasts ...' : '') . '</p>';
			} else {
				$html = "\n<p>{$this->tick} You do not appear to have any logged {$this->settings['type']} problems outstanding.</p>";
			}
			
			# Return the HTML
			return $html;
		}
		
		# Construct the HTML
		if (!$callId) {
			$html .= "\n\n" . (!$limitDate ? '<p class="helpdeskdescription">All items (' . number_format (count ($calls)) . ') ' . ($this->userIsAdministrator ? 'which have been submitted' : 'which you have submitted') . ' are listed below.' : '<p class="helpdeskdescription">Problems ' . ($this->userIsAdministrator ? '' : 'resolved within the last ' . $this->settings['completedJobExpiryDays'] . ' day' . (($this->settings['completedJobExpiryDays'] == 1) ? '' : 's') . ' or ') . 'unresolved (' . count ($calls) . ') are listed below, '. ($this->settings['listMostRecentFirst'] ? 'most recent' : 'earliest') . ' first.') . (strlen ($searchTerm) ? '' : " You can also: " . ($limitDate ? '<a href="' . $this->baseUrl . '/calls/all.html">include any older, resolved items also' : '<a href="' . $this->baseUrl . '/calls/">list only recent/unresolved items') . '</a>.') . '</p>';
		}
		
		# Determine whether this is the listing mode (i.e. calls page for admins only)
		$fullListing = ($this->userIsAdministrator && $this->action == 'calls');
		
		# Compile the panels
		$panels = array ();
		foreach ($calls as $id => $call) {
			
			# Start with the heading
			$panels[$id]  = "\n<h3>" . htmlspecialchars ("#{$id} [{$call['formattedDate']}]: {$call['subject']}" . (($fullListing || $this->action == 'search') ? " - {$call['user']}" : '')) . ($call['currentStatus'] == 'completed' ? ' <span class="resolved">[resolved]</span>' : '') . '</h3>';
			
			# Evaluate whether the call is editable; a call is editable if the currentStatus is not complete or the currentStatus is complete but the time difference is < $this->settings['completedJobExpiryDays'] days
			#!# Ideally this would somehow be done as merged with the above, but that might require two separate SQL lookups and merging/demerging them
			$userHasEditRights = (($call['currentStatus'] != 'completed') || (((strtotime (date ('Y-m-d')) - strtotime ($call['timeCompleted'])) < ($this->settings['completedJobExpiryDays'] * 24 * 60 * 60)) && ($call['currentStatus'] == 'completed')));
			
			# Append the call HTML to the main HTML
			$panels[$id] .= $this->callHtml ($call, $userHasEditRights, ($userHasEditRights && $callId), $minimised = true);
		}
		
		# If not the single call screen, do expansion of headings using jQuery
		if ($callId) {
			$html .= implode ($panels);
		} else {
			$html .= $this->callsExpandableUi ($panels);
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to render calls in an expandable UI
	private function callsExpandableUi ($panels)
	{
		# Determine the items to expand
		$expandState = ($this->userIsAdministrator ? $this->administrators[$this->user]['state'] : false);
		
		# Only save the state when on the full listing (not a partial listing) and the user is an administrator
		$saveState = ($this->userIsAdministrator && $this->action == 'calls');
		
		# Convert the panels to an expandable listing
		require_once ('jquery.php');
		$jQuery = new jQuery ($this->databaseConnection, "{$this->baseUrl}/data.html", $_SERVER['REMOTE_USER']);
		$jQuery->expandable ($panels, $expandState, $saveState);
		$html = $jQuery->getHtml ();
		
		# Stop jQuery being reloaded
		#!# Bit hacky this
		$this->jQueryLoaded = true;
		
		# Return the HTML
		return $html;
	}
	
	
	# Model function to get calls data
	private function getCalls ($callId = false, $limitDate = true, $searchTerm = false, $listMostRecentFirst = false)
	{
		# Start constraints
		$constraints = array ();
		$preparedStatementValues = array ();
		
		# For a call ID, limit to that call
		if ($callId) {
			$constraints[] = "{$this->settings['table']}.id = :id";
			$preparedStatementValues['id'] = $callId;
		}
		
		# Limit to user if required
		if (!$this->userIsAdministrator || $this->action == 'home') {
			$constraints[] = 'calls.username = :user';
			$preparedStatementValues['user'] = $this->user;
		}
		
		# Limit by date if required
		if ($limitDate && !$callId) {
			$expirySeconds = ($this->settings['completedJobExpiryDays'] *  24 * 60 * 60);
			$constraints[] = "(currentStatus != 'completed'" . (!$callId && $this->userIsAdministrator ? ')' : " OR ((UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(timeCompleted) < {$expirySeconds}) AND currentStatus = 'completed'))");
		}
		
		# Deal with searches, and insert the search into the database
		if (strlen ($searchTerm)) {
			$preparedStatementValues['searchTerm'] = '%' . $searchTerm . '%';
			$preparedStatementValues['searchId'] = $searchTerm;
			$constraints[] = "
			(	   subject LIKE :searchTerm
				OR calls.id = :searchId
				OR calls.username LIKE :searchTerm
				OR people.forename LIKE :searchTerm
				OR people.surname LIKE :searchTerm
				/* OR location LIKE :searchTerm */
				OR category LIKE :searchTerm
				/* OR itnumber LIKE :searchTerm */
				OR details LIKE :searchTerm
				OR reply LIKE :searchTerm
				OR internalNotes LIKE :searchTerm
			)";
		}
		
		# Determine whether split building/room fields are present
		$fields = $this->databaseConnection->getFields ($this->settings['database'], $this->settings['table']);
		$fields = array_keys ($fields);
		$locationFields = '';
		if (in_array ('building', $fields) && in_array ('room', $fields)) {
			$locationFields = "{$this->settings['table']}.building, {$this->settings['table']}.room,";
		}
		
		# Assemble the SQL query
		$query = "SELECT
				{$this->settings['table']}.id,
				subject,
				currentStatus,
				timeSubmitted,
				timeCompleted,
				category,
				{$locationFields}
				details,
				reply,
				internalNotes,
				CONCAT(people.forename,' ',people.surname,' <',people.username,'>') as user,
				CONCAT(DATE_FORMAT(CAST(timeSubmitted AS DATE), '%e/'), SUBSTRING(DATE_FORMAT(CAST(timeSubmitted AS DATE), '%M'), 1, 3), DATE_FORMAT(CAST(timeSubmitted AS DATE), '/%y')) AS formattedDate
			FROM {$this->settings['table']}
			LEFT OUTER JOIN {$this->settings['database']}.categories ON {$this->settings['table']}.categoryId = categories.id
			LEFT OUTER JOIN {$this->settings['peopleDatabase']}.people ON {$this->settings['table']}.username = people.username
			" . ($constraints ? 'WHERE ' . implode (' AND ', $constraints) : '') . '
		;';
		
		# End the SQL query by specifying the order
		$listMostRecentFirst = ($listMostRecentFirst || $this->settings['listMostRecentFirst'] && !$this->userIsAdministrator);
		$query .= ' ORDER BY id' . ($listMostRecentFirst ? ' DESC' : '') . ';';
		
		# Execute the query and obtain an array of problems from it; if there are none, state so
		$calls = $this->databaseConnection->getData ($query, "{$this->settings['database']}.{$this->settings['table']}", true, $preparedStatementValues);
		
		# Return the data
		return $calls;
	}
	
	
	# Function to save the state of the heading expansion
	public function data ()
	{
		# Delegate to jQuery
		require_once ('jquery.php');
		$jQuery = new jQuery ($this->databaseConnection, "{$this->baseUrl}/data.html", $_SERVER['REMOTE_USER']);
		return $jQuery->expandable_data ($this->settings['database'], 'administrators', 'id');
	}
	
	
	# Function to load jQuery
	private function loadJquery ()
	{
		# Load jQuery (once only)
		if (!$this->jQueryLoaded) {
			echo "\n" . '<script type="text/javascript" src="//code.jquery.com/jquery.min.js"></script>';
			$this->jQueryLoaded = true;
		}
	}
	
	
	# Function to produce HTML from a specified call
	private function callHtml ($call, $userHasEditRights, $editMode = false, $minimised = false)
	{
		# Start the HTML
		$html  = '';
		if ($call['currentStatus'] == 'completed') {
			$html .= '<p class="warning">Note: this call below has been marked as resolved.</p>';
		}
		
		# If editing is required, hand off to the call submission method
		if ($editMode) {
			$html .= $this->reportForm ($call['id']);
			return $html;
		}
		
		# Format the timestamp
		require_once ('timedate.php');
		$call['timeSubmitted'] = timedate::convertTimestamp ($call['timeSubmitted']);
		
		# Remove double line-breaks in the reply
		$call['reply'] = str_replace ("\r\n", "\n", $call['reply']);
		while (substr_count ($call['reply'], "\n\n\n")) {
			$call['reply'] = str_replace ("\n\n\n", "\n\n", $call['reply']);
		}
		
		# Convert HTML entities in array
		foreach ($call as $key => $value) {
			$call[$key] = nl2br (htmlspecialchars (trim ($value)));
		}
		
		# Substitute the job status
		$call['currentStatus'] = ucfirst ($call['currentStatus']) . ' (' . str_replace ('%type', $this->settings['type'], $this->currentStatusDefinitions[$call['currentStatus']]) . ')';
		$call['reply'] = ($call['reply'] ? $call['reply'] : '<em class="comment">None as yet</em>');
		
		# Construct the table data
		$table = array ();
		if (!$minimised) {$table['Call number'] = $call['id'];}
		if (!$minimised) {$table['Submitted on'] = $call['timeSubmitted'];}
		if (!$minimised) {$table['Submitted by'] = $call['user'];}
		$table['details'] = application::makeClickableLinks ($call['details']);
		if (isSet ($call['building'])) {$table['building'] = $call['building'];}
		if (isSet ($call['room'])) {$table['room'] = $call['room'];}
		$table['category'] = $call['category'];
		$table['currentStatus'] =  $call['currentStatus'];
		$table['reply'] = application::makeClickableLinks ($call['reply']);
		
		# Set the heading labels
		$headings = array (
			'building'		=> 'Building:',
			'room'			=> 'Room:',
			'details'		=> ($userHasEditRights ? "<a class=\"actions\" href=\"{$this->baseUrl}/calls/{$call['id']}/\"><img src=\"/images/icons/pencil.png\" alt=\"\" class=\"icon\" /> <strong>Edit</strong></a>" : ''),
			'category'		=> 'Category:',
			'currentStatus'	=> 'Current status:',
			'reply'			=> "Reply from {$this->settings['type']} staff:",
		);
		
		# Compile the table
		$html .= application::htmlTableKeyed ($table, $headings, false, 'lines helpdeskcall regulated', $allowHtml = true, $showColons = false, $addRowKeyClasses = true);
		
		# Return the HTML
		return $html;
	}
	
	
	# Facility to amend the problem types
	public function categories ($item = false)
	{
		# Start the HTML
		$html = '';
		
		# Add introduction
		$html .= "\n<p>In this section, you can manage the available categories.</p>";
		$html .= "\n<p>You should <strong>not</strong> delete categories in use, as this will disrupt existing submitted calls. Instead, mark them as hidden.</p>";
		$html .= "\n<br />";
		
		# Add the editing table
		$sinenomineExtraSettings = array (
			'hideSearchBox' => true,
			'fieldFiltering' => false,
			'hideExport' => true,
			'int1ToCheckbox' => true,
			'orderby' => array (__FUNCTION__ => 'listpriority'),
		);
		$html .= $this->editingTable (__FUNCTION__, array (), 'graybox lines', false, $sinenomineExtraSettings);
		
		# Show the HTML
		echo $html;
	}
	
	
	# Function to get total calls outstanding
	private function totalCalls ($limitToOutstanding = true)
	{
		# Get the count
		$restrictionSql = "WHERE currentStatus != 'completed' /* AND currentStatus != 'deferred' */";
		$count = $this->databaseConnection->getTotal ($this->settings['database'], $this->settings['table'], ($limitToOutstanding ? $restrictionSql : false));
		
		# Return the count
		return $count;
	}
	
	
	# Statistics screen
	public function statistics ()
	{
		# Start the HTML
		$html  = '';
		
		# Calls logged
		$html .= "\n<p>Total calls logged: <strong>" . $this->totalCalls (false) . "</strong></p>";
		
		# Calls outstanding
		$html .= "\n<p>Total calls outstanding (not completed): <strong>" . $this->totalCalls () . "</strong></p>";
		
		# Calls per working day
		$query = "SELECT
			(ROUND(COUNT(id) / ((UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(MIN(timeSubmitted))) / ((7/5) * 24 * 60 * 60)),2)) AS count
			FROM {$this->settings['database']}.{$this->settings['table']}
		;";
		if ($data = $this->databaseConnection->getOne ($query)) {
			$html .= "\n<p>Total calls logged per working day: <strong>{$data['count']}</strong></p>";
		}
		
		# Problem areas
		$query = "SELECT {$this->settings['database']}.categories.category as 'Problem area', COUNT(*) as Total
			FROM {$this->settings['database']}.{$this->settings['table']},{$this->settings['database']}.categories
			WHERE {$this->settings['database']}.{$this->settings['table']}.categoryId = {$this->settings['database']}.categories.id
			GROUP BY category
			ORDER BY Total DESC,category
		;";
		if ($data = $this->databaseConnection->getData ($query)) {
			$html .= "\n<h3>Problem areas:</h3>";
			$html .= "\n" . application::htmlTable ($data, array (), 'lines compressed', false);
		}
		
		# Average response time
		// $query = "SELECT (ROUND((AVG(UNIX_TIMESTAMP(timeCompleted) - UNIX_TIMESTAMP(timeSubmitted))) / ((7/5) * 24 * 60 * 60),1)) as workingdays FROM {$this->settings['database']}.{$this->settings['table']} WHERE ((UNIX_TIMESTAMP(timeCompleted) >= UNIX_TIMESTAMP(timeSubmitted)) AND (((UNIX_TIMESTAMP(timeCompleted) - UNIX_TIMESTAMP(timeSubmitted)) / ((7/5) * 24 * 60 * 60)) < 21));";
		// $html .= "\n<p>Average duration to resolve calls: <strong>{$data['workingdays']} working days</strong><br /><span class=\"comment\">Note: Average resolution duration is not necessary reliable, due to incomplete legacy data and a small number of long-standing calls which skew the data.</span></p>";
		$query = "SELECT ROUND(((UNIX_TIMESTAMP(timeCompleted) - UNIX_TIMESTAMP(timeSubmitted)) / ((7/5) * 24 * 60 * 60)),0) as 'Working days', COUNT(id) as 'Number of calls' FROM {$this->settings['database']}.{$this->settings['table']} WHERE (UNIX_TIMESTAMP(timeCompleted) >= UNIX_TIMESTAMP(timeSubmitted)) GROUP BY 'Working days' ORDER BY 'Working days';";
		$data = $this->databaseConnection->getData ($query);
		$html .= "\n<h3>(Working) days to resolve calls:</h3>";
		$html .= "\n" . application::htmlTable ($data, array (), 'lines compressed', false);
		/*
		foreach ($data as $index => $attributes) {
			$barchart[$attributes['Working days']] = $attributes['Number of calls'];
		}
		$html .= $this->barchart ($barchart);
		*/
		
		# Show the HTML
		echo $html;
	}
	
	
	/*
	private function barchart ($data)
	{
		$html  = '<table class="lines" width="100%">';
		$max = array_sum ($data);
		
		foreach ($data as $key => $value) {
			
			$percent = ($value / $max) * 100;
			
			$html .= "	<tr>
				<td>{$key}</td>
				<td>{$value}</td>
				<td width=\"99%\">
				<table width=\"100%\">
						<tr><td width=\"" . ceil ($percent) . "%\" bgcolor=\"#aaa\">&nbsp;</td><td></td></tr>
				</table>
				</td>
				</tr>
			";
		}
		$html .= '</table>';
		return $html;
	}
	*/
	
	
	# API call for dashboard
	public function apiCall_dashboard ($username = NULL)
	{
		# Start the HTML
		$html = '';
		
		# State that the service is enabled
		$data['enabled'] = true;
		
		# Ensure a username is supplied
		if (!$username) {
			$data['error'] = 'No username was supplied.';
			return $data;
		}
		
		# Define description
		$data['descriptionHtml'] = "<p>The online helpdesk system enables you to requesting help with {$this->settings['type']} problems.</p>";
		
		# Add key links
		$data['links']["{$this->baseUrl}/report.html"] = '{icon:add} Report a problem';
		
		# Add admin links
		if (isSet ($this->administrators[$username])) {
			$count = $this->totalCalls ();
			$html .= "\n<p>Currently <a href=\"{$this->baseUrl}/calls/\" class=\"actions\"><strong>{$count} helpdesk calls</strong></a> outstanding.</p>";
		}
		
		# List calls
		#!# Not yet implemented
		
		# Register the HTML
		$data['html'] = $html;
		
		# Return the data
		return $data;
	}
}

?>
