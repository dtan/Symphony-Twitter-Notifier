<?php

require_once TOOLKIT . '/class.administrationpage.php';


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
class contentExtensionTwitterNotifierAccounts extends AdministrationPage
{
   /**
	* A convenience property that stores the table prefix.
	* @access Protected
	* @var String
	*/
	protected $prefix;


   // ! Constructor Method

   /**
	* Instantiates class and defines instance variables.
	*
	* @param Object $Parent Instance of the parent passed by reference.
	* @author Daniel Wilhelm II Murdoch <wilhelm.murdoch@gmail.com>
	* @since Build 1.0.0 Alpha
	* @access Public
	* @return Void
	*/
	public function __construct(&$Parent)
	{
		parent::__construct($Parent);

		$this->prefix = $this->_Parent->Configuration->get('tbl_prefix', 'database');
	}


   // ! Executor Method

   /**
	* Displays the form that allows the use to add a new Twitter account. This
	* method also inserts the new record into the database after some validation.
	*
	* @param None
	* @author Daniel Wilhelm II Murdoch <wilhelm.murdoch@gmail.com>
	* @since Build 1.0.0 Alpha
	* @access Public
	* @return True
	*/
	public function __viewNew()
	{
		$this->setPageType('form');
		$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Twitter Accounts'))));
		$this->appendSubheading(__('Untitled'));

		if($_POST)
		{
			if(false == trim($_POST['fields']['account']))
			{
				$errors['account'] = 'You need to specify your Twitter account name!';
			}

			if(false == trim($_POST['fields']['password']))
			{
				$errors['password'] = 'Gotta have your password to access your Twitter account!';
			}

			if(false == trim($_POST['fields']['url']))
			{
				$errors['url'] = 'Need a url to point to your new content!';
			}

			if(false == preg_match('#\{entry_id\}#i', $_POST['fields']['url']))
			{
				$errors['url'] = 'You need to use {entry_id} to identify your matched content!';
			}

			if(false == trim($_POST['fields']['path']))
			{
				$errors['path'] = 'How am I supposed to find my way to your content without a path?';
			}

			if(false == $errors)
			{
				$this->_Parent->Database->insert($_POST['fields'], "{$this->prefix}twitter_accounts");

				redirect(URL . '/symphony/extension/twitternotifier/accounts/');
			}
		}

		$fieldset = new XMLElement('fieldset');
		$fieldset->setAttribute('class', 'settings');
		$fieldset->appendChild(new XMLElement('legend', 'Account Details'));


		if($fields['id'])
		{
			$fieldset->appendChild(Widget::Input("fields[id]", $fields['id'], 'hidden'));
		}


		$label = Widget::Label('Account');
		$label->appendChild(Widget::Input('fields[account]', General::sanitize($fields['account'])));

		if(isset($errors['account']))
		{
			$label = Widget::wrapFormElementWithError($label, $errors['account']);
		}

		$fieldset->appendChild($label);


		$label = Widget::Label('Password');
		$label->appendChild(Widget::Input('fields[password]', General::sanitize($fields['password'])));

		if(isset($errors['password']))
		{
			$label = Widget::wrapFormElementWithError($label, $errors['password']);
		}

		$fieldset->appendChild($label);

		$this->Form->appendChild($fieldset);

		$fieldset = new XMLElement('fieldset');
		$fieldset->setAttribute('class', 'settings');
		$fieldset->appendChild(new XMLElement('legend', 'Posting Details'));


		$label = Widget::Label('Section');

		$SectionManager = new SectionManager($this->_Parent);

		$options = array();

		foreach($SectionManager->fetch(NULL, 'ASC', 'sortorder') as $section)
		{
			$options[] = array($section->get('id'), ($fields['section'] == $section->get('id')), $section->get('name'));
		}

		$label->appendChild(Widget::Select('fields[section]', $options, array('id' => 'section')));


		$p = new XMLElement('p', __('Select the section to monitor.'));
		$p->setAttribute('class', 'help');

		if(isset($this->_errors['account']))
		{
			$label = Widget::wrapFormElementWithError($label, $this->_errors['account']);
		}

		$fieldset->appendChild($label);
		$fieldset->appendChild($p);


		$label = Widget::Label('Path');
		$label->appendChild(Widget::Input('fields[path]', General::sanitize($fields['path'])));

		$p = new XMLElement('p', __('Use XPath here to find the identifier you use to access your content. <br />Example: //root/entry/name[@handle]'));
		$p->setAttribute('class', 'help');

		if(isset($errors['path']))
		{
			$label = Widget::wrapFormElementWithError($label, $errors['path']);
		}

		$fieldset->appendChild($label);
		$fieldset->appendChild($p);


		$label = Widget::Label('Url');
		$label->appendChild(Widget::Input('fields[url]', General::sanitize($fields['url'])));

		$p = new XMLElement('p', __('Enter the full URL to access your section content. Use <strong>{entry_id}</strong> where you would place the unique identifier for your content.<br />Example: http://www.thedrunkenepic.com/articles/<strong>{entry_id}</strong>/'));
		$p->setAttribute('class', 'help');

		if(isset($errors['url']))
		{
			$label = Widget::wrapFormElementWithError($label, $errors['url']);
		}


		$fieldset->appendChild($label);
		$fieldset->appendChild($p);

		$this->Form->appendChild($fieldset);

		$div = new XMLElement('div');
		$div->setAttribute('class', 'actions');
		$div->appendChild(Widget::Input('action[save]', __('Add Twitter Account'), 'submit', array('accesskey' => 's')));

		$this->Form->appendChild($div);

		return true;
	}


