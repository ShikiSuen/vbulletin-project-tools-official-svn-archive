/*======================================================================*\
|| #################################################################### ||
|| #                  vBulletin Project Tools 2.3.0                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2015 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file is part of vBulletin Project Tools and subject to terms# ||
|| #               of the vBulletin Open Source License               # ||
|| # ---------------------------------------------------------------- # ||
|| #    http://www.vbulletin.org/open_source_license_agreement.php    # ||
|| #################################################################### ||
\*======================================================================*/

var pt_icon_id_prefix = 'project_statusicon_';

// #############################################################################
// vB_AJAX_PT_ReadMarker
// #############################################################################

/**
* vBulletin AJAX projectid read marker class
*
* Allows a projectid and all contained issues to be marked as read
*
* @param	integer	Project ID to be marked as read
* @param	boolean	Extended search for repeated projects
*/
function vB_AJAX_PT_ReadMarker(projectid, extended_search)
{
	this.projectid = projectid;
	this.extended_search = extended_search;
};

/**
* Initializes the AJAX request to mark the project as read
*/
vB_AJAX_PT_ReadMarker.prototype.mark_read = function()
{
	YAHOO.util.Connect.asyncRequest("POST", 'projectajax.php?do=markread&projectid=' + this.projectid, {
		success: this.handle_ajax_request,
		failure: this.handle_ajax_error,
		timeout: vB_Default_Timeout,
		scope: this
	}, SESSIONURL + 'securitytoken=' + SECURITYTOKEN + '&do=markread&projectid=' + this.projectid);
};

/**
* Handles AJAX Errors
*
* @param	object	YUI AJAX
*/
vB_AJAX_PT_ReadMarker.prototype.handle_ajax_error = function(ajax)
{
	//TODO: Something bad happened, try again
	vBulletin_AJAX_Error_Handler(ajax);
};

/**
* Handles the XML response from the AJAX response
*
* Passes project IDs in XML to handler functions
*
* @param	object	YUI AJAX
*/
vB_AJAX_PT_ReadMarker.prototype.handle_ajax_request = function(ajax)
{
	var projectid_nodes = fetch_tags(ajax.responseXML, 'project');
	for (var nodeid = 0; nodeid < projectid_nodes.length; nodeid++)
	{
		var projectid = projectid_nodes[nodeid].getAttribute('projectid');
		var id_keys = new Array();

		if (this.extended_search)
		{
			var id_search = pt_icon_id_prefix + projectid;
			var images = fetch_tags(document, 'img');
			for (var image_id = 0; image_id < images.length; image_id++)
			{
				if (images[image_id].id && images[image_id].id.substr(0, id_search.length) == id_search)
				{
					var id_parts = images[image_id].id.substr(pt_icon_id_prefix.length).match(/^(\d+_\d+)/);
					if (id_parts)
					{
						id_keys.push(id_parts[1]);
					}
				}
			}
		}
		else
		{
			id_keys.push(projectid);
		}

		for (var id_index = 0; id_index < id_keys.length; id_index++)
		{
			this.update_project_status(id_keys[id_index]);

			var issuetypes = fetch_tags(projectid_nodes[nodeid], 'issuetype');
			for (var typecounter = 0; typecounter < issuetypes.length; typecounter++)
			{
				var issuetypecount = fetch_object('project_typecount_' + id_keys[id_index] + '_' + issuetypes[typecounter].firstChild.nodeValue);
				if (issuetypecount)
				{
					issuetypecount.style.fontWeight = '';
				}
			}
		}
	}
};

/**
* Updates the status of a 'projectbit*' template
*
* @param	string	The end of the ID (probably projectid or <projectid>_<forumid>)
*/
vB_AJAX_PT_ReadMarker.prototype.update_project_status = function(id_ending)
{
	var imageobj = fetch_object(pt_icon_id_prefix + id_ending);
	if (imageobj)
	{
		imageobj.style.cursor = 'default';
		imageobj.title = imageobj.otitle;
		imageobj.src = this.fetch_old_src(imageobj.src);
	}
};

/**
* Converts an image source from x_new.y to the appropriate x_old.y format
*
* @param	string	Original image source
*
* @return	string	New image source
*/
vB_AJAX_PT_ReadMarker.prototype.fetch_old_src = function(newsrc)
{
	var foo = newsrc.replace(/_(new)([\._])(.+)$/i, '_old$2$3');
	return foo;
};

// #############################################################################
// Ancilliary functions
// #############################################################################

/**
* Initializes a request to mark a projectid
*
* @param	integer	Project ID to be marked as read
* @param	boolean	Do extended ID search (looks for projectid_forumid)
*
* @return	boolean	false
*/
function mark_project_read(projectid, extended_search)
{
	if (AJAX_Compatible)
	{
		var read_marker = new vB_AJAX_PT_ReadMarker(projectid, extended_search);
		read_marker.mark_read();
	}
	else
	{
		window.location = 'projectajax.php?' + SESSIONURL + 'do=markread&projectid=' + projectid;
	}

	return false;
};

/**
* Translates the ID of a scanned object into a projectid ID and passes it to mark_projectid_read()
*
* @param	event
*/
function init_project_readmarker_icon(e)
{
	var id_parts = this.id.substr(pt_icon_id_prefix.length).match(/^(\d+)(_(\d+))?/);
	// <prefix>_<projectid>_<forumid>
	mark_project_read(id_parts[1], id_parts[3] ? true : false);
};

/**
* Scans images on a page for projectid status icons indicating that they contain new posts
* then initializes them to activate the read marking system on double-click
*/
function init_project_readmarker_system()
{
	var images = fetch_tags(document, 'img');
	for (var i = 0; i < images.length; i++)
	{
		if (images[i].id && images[i].id.substr(0, pt_icon_id_prefix.length) == pt_icon_id_prefix)
		{
			if (images[i].src.search(/\/([^\/]+)(new)(_lock)?\.([a-z0-9]+)$/i) != -1)
			{
				img_alt_2_title(images[i]);
				images[i].otitle = images[i].title;
				images[i].title = vbphrase['doubleclick_project_markread'];
				images[i].style.cursor = pointer_cursor;
				images[i].ondblclick = init_project_readmarker_icon;
			}
		}
	}
};
