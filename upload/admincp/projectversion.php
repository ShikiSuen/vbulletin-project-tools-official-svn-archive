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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$Rev$');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array(
	'projecttools',
	'projecttoolsadmin'
);

$specialtemplates = array(
	'pt_bitfields',
	'pt_permissions',
);

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

if (empty($vbulletin->products['vbprojecttools']))
{
	print_stop_message('product_not_installed_disabled');
}

require_once(DIR . '/includes/adminfunctions_projecttools.php');
require_once(DIR . '/includes/functions_projecttools.php');

if (!function_exists('ini_size_to_bytes') OR (($current_memory_limit = ini_size_to_bytes(@ini_get('memory_limit'))) < 128 * 1024 * 1024 AND $current_memory_limit > 0))
{
	@ini_set('memory_limit', 128 * 1024 * 1024);
}

$full_product_info = fetch_product_list(true);

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canpt'))
{
	print_cp_no_permission();
}

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array(
	'projectid' => TYPE_UINT,
	'projectversionid' => TYPE_UINT,
	'projectversiongroupid' => TYPE_UINT,
));

log_admin_action((!empty($vbulletin->GPC['projectid']) ? ' project id = ' . $vbulletin->GPC['projectid'] : '') . (!empty($vbulletin->GPC['projectversiongroupid']) ? ' version group id = ' . $vbulletin->GPC['projectversiongroupid'] : '') . (!empty($vbulletin->GPC['projectversionid']) ? ' version id = ' . $vbulletin->GPC['projectversionid'] : ''));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['project_tools']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'projectversion';
}

$issuetype_options = array();

