<?php
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.2.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

if (!class_exists('vB_DataManager'))
{
	exit;
}

/**
* Class to do data save/delete operations for PT issue assignments.
*
* @package 		vBulletin Project Tools
* @author		$Author$
* @since		$Date$
* @version		$Rev$
* @copyright 	http://www.vbulletin.org/open_source_license_agreement.php
*/
class vB_DataManager_Pt_MagicSelect extends vB_DataManager
{
	/**
	* Array of recognized/required fields and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'magicselectid'			=> array(TYPE_UINT,	REQ_INCR),
		'varname'				=> array(TYPE_STR,	REQ_NO),
		'text'					=> array(TYPE_STR,	REQ_NO),
		'displayorder'			=> array(TYPE_UINT,	REQ_NO),
		'active'				=> array(TYPE_BOOL,	REQ_NO),
		'projects'				=> array(TYPE_STR,	REQ_NO),
		'htmlcode'				=> array(TYPE_STR,	REQ_NO),
		'fetchcode'				=> array(TYPE_STR,	REQ_NO),
		'savecode'				=> array(TYPE_STR,	REQ_NO)
	);

	/**
	* Information and options that may be specified for this DM
	*
	* @var	array
	*/
	var $info = array();

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'pt_magicselect';

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('magicselectid = %1$d', 'magicselectid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Pt_MagicSelect(&$registry, $errtype = ERRTYPE_CP)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('pt_magicselect_start')) ? eval($hook) : false;
	}

	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (empty($this->info['text']))
		{
			$this->error('missing_text');
			return false;
		}

		if (!$this->fetch_field('projects'))
		{
			$this->error('no_selected_project');
			return false;
		}

		$protectedvalues = array(
			'issueid',
			'projectid',
			'issuestatusid',
			'issuetypeid',
			'title',
			'summary',
			'submituserid',
			'submitusername',
			'submitdate',
			'appliesversionid',
			'isaddressed',
			'addressedversionid',
			'priority',
			'visible',
			'lastpost',
			'lastactivity',
			'lastpostuserid',
			'lastpostusername',
			'firstnoteid',
			'lastnoteid',
			'attachcount',
			'pendingpetitions',
			'replycount',
			'votepositive',
			'votenegative',
			'projectcategoryid',
			'assignedusers',
			'privatecount',
			'state',
			'milestoneid'
		);

		if (in_array($this->fetch_field('varname'), $protectedvalues))
		{
			$this->error('magic_select_varname_cant_be_used');
			return false;
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('pt_magicselect_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		// create automatically the corresponding column in pt_issue table
		$db =& $this->registry->db;

		// Hide query error on magic select edit
		$db->hide_errors();
		$db->query_write("
			ALTER TABLE " . TABLE_PREFIX . "pt_issue
			ADD " . $this->fetch_field('varname') . " INT(10) UNSIGNED NOT NULL DEFAULT '0'
		");
		$db->show_errors();

		// replace (master) phrases entry
		require_once(DIR . '/includes/adminfunctions.php');
		$full_product_info = fetch_product_list(true);
		$product_version = $full_product_info['vbprojecttools']['version'];

		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "phrase
				(languageid, fieldname, varname, text, product, username, dateline, version)
			VALUES
				(
					0,
					'projecttools',
					'magicselect" . $this->fetch_field('magicselectid') . "',
					'" . $db->escape_string($this->info['text']) . "',
					'vbprojecttools',
					'" . $db->escape_string($this->registry->userinfo['username']) . "',
					" . TIMENOW . ",
					'" . $db->escape_string($product_version) . "'
				)
		");

		$db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "phrase
				(languageid, fieldname, varname, text, product, username, dateline, version)
			VALUES
				(
					0,
					'projecttools',
					'field_" . $this->fetch_field('varname') . "',
					'" . $db->escape_string($this->info['text']) . "',
					'vbprojecttools',
					'" . $db->escape_string($this->registry->userinfo['username']) . "',
					" . TIMENOW . ",
					'" . $db->escape_string($product_version) . "'
				)
		");

		// Rebuild language
		require_once(DIR . '/includes/adminfunctions_language.php');
		build_language();

		($hook = vBulletinHook::fetch_hook('pt_magicselect_postsave')) ? eval($hook) : false;

		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		// create automatically the corresponding column in pt_issue table
		$db =& $this->registry->db;
		$db->query_write("
			ALTER TABLE " . TABLE_PREFIX . "pt_issue
			DROP " . $this->fetch_field('varname') . "
		");

		$magicselectid = intval($this->fetch_field('magicselectid'));

		// Phrases
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "phrase
			WHERE varname = 'magicselect" . $magicselectid . "'
		");

		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "phrase
			WHERE varname = 'field_" . $this->fetch_field('varname') . "'
		");

		// Rebuild language
		require_once(DIR . '/includes/adminfunctions_language.php');
		build_language();

		($hook = vBulletinHook::fetch_hook('pt_magicselect_delete')) ? eval($hook) : false;
		return true;
	}
}
?>