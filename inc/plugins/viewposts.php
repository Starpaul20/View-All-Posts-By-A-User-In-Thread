<?php
/**
 * View All Posts by a User in Thread
 * Copyright 2010 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Tell MyBB when to run the hooks
$plugins->add_hook("search_start", "viewposts_run");

// The information that shows up on the plugin manager
function viewposts_info()
{
	global $lang;
	$lang->load("viewposts", true);

	return array(
		"name"				=> $lang->viewposts_info_name,
		"description"		=> $lang->viewposts_info_desc,
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.0.1",
		"codename"			=> "viewposts",
		"compatibility"		=> "18*"
	);
}

// This function runs when the plugin is activated.
function viewposts_activate()
{
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("misc_whoposted_poster", "#".preg_quote('{$poster[\'posts\']}')."#i", '<a href="search.php?action=findposts&uid={$poster[\'uid\']}&tid={$tid}" onclick="opener.location=(\'search.php?action=findposts&uid={$poster[\'uid\']}&tid={$tid}\'); self.close();">{$poster[\'posts\']}</a>');
}

// This function runs when the plugin is deactivated.
function viewposts_deactivate()
{
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("misc_whoposted_poster", "#".preg_quote('<a href="search.php?action=findposts&uid={$poster[\'uid\']}&tid={$tid}" onclick="opener.location=(\'search.php?action=findposts&uid={$poster[\'uid\']}&tid={$tid}\'); self.close();">{$poster[\'posts\']}</a>')."#i", '{$poster[\'posts\']}', 0);
}

// View all posts search page
function viewposts_run()
{
	global $db, $lang, $mybb, $session;
	$lang->load("search");

	if($mybb->input['action'] == "findposts")
	{
		$where_sql = "tid='".$mybb->get_input('tid', MyBB::INPUT_INT)."'";
		if(!$mybb->input['tid'])
		{
			error($lang->error_invalidsearch);
		}

		$where_sql .= " AND uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'";
		if(!$mybb->input['uid'])
		{
			error($lang->error_invalidsearch);
		}

		$onlyusfids = array();

		// Check group permissions if we can't view threads not started by us
		$group_permissions = forum_permissions();
		foreach($group_permissions as $fid => $forum_permissions)
		{
			if($forum_permissions['canonlyviewownthreads'] == 1)
			{
				$onlyusfids[] = $fid;
			}
		}

		if(!empty($onlyusfids))	
		{
			$where_sql .= "AND ((fid IN(".implode(',', $onlyusfids).") AND uid='{$mybb->user['uid']}') OR fid NOT IN(".implode(',', $onlyusfids)."))";
		}

		$unsearchforums = get_unsearchable_forums();
		if($unsearchforums)
		{
			$where_sql .= " AND fid NOT IN ($unsearchforums)";
		}
		$inactiveforums = get_inactive_forums();
		if($inactiveforums)
		{
			$where_sql .= " AND fid NOT IN ($inactiveforums)";
		}

		$options = array(
			'order_by' => 'dateline',
			'order_dir' => 'desc'
		);

		// Do we have a hard search limit?
		if((int)$mybb->settings['searchhardlimit'] > 0)
		{
			$options['limit'] = (int)$mybb->settings['searchhardlimit'];
		}

		$pids = '';
		$comma = '';
		$query = $db->simple_select("posts", "pid", "{$where_sql}", $options);
		while($pid = $db->fetch_field($query, "pid"))
		{
			$pids .= $comma.$pid;
			$comma = ',';
		}

		$sid = md5(uniqid(microtime(), true));
		$searcharray = array(
			"sid" => $db->escape_string($sid),
			"uid" => (int)$mybb->user['uid'],
			"dateline" => TIME_NOW,
			"ipaddress" => $db->escape_binary($session->packedip),
			"threads" => $mybb->get_input('tid', MyBB::INPUT_INT),
			"posts" => $db->escape_string($pids),
			"resulttype" => "posts",
			"querycache" => $db->escape_string($where_sql),
			"keywords" => ''
		);

		$db->insert_query("searchlog", $searcharray);
		redirect("search.php?action=results&sid=".$sid, $lang->redirect_searchresults);
	}
}