$types = $db->query_read("
	SELECT *
	FROM " . TABLE_PREFIX . "pt_issuetype
	ORDER BY displayorder
");

while ($type = $db->fetch_array($types))
{
	$issuetype_options["$type[issuetypeid]"] = $vbphrase["issuetype_$type[issuetypeid]_singular"];
}

$helpcache['project']['projectadd']['afterforumids[]'] = 1;
$helpcache['project']['projectedit']['afterforumids[]'] = 1;

// ########################################################################
// ################### PROJECT VERSION MANAGEMENT #########################
// ########################################################################
if ($_POST['do'] == 'projectversionupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'versionname' => TYPE_NOHTML,
		'displayorder' => TYPE_UINT,
		'nextversion' => TYPE_BOOL,
		'default' => TYPE_BOOL
	));

	// Edit
	if ($vbulletin->GPC['projectversionid'])
	{
		$projectversion = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectversion
			WHERE projectversionid = " . $vbulletin->GPC['projectversionid']
		);

		$vbulletin->GPC['projectversiongroupid'] = $projectversion['projectversiongroupid'];
	}
	else
	{
		$projectversion = array();
	}

	$projectversiongroup = $db->query_first("
		SELECT pt_projectversiongroup.*
		FROM " . TABLE_PREFIX . "pt_projectversiongroup AS pt_projectversiongroup
		INNER JOIN " . TABLE_PREFIX . "pt_project AS pt_project ON (pt_project.projectid = pt_projectversiongroup.projectid)
		WHERE pt_projectversiongroup.projectversiongroupid = " . $vbulletin->GPC['projectversiongroupid']
	);

	if (!$projectversiongroup)
	{
		print_stop_message('invalid_action_specified');
	}

	if (empty($vbulletin->GPC['versionname']))
	{
		print_stop_message('please_complete_required_fields');
	}

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	// effective order means that sorting just the version table will return versions ordered by group first
	if ($projectversion['projectversionid'])
	{
		// Edit
		// Check first if the default value is already defined for this project
		// If yes, remove it and save the actual form
		$defaultvalue = $db->query_first("
			SELECT projectversionid AS ver
			FROM " . TABLE_PREFIX . "pt_projectversion
			WHERE defaultvalue = 1
				AND projectid = " . intval($project['projectid']) . "
		");

		if ($defaultvalue['ver'] != $projectversion['projectversionid'])
		{
			// Default value already defined for an other version
			// Removing it
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_projectversion SET
					defaultvalue = 0
				WHERE projectversionid = " . intval($defaultvalue['ver']) . "
			");
		}

		// Perform the save
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_projectversion SET
				versionname = '" . $db->escape_string($vbulletin->GPC['versionname']) . "',
				displayorder = " . $vbulletin->GPC['displayorder'] . ",
				effectiveorder = " . ($vbulletin->GPC['displayorder'] + $projectversiongroup['displayorder'] * 100000) . ",
				defaultvalue = " . ($vbulletin->GPC['default'] ? 1 : 0) . "
			WHERE projectversionid = " . $projectversion['projectversionid'] . "
		");
	}
	else
	{
		// Add
		// Check first if the default value is already defined for this project
		// If yes, remove it and save the actual form
		$defaultvalue = $db->query_first("
			SELECT projectversionid AS ver
			FROM " . TABLE_PREFIX . "pt_projectversion
			WHERE defaultvalue = 1
				AND projectid = " . intval($project['projectid']) . "
		");

		if ($defaultvalue['ver'])
		{
			// Default value already defined for an other category
			// Removing it
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_projectversion SET
					defaultvalue = 0
				WHERE projectversionid = " . intval($defaultvalue['ver']) . "
			");
		}

		// Perform the save
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "pt_projectversion
				(projectid, versionname, projectversiongroupid, displayorder, effectiveorder)
			VALUES
				(" . $projectversiongroup['projectid'] . ",
				'" . $db->escape_string($vbulletin->GPC['versionname']) . "',
				" . $projectversiongroup['projectversiongroupid'] . ",
				" . $vbulletin->GPC['displayorder'] . ",
				" . ($vbulletin->GPC['displayorder'] + $projectversiongroup['displayorder'] * 100000) . ")
		");

		$projectversionid = $db->insert_id();

		if ($vbulletin->GPC['nextversion'])
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_issue SET
					addressedversionid = " . $projectversionid . "
				WHERE projectid = " . $projectversiongroup['projectid'] . "
					AND isaddressed = 1
					AND addressedversionid = 0
			");
		}
	}

	build_version_cache();

	define('CP_REDIRECT', 'projectversion.php?do=projectversion&projectid=' . $projectversiongroup['projectid']);
	print_stop_message('project_version_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectversionadd' OR $_REQUEST['do'] == 'projectversionedit')
{
	if ($vbulletin->GPC['projectversionid'])
	{
		// Edit
		$projectversion = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectversion
			WHERE projectversionid = " . $vbulletin->GPC['projectversionid']
		);

		$vbulletin->GPC['projectversiongroupid'] = $projectversion['projectversiongroupid'];
	}
	else
	{
		// Add
		$maxorder = $db->query_first("
			SELECT MAX(displayorder) AS maxorder
			FROM " . TABLE_PREFIX . "pt_projectversiongroup
			WHERE projectversiongroupid = " . $vbulletin->GPC['projectversiongroupid']
		);

		$projectver = $db->query_first("
			SELECT projectid
			FROM " . TABLE_PREFIX . "pt_projectversion
			WHERE projectversiongroupid = " . $vbulletin->GPC['projectversiongroupid']
		);

		$projectversion = array(
			'projectversionid' => 0,
			'displayorder' => $maxorder['maxorder'] + 10,
			'defaultvalue' => 0,
			'projectid' => $projectver['projectid']
		);
	}

	$projectversiongroup = $db->query_first("
		SELECT pt_projectversiongroup.*
		FROM " . TABLE_PREFIX . "pt_projectversiongroup AS pt_projectversiongroup
		INNER JOIN " . TABLE_PREFIX . "pt_project AS pt_project ON (pt_project.projectid = pt_projectversiongroup.projectid)
		WHERE pt_projectversiongroup.projectversiongroupid = " . $vbulletin->GPC['projectversiongroupid']
	);

	if (!$projectversiongroup)
	{
		print_stop_message('invalid_action_specified');
	}

	print_form_header('projectversion', 'projectversionupdate');

	if ($projectversion['projectversionid'])
	{
		print_table_header(construct_phrase($vbphrase['edit_project_version'], $projectversion['versionname']));
	}
	else
	{
		print_table_header($vbphrase['add_project_version']);
	}

	print_label_row($vbphrase['version_group'], $projectversiongroup['groupname']);
	print_input_row($vbphrase['title'], 'versionname', $projectversion['versionname'], false);
	print_input_row($vbphrase['display_order'] . '<dfn>' . $vbphrase['note_a_larger_value_will_be_displayed_first'] . '</dfn>', 'displayorder', $projectversion['displayorder'], true, 5);
	print_yes_no_row($vbphrase['default_value'], 'default', $projectversion['defaultvalue']);

	if (!$projectversion['projectversionid'])
	{
		print_yes_no_row($vbphrase['denote_as_next_version'], 'nextversion', 0);
	}

	construct_hidden_code('projectversionid', $projectversion['projectversionid']);
	construct_hidden_code('projectversiongroupid', $projectversiongroup['projectversiongroupid']);
	construct_hidden_code('projectid', $projectversion['projectid']);
	print_submit_row();
}

// ########################################################################
if ($_POST['do'] == 'projectversionkill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'appliesversionid' => TYPE_UINT,
		'addressedversionid' => TYPE_INT
	));

	$projectversion = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectversion
		WHERE projectversionid = " . $vbulletin->GPC['projectversionid']
	);

	$project = fetch_project_info($projectversion['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_projectversion
		WHERE projectversionid = " . $projectversion['projectversionid'] . "
	");

	// updated applies version
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "pt_issue SET
			appliesversionid = " . $vbulletin->GPC['appliesversionid'] . "
		WHERE appliesversionid = " . $projectversion['projectversionid'] . "
	");

	// update addressed version
	if ($vbulletin->GPC['addressedversionid'] == -1)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issue SET
				addressedversionid = 0,
				isaddressed = 1
			WHERE addressedversionid = " . $projectversion['projectversionid'] . "
		");
	}
	else if ($vbulletin->GPC['addressedversionid'] == 0)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issue SET
				addressedversionid = 0,
				isaddressed = 0
			WHERE addressedversionid = " . $projectversion['projectversionid'] . "
		");
	}
	else
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issue SET
				addressedversionid = " . $vbulletin->GPC['addressedversionid'] . ",
				isaddressed = 1
			WHERE addressedversionid = " . $projectversion['projectversionid'] . "
		");
	}

	build_version_cache();

	define('CP_REDIRECT', 'projectversion.php?do=projectversion&projectid=' . $project['projectid']);
	print_stop_message('project_version_deleted');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectversiondelete')
{
	$projectversion = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectversion
		WHERE projectversionid = " . $vbulletin->GPC['projectversionid']
	);

	$project = fetch_project_info($projectversion['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$version_groups = array();

	$version_query = $db->query_read("
		SELECT projectversion.projectversionid, projectversion.versionname, projectversiongroup.groupname
		FROM " . TABLE_PREFIX . "pt_projectversion AS projectversion
			INNER JOIN " . TABLE_PREFIX . "pt_projectversiongroup AS projectversiongroup ON (projectversion.projectversiongroupid = projectversiongroup.projectversiongroupid)
		WHERE projectversion.projectid = " . $project['projectid'] . "
			AND projectversion.projectversionid <> " . $vbulletin->GPC['projectversionid'] . "
		ORDER BY projectversion.effectiveorder DESC
	");

	while ($version = $db->fetch_array($version_query))
	{
		$version_groups["$version[groupname]"]["$version[projectversionid]"] = $version['versionname'];
	}

	$applies_version = array(0 => $vbphrase['unknown']) + $version_groups;
	$addressed_version = array(0 => $vbphrase['none_meta'], '-1' => $vbphrase['next_release']) + $version_groups;

	print_delete_confirmation(
		'pt_projectversion',
		$projectversion['projectversionid'],
		'project',
		'projectversionkill',
		'',
		0,
		construct_phrase($vbphrase['existing_affected_issues_updated_delete_select_versions_x_y'],
			'<select name="appliesversionid">' . construct_select_options($applies_version, 0) . '</select>',
			'<select name="addressedversionid">' . construct_select_options($addressed_version, -1) . '</select>'
		),
		'versionname'
	);
}

// ########################################################################
if ($_POST['do'] == 'projectversiongroupupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'groupname' => TYPE_NOHTML,
		'displayorder' => TYPE_UINT
	));

	if ($vbulletin->GPC['projectversiongroupid'])
	{
		$projectversiongroup = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectversiongroup
			WHERE projectversiongroupid = " . $vbulletin->GPC['projectversiongroupid']
		);

		$vbulletin->GPC['projectid'] = $projectversiongroup['projectid'];
	}
	else
	{
		$projectversiongroup = array();
	}

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	if (empty($vbulletin->GPC['groupname']))
	{
		print_stop_message('please_complete_required_fields');
	}

	if ($projectversiongroup['projectversiongroupid'])
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_projectversiongroup SET
				groupname = '" . $db->escape_string($vbulletin->GPC['groupname']) . "',
				displayorder = " . $vbulletin->GPC['displayorder'] . "
			WHERE projectversiongroupid = $projectversiongroup[projectversiongroupid]
		");
	}
	else
	{
		$db->query_write("
			INSERT INTO " . TABLE_PREFIX . "pt_projectversiongroup
				(projectid, groupname, displayorder)
			VALUES
				($project[projectid],
				'" . $db->escape_string($vbulletin->GPC['groupname']) . "',
				" . $vbulletin->GPC['displayorder'] . ")
		");
	}

	build_version_cache();

	define('CP_REDIRECT', 'projectversion.php?do=projectversion&projectid=' . $project['projectid']);
	print_stop_message('project_version_saved');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectversiongroupadd' OR $_REQUEST['do'] == 'projectversiongroupedit')
{
	if ($vbulletin->GPC['projectversiongroupid'])
	{
		$projectversiongroup = $db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "pt_projectversiongroup
			WHERE projectversiongroupid = " . $vbulletin->GPC['projectversiongroupid']
		);

		$vbulletin->GPC['projectid'] = $projectversiongroup['projectid'];
	}
	else
	{
		$maxorder = $db->query_first("
			SELECT MAX(displayorder) AS maxorder
			FROM " . TABLE_PREFIX . "pt_projectversiongroup
			WHERE projectid = " . $vbulletin->GPC['projectid']
		);

		$projectversiongroup = array(
			'projectversiongroupid' => 0,
			'displayorder' => $maxorder['maxorder'] + 10
		);
	}

	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	print_form_header('projectversion', 'projectversiongroupupdate');

	if ($projectversiongroup['projectversiongroupid'])
	{
		print_table_header(construct_phrase($vbphrase['edit_project_version_group'], $projectversiongroup['groupname']));
	}
	else
	{
		print_table_header($vbphrase['add_project_version_group']);
	}

	print_input_row($vbphrase['title'], 'groupname', $projectversiongroup['groupname'], false);
	print_input_row($vbphrase['display_order'] . '<dfn>' . $vbphrase['note_a_larger_value_will_be_displayed_first'] . '</dfn>', 'displayorder', $projectversiongroup['displayorder'], true, 5);
	construct_hidden_code('projectid', $project['projectid']);
	construct_hidden_code('projectversiongroupid', $projectversiongroup['projectversiongroupid']);
	print_submit_row();
}

// ########################################################################
if ($_POST['do'] == 'projectversiongroupkill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'appliesversionid' => TYPE_UINT,
		'addressedversionid' => TYPE_INT
	));

	$projectversiongroup = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectversiongroup
		WHERE projectversiongroupid = " . $vbulletin->GPC['projectversiongroupid']
	);

	$project = fetch_project_info($projectversiongroup['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$group_versions = array();

	$group_version_data = $db->query_read("
		SELECT projectversionid
		FROM " . TABLE_PREFIX . "pt_projectversion
		WHERE projectversiongroupid = $projectversiongroup[projectversiongroupid]
	");

	while ($group_version = $db->fetch_array($group_version_data))
	{
		$group_versions[] = $group_version['projectversionid'];
	}

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_projectversiongroup
		WHERE projectversiongroupid = $projectversiongroup[projectversiongroupid]
	");

	$db->query_write("
		DELETE FROM " . TABLE_PREFIX . "pt_projectversion
		WHERE projectversiongroupid = $projectversiongroup[projectversiongroupid]
	");

	if ($group_versions)
	{
		// updated applies version
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_issue SET
				appliesversionid = " . $vbulletin->GPC['appliesversionid'] . "
			WHERE appliesversionid IN (" . implode(',', $group_versions) . ")
		");

		// update addressed version
		if ($vbulletin->GPC['addressedversionid'] == -1)
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_issue SET
					addressedversionid = 0,
					isaddressed = 1
				WHERE addressedversionid IN (" . implode(',', $group_versions) . ")
			");
		}
		else if ($vbulletin->GPC['addressedversionid'] == 0)
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_issue SET
					addressedversionid = 0,
					isaddressed = 0
				WHERE addressedversionid IN (" . implode(',', $group_versions) . ")
			");
		}
		else
		{
			$db->query_write("
				UPDATE " . TABLE_PREFIX . "pt_issue SET
					addressedversionid = " . $vbulletin->GPC['addressedversionid'] . ",
					isaddressed = 1
				WHERE addressedversionid IN (" . implode(',', $group_versions) . ")
			");
		}
	}

	build_version_cache();

	define('CP_REDIRECT', 'projectversion.php?do=projectversion&projectid=' . $project['projectid']);
	print_stop_message('project_version_deleted');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectversiongroupdelete')
{
	$projectversiongroup = $db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectversiongroup
		WHERE projectversiongroupid = " . $vbulletin->GPC['projectversiongroupid']
	);

	$project = fetch_project_info($projectversiongroup['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$version_groups = array();

	$version_query = $db->query_read("
		SELECT projectversion.projectversionid, projectversion.versionname, projectversiongroup.groupname
		FROM " . TABLE_PREFIX . "pt_projectversion AS projectversion
		INNER JOIN " . TABLE_PREFIX . "pt_projectversiongroup AS projectversiongroup ON
			(projectversion.projectversiongroupid = projectversiongroup.projectversiongroupid)
		WHERE projectversion.projectid = $project[projectid]
			AND projectversiongroup.projectversiongroupid <> " . $vbulletin->GPC['projectversiongroupid'] . "
		ORDER BY projectversion.effectiveorder DESC
	");

	while ($version = $db->fetch_array($version_query))
	{
		$version_groups["$version[groupname]"]["$version[projectversionid]"] = $version['versionname'];
	}

	$applies_version = array(0 => $vbphrase['unknown']) + $version_groups;
	$addressed_version = array(0 => $vbphrase['none_meta'], '-1' => $vbphrase['next_release']) + $version_groups;

	print_delete_confirmation(
		'pt_projectversiongroup',
		$projectversiongroup['projectversiongroupid'],
		'project',
		'projectversiongroupkill',
		'',
		0,
		construct_phrase($vbphrase['existing_affected_issues_updated_delete_select_versions_x_y'], '<select name="appliesversionid">' . construct_select_options($applies_version, 0) . '</select>', '<select name="addressedversionid">' . construct_select_options($addressed_version, -1) . '</select>'),
		'groupname'
	);
}

// ########################################################################
if ($_POST['do'] == 'projectversiondisplayorder')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'versionorder' => TYPE_ARRAY_UINT,
		'grouporder' => TYPE_ARRAY_UINT
	));

	$groupcase = '';
	$grouporder = array();

	foreach ($vbulletin->GPC['grouporder'] AS $id => $displayorder)
	{
		$grouporder[intval($id)] = $displayorder;
		$groupcase .= "\nWHEN " . intval($id) . " THEN " . $displayorder;
	}

	if ($groupcase)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_projectversiongroup SET
				displayorder = CASE projectversiongroupid $groupcase ELSE displayorder END
		");
	}

	$versioncase_display = '';

	foreach ($vbulletin->GPC['versionorder'] AS $id => $displayorder)
	{
		$versioncase_display .= "\nWHEN " . intval($id) . " THEN " . $displayorder;
	}

	if ($versioncase_display)
	{
		$db->query_write("
			UPDATE " . TABLE_PREFIX . "pt_projectversion AS projectversion
			INNER JOIN " . TABLE_PREFIX . "pt_projectversiongroup AS projectversiongroup ON
				(projectversion.projectversiongroupid = projectversiongroup.projectversiongroupid)
			SET
				projectversion.displayorder = CASE projectversion.projectversionid $versioncase_display ELSE projectversion.displayorder END,
				projectversion.effectiveorder = projectversion.displayorder + (projectversiongroup.displayorder * 100000)
		");
	}

	define('CP_REDIRECT', 'projectversion.php?do=projectversion&projectid=' . $vbulletin->GPC['projectid']);
	print_stop_message('saved_display_order_successfully');
}

// ########################################################################
if ($_REQUEST['do'] == 'projectversion')
{
	$project = fetch_project_info($vbulletin->GPC['projectid'], false);

	if (!$project)
	{
		print_stop_message('invalid_action_specified');
	}

	$groups = array();

	$group_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectversiongroup
		WHERE projectid = $project[projectid]
		ORDER BY displayorder DESC
	");

	while ($group = $db->fetch_array($group_data))
	{
		$groups["$group[projectversiongroupid]"] = $group;
	}

	$versions = array();

	$version_data = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "pt_projectversion
		WHERE projectid = $project[projectid]
		ORDER BY displayorder DESC
	");

	while ($version = $db->fetch_array($version_data))
	{
		$versions["$version[projectversiongroupid]"][] = $version;
	}

	print_form_header('projectversion', 'projectversiondisplayorder');
	print_table_header(construct_phrase($vbphrase['project_versions_for_x'], $project['title_clean']), 3);

	if ($groups)
	{
		foreach ($groups AS $group)
		{
			print_cells_row(array(
				$group['groupname'],
				"<input type=\"text\" class=\"bginput\" name=\"grouporder[$group[projectversiongroupid]]\" value=\"$group[displayorder]\" tabindex=\"1\" size=\"3\" />",
				'<div align="' . vB_Template_Runtime::fetchStyleVar('right') . '" class="normal smallfont">' .
					construct_link_code($vbphrase['edit'], 'projectversion.php?do=projectversiongroupedit&amp;projectversiongroupid=' . $group['projectversiongroupid']) .
					construct_link_code($vbphrase['delete'], 'projectversion.php?do=projectversiongroupdelete&amp;projectversiongroupid=' . $group['projectversiongroupid']) .
					construct_link_code($vbphrase['add_version'], 'projectversion.php?do=projectversionadd&amp;projectversiongroupid=' . $group['projectversiongroupid']) .
					'</div>',
			), 'thead');

			if (is_array($versions["$group[projectversiongroupid]"]))
			{
				foreach ($versions["$group[projectversiongroupid]"] AS $version)
				{
					print_cells_row(array(
						$version['versionname'],
						"<input type=\"text\" class=\"bginput\" name=\"versionorder[$version[projectversionid]]\" value=\"$version[displayorder]\" tabindex=\"1\" size=\"3\" />",
						'<div align="' . vB_Template_Runtime::fetchStyleVar('right') . '" class="smallfont">' .
							construct_link_code($vbphrase['edit'], 'projectversion.php?do=projectversionedit&amp;projectversionid=' . $version['projectversionid']) .
							construct_link_code($vbphrase['delete'], 'projectversion.php?do=projectversiondelete&amp;projectversionid=' . $version['projectversionid']) .
						'</div>'
					));
				}
			}
			else
			{
				print_description_row($vbphrase['no_versions_defined_in_this_group'], false, 3, '', 'center');
			}
		}

		construct_hidden_code('projectid', $project['projectid']);
		print_submit_row($vbphrase['save_display_order'], '', 3);
	}
	else
	{
		print_description_row($vbphrase['no_versions_groups_defined_project'], false, 3, '', 'center');
		print_table_footer();
	}

	echo '<p align="center">' . construct_link_code($vbphrase['add_project_version_group'], 'projectversion.php?do=projectversiongroupadd&amp;projectid=' . $project['projectid']) . '</p>';
	echo '<p align="center" class="smallfont">' . $vbphrase['note_higer_display_orders_first'] . '</p>';
}

print_cp_footer();

?>