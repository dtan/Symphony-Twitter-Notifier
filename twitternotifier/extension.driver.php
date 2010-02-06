<?php

require_once TOOLKIT . '/class.sectionmanager.php';
require_once TOOLKIT . '/class.entrymanager.php';


/***
 * Twitter Notifier
 *
 * Provides a mechanism for Symphony to post a Twitter update every time a new piece of content is posted.
 *
 * @package Symphony
 * @category Extensions
 * @author Daniel Wilhelm II Murdoch <wilhelm.murdoch@gmail.com>
 * @license MIT License <http://www.opensource.org/licenses/mit-license.php>
 * @copyright Copyright (c) 2009, Daniel Wilhelm II Murdoch
 * @link http://www.thedrunkenepic.com
 * @since Build 1.0.0 Alpha
 ***/
class Extension_TwitterNotifier extends Extension
{
   // ! Accessor Method

   /**
	* Returns extension-specific meta data.
	*
	* @param None
	* @author Daniel Wilhelm II Murdoch <wilhelm.murdoch@gmail.com>
	* @since Build 1.0.0 Alpha
	* @access Public
	* @return Array
	*/
	public function about()
	{
		return array
		(
			'name'         => 'Twitter Notifier',
			'version'      => '1.000',
			'release-date' => '2009-03-17',
			'author'       => array
			(
				'name'    => 'Wilhelm Murdoch',
				'website' => 'http://www.thedrunkenepic.com/',
				'email'   => 'wilhelm.murdoch@gmail.com'
			)
		);

		return true;
	}


   // ! Executor Method

   /**
	* Uninstalls this extension's table structure.
	*
	* @param None
	* @author Daniel Wilhelm II Murdoch <wilhelm.murdoch@gmail.com>
	* @since Build 1.0.0 Alpha
	* @access Public
	* @return True
	*/
	public function uninstall()
	{
		$prefix = $this->_Parent->Configuration->get('tbl_prefix', 'database');

		$this->_Parent->Database->query("DROP TABLE `{$prefix}twitter_accounts`");

		return true;
	}


   // ! Executor Method

   /**
	* Installs this extension's table structure.
	*
	* @param None
	* @author Daniel Wilhelm II Murdoch <wilhelm.murdoch@gmail.com>
	* @since Build 1.0.0 Alpha
	* @access Public
	* @return True
	*/
	public function install()
	{
		$prefix = $this->_Parent->Configuration->get('tbl_prefix', 'database');

		$this->_Parent->Database->query
		("
			CREATE TABLE IF NOT EXISTS `{$prefix}twitter_accounts` (
				`id` int(10) unsigned NOT NULL auto_increment,
				`account` varchar(100) NOT NULL,
				`password` varchar(100) NOT NULL,
				`section` int(10) unsigned NOT NULL,
				`url` varchar(250) NOT NULL,
				`date_last_sent` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
				`path` varchar(250) NOT NULL,
				PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1;
		");

		return true;
	}


   // ! Accessor Method

   /**
	* Returns an array of subscribed delegates.
	*
	* @param None
	* @author Daniel Wilhelm II Murdoch <wilhelm.murdoch@gmail.com>
	* @since Build 1.0.0 Alpha
	* @access Public
	* @return Array
	*/
	public function getSubscribedDelegates()
	{
		return array
		(
			array
			(
				'page'     => '/administration/',
				'delegate' => 'NavigationPreRender',
				'callback' => 'addToNavigation'
			),
			array
			(
				'page'     => '/publish/new/',
				'delegate' => 'EntryPostCreate',
				'callback' => 'sendTwitterNotification'
			)
		);
	}


   // ! Executor Method

   /**
	* Adds a 'Twitter' navigation entry under 'Settings'.
	*
	* @param Object $context Current context passed by reference.
	* @author Daniel Wilhelm II Murdoch <wilhelm.murdoch@gmail.com>
	* @since Build 1.0.0 Alpha
	* @access Public
	* @return True
	*/
	public function addToNavigation(&$context)
	{
		$context['navigation'][300]['children'][] = array
		(
			'link' => '/extension/twitternotifier/accounts/',
			'name' => 'Twitter'
		);

		return true;
	}


   // ! Executor Method

   /**
	* Sends the Twitter notification to all accounts monitoring the
	* posted section. This method iterates through all section-subscribed
	* accounts, uses XPath to determine the unique ID of the content,
	* builds the URL to the content, shortens it and then posts it to the
	* corresponding account.
	*
	* @param Object $context Current context passed by reference.
	* @author Daniel Wilhelm II Murdoch <wilhelm.murdoch@gmail.com>
	* @since Build 1.0.0 Alpha
	* @access Public
	* @return True
	*/
	public function sendTwitterNotification(&$context)
	{
		$prefix = $this->_Parent->Configuration->get('tbl_prefix', 'database');
		$XPath  = new DOMXPath($this->getEntry($context['entry']->_fields['id']));

		$accounts = $this->_Parent->Database->fetch
		("
			SELECT
				id,
				account,
				password,
				url,
				path
			FROM {$prefix}twitter_accounts
			WHERE section = " . (int) $context['section']->_data['id'] ."
		");

		foreach($accounts as $account)
		{
			$Results = $XPath->query($account['path']);

			if($identifier = trim($Results->item(0)->nodeValue))
			{
				$url_to_shorten = str_replace('{entry_id}', $identifier, $account['url']);

				// Shorten the URL to the new entry:

				$ch = curl_init("http://is.gd/api.php?longurl={$url_to_shorten}");

				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_VERBOSE, true);
				curl_setopt($ch, CURLOPT_NOBODY, false);
				curl_setopt($ch, CURLOPT_HEADER, false);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

				$link_to_entry = curl_exec($ch);
				curl_close($ch);


				// Notify Twitter:

				$headers = array
				(
					'Expect: ',
					'X-Twitter-Client: Twitter Notifier',
					'X-Twitter-Client-Version: 1.0.0 Alpha',
					'X-Twitter-Client-URL: http://www.thedrunkenepic.com/'
				);

				$message = 'A new entry from ' . $this->_Parent->Configuration->get('sitename', 'general') . "! {$link_to_entry}";

				$url = 'http://twitter.com/statuses/update.xml?status=' . urlencode(stripslashes(urldecode($message)));

				$ch = curl_init($url);

				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, array('source' => 'Symphony CMS'));
				curl_setopt($ch, CURLOPT_USERPWD, "{$account['account']}:{$account['password']}");
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_VERBOSE, true);
				curl_setopt($ch, CURLOPT_NOBODY, false);
				curl_setopt($ch, CURLOPT_HEADER, false);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

				$response = curl_exec($ch);
				curl_close($ch);

				$this->_Parent->Database->query("UPDATE {$prefix}twitter_accounts SET date_last_sent = NOW() WHERE id = {$account['id']}");
			}
		}

		return true;
	}


   // ! Executor Method

   /**
	* Begins building the XML DOM for the current entry.
	*
	* @param Integer $id The id of the current entry.
	* @author Daniel Wilhelm II Murdoch <wilhelm.murdoch@gmail.com>
	* @since Build 1.0.0 Alpha
	* @access Public
	* @return Object
	*/
	protected function getEntry($id)
	{
		$EntryXml = new XMLElement('entry');
		$this->getDataEntry($id, $EntryXml);

		$ReturnXml = new XMLElement('root');
		$ReturnXml->appendChild($EntryXml);

		$Dom = new DOMDocument();
		$Dom->loadXML($ReturnXml->generate(true));

		return $Dom;
	}


   // ! Executor Method

   /**
	* Fetches all section fields and corresponding data for the
	* current entry.
	*
	* @param Integer $id The id of the current entry.
	* @param Object $EntryXml The current XML document.
	* @author Daniel Wilhelm II Murdoch <wilhelm.murdoch@gmail.com>
	* @since Build 1.0.0 Alpha
	* @access Public
	* @return True
	*/
	protected function getDataEntry($id, $EntryXml)
	{
		$EntryManager = new EntryManager($this->_Parent);
		$EntryManager->setFetchSorting('id', 'ASC');

		$entries = $EntryManager->fetch($id, null, null, null, null, null, false, true);

		$Entry = @$entries[0];

		$EntryXml->setAttribute('id', $id);

		foreach($Entry->fetchAllAssociatedEntryCounts() as $section => $count)
		{
			$handle = $this->_Parent->Database->fetchVar('handle', 0, "SELECT handle FROM tbl_sections WHERE id = '{$section}' LIMIT 1");

			$EntryXml->setAttribute($handle, (string) $count);
		}

		foreach($Entry->getData() as $field_id => $values)
		{
			$field =& $EntryManager->fieldManager->fetch($field_id);
			$field->appendFormattedElement($EntryXml, $values, false);
		}

		return true;
	}
}