   // ! Executor Method

   /**
	* Removes the select Twitter accounts from the database.
	*
	* @param None
	* @author Daniel Wilhelm II Murdoch <wilhelm.murdoch@gmail.com>
	* @since Build 1.0.0 Alpha
	* @access Public
	* @return True
	*/
	public function __actionIndex()
	{
		$prefix  = $this->_Parent->Configuration->get('tbl_prefix', 'database');
		$checked = @array_keys($_POST['items']);

		if(is_array($checked))
		{
			switch ($_POST['with-selected'])
			{
				case 'delete':
					foreach ($checked as $id)
					{
						$this->_Parent->Database->query("DELETE FROM {$this->prefix}twitter_accounts WHERE id = {$id}");
					}

					redirect(URL . '/symphony/extension/twitternotifier/accounts/');
					break;
			}
		}

		return true;
	}


   // ! Accessor Method

   /**
	* Simply displays a list of all Twitter accounts.
	*
	* @param None
	* @author Daniel Wilhelm II Murdoch <wilhelm.murdoch@gmail.com>
	* @since Build 1.0.0 Alpha
	* @access Public
	* @return True
	*/
	public function __viewIndex()
	{
		$this->setPageType('table');
		$this->setTitle('Symphony &ndash; Twitter Accounts');
		$this->appendSubheading(__('Twitter Accounts'), Widget::Anchor(__('Create New'), $this->_Parent->getCurrentPageURL().'new/', __('Add an account'), 'create button'));

		$thead = array
		(
			array('Account',   'col'),
			array('Section',   'col'),
			array('Last Sent', 'col')
		);

		$tbody = array();
		$sections = array();

		$SectionManager = new SectionManager($this->_Parent);

		foreach($SectionManager->fetch(NULL, 'ASC', 'sortorder') as $section)
		{
			$sections[$section->get('id')] = $section->get('name');
		}

		$accounts = $this->_Parent->Database->fetch
		("
			SELECT
				id,
				account,
				section,
				date_last_sent
			FROM {$this->prefix}twitter_accounts
		");

		if(false == $accounts)
		{
			$tbody = array
			(
				Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', null, count($thead))))
			);
		}
		else
		{
			foreach($accounts as $account)
			{
				$DateColumn = Widget::TableData(General::sanitize(DateTimeObj::get(__SYM_DATETIME_FORMAT__, strtotime($account['date_last_sent']))));
				$DateColumn->appendChild(Widget::Input("items[{$account['id']}]", null, 'checkbox'));

				$tbody[] = Widget::TableRow
				(
					array
					(
						Widget::TableData(General::sanitize($account['account'])),
						Widget::TableData(General::sanitize($sections[$account['section']])),
						$DateColumn
					)
				);
			}
		}

		$table = Widget::Table
		(
			Widget::TableHead($thead),
			null,
			Widget::TableBody($tbody)
		);

		$this->Form->appendChild($table);

		$actions = new XMLElement('div');
		$actions->setAttribute('class', 'actions');

		$options = array
		(
			array(null, false, 'With Selected...'),
			array('delete', false, 'Delete')
		);

		$actions->appendChild(Widget::Select('with-selected', $options));
		$actions->appendChild(Widget::Input('action[apply]', 'Apply', 'submit'));

		$this->Form->appendChild($actions);

		return true;
	}
}

?>