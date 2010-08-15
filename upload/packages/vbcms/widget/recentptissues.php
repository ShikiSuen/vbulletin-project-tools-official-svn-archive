<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.1.2                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2010 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * Test Widget Controller
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 37934 $
 * @since $Date: 2010-07-16 13:34:18 -0700 (Fri, 16 Jul 2010) $
 * @copyright vBulletin Solutions Inc.
 */
class vBCms_Widget_RecentPTIssues extends vBCms_Widget
{
	/*Properties====================================================================*/

	/**
	 * A package identifier.
	 * This is used to resolve any related class names.
	 * It is also used by client code to resolve the class name of this widget.
	 *
	 * @var string
	 */
	protected $package = 'vBCms';

	/**
	 * A class identifier.
	 * This is used to resolve any related class names.
	 * It is also used by client code to resolve the class name of this widget.
	 *
	 * @var string
	 */
	protected $class = 'RecentPTIssues';

	/**
	 * Whether the content is configurable with getConfigView().
	 * @see vBCms_Widget::getConfigView()
	 *
	 * @var bool
	 */
	protected $canconfig = true;

	/*** this widget's configuration settings ****/
	protected $config;

	/*Render========================================================================*/

	/**
	 * Returns the config view for the widget.
	 *
	 * @param	vB_Widget	$widget
	 * @return vBCms_View_Widget				- The view result
	 */
	public function getConfigView($widget = false)
	{
		$this->assertWidget();
		require_once DIR . '/includes/functions_databuild.php';
		fetch_phrase_group('cpcms');
		fetch_phrase_group('vbblock');
		fetch_phrase_group('vbblocksettings');
		fetch_phrase_group('projecttools');

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'do'				=> vB_Input::TYPE_STR,
			'template_name'		=> vB_Input::TYPE_STR,
			'cache_ttl'			=> vB_Input::TYPE_INT,
			'projectid'			=> vB_Input::TYPE_STR,
			'messagemaxchars'	=> vB_Input::TYPE_INT,
			'days'				=> vB_Input::TYPE_INT,
			'count'				=> vB_Input::TYPE_INT
		));

		$view = new vB_View_AJAXHTML('cms_widget_config');
		$view->title = new vB_Phrase('vbcms', 'configuring_widget_x', $this->widget->getTitle());

		$config = $this->widget->getConfig();

		if ((vB::$vbulletin->GPC['do'] == 'config') AND $this->verifyPostId())
		{
			// save the config
			$widgetdm = new vBCms_DM_Widget($this->widget);

			if (vB::$vbulletin->GPC_exists['template_name'])
			{
				$config['template_name'] = vB::$vbulletin->GPC['template_name'];
			}

			if (vB::$vbulletin->GPC_exists['days'])
			{
				$config['days'] = vB::$vbulletin->GPC['days'];
			}

			if (vB::$vbulletin->GPC_exists['count'])
			{
				$config['count'] = vB::$vbulletin->GPC['count'];
			}

			if (vB::$vbulletin->GPC_exists['cache_ttl'])
			{
				$config['cache_ttl'] = vB::$vbulletin->GPC['cache_ttl'];
			}

			if (vB::$vbulletin->GPC_exists['messagemaxchars'])
			{
				$config['messagemaxchars'] = vB::$vbulletin->GPC['messagemaxchars'];
			}

			if (vB::$vbulletin->GPC_exists['projectid'])
			{
				//We could be passed an empty string. If so, clear the existing value
				if (empty(vB::$vbulletin->GPC['projectid']))
				{
					$config['projectid'] = '';
				}
				else
				{
					//We need to confirm these are valid ids
					$projectids = explode(',', vB::$vbulletin->GPC['projectid']);
					$projectid_checked = array();

					foreach ($projectids AS $key => $projectid)
					{
						$projectid_checked[] = intval($projectid);
					}

					$rst = vB::$db->query_read("
						SELECT projectid
						FROM " . TABLE_PREFIX . "pt_project
						WHERE projectid IN (" . implode(',', $projectid_checked) . ")
					");

					if ($rst)
					{
						$projectids = array();

						while ($record = vB::$db->fetch_array($rst))
						{
							$projectids[] = $record['projectid'];
						}
					}
					$config['projectid'] = implode(',', $projectids);
				}
			}

			$widgetdm->set('config', $config);

			$widgetdm->save();

			if (!$widgetdm->hasErrors())
			{
				if ($this->content)
				{
					$segments = array('node' => $this->content->getNodeURLSegment(), 'action' => vB_Router::getUserAction('vBCms_Controller_Content', 'EditPage'));
					$view->setUrl(vB_View_AJAXHTML::URL_FINISHED, vBCms_Route_Content::getURL($segments));
				}

				$view->setStatus(vB_View_AJAXHTML::STATUS_FINISHED, new vB_Phrase('vbcms', 'configuration_saved'));
			}
			else
			{
				if (vB::$vbulletin->debug)
				{
					$view->addErrors($widgetdm->getErrors());
				}

				// only send a message
				$view->setStatus(vB_View_AJAXHTML::STATUS_MESSAGE, new vB_Phrase('vbcms', 'configuration_failed'));
			}
		}
		else
		{
			// show the config page
			$configview = $this->createView('config');

			if (!isset($config['template_name']) OR ($config['template_name'] == ''))
			{
				$config['template_name'] = 'vbcms_widget_recentptissues_page';
			}

			// add the config content
			$configview->template_name = $config['template_name'];
			$configview->days = $config['days'];
			$configview->count = $config['count'];
			$configview->messagemaxchars = $config['messagemaxchars'];
			$configview->projectid = $config['projectid'];
			$configview->cache_ttl = $config['cache_ttl'];

			// filling hidden fields into the form like 'item_type'
			$this->addPostId($configview);

			// filling the template with the actual content
			$view->setContent($configview);

			// send the view
			$view->setStatus(vB_View_AJAXHTML::STATUS_VIEW, new vB_Phrase('vbcms', 'configuring_widget'));
		}

		return $view;
	}

	/**
	 * Fetches the standard page view for a widget.
	 *
	 * @param bool $skip_errors		- If using a collection, omit widgets that throw errors
	 * @return vBCms_View_Widget	- The resolved view, or array of views
	 */
	public function getPageView()
	{
		include_once DIR . '/includes/functions_search.php';
		$this->assertWidget();
		$this->config = $this->widget->getConfig();

		if (!isset($this->config['template_name']) OR ($this->config['template_name'] == ''))
		{
			$this->config['template_name'] = 'vbcms_widget_recentptissues_page';
		}

		if (!isset($this->config['days']) OR ($this->config['days'] == ''))
		{
			$this->config['days'] = 7;
		}

		if (!isset($this->config['count']) OR (intval($this->config['count']) > 20) OR (intval($this->config['count']) == 0))
		{
			$this->config['count'] = 5;
		}

		if (!isset($this->config['cache_ttl']) OR !intval($this->config['cache_ttl']) OR (intval($this->config['cache_ttl']) < 5) OR (intval($this->config['cache_ttl']) > 43200))
		{
			$this->config['cache_ttl'] = 1440;
		}

		if (!isset($this->config['projectid']) OR ($this->config['projectid'] == ''))
		{
			$this->config['projectid'] = 1;
		}

		$view = new vBCms_View_Widget($this->config['template_name']);
		$view->class = $this->widget->getClass();
		$view->title = $this->widget->getTitle();
		$view->description = $this->widget->getDescription();
		$view->widget_title = $this->widget->getTitle();
		$view->avatarenabled = vB::$vbulletin->options['avatarenabled'];

		$hashkey = $this->getHash();
		$results = vB_Cache::instance()->read($hashkey);

		if (!$results)
		{
			$results = $this->makeResults();
			vB_Cache::instance()->write($hashkey, $results, $this->config['cache_ttl'], 'cms_comments_change');
		}

		if (!$results)
		{
			$view->setDisplayView(false);
		}
		$view->results_html = $results;
		//echo '<div><pre>';print_r($view);echo '</pre></div>';exit;
		return $view;
	}

	/**
	 * This does the actual work of creating the navigation elements. This needs some
	 * styling, but we'll do that later.
	 * We use the existing search functionality. It's already all there, we just need
	 * to
	 *
	 * @return string;
	 */
	private function makeResults()
	{
		if (!isset($this->config['days']) OR (!intval($this->config['days'])))
		{
			$this->config['days'] = 7;
		}

		if (!isset($this->config['count']) OR (!intval($this->config['count'])))
		{
			$this->config['count'] = 10;
		}

		if (!isset($this->config['messagemaxchars']) OR (!intval($this->config['messagemaxchars'])))
		{
			$this->config['messagemaxchars'] = 200;
		}

		// require default files
		require_once(DIR . '/includes/functions_projecttools.php');

		$datastores = vB::$db->query_read("
			SELECT data, title
			FROM " . TABLE_PREFIX . "datastore
			WHERE title IN ('pt_permissions', 'pt_bitfields')
		");

		while ($datastore = vB::$db->fetch_array($datastores))
		{
			$title = $datastore['title'];
			$data = $datastore['data'];

			if (!is_array($data))
			{
				$data = unserialize($data);
				if (is_array($data))
				{
					vB::$vbulletin->$title = $data;
				}
			}
			else if ($data != '')
			{
				vB::$vbulletin->$title = $data;
			}
		}

		$issuebits = '';
		$criteria = array();
		$array = array();

		$projectids = explode(',', $this->config['projectid']);

		// Checking each projectid to add issue IDs in an array for later
		foreach ($projectids AS $arrayid => $projectid)
		{
			$project = verify_project($projectid);

			// activity list
			$perms_query = build_issue_permissions_query(vB::$vbulletin->userinfo);

			if (empty($perms_query["$project[projectid]"]))
			{
				return;
			}

			$criteria[] = "(" . $perms_query["$project[projectid]"] . ")";

			build_issue_private_lastpost_sql_project(vB::$vbulletin->userinfo, $project['projectid'], $private_lastpost_join, $private_lastpost_fields);

			$replycount_clause = fetch_private_replycount_clause(vB::$vbulletin->userinfo, $project['projectid']);

			$marking = (vB::$vbulletin->options['threadmarking'] AND vB::$vbulletin->userinfo['userid']);
		}

		// issue list
		$this->result = vB::$vbulletin->db->query_read("
			SELECT
				issue.*, issuedeletionlog.reason AS deletionreason, issuenote.pagetext, user.*
				" . (vB::$vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid)
				AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,
				customavatar.height AS avheight" : "") . "
				" . (vB::$vbulletin->userinfo['userid'] ? ", issuesubscribe.subscribetype, IF(issueassign.issueid IS NULL, 0, 1) AS isassigned" : '') . "
				" . ($marking ? ", issueread.readtime AS issueread, projectread.readtime AS projectread" : '') . "
				" . ($private_lastpost_fields ? ", $private_lastpost_fields" : '') . "
				" . ($replycount_clause ? ", $replycount_clause AS replycount" : '') . "
			FROM " . TABLE_PREFIX . "pt_issue AS issue
				LEFT JOIN " . TABLE_PREFIX . "pt_issuedeletionlog AS issuedeletionlog ON (issuedeletionlog.primaryid = issue.issueid AND issuedeletionlog.type = 'issue')
				LEFT JOIN " . TABLE_PREFIX . "pt_issuenote AS issuenote ON (issuenote.issuenoteid = issue.firstnoteid)
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = issue.submituserid)
				" . (vB::$vbulletin->options['avatarenabled'] ? "
					LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON (avatar.avatarid = user.avatarid)
					LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)
				" : "") . "
				LEFT JOIN " . TABLE_PREFIX . "pt_projectversion AS projectversion ON (projectversion.projectversionid = issue.appliesversionid)
				" . (vB::$vbulletin->userinfo['userid'] ? "
					LEFT JOIN " . TABLE_PREFIX . "pt_issuesubscribe AS issuesubscribe ON (issuesubscribe.issueid = issue.issueid AND issuesubscribe.userid = " . vB::$vbulletin->userinfo['userid'] . ")
					LEFT JOIN " . TABLE_PREFIX . "pt_issueassign AS issueassign ON (issueassign.issueid = issue.issueid AND issueassign.userid = " . vB::$vbulletin->userinfo['userid'] . ")
				" : '') . "
				" . ($marking ? "
					LEFT JOIN " . TABLE_PREFIX . "pt_issueread AS issueread ON (issueread.issueid = issue.issueid AND issueread.userid = " . vB::$vbulletin->userinfo['userid'] . ")
					LEFT JOIN " . TABLE_PREFIX . "pt_projectread as projectread ON (projectread.projectid = issue.projectid AND projectread.userid = " . vB::$vbulletin->userinfo['userid'] . " AND projectread.issuetypeid = issue.issuetypeid)
				" : '') . "
				$private_lastpost_join
			WHERE " . implode(' OR ', $criteria) . "
				AND (issue.submitdate > " . (TIMENOW - (86400 * $this->config['days'])) .  ")
			ORDER BY issue.lastpost DESC
			LIMIT 0, " . $this->config['count'] . "
		");

		while ($issue = vB::$db->fetch_array($this->result))
		{
			//get the avatar
			if (vB::$vbulletin->options['avatarenabled'])
			{
				require_once(DIR . "/includes/functions_user.php");
				$issue['avatar'] = fetch_avatar_from_record($issue);
			}
			else
			{
				$issue['avatar'] = 0;
			}

			$issue['pagetext'] = $this->getSummary($issue['pagetext'], $this->config['messagemaxchars']);

			$array[$issue['issueid']] = $issue;
		}

		return $array;
	}

	protected function getSummary($pagetext, $length)
	{
		require_once(DIR . '/includes/functions_search.php');

		//figure out how to handle the 'cancelwords'
		$display['highlight'] = array();
		$page_text =  preg_replace('#\[quote(=(&quot;|"|\'|)??.*\\2)?\](((?>[^\[]*?|(?R)|.))*)\[/quote\]#siUe', "process_quote_removal('\\3', \$display['highlight'])", $pagetext);

		$strip_quotes = true;

		// Deal with the case that quote was the only content of the post
		if (trim($page_text) == '')
		{
			$page_text = $pagetext;
			$strip_quotes = false;
		}

		return htmlspecialchars_uni(fetch_censored_text(trim(fetch_trimmed_title(strip_bbcode($page_text, $strip_quotes, false, false, true), $length))));
	}

	/**
	 * This function generates a unique hash for this item
	 *
	 * @return	string
	 */
	protected function getHash()
	{
		$context = new vB_Context('widget', array(
			'widgetid' => $this->widget->getId(),
			'permissions' => vB::$vbulletin->userinfo['permissions']['ptpermissions'])
		);

		return strval($context);
	}
}