<?php
/**
*
* @package Ultimate SEO URL phpBB SEO
* @version $$
* @copyright (c) 2006 - 2014 www.phpbb-seo.com
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace phpbbseo\usu\acp;

/**
* phpBB_SEO Class
* www.phpBB-SEO.com
* @package Ultimate SEO URL phpBB SEO
*/
class usu
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbbseo\usu\core */
	protected $core;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var string */
	protected $phpbb_root_path;

	/** @var string */
	protected $php_ext;

	var $u_action;
	var $new_config = array();
	var $dyn_select = array();
	var $forum_ids = array();
	var $array_type_cfg = array();
	var $multiple_options = array();
	var $modrtype_lang = array();
	var $write_type = 'forum';
	var $lengh_limit = 20;
	var $word_limit = 3;
	var $seo_unset_opts = array();

	function main($id, $mode)
	{
		global $config, $db, $user, $template, $request;
		global $phpbb_root_path, $phpEx;
		global $phpbb_container;

		$this->config = $config;
		$this->core = $phpbb_container->get('phpbbseo.usu.core');
		$this->db = $db;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $phpEx;

		$this->user->add_lang_ext('phpbbseo/usu', 'acp_usu');

		$action	= $this->request->variable('action', '');

		$submit = $this->request->is_set_post('submit');
		$form_key = 'acp_board';
		add_form_key($form_key);
		$display_vars = array();

		// --> Zero Dupe
		if (@isset($this->core->seo_opt['zero_dupe']))
		{
			$this->multiple_options['zero_dupe']['post_redir_values'] = array('off' => 'off', 'post' => 'post', 'guest' => 'guest', 'all' => 'all'); // do not change

			$this->multiple_options['zero_dupe']['post_redir_lang'] = array(
				'off'	=> $this->user->lang['ACP_ZERO_DUPE_OFF'],
				'post'	=> $this->user->lang['ACP_ZERO_DUPE_MSG'],
				'guest'	=> $this->user->lang['ACP_ZERO_DUPE_GUEST'],
				'all'	=> $this->user->lang['ACP_ZERO_DUPE_ALL']
			);
		}

		// <-- Mod rewrite selector
		if ($this->core->modrtype == 1)
		{
			$this->seo_unset_opts = array('cache_layer', 'rem_ids');
		}
		else if (!$this->core->seo_opt['cache_layer'])
		{
			$this->seo_unset_opts = array('rem_ids');
		}

		$this->modrtype_lang = $this->set_phpbb_seo_links();
		$this->multiple_options['modrtype_lang'] = $this->modrtype_lang['titles'];

		if (@isset($this->core->seo_opt['modrtype']))
		{
			$this->multiple_options['modrtype_values'] = array(1 => 1, 2 => 2, 3 => 3); // do not change;
		}

		// <-- Mod rewrite selector
		foreach ($this->seo_unset_opts as $opt)
		{
			if ($optkey = array_search($opt, $this->core->cache_config['dynamic_options']))
			{
				unset($this->core->cache_config['dynamic_options'][$optkey]);
			}
		}

		// We need shorter URLs with Virtual Folder Trick
		if ($this->core->seo_opt['virtual_folder'])
		{
			$this->lengh_limit = 20;
			$this->word_limit = 3;
		}
		else
		{
			$this->lengh_limit = 30;
			$this->word_limit = 5;
		}

		$related_installed = false;

		switch ($mode)
		{
			case 'settings':
				$this->write_type = 'forum';
				$display_vars['title'] = 'ACP_PHPBB_SEO_CLASS';
				$this->user->lang['ACP_PHPBB_SEO_CLASS_EXPLAIN'] = sprintf($this->user->lang['ACP_PHPBB_SEO_CLASS_EXPLAIN'], $this->modrtype_lang['ulink'], $this->modrtype_lang['uforumlink'], '</p><hr/><p><b>' . $this->user->lang['ACP_PHPBB_SEO_MODE'] . ' : ' . $this->modrtype_lang['link'] . ' - ( ' . $this->modrtype_lang['forumlink'] . ' )</b></p><hr/><p>');
				$display_vars['vars'] = array();
				$i = 2;
				$display_vars['vars']['legend1'] = 'ACP_PHPBB_SEO_CLASS';

				foreach($this->core->cache_config['dynamic_options'] as $optionname => $optionvalue)
				{
					if (@is_bool($this->core->seo_opt[$optionvalue]))
					{
						if ($optionvalue == 'virtual_root' && !$this->core->seo_path['phpbb_script'])
						{
							continue;
						}

						$display_vars['vars'][$optionvalue] = array('lang' => $optionvalue, 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true, 'lang_explain' => $optionvalue . '_explain');
						$this->new_config[$optionvalue] = $this->core->seo_opt[$optionvalue];
					}
					else if (@isset($this->multiple_options[$optionvalue . '_values']))
					{
						$this->dyn_select[$optionvalue] = $this->multiple_options[$optionvalue . '_values'];
						$display_vars['vars'][$optionvalue] = array('lang' => $optionvalue, 'validate' => 'string', 'type' => 'select', 'method' => 'select_string', 'explain' => true, 'lang_explain' => $optionvalue . '_explain');
						$this->new_config[$optionvalue] = $this->core->seo_opt[$optionvalue];
					}
					else if (is_array($optionvalue))
					{
						$display_vars['vars']['legend' . $i] = $optionname;
						$i++;

						foreach ($optionvalue as $key => $value)
						{
							$this->array_type_cfg[$optionname . '_' . $key] = array('main' => $optionname, 'sub' => $key);

							if (is_bool($value))
							{
								$display_vars['vars'][$optionname . '_' . $key] = array('lang' => $optionname . '_' . $key, 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true, 'lang_explain' => $optionname . '_' . $key . '_explain');
								$this->new_config[$optionname . '_' . $key] = $this->core->seo_opt[$optionname][$key];
							}
							else if (@isset($this->multiple_options[$optionname][$key . '_values']))
							{
								$this->dyn_select[$optionname . '_' . $key] = $this->multiple_options[$optionname][$key . '_values'];
								$display_vars['vars'][$optionname . '_' . $key] = array('lang' => $optionname . '_' . $key, 'validate' => 'string', 'type' => 'select', 'method' => 'select_string', 'explain' => true, 'lang_explain' => $optionname . '_' . $key . '_explain');
								$this->new_config[$optionname . '_' . $key] = $this->core->seo_opt[$optionname][$key];
							}
							else
							{
								$display_vars['vars'][$optionname . '_' . $key] = array('lang' => $optionname . '_' . $key, 'validate' => 'string:0:50', 'type' => 'text:50:50', 'explain' => true, 'lang_explain' => $optionname . '_' . $key . '_explain');
								$this->new_config[$optionname . '_' . $key] = $this->core->seo_opt[$optionname][$key];
							}
						}
					}
				}

				break;
			case 'forum_url':
				// used for cache
				$this->write_type = 'forum';

				$forbidden = array(
					$this->core->seo_static['forum'],
					$this->core->seo_static['global_announce'],
					$this->core->seo_static['user'],
					$this->core->seo_static['topic'],
					$this->core->seo_static['atopic'],
					$this->core->seo_static['utopic'],
					$this->core->seo_static['leaders'],
					$this->core->seo_static['post'],
					$this->core->seo_static['group'],
					$this->core->seo_static['npost'],
					$this->core->seo_static['index'],
				);

				if ($this->core->modrtype == 1 || !$this->core->seo_opt['cache_layer'])
				{
					trigger_error($this->user->lang['ACP_NO_FORUM_URL'] . preg_replace('`(&amp;|&|\?)mode=forum_url`i', '', adm_back_link($this->u_action)));

					break;
				}

				$display_vars['title'] = 'ACP_FORUM_URL';
				$this->user->lang['ACP_FORUM_URL_EXPLAIN'] .= '</p><hr/><p><b>' . $this->user->lang['ACP_PHPBB_SEO_VERSION'] . ' : ' . $this->modrtype_lang['link'] . ' - ( ' . $this->modrtype_lang['forumlink'] . ' )</b></p><hr/><p>';
				$display_vars['vars'] = array();
				$display_vars['vars']['legend1'] = 'ACP_FORUM_URL';

				$sql = "SELECT forum_id, forum_name
					FROM " . FORUMS_TABLE . "
					ORDER BY left_id ASC";
				$result = $this->db->sql_query($sql);

				$forum_url_title = $error_cust = '';

				while($row = $this->db->sql_fetchrow($result))
				{
					$this->forum_ids[$row['forum_id']] = $row['forum_name'];
				}

				$this->db->sql_freeresult($result);

				// take care of deleted forums
				foreach ($this->core->cache_config['forum_urls'] as $fid => $null)
				{
					if (!isset($this->forum_ids[$fid]))
					{
						unset($this->core->cache_config['forum_urls'][$fid]);
					}
				}

				foreach ($this->forum_ids as $forum_id => $forum_name)
				{
					$error_cust = '';

					// Is the URL cached already ?
					if (empty($this->core->cache_config['forum_urls'][$forum_id]))
					{
						// Suggest the one from the title
						$forum_url_title = $this->core->format_url($forum_name, $this->core->seo_static['forum']);

						if (!in_array($forum_url_title, $forbidden))
						{
							if (array_search($forum_url_title, $this->core->cache_config['forum_urls']))
							{
								$this->new_config['forum_url' . $forum_id] = $forum_url_title .  $this->core->seo_delim['forum'] . $forum_id;
								$error_cust = '<li>&nbsp;' . sprintf($this->user->lang['SEO_ADVICE_DUPE'], $forum_url_title) . '</li>';
							}
							else
							{
								$this->new_config['forum_url' . $forum_id] = $forum_url_title . (@$this->core->cache_config['settings']['rem_ids'] ? '': $this->core->seo_delim['forum'] . $forum_id);
							}
						}
						else
						{
							$this->new_config['forum_url' . $forum_id] = $forum_url_title . $this->core->seo_delim['forum'] . $forum_id;
							$error_cust = '<li>&nbsp;' . sprintf($this->user->lang['SEO_ADVICE_RESERVED'], $forum_url_title) . '</li>';
						}

						$title = '<b style="color:red">' . $forum_name . ' - ID ' . $forum_id . '</b>';
						$status_msg = '<b style="color:red">' . $this->user->lang['SEO_CACHE_URL_NOT_OK'] . '</b>';
						$status_msg .= '<br/><span style="color:red">' . $this->user->lang['SEO_CACHE_URL'] . '&nbsp;:</span>&nbsp;' . $this->new_config['forum_url' . $forum_id] . $this->core->seo_ext['forum'];

						$display_vars['vars']['forum_url' . $forum_id] = array(
							'lang'					=> $title,
							'validate'				=> 'string',
							'type'					=> 'custom',
							'method'				=> 'forum_url_input',
							'explain'				=> true,
							'lang_explain_custom'	=> $status_msg,
							'append'				=> $this->seo_advices($this->new_config['forum_url' . $forum_id], $forum_id, false, $error_cust),
						);
					}
					else
					{
						// Cached
						$this->new_config['forum_url' . $forum_id] = $this->core->cache_config['forum_urls'][$forum_id];

						$title = '<b style="color:green">' . $forum_name . ' - ID ' . $forum_id . '</b>';
						$status_msg = '<span style="color:green">' . $this->user->lang['SEO_CACHE_URL_OK'] . '&nbsp;:</span>&nbsp;<b style="color:green">' . $this->new_config['forum_url' . $forum_id] . '</b>';
						$status_msg .= '<br/><span style="color:green">' . $this->user->lang['SEO_CACHE_URL'] . '&nbsp;:</span>&nbsp;' . $this->new_config['forum_url' . $forum_id] . $this->core->seo_ext['forum'];

						$display_vars['vars']['forum_url' . $forum_id] = array(
							'lang'					=> $title,
							'validate'				=> 'string:0:100',
							'type'					=> 'custom',
							'method'				=> 'forum_url_input',
							'explain'				=> true,
							'lang_explain_custom'	=> $status_msg,
							'append'				=> $this->seo_advices($this->new_config['forum_url' . $forum_id], $forum_id, true),
						);
					}
				}

				break;
			case 'htaccess':
				$this->write_type = 'htaccess';
				$display_vars['title'] = 'ACP_HTACCESS';
				$this->user->lang['ACP_HTACCESS_EXPLAIN'] .= '</p><hr/><p><b>' . $this->user->lang['ACP_PHPBB_SEO_VERSION'] . ' : ' . $this->modrtype_lang['link'] . ' - ( ' . $this->modrtype_lang['forumlink'] . ' )</b></p><p>';
				$display_vars['vars'] = array();
				$display_vars['vars']['legend1'] = 'ACP_HTACCESS';
				$display_vars['vars']['save'] = array('lang' => 'SEO_HTACCESS_SAVE', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true);
				$display_vars['vars']['more_options'] = array('lang' => 'SEO_MORE_OPTION', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true);
				$this->new_config['save'] = false;
				$cfg_array = ($this->request->is_set('config')) ? utf8_normalize_nfc($this->request->variable('config', array('' => ''), true)) : $this->new_config;
				$this->new_config['more_options'] = isset($cfg_array['more_options']) ? $cfg_array['more_options'] : false;
				$this->new_config['slash'] = isset($cfg_array['slash']) ? $cfg_array['slash'] : false;
				$this->new_config['wslash'] = isset($cfg_array['wslash']) ? $cfg_array['wslash'] : false;
				$this->new_config['rbase'] = isset($cfg_array['rbase']) ? $cfg_array['rbase'] : false;

				if ($this->core->seo_path['phpbb_script'] && !$this->core->seo_opt['virtual_root'])
				{
					$display_vars['vars']['rbase'] = array('lang' => 'SEO_HTACCESS_RBASE', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true);
				}

				if ($this->new_config['more_options'])
				{
					$display_vars['vars']['slash'] = array('lang' => 'SEO_HTACCESS_SLASH', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true);
					$display_vars['vars']['wslash'] = array('lang' => 'SEO_HTACCESS_WSLASH', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true);
				}

				// Dirty yet simple templating
				$this->user->lang['ACP_HTACCESS_EXPLAIN'] .= $this->seo_htaccess();

				$this->template->assign_vars(array(
					'S_SEO_HTACCESS'	=> 1,
				));

				break;
			case 'extended':
				$display_vars = array(
					'title'	=> 'ACP_SEO_EXTENDED',
					'vars'	=> array(
						'legend1'			=> 'SEO_EXTERNAL_LINKS',
						'seo_ext_links'		=> array('lang' => 'SEO_EXTERNAL_LINKS', 'validate' => 'bool', 'type' => 'radio:enabled_disabled', 'explain' => true, 'default' => 1),
						'seo_ext_subdomain'	=> array('lang' => 'SEO_EXTERNAL_SUBDOMAIN', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true, 'default' => 0),
						'seo_ext_classes'	=> array('lang' => 'SEO_EXTERNAL_CLASSES', 'validate' => 'string', 'type' => 'text:40:250', 'explain' => true, 'default' => ''),
					),
				);

				// Related topics
				if (!empty($this->config['seo_related_on']))
				{
					$related_installed = true;

					$this->user->add_lang_ext('phpbbseo/usu', 'acp_usu_install');

					$display_vars['vars'] += array(
						'legend2'					=> 'RELATED_TOPICS',
						'seo_related'				=> array('lang' => 'SEO_RELATED', 'validate' => 'bool', 'type' => 'radio:enabled_disabled', 'explain' => true, 'append' => !empty($this->config['seo_related']) ? '<br/>' . (!empty($this->config['seo_related_fulltext']) ? $this->user->lang['FULLTEXT_INSTALLED'] : $this->user->lang['FULLTEXT_NOT_INSTALLED']) : '', 'default' => 0),
						'seo_related_check_ignore'	=> array('lang' => 'SEO_RELATED_CHECK_IGNORE', 'validate' => 'bool', 'type' => 'radio:enabled_disabled', 'explain' => true, 'default' => 0),
						'seo_related_limit'			=> array('lang' => 'SEO_RELATED_LIMIT', 'validate' => 'int:2:25', 'type' => 'text:3:4', 'explain' => true, 'default' => 5),
						'seo_related_allforums'		=> array('lang' => 'SEO_RELATED_ALLFORUMS', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true, 'default' => 0),
					);
				}

				// dynamic meta tag mod
				if (!empty($this->config['seo_meta_on']))
				{
					$display_vars['vars'] += array(
						'legend3' 					=> 'SEO_META',
						'seo_meta_title'			=> array('lang' => 'SEO_META_TITLE', 'validate' => 'string:0:225', 'type' => 'text:40:250', 'explain' => true, 'default' => $this->config['sitename']),
						'seo_meta_desc'				=> array('lang' => 'SEO_META_DESC', 'validate' => 'string:0:225', 'type' => 'text:40:250', 'explain' => true, 'default' => $this->config['site_desc']),
						'seo_meta_desc_limit'		=> array('lang' => 'SEO_META_DESC_LIMIT', 'validate' => 'int:5:40', 'type' => 'text:3:4', 'explain' => true, 'default' => 25),
						'seo_meta_bbcode_filter'	=> array('lang' => 'SEO_META_BBCODE_FILTER', 'validate' => 'string:0:225', 'type' => 'text:40:250', 'explain' => true, 'default' => 'img|url|flash|code'),
						'seo_meta_keywords'			=> array('lang' => 'SEO_META_KEYWORDS', 'validate' => 'string:0:225', 'type' => 'text:40:250', 'explain' => true, 'default' => $this->config['site_desc']),
						'seo_meta_keywords_limit'	=> array('lang' => 'SEO_META_KEYWORDS_LIMIT', 'validate' => 'int:5:40', 'type' => 'text:3:4', 'explain' => true, 'default' => 15),
						'seo_meta_min_len'			=> array('lang' => 'SEO_META_MIN_LEN', 'validate' => 'int:0:10', 'type' => 'text:3:4', 'explain' => true, 'default' => 2),
						'seo_meta_check_ignore'		=> array('lang' => 'SEO_META_CHECK_IGNORE', 'validate' => 'bool', 'type' => 'radio:enabled_disabled', 'explain' => true, 'default' => 0),
						'seo_meta_lang'				=> array('lang' => 'SEO_META_LANG', 'validate' => 'lang', 'type' => 'select', 'method' => 'language_select', 'params' => array('{CONFIG_VALUE}'), 'explain' => true,  'default' => $this->config['default_lang']),
						'seo_meta_copy'				=> array('lang' => 'SEO_META_COPY', 'validate' => 'string:0:225', 'type' => 'text:40:250', 'explain' => true, 'default' => $this->config['sitename']),
						'seo_meta_file_filter'		=> array('lang' => 'SEO_META_FILE_FILTER', 'validate' => 'string:0:225', 'type' => 'text:40:250', 'explain' => true, 'default' => 'ucp'),
						'seo_meta_get_filter'		=> array('lang' => 'SEO_META_GET_FILTER', 'validate' => 'string:0:225', 'type' => 'text:40:250', 'explain' => true, 'default' => 'style,hilit,sid'),
						'seo_meta_robots'			=> array('lang' => 'SEO_META_ROBOTS', 'validate' => 'string:0:225', 'type' => 'text:25:150', 'explain' => true, 'default' => 'index,follow'),
						'seo_meta_noarchive'		=> array('lang' => 'SEO_META_NOARCHIVE', 'validate' => 'string:0:225', 'multiple_validate' => 'int', 'type' => 'custom', 'method' => 'select_multiple', 'params' => array('{CONFIG_VALUE}', '{KEY}', $this->forum_select()), 'explain' => true, 'default' => ''),
					);
				}

				// Optimal title
				if (!empty($this->config['seo_optimal_title_on']))
				{
					$display_vars['vars'] += array(
						'legend4'				=> 'SEO_PAGE_TITLES',
						'seo_append_sitename'	=> array('lang' => 'SEO_APPEND_SITENAME', 'validate' => 'bool', 'type' => 'radio:yes_no', 'explain' => true, 'default' => 0),
					);
				}

				// install if necessary
				foreach ($display_vars['vars'] as $config_name => $config_setup)
				{
					if (strpos($config_name, 'legend') !== false)
					{
						continue;
					}

					if (!isset($this->config[$config_name]))
					{
						set_config($config_name, $config_setup['default']);
						unset($display_vars['vars'][$config_name]['default']);
					}
				}

				$this->new_config = $this->config;
				break;
			default:
				trigger_error('NO_MODE', E_USER_ERROR);
			break;
		}

		$error = array();
		$seo_msg = array();
		$cfg_array = ($this->request->is_set('config')) ? utf8_normalize_nfc($this->request->variable('config', array('' => ''), true)) : $this->new_config;

		if ($submit && !check_form_key($form_key))
		{
			$error[] = $this->user->lang['FORM_INVALID'];
		}

		// We validate the complete config if whished
		validate_config_vars($display_vars['vars'], $cfg_array, $error);

		// Do not write values if there is an error
		if (!empty($error))
		{
			$submit = false;
		}

		$additional_notes = '';

		// We go through the display_vars to make sure no one is trying to set variables he/she is not allowed to...
		foreach ($display_vars['vars'] as $config_name => $cfg_setup)
		{
			if ((!isset($cfg_array[$config_name]) && @$cfg_setup['method'] != 'select_multiple') || strpos($config_name, 'legend') !== false)
			{
				continue;
			}

			// Handle multiple select options
			if (!empty($cfg_setup['method']) && $cfg_setup['method'] == 'select_multiple')
			{
				if (isset($_POST['multiple_' . $config_name]))
				{
					$m_values = utf8_normalize_nfc($this->request->variable('multiple_' . $config_name, array('' => '')));
					$validate_int = !empty($cfg_setup['multiple_validate']) && $cfg_setup['multiple_validate'] == 'int' ? true : false;

					foreach($m_values as $k => $v)
					{
						if ($validate_int)
						{
							$v = max(0, (int) $v);
						}

						if (empty($v))
						{
							unset($m_values[$k]);
						}
						else
						{
							$m_values[$k] = $v;
						}
					}

					sort($m_values);

					$this->new_config[$config_name] = $m_values;
					$config_value = implode(',', $m_values);

					if (strlen($config_value) > 255)
					{
						$error[] = sprintf($this->user->lang['SETTING_TOO_LONG'], $this->user->lang[$cfg_setup['lang']], 255);
					}

					$submit = empty($error);
				}
				else
				{
					if ($submit)
					{
						$this->new_config[$config_name] = array();
						$config_value = '';
					}
					else
					{
						$config_value = $this->new_config[$config_name];
						$this->new_config[$config_name] = !empty($config_value) ? explode(',', $config_value) : array();
					}
				}
			}
			else
			{
				$this->new_config[$config_name] = $config_value = $cfg_array[$config_name];
			}

			if ($submit)
			{
				// In case we deal with forum URLs
				if ($mode == 'forum_url' && preg_match('`^forum_url([0-9]+)$`', $config_name, $matches))
				{
					// Check if this is an actual forum_id
					if (isset($this->forum_ids[$matches[1]]))
					{
						$forum_id = intval($matches[1]);
						$config_value = $this->core->format_url($config_value, $this->core->seo_static['forum']);

						// Remove delim if required
						while (preg_match('`^[a-z0-9_-]+' . $this->core->seo_delim['forum'] . '[0-9]+$`i', $config_value))
						{
							$config_value = preg_replace('`^([a-z0-9_-]+)' . $this->core->seo_delim['forum'] . '[0-9]+$`i', '\\1', $config_value);

							if (@$this->core->cache_config['settings']['rem_ids'])
							{
								$seo_msg['SEO_ADVICE_DELIM_REM'] = '<li>&nbsp;' . $this->user->lang['SEO_ADVICE_DELIM_REM'] . '</li>';
							}
						}

						// Forums cannot end with the pagination param
						while (preg_match('`^[a-z0-9_-]+' . $this->core->seo_delim['start'] . '[0-9]+$`i', $config_value))
						{
							$config_value = preg_replace('`^([a-z0-9_-]+)' . $this->core->seo_delim['start'] . '[0-9]+$`i', "\\1", $config_value);
							$seo_msg['SEO_ADVICE_START'] = '<li>&nbsp;' . $this->user->lang['SEO_ADVICE_START'] . '</li>';
						}

						// Only update if the value is not a static one for forums
						if (!in_array($config_value, $forbidden))
						{
							// and updated (sic)
							if ($config_value != @$this->core->cache_config['forum_urls'][$forum_id])
							{
								// and if not already set
								if (!array_search($config_value, $this->core->cache_config['forum_urls']))
								{
									$this->core->cache_config['forum_urls'][$forum_id] = $config_value . (@$this->core->cache_config['settings']['rem_ids'] ? '': $this->core->seo_delim['forum'] . $forum_id);
								}
								else
								{
									$seo_msg['SEO_ADVICE_DUPE_' . $forum_id] = '<li>&nbsp;' . sprintf($this->user->lang['SEO_ADVICE_DUPE'], $config_value) . '</li>';
								}
							}
						}
						else
						{
							$seo_msg['SEO_ADVICE_RESERVED_' . $forum_id] = '<li>&nbsp;' . sprintf($this->user->lang['SEO_ADVICE_RESERVED'], $config_value) . '</li>';
						}
					}
				}
				else if ($mode == 'settings')
				{
					if (isset($this->array_type_cfg[$config_name]) && isset($this->core->seo_opt[$this->array_type_cfg[$config_name]['main']][$this->array_type_cfg[$config_name]['sub']]))
					{
						if (is_bool($this->core->seo_opt[$this->array_type_cfg[$config_name]['main']][$this->array_type_cfg[$config_name]['sub']]))
						{
							$this->core->cache_config['settings'][$this->array_type_cfg[$config_name]['main']][$this->array_type_cfg[$config_name]['sub']] = ($config_value == 1) ? true : false;
						}
						else if (is_numeric($this->core->seo_opt[$this->array_type_cfg[$config_name]['main']][$this->array_type_cfg[$config_name]['sub']]))
						{
							$this->core->cache_config['settings'][$this->array_type_cfg[$config_name]['main']][$this->array_type_cfg[$config_name]['sub']] = intval($config_value);
						}
						else if (is_string($this->core->seo_opt[$this->array_type_cfg[$config_name]['main']][$this->array_type_cfg[$config_name]['sub']]))
						{
							$this->core->cache_config['settings'][$this->array_type_cfg[$config_name]['main']][$this->array_type_cfg[$config_name]['sub']] = $config_value;
						}
					}
					else if (isset($this->core->seo_opt[$config_name]))
					{
						if (is_bool($this->core->seo_opt[$config_name]))
						{
							$this->core->cache_config['settings'][$config_name] = ($config_value == 1) ? true : false;
						}
						else if (is_numeric($this->core->seo_opt[$config_name]))
						{
							$this->core->cache_config['settings'][$config_name] = intval($config_value);
						}
						else if (is_string($this->core->seo_opt[$config_name]))
						{
							$this->core->cache_config['settings'][$config_name] = $config_value;
						}
					}

					// Let's make sure that the proper field was added to the topic table
					if ($config_name === 'sql_rewrite' && $config_value == 1 && !$this->core->seo_opt['sql_rewrite'])
					{
						$db_tools = new \phpbb\db\tools($this->db);
						$db_tools->db->sql_return_on_error(true);

						if (!$db_tools->sql_column_exists(TOPICS_TABLE, 'topic_url'))
						{
							$db_tools->sql_column_add(TOPICS_TABLE, 'topic_url', array('VCHAR:255', ''));
						}

						$additional_notes = sprintf($this->user->lang['SYNC_TOPIC_URL_NOTE'], '<a href="' . $this->core->seo_path['phpbb_url'] . 'phpbb_seo/sync_url.' . $this->php_ext . '" onclick="window.open(this.href); return false;">', '</a>');

						if ($db_tools->db->get_sql_error_triggered())
						{
							$error[] = '<b>' . $this->user->lang['sql_rewrite'] . '</b> : ' . $this->user->lang['SEO_SQL_ERROR'] . ' [ ' . $db_tools->db->get_sql_layer() . ' ] : ' . $db_tools->db->sql_error_returned['message'] . ' [' . $db_tools->db->sql_error_returned['code'] . ']' . '<br/>' . $this->user->lang['SEO_SQL_TRY_MANUALLY'] . '<br/>' . $db_tools->db->sql_error_sql;
							$submit = false;
						}

						$db_tools->db->sql_return_on_error(false);
					}
				}
				else if ($mode == 'extended')
				{
					set_config($config_name, $config_value);
				}
			}
		}

		if (sizeof($error))
		{
			$submit = false;
		}

		if ($submit)
		{
			if ($mode == 'htaccess')
			{
				if ($this->new_config['save'])
				{
					$this->write_cache($this->write_type);

					add_log('admin', 'SEO_LOG_CONFIG_' . strtoupper($mode));
				}
			}
			else if ($mode == 'extended')
			{
				add_log('admin', 'SEO_LOG_CONFIG_' . strtoupper($mode));

				trigger_error($this->user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
			}
			else
			{
				if ($this->write_cache($this->write_type))
				{
					global $msg_long_text;

					ksort($this->core->cache_config[$this->write_type]);

					add_log('admin', 'SEO_LOG_CONFIG_' . strtoupper($mode));

					$msg = !empty($seo_msg) ? '<br /><h1 style="color:red;text-align:left;">' . $this->user->lang['SEO_VALIDATE_INFO'] . '</h1><ul style="text-align:left;">' . implode(' ', $seo_msg) . '</ul><br />' : '';

					$msg_long_text = $this->user->lang['SEO_CACHE_MSG_OK'] . $msg . adm_back_link($this->u_action);

					if ($additional_notes)
					{
						$msg_long_text .= "<br/><br/>$additional_notes";
					}

					trigger_error(false);
				}
				else
				{
					trigger_error($this->user->lang['SEO_CACHE_MSG_FAIL'] . adm_back_link($this->u_action));
				}
			}
		}

		$this->tpl_name = 'acp_board';
		$this->page_title = $display_vars['title'];

		$this->core->seo_end();

		$l_title_explain = $this->user->lang[$display_vars['title'] . '_EXPLAIN'];

		if ($mode != 'extended')
		{
			$l_title_explain .= $mode == 'htaccess' ? '' : $this->check_cache_folder($this->core->seo_opt['cache_folder']);
		}

		$this->template->assign_vars(array(
			'L_TITLE'			=> $this->user->lang[$display_vars['title']],
			'L_TITLE_EXPLAIN'	=> $l_title_explain,

			'S_ERROR'			=> (sizeof($error)) ? true : false,
			'ERROR_MSG'			=> implode('<br />', $error),

			'U_ACTION'			=> $this->u_action,
		));

		// Output relevant page
		foreach ($display_vars['vars'] as $config_key => $vars)
		{
			if (!is_array($vars) && strpos($config_key, 'legend') === false)
			{
				continue;
			}

			if (strpos($config_key, 'legend') !== false)
			{
				$this->template->assign_block_vars('options', array(
					'S_LEGEND'		=> true,
					'LEGEND'		=> (isset($this->user->lang[$vars])) ? $this->user->lang[$vars] : $vars,
				));

				continue;
			}

			$type = explode(':', $vars['type']);
			$l_explain = '';

			if ($vars['explain'] && isset($vars['lang_explain']))
			{
				$l_explain = (isset($this->user->lang[$vars['lang_explain']])) ? $this->user->lang[$vars['lang_explain']] : $vars['lang_explain'];
			}
			else if ($vars['explain'] && isset($vars['lang_explain_custom']))
			{
				$l_explain = $vars['lang_explain_custom'];
			}
			else if ($vars['explain'])
			{
				$l_explain = (isset($this->user->lang[$vars['lang'] . '_EXPLAIN'])) ? $this->user->lang[$vars['lang'] . '_EXPLAIN'] : '';
			}

			$this->template->assign_block_vars('options', array(
				'KEY'			=> $config_key,
				'TITLE'			=> (isset($this->user->lang[$vars['lang']])) ? $this->user->lang[$vars['lang']] : $vars['lang'],
				'S_EXPLAIN'		=> $vars['explain'],
				'TITLE_EXPLAIN'	=> $l_explain,
				'CONTENT'		=> build_cfg_template($type, $config_key, $this->new_config, $config_key, $vars),
			));

			unset($display_vars['vars'][$config_key]);
		}
	}

	/**
	*  forum_url_check validation
	*/
	function forum_url_input($value, $key)
	{
		return '<input id="' . $key . '" type="text" size="40" maxlength="255" name="config[' . $key . ']" value="' . $value . '" /> ';
	}

	/**
	*  select_string custom select string
	*/
	function select_string($value, $key)
	{
		$select_ary = $this->dyn_select[$key];
		$html = '';

		foreach ($select_ary as $sel_value)
		{
			if (@isset($this->array_type_cfg[$key]))
			{
				$selected = ($sel_value == @$this->core->seo_opt[$this->array_type_cfg[$key]['main']][$this->array_type_cfg[$key]['sub']]) ? ' selected="selected"' : '';
				$sel_title = @isset($this->multiple_options[$this->array_type_cfg[$key]['main']][$this->array_type_cfg[$key]['sub'] . '_lang'][$sel_value]) ? $this->multiple_options[$this->array_type_cfg[$key]['main']][$this->array_type_cfg[$key]['sub'] . '_lang'][$sel_value] : $sel_value;
			}
			else
			{
				$selected = ($sel_value == @$this->core->cache_config['settings'][$key]) ? ' selected="selected"' : '';
				$sel_title = @isset($this->multiple_options[$key . '_lang'][$sel_value]) ? $this->multiple_options[$key . '_lang'][$sel_value] : $sel_value;
			}

			$html .= '<option value="' . $sel_value . '"' . $selected . '>' . $sel_title . '</option>';
		}

		return $html;
	}

	/**
	*  seo_advices Always needed :-)
	*/
	function seo_advices($url, $forum_id, $cached = false, $error_cust = '')
	{
		$seo_advice = '';

		// Check how well is the URL SEO wise
		if (!empty($error_cust))
		{
			$seo_advice .= $error_cust;
		}

		if (strlen($url) > $this->lengh_limit)
		{
			// Size
			$seo_advice .= '<li>&nbsp;' . $this->user->lang['SEO_ADVICE_LENGTH'] . '</li>';
		}

		if (preg_match('`^[a-z0-9_-]+' . $this->core->seo_delim['forum'] . '[0-9]+$`i', $url))
		{
			// With delimiter and id
			if (@$this->core->cache_config['settings']['rem_ids'])
			{
				$seo_advice .= '<li style="color:red">&nbsp;' . $this->user->lang['SEO_ADVICE_DELIM'] . '</li>';
			}
		}

		if ($this->core->seo_static['forum'] == $url)
		{
			// default
			$seo_advice .= '<li>&nbsp;' . $this->user->lang['SEO_ADVICE_DEFAULT'] . '</li>';
		}

		// Check the number of word
		$url_words = explode('-', $url);

		if (count($url_words) > $this->word_limit)
		{
			$seo_advice .= '<li>&nbsp;' . $this->user->lang['SEO_ADVICE_WORDS'] . '</li>';
		}

		return $seo_advice ? '<ul  style="color:red">' . $seo_advice . '</ul>' : '';
	}

	/**
	*  seo_htaccess The evil one ;-)
	*/
	function seo_htaccess($html = true)
	{
		$htaccess_code = '';
		$htaccess_tpl = '';

		if (empty($htaccess_code))
		{
			// get mods .htaccess tpls
			$mods_ht = $this->get_mods_ht();
			$default_slash = '/';
			$wierd_slash = '';
			$phpbb_path = trim($this->core->seo_path['phpbb_script'], '/');
			$show_rewritebase_opt = false;
			$rewritebase = '';
			$wierd_slash = $this->new_config['wslash'] ? '<b style="color:red">/</b>' : '';
			$default_slash = $this->new_config['slash'] ? '' : '/';

			if (!empty($phpbb_path))
			{
				$phpbb_path = $phpbb_path . '/';

				if ($this->new_config['rbase'])
				{
					$rewritebase = $phpbb_path;
					$default_slash = $this->new_config['slash'] ? '/' : '';
				}

				$rewritebase = $this->new_config['rbase'] ? $phpbb_path : '';
				$show_rewritebase_opt = $this->core->seo_opt['virtual_root'] ? false : true;
			}

			$colors = array(
				'color'		=> '<b style="color:%1$s">%2$s</b>',
				'static'	=> '#A020F0',
				'ext'		=> '#6A5ACD',
				'delim'		=> '#FF00FF',
			);

			$tpl = array(
				'paginpage'	=> '/?(<b style="color:' . $colors['static'] . '">%1$s</b>([0-9]+)<b style="color:' . $colors['ext'] . '">%2$s</b>)?',
				'pagin'		=> '(<b style="color:' . $colors['delim'] . '">%1$s</b>([0-9]+))?<b style="color:' . $colors['ext'] . '">%2$s</b>',
				'static'	=> sprintf($colors['color'] , $colors['static'], '%1$s'),
				'ext'		=> sprintf($colors['color'] , $colors['ext'], '%1$s'),
				'delim'		=> sprintf($colors['color'] , $colors['delim'], '%1$s'),
			);

			$modrtype = array(
				1		=> 'SIMPLE',
				2		=> 'MIXED',
				1		=> 'SIMPLE',
				3		=> 'ADVANCED',
				'type'	=> intval($this->core->modrtype),
			);

			//
			$htaccess_tpl = '<b style="color:brown">&lt;IfModule</b> <b style="color:#FF00FF">mod_rewrite.c</b><b style="color:brown">&gt;</b>' . "\n\n";

			$htaccess_tpl .= '<b style="color:blue"># You may need to un-comment the following lines' . "\n";
			$htaccess_tpl .= '# Options +FollowSymlinks' . "\n";
			$htaccess_tpl .= '# To make sure that rewritten dir or file (/|.html) will not load dir.php in case it exist' . "\n";
			$htaccess_tpl .= '# Options -MultiViews' . "\n";
			$htaccess_tpl .= '# REMEBER YOU ONLY NEED TO STARD MOD REWRITE ONCE</b>' . "\n";
			$htaccess_tpl .= '<b style="color:green">RewriteEngine</b> <b style="color:#FF00FF">On</b>' . "\n";
			$htaccess_tpl .= '<b style="color:blue"># Uncomment the statement below if you want to make use of' . "\n";
			$htaccess_tpl .= '# HTTP authentication and it does not already work.' . "\n";
			$htaccess_tpl .= '# This could be required if you are for example using PHP via Apache CGI.' . "\n";
			$htaccess_tpl .= '# RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]</b>' . "\n";
			$htaccess_tpl .= '<b style="color:blue"># REWRITE BASE</b>' . "\n";
			$htaccess_tpl .= '<b style="color:green">RewriteBase</b> <b>/{REWRITEBASE}</b>' . "\n";
			$htaccess_tpl .= '<b style="color:blue"># HERE IS A GOOD PLACE TO FORCE CANONICAL DOMAIN</b>' . "\n";
			$htaccess_tpl .= '<b style="color:blue"># Define fully qualified ssl aware protocol</b>' . "\n";
			$htaccess_tpl .= '<b style="color:blue"># RewriteCond %{SERVER_PORT}s ^(443(s)|[0-9]+s)$</b>' . "\n";
			$htaccess_tpl .= '<b style="color:blue"># RewriteRule ^.*$ - [env=HttpFullProto:http%2://]</b>' . "\n";
			$htaccess_tpl .= '<b style="color:blue"># RewriteCond %{HTTP_HOST} !^' . str_replace(array('https://', 'http://', '.'), array('', '', '\\.'), trim($this->core->seo_path['root_url'], '/ ')) . '$ [NC]</b>' . "\n";
			$htaccess_tpl .= '<b style="color:blue"># RewriteRule ^(.*)$ ' . str_replace(array('https://', 'http://'), '%{ENV:HttpFullProto}', $this->core->seo_path['root_url']) . '{REWRITEBASE}$1 [QSA,L,R=301]</b>' . "\n\n";
			$htaccess_tpl .= '<b style="color:blue"># DO NOT GO FURTHER IF THE REQUESTED FILE / DIR DOES EXISTS</b>' . "\n";
			$htaccess_tpl .= '<b style="color:green">RewriteCond</b> %{REQUEST_FILENAME} -f [OR]' . "\n";
			$htaccess_tpl .= '<b style="color:green">RewriteCond</b> %{REQUEST_FILENAME} -d' . "\n";
			$htaccess_tpl .= '<b style="color:green">RewriteRule</b> . - [L]' . "\n";
			$htaccess_tpl .= '<b style="color:blue">#####################################################' . "\n";
			$htaccess_tpl .= '# PHPBB SEO REWRITE RULES ALL MODES' . "\n";
			$htaccess_tpl .= '#####################################################' . "\n";
			$htaccess_tpl .= '# AUTHOR : dcz www.phpbb-seo.com' . "\n";
			$htaccess_tpl .= '# STARTED : 01/2006' . "\n";
			$htaccess_tpl .= '#################################' . "\n";
			$htaccess_tpl .= '# FORUMS PAGES' . "\n";
			$htaccess_tpl .= '###############</b>' . "\n";

			if (!empty($this->core->seo_static['index']))
			{
				$htaccess_tpl .= '<b style="color:blue"># FORUM INDEX</b>' . "\n";
				$htaccess_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}{STATIC_INDEX}{EXT_INDEX}$ {DEFAULT_SLASH}{PHPBB_RPATH}index.{PHP_EX} [QSA,L,NC]' . "\n";
			}
			else
			{
				$htaccess_tpl .= '<b style="color:blue"># FORUM INDEX REWRITERULE WOULD STAND HERE IF USED. "forum" REQUIRES TO BE SET AS FORUM INDEX' . "\n";
				$htaccess_tpl .= '# RewriteRule ^{WIERD_SLASH}{PHPBB_LPATH}<b style="color:#A020F0">forum</b>\.<b style="color:#6A5ACD">html</b>$ {DEFAULT_SLASH}{PHPBB_RPATH}index.{PHP_EX} [QSA,L,NC]</b>' . "\n";
			}

			$htaccess_common_tpl = '<b style="color:blue"># PHPBB FILES ALL MODES</b>' . "\n";
			$htaccess_common_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}{STATIC_FILE_INDEX}{DELIM_FILE}[a-z0-9_-]+{DELIM_FILE}({STATIC_THUMB}{DELIM_FILE})?([0-9]+)$ {DEFAULT_SLASH}{PHPBB_RPATH}download/file.{PHP_EX}?id=$2&amp;t=$1 [QSA,L,NC]' . "\n";

			if ($this->core->seo_opt['profile_noids'])
			{
				$htaccess_common_tpl .= '<b style="color:blue"># PROFILES THROUGH USERNAME</b>' . "\n";
				$htaccess_common_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}{STATIC_USER}/([^/]+)/?$ {DEFAULT_SLASH}{PHPBB_RPATH}memberlist.{PHP_EX}?mode=viewprofile&amp;un=$1 [QSA,L,NC]' . "\n";
				$htaccess_common_tpl .= '<b style="color:blue"># USER MESSAGES THROUGH USERNAME</b>' . "\n";
				$htaccess_common_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}{STATIC_USER}/([^/]+)/(topics|posts){USER_PAGINATION}$ {DEFAULT_SLASH}{PHPBB_RPATH}search.{PHP_EX}?author=$1&amp;sr=$2&amp;start=$4 [QSA,L,NC]' . "\n";
			}
			else
			{
				$htaccess_common_tpl .= '<b style="color:blue"># PROFILES ALL MODES WITH ID</b>' . "\n";
				$htaccess_common_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}({STATIC_USER}|[a-z0-9_-]*{DELIM_USER})([0-9]+){EXT_USER}$ {DEFAULT_SLASH}{PHPBB_RPATH}memberlist.{PHP_EX}?mode=viewprofile&amp;u=$2 [QSA,L,NC]' . "\n";
				$htaccess_common_tpl .= '<b style="color:blue"># USER MESSAGES ALL MODES WITH ID</b>' . "\n";
				$htaccess_common_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}({STATIC_USER}|[a-z0-9_-]*{DELIM_USER})([0-9]+){DELIM_SR}(topics|posts){USER_PAGINATION}$ {DEFAULT_SLASH}{PHPBB_RPATH}search.{PHP_EX}?author_id=$2&amp;sr=$3&amp;start=$5 [QSA,L,NC]' . "\n";
			}

			$htaccess_common_tpl .= '<b style="color:blue"># GROUPS ALL MODES</b>' . "\n";
			$htaccess_common_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}({STATIC_GROUP}|[a-z0-9_-]*{DELIM_GROUP})([0-9]+){GROUP_PAGINATION}$ {DEFAULT_SLASH}{PHPBB_RPATH}memberlist.{PHP_EX}?mode=group&amp;g=$2&amp;start=$4 [QSA,L,NC]' . "\n";
			$htaccess_common_tpl .= '<b style="color:blue"># POST</b>' . "\n";
			$htaccess_common_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}{STATIC_POST}([0-9]+){EXT_POST}$ {DEFAULT_SLASH}{PHPBB_RPATH}viewtopic.{PHP_EX}?p=$1 [QSA,L,NC]' . "\n";
			$htaccess_common_tpl .= '<b style="color:blue"># ACTIVE TOPICS</b>' . "\n";
			$htaccess_common_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}{STATIC_ATOPIC}{ATOPIC_PAGINATION}$ {DEFAULT_SLASH}{PHPBB_RPATH}search.{PHP_EX}?search_id=active_topics&amp;start=$2&amp;sr=topics [QSA,L,NC]' . "\n";
			$htaccess_common_tpl .= '<b style="color:blue"># UNANSWERED TOPICS</b>' . "\n";
			$htaccess_common_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}{STATIC_UTOPIC}{UTOPIC_PAGINATION}$ {DEFAULT_SLASH}{PHPBB_RPATH}search.{PHP_EX}?search_id=unanswered&amp;start=$2&amp;sr=topics [QSA,L,NC]' . "\n";
			$htaccess_common_tpl .= '<b style="color:blue"># NEW POSTS</b>' . "\n";
			$htaccess_common_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}{STATIC_NPOST}{NPOST_PAGINATION}$ {DEFAULT_SLASH}{PHPBB_RPATH}search.{PHP_EX}?search_id=newposts&amp;start=$2&amp;sr=topics [QSA,L,NC]' . "\n";
			$htaccess_common_tpl .= '<b style="color:blue"># UNREAD POSTS</b>' . "\n";
			$htaccess_common_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}{STATIC_URPOST}{URPOST_PAGINATION}$ {DEFAULT_SLASH}{PHPBB_RPATH}search.{PHP_EX}?search_id=unreadposts&amp;start=$2 [QSA,L,NC]' . "\n";
			$htaccess_common_tpl .= '<b style="color:blue"># THE TEAM</b>' . "\n";
			$htaccess_common_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}{STATIC_LEADERS}{EXT_LEADERS}$ {DEFAULT_SLASH}{PHPBB_RPATH}memberlist.{PHP_EX}?mode=leaders [QSA,L,NC]' . "\n";
			$htaccess_common_tpl .= '<b style="color:blue"># HERE IS A GOOD PLACE TO ADD OTHER PHPBB RELATED REWRITERULES</b>' . "\n\n";

			// We now handle all modes at once (simple / mixed / advanced)
			$htaccess_tpl .= '<b style="color:blue"># FORUM ALL MODES</b>' . "\n";
			$htaccess_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}({STATIC_FORUM}|[a-z0-9_-]*{DELIM_FORUM})([0-9]+){FORUM_PAGINATION}$ {DEFAULT_SLASH}{PHPBB_RPATH}viewforum.{PHP_EX}?f=$2&amp;start=$4 [QSA,L,NC]' . "\n";
			$htaccess_tpl .= '<b style="color:blue"># TOPIC WITH VIRTUAL FOLDER ALL MODES</b>' . "\n";
			$htaccess_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}({STATIC_FORUM}|[a-z0-9_-]*{DELIM_FORUM})([0-9]+)/({STATIC_TOPIC}|[a-z0-9_-]*{DELIM_TOPIC})([0-9]+){TOPIC_PAGINATION}$ {DEFAULT_SLASH}{PHPBB_RPATH}viewtopic.{PHP_EX}?f=$2&amp;t=$4&amp;start=$6 [QSA,L,NC]' . "\n";
		//	Not needed any more
		//	$htaccess_tpl .= '<b style="color:blue"># GLOBAL ANNOUNCEMENTS WITH VIRTUAL FOLDER ALL MODES</b>' . "\n";
		//	$htaccess_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}{STATIC_GLOBAL_ANNOUNCE}{EXT_GLOBAL_ANNOUNCE}({STATIC_TOPIC}|[a-z0-9_-]*{DELIM_TOPIC})([0-9]+){TOPIC_PAGINATION}$ {DEFAULT_SLASH}{PHPBB_RPATH}viewtopic.{PHP_EX}?t=$2&amp;start=$4 [QSA,L,NC]' . "\n";
			$htaccess_tpl .= '<b style="color:blue"># TOPIC WITHOUT FORUM ID &amp; DELIM ALL MODES</b>' . "\n";
			$htaccess_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}([a-z0-9_-]*)/?({STATIC_TOPIC}|[a-z0-9_-]*{DELIM_TOPIC})([0-9]+){TOPIC_PAGINATION}$ {DEFAULT_SLASH}{PHPBB_RPATH}viewtopic.{PHP_EX}?forum_uri=$1&amp;t=$3&amp;start=$5 [QSA,L,NC]' . "\n";
			$htaccess_tpl .= $htaccess_common_tpl;

			// mods .htaccess pos1
			if (!empty($mods_ht['pos1']))
			{
				$htaccess_tpl .= $mods_ht['pos1'];
			}

			$htaccess_tpl .= '<b style="color:blue"># FORUM WITHOUT ID &amp; DELIM ALL MODES</b>' . "\n";

			if (trim($this->core->seo_ext['forum'],'/'))
			{
				$htaccess_tpl .= '<b style="color:blue"># THESE FOUR LINES MUST BE LOCATED AT THE END OF YOUR HTACCESS TO WORK PROPERLY</b>' . "\n";
				$htaccess_tpl .= '<b style="color:green">RewriteCond</b> %{REQUEST_FILENAME} !-f' . "\n";
				$htaccess_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}([a-z0-9_-]+)(-([0-9]+)){EXT_FORUM}$ {DEFAULT_SLASH}{PHPBB_RPATH}viewforum.{PHP_EX}?forum_uri=$1&amp;start=$3 [QSA,L,NC]' . "\n";
				$htaccess_tpl .= '<b style="color:green">RewriteCond</b> %{REQUEST_FILENAME} !-f' . "\n";
				$htaccess_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}([a-z0-9_-]+){EXT_FORUM}$ {DEFAULT_SLASH}{PHPBB_RPATH}viewforum.{PHP_EX}?forum_uri=$1 [QSA,L,NC]' . "\n";
			}
			else
			{
				$htaccess_tpl .= '<b style="color:blue"># THESE THREE LINES MUST BE LOCATED AT THE END OF YOUR HTACCESS TO WORK PROPERLY</b>' . "\n";
				$htaccess_tpl .= '<b style="color:green">RewriteCond</b> %{REQUEST_FILENAME} !-f' . "\n";
				$htaccess_tpl .= '<b style="color:green">RewriteCond</b> %{REQUEST_FILENAME} !-d' . "\n";
				$htaccess_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}([a-z0-9_-]+){FORUM_PAGINATION}$ {DEFAULT_SLASH}{PHPBB_RPATH}viewforum.{PHP_EX}?forum_uri=$1&amp;start=$3 [QSA,L,NC]' . "\n";
			}

			// fix for dumb clients unable to deal with base href
			$htaccess_tpl .= '<b style="color:blue"># FIX RELATIVE PATHS : FILES</b>' . "\n";
			$htaccess_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_RPATH}.+/(style\.{PHP_EX}|ucp\.{PHP_EX}|mcp\.{PHP_EX}|faq\.{PHP_EX}|download/file.{PHP_EX})$ {DEFAULT_SLASH}{PHPBB_RPATH}$1 [QSA,L,NC,R=301]' . "\n";
			$htaccess_tpl .= '<b style="color:blue"># FIX RELATIVE PATHS : IMAGES</b>' . "\n";
			$htaccess_tpl .= '<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_RPATH}.+/(styles/.*|images/.*)/$ {DEFAULT_SLASH}{PHPBB_RPATH}$1 [QSA,L,NC,R=301]' . "\n";
			$htaccess_tpl .= '<b style="color:blue"># END PHPBB PAGES' . "\n";
			$htaccess_tpl .= '#####################################################</b>' . "\n\n";

			// mods .htaccess pos2
			if (!empty($mods_ht['pos2']))
			{
				$htaccess_tpl .= $mods_ht['pos2'];
			}

			$htaccess_tpl .= '#
# The following 3 lines will rewrite URLs passed through the front controller
# to not require app.php in the actual URL. In other words, a controller is
# by default accessed at /app.php/my/controller, but can also be accessed at
# /my/controller
#
<b style="color:green">RewriteCond</b> %{REQUEST_FILENAME} !-f
<b style="color:green">RewriteCond</b> %{REQUEST_FILENAME} !-d
<b style="color:green">RewriteRule</b> ^{WIERD_SLASH}{PHPBB_LPATH}(.*)$ app.{PHP_EX} [QSA,L]' . "\n\n";

			$htaccess_tpl .= '<b style="color:brown">&lt;/IfModule&gt;</b>' . "\n\n";

			$htaccess_tpl .= '# With Apache 2.4 the "Order, Deny" syntax has been deprecated and moved from
# module mod_authz_host to a new module called mod_access_compat (which may be
# disabled) and a new "Require" syntax has been introduced to mod_authz_host.
# We could just conditionally provide both versions, but unfortunately Apache
# does not explicitly tell us its version if the module mod_version is not
# available. In this case, we check for the availability of module
# mod_authz_core (which should be on 2.4 or higher only) as a best guess.
<b style="color:brown">&lt;IfModule</b> <b style="color:#FF00FF">mod_version.c</b><b style="color:brown">&gt;</b>
	<b style="color:brown">&lt;IfVersion</b> <b style="color:#FF00FF">&lt; 2.4</b><b style="color:brown">&gt;</b>
		<b style="color:brown">&lt;Files</b> <b style="color:#FF00FF">"config.php"</b><b style="color:brown">&gt;</b>
			Order Allow,Deny
			Deny from All
		<b style="color:brown">&lt;/Files&gt;</b>
		<b style="color:brown">&lt;Files </b> <b style="color:#FF00FF">"common.php"</b><b style="color:brown">&gt;</b>
			Order Allow,Deny
			Deny from All
		<b style="color:brown">&lt;/Files&gt;</b>
	<b style="color:brown">&lt;/IfVersion&gt;</b>
	<b style="color:brown">&lt;Files</b> <b style="color:#FF00FF">"config.php"</b><b style="color:brown">&gt;</b>IfVersion</b> <b style="color:#FF00FF">&gt;= 2.4</b><b style="color:brown">&gt;</b>
		<b style="color:brown">&lt;Files</b> <b style="color:#FF00FF">"config.php"</b><b style="color:brown">&gt;</b>
			Require all denied
		<b style="color:brown">&lt;/Files&gt;</b>
		<b style="color:brown">&lt;Files </b> <b style="color:#FF00FF">"common.php"</b><b style="color:brown">&gt;</b>
			Require all denied
		<b style="color:brown">&lt;/Files&gt;</b>
	<b style="color:brown">&lt;/IfVersion&gt;</b>
<b style="color:brown">&lt;/IfModule&gt;</b>
<b style="color:brown">&lt;IfModule</b> <b style="color:#FF00FF">!mod_version.c</b><b style="color:brown">&gt;</b>
	<b style="color:brown">&lt;IfModule</b> <b style="color:#FF00FF">!mod_authz_core.c</b><b style="color:brown">&gt;</b>
		<b style="color:brown">&lt;Files</b> <b style="color:#FF00FF">"config.php"</b><b style="color:brown">&gt;</b>
			Order Allow,Deny
			Deny from All
		<b style="color:brown">&lt;/Files&gt;</b>
		<b style="color:brown">&lt;Files </b> <b style="color:#FF00FF">"common.php"</b><b style="color:brown">&gt;</b>
			Order Allow,Deny
			Deny from All
		<b style="color:brown">&lt;/Files&gt;</b>
	<b style="color:brown">&lt;/IfModule&gt;</b>
	<b style="color:brown">&lt;IfModule</b> <b style="color:#FF00FF">mod_authz_core.c</b><b style="color:brown">&gt;</b>
		<b style="color:brown">&lt;Files</b> <b style="color:#FF00FF">"config.php"</b><b style="color:brown">&gt;</b>
			Require all denied
		<b style="color:brown">&lt;/Files&gt;</b>
		<b style="color:brown">&lt;Files </b> <b style="color:#FF00FF">"common.php"</b><b style="color:brown">&gt;</b>
			Require all denied
		<b style="color:brown">&lt;/Files&gt;</b>
	<b style="color:brown">&lt;/IfModule&gt;</b>
<b style="color:brown">&lt;/IfModule&gt;</b>';

			if (!empty($default_slash) && $this->new_config['more_options'])
			{
				$default_slash = '<b style="color:red">' . $default_slash . '</b>';
			}

			// The tpl array
			$htaccess_tpl_vars = array();

			// handle the suffixes proper in the RegEx
			// set up pagination reg ex
			// set up ext bits
			$seo_ext = array(
				// force '/' for both / and empty ext to add /? in RegEx (which allows both cases)
				'pagination'	=> trim($this->core->seo_ext['pagination'], '/') ? str_replace('.', '\\.', $this->core->seo_ext['pagination']) : '/',
			);

			$reg_ex_page = sprintf($tpl['paginpage'], $this->core->seo_static['pagination'], $seo_ext['pagination'] . ($seo_ext['pagination'] === '/' ? '?' : ''));

			foreach ($this->core->seo_ext as $type => $value)
			{
				$_value = trim($value, '/');
				// force '/' for both / and empty ext to add /? in RegEx (which allows both cases)
				$seo_ext[$type] = $_value ? str_replace('.', '\\.', $value) : '/';
				$htaccess_tpl_vars['{' . strtoupper($type) . '_PAGINATION}'] = $_value ? sprintf($tpl['pagin'], $this->core->seo_delim['start'], $seo_ext[$type]) : $reg_ex_page;
				// use url/? to allow both url and url/ to work as expected
				$htaccess_tpl_vars['{EXT_' . strtoupper($type) . '}'] = sprintf($tpl['ext'] , $seo_ext[$type]) . ($_value ? '' : '?');
			}

			$htaccess_tpl_vars['{PAGE_PAGINATION}'] = $reg_ex_page;

			// static bits
			foreach ($this->core->seo_static as $type => $value)
			{
				if (!is_array($this->core->seo_static[$type]))
				{
					$htaccess_tpl_vars['{STATIC_' . strtoupper($type) . '}'] = sprintf($tpl['static'], $this->core->seo_static[$type]);
				}
			}

			// delim bits
			foreach ($this->core->seo_delim as $type => $value)
			{
				$htaccess_tpl_vars['{DELIM_' . strtoupper($type) . '}'] = sprintf($tpl['delim'], $this->core->seo_delim[$type]);
			}

			// common .htaccess vars
			$htaccess_tpl_vars += array(
				'{REWRITEBASE}'		=> $rewritebase,
				'{PHP_EX}'			=> $this->php_ext,
				'{PHPBB_LPATH}'		=> ($this->new_config['rbase'] || $this->core->seo_opt['virtual_root']) ? '' : $phpbb_path,
				'{PHPBB_RPATH}'		=> $this->new_config['rbase'] ? '' : $phpbb_path,
				'{DEFAULT_SLASH}'	=> $default_slash,
				'{WIERD_SLASH}'		=> $wierd_slash,
				'{MOD_RTYPE}'		=> $modrtype[$modrtype['type']],
			);

			// Parse .htaccess
			$htaccess_code = str_replace(array_keys($htaccess_tpl_vars), array_values($htaccess_tpl_vars), $htaccess_tpl);
		} // else the .htaccess is already generated

		if ($html)
		{
			// HTML output
			$htaccess_output = '</p>';

			// build location message
			if ($show_rewritebase_opt && $this->new_config['rbase'])
			{
				$msg_loc = sprintf($this->user->lang['SEO_HTACCESS_FOLDER_MSG'], '<em style="color:#000">' . $this->core->seo_path['phpbb_url'] . '</em>');
			}
			else
			{
				$msg_loc = sprintf($this->user->lang['SEO_HTACCESS_ROOT_MSG'], '<em style="color:#000">' . $this->core->seo_path['root_url'] . '</em>');
			}

			$htaccess_output .= '<div class="content"><hr/>' . "\n" . '<b style="color:red">&rArr;&nbsp;' . $msg_loc . '</b><br/><br/><hr/>' . "\n";
			$htaccess_output .= '<b>.htaccess :&nbsp;<a id="htaccess_toggle">' . $this->user->lang['SEO_SHOW'] . '&nbsp;/&nbsp;' . $this->user->lang['SEO_HIDE'] . '</a></b>' . "\n";
			$htaccess_output .= '<div id="htaccess_code"><dl style="padding:5px;background-color:#FFFFFF;border:1px solid #d8d8d8;font-size:12px;"><dt style="border-bottom:1px solid #CCCCCC;margin-bottom:3px;font-weight:bold;display:block;">&nbsp;<a id="htaccess_select">' . $this->user->lang['SEO_SELECT_ALL'] . '</a></dt>' . "\n";
			$htaccess_output .= '<dd ><code style="padding-top:5px;line-height:1.3em;color:#8b8b8b;font-weight:bold;font-family: monospace;white-space: pre;" id="htaccess_code_select"><br/><br/>' . str_replace("\n", '<br/>', $htaccess_code) . '</code></dd>' . "\n";
			$htaccess_output .= '</dl>' . "\n";
			$htaccess_output .= '<div style="padding:5px;margin-top:10px;background-color:#FFFFFF;border:1px solid #d8d8d8;font-size:12px;"><b>' . $this->user->lang['SEO_HTACCESS_CAPTION'] . ':</b><ul style="margin-left:30px;margin-top:10px;font-weight:bold;font-size:12px;">' . "\n";
			$htaccess_output .= '<li style="color:blue">&nbsp;' . $this->user->lang['SEO_HTACCESS_CAPTION_COMMENT'] . '</li>' . "\n";
			$htaccess_output .= '<li style="color:#A020F0">&nbsp;' . $this->user->lang['SEO_HTACCESS_CAPTION_STATIC'] . '</li>' . "\n";
			$htaccess_output .= '<li style="color:#6A5ACD">&nbsp;' . $this->user->lang['SEO_HTACCESS_CAPTION_SUFFIX'] . '</li>' . "\n";
			$htaccess_output .= '<li style="color:#FF00FF">&nbsp;' . $this->user->lang['SEO_HTACCESS_CAPTION_DELIM'] . '</li>' . "\n";

			if ($this->new_config['more_options'])
			{
				$htaccess_output .= '<li style="color:red">&nbsp;' . $this->user->lang['SEO_HTACCESS_CAPTION_SLASH'] . '</li>&nbsp;' . "\n";
			}

			$htaccess_output .= '</ul></div>' . "\n" . '</div></div><p>' . "\n";
		}
		else
		{
			// File output
			$htaccess_output = str_replace(array('&lt;', '&gt;', '&amp;'), array('<', '>', '&'), strip_tags($htaccess_code));
		}

		return $htaccess_output;
	}

	/**
	*  get_mods_ht Get all mods htaccess tpls
	*/
	function get_mods_ht()
	{
		$all_ht_tpl = array('pos1' => '', 'pos2' => '');
		$path = PHPBB_SEO_USU_ROOT_DIR . 'htmods';

		if (!($dir = @opendir($path)))
		{
			return false;
		}

		while(($file = @readdir($dir)) !== false)
		{
			if (!trim($file, '. '))
			{
				continue;
			}

			if (preg_match('`^ht_([a-z0-9_-]+)\.' . $this->php_ext . '$`i', $file, $match))
			{
				$mode = $match[1];
				$class = 'ht_' . $mode;

				require_once("{$path}/{$file}");

				$module = new $class();

				if ($tpl = $module->get_tpl())
				{
					if (!empty($tpl['pos1']))
					{
						$all_ht_tpl['pos1'] .= $tpl['pos1'] . "\n";
					}

					if (!empty($tpl['pos2']))
					{
						$all_ht_tpl['pos2'] .= $tpl['pos2'] . "\n";
					}
				}
			}
		}

		return !empty($all_ht_tpl['pos1']) || !empty($all_ht_tpl['pos2']) ? $all_ht_tpl : false;
	}

	/**
	*  set_phpbb_seo_links Builds links to support threads
	*/
	function set_phpbb_seo_links()
	{
		$modrtype_lang = array();
		$this->core->modrtype = intval($this->core->modrtype);

		if ($this->core->modrtype < 1 || $this->core->modrtype > 3)
		{
			$this->core->modrtype = 1;
		}

		$modrtype_lang['titles'] = array(
			1	=> $this->user->lang['ACP_SEO_SIMPLE'],
			2	=> $this->user->lang['ACP_SEO_MIXED'],
			3	=> $this->user->lang['ACP_SEO_ADVANCED'],
			'u'	=> $this->user->lang['ACP_ULTIMATE_SEO_URL'],
		);

		$modrtype_lang['title'] = $modrtype_lang['titles'][$this->core->modrtype];
		$modrtype_lang['utitle'] = $modrtype_lang['titles']['u'];
		$modrtype_lang['types'] = array(1 => 'SIMPLE', 2 => 'MIXED', 1 => 'SIMPLE', 3 => 'ADVANCED');
		$modrtype_lang['type'] = $modrtype_lang['types'][$this->core->modrtype];

		$modrtype_lang['modrlinks_en'] = array(
			1	=> 'http://www.phpbb-seo.com/en/simple-seo-url/simple-phpbb-seo-url-t1566.html',
			2	=> 'http://www.phpbb-seo.com/en/mixed-seo-url/mixed-phpbb-seo-url-t1565.html',
			3	=> 'http://www.phpbb-seo.com/en/advanced-seo-url/advanced-phpbb-seo-url-t1219.html',
			'u'	=> 'http://www.phpbb-seo.com/en/phpbb-mod-rewrite/ultimate-seo-url-t4608.html',
		);

		$modrtype_lang['modrlinks_fr'] = array(
			1	=> 'http://www.phpbb-seo.com/fr/reecriture-url-simple/seo-url-phpbb-simple-t1945.html',
			2	=> 'http://www.phpbb-seo.com/fr/reecriture-url-intermediaire/seo-url-intermediaire-t1946.html',
			3	=> 'http://www.phpbb-seo.com/fr/reecriture-url-avancee/seo-url-phpbb-avance-t1501.html',
			'u'	=> 'http://www.phpbb-seo.com/fr/mod-rewrite-phpbb/ultimate-seo-url-t4489.html',
		);

		$modrtype_lang['modrforumlinks_en'] = array(
			1	=> 'http://www.phpbb-seo.com/en/simple-seo-url/',
			2	=> 'http://www.phpbb-seo.com/en/mixed-seo-url/',
			3	=> 'http://www.phpbb-seo.com/en/advanced-seo-url/',
			'u'	=> 'http://www.phpbb-seo.com/en/phpbb-mod-rewrite/',
		);

		$modrtype_lang['modrforumlinks_fr'] = array(
			1	=> 'http://www.phpbb-seo.com/fr/reecriture-url-simple/',
			2	=> 'http://www.phpbb-seo.com/fr/reecriture-url-intermediaire/',
			3	=> 'http://www.phpbb-seo.com/fr/reecriture-url-avancee/',
			'u'	=> 'http://www.phpbb-seo.com/fr/mod-rewrite-phpbb/',
		);

		if (strpos($this->config['default_lang'], 'fr') !== false)
		{
			$modrtype_lang['linkurl'] = $modrtype_lang['modrlinks_fr'][$this->core->modrtype];
			$modrtype_lang['forumlinkurl'] = $modrtype_lang['modrforumlinks_fr'][$this->core->modrtype];
			$modrtype_lang['ulinkurl'] = $modrtype_lang['modrlinks_fr']['u'];
			$modrtype_lang['uforumlinkurl'] = $modrtype_lang['modrforumlinks_fr']['u'];
		}
		else
		{
			$modrtype_lang['linkurl'] = $modrtype_lang['modrlinks_en'][$this->core->modrtype];
			$modrtype_lang['forumlinkurl'] = $modrtype_lang['modrforumlinks_en'][$this->core->modrtype];
			$modrtype_lang['ulinkurl'] = $modrtype_lang['modrlinks_en']['u'];
			$modrtype_lang['uforumlinkurl'] = $modrtype_lang['modrforumlinks_en']['u'];
		}

		$modrtype_lang['link'] = '<a href="' . $modrtype_lang['linkurl'] . '" title="' . $this->user->lang['ACP_PHPBB_SEO_VERSION'] . ' ' . $modrtype_lang['title'] . '" onclick="window.open(this.href); return false;"><b>' . $modrtype_lang['title'] . '</b></a>';
		$modrtype_lang['forumlink'] = '<a href="' . $modrtype_lang['forumlinkurl'] . '" title="' . $this->user->lang['ACP_SEO_SUPPORT_FORUM'] . '" onclick="window.open(this.href); return false;"><b>' . $this->user->lang['ACP_SEO_SUPPORT_FORUM'] . '</b></a>';
		$modrtype_lang['ulink'] = '<a href="' . $modrtype_lang['ulinkurl'] . '" title="' . $this->user->lang['ACP_PHPBB_SEO_VERSION'] . ' ' . $modrtype_lang['utitle'] . '" onclick="window.open(this.href); return false;"><b>' . $modrtype_lang['utitle'] . '</b></a>';
		$modrtype_lang['uforumlink'] = '<a href="' . $modrtype_lang['uforumlinkurl'] . '" title="' . $this->user->lang['ACP_SEO_SUPPORT_FORUM'] . '" onclick="window.open(this.href); return false;"><b>' . $this->user->lang['ACP_SEO_SUPPORT_FORUM'] . '</b></a>';

		return $modrtype_lang;
	}

	/**
	*  check_cache_folder Validates the cache folder status
	*/
	function check_cache_folder($cache_dir, $msg = true)
	{
		$exists = $write = $inner_write = false;
		$cache_msg = '';

		if (file_exists($cache_dir) && is_dir($cache_dir))
		{
			$exists = true;

			if (!is_writeable($cache_dir))
			{
				phpbb_chmod($cache_dir, CHMOD_READ | CHMOD_WRITE);

				$fp = @fopen($cache_dir . 'test_lock', 'wb');

				if ($fp !== false)
				{
					$write = true;
				}

				@fclose($fp);
				@unlink($cache_dir . 'test_lock');
			}
			else
			{
				$write = true;
			}

			// check if the config cache file is here already and writeable
			$check = $this->core->cache_config['file'];
			$checks = array("{$check}.old", "{$check}.current", "{$cache_dir}.htaccess", "{$cache_dir}.htaccess.old", "{$cache_dir}.htaccess.current");

			// let's check all files
			$inner_write = true;

			foreach($checks as $check)
			{
				if (file_exists($check))
				{
					if (!is_writeable($check))
					{
						$inner_write = false;

						phpbb_chmod($check, CHMOD_READ | CHMOD_WRITE);

						$fp = @fopen($check, 'wb');

						if ($fp !== false)
						{
							$inner_write = true;
						}

						@fclose($fp);
					}
				}
			}
		}

		if ($msg)
		{
			$exists = ($exists) ? '<b style="color:green">' . $this->user->lang['SEO_CACHE_FOUND'] . '</b>' : '<b style="color:red">' . $this->user->lang['SEO_CACHE_NOT_FOUND'] . '</b>';
			$write = ($write) ? '<br/> <b style="color:green">' . $this->user->lang['SEO_CACHE_WRITABLE'] . '</b>' : (($exists) ? '<br/> <b style="color:red">' . $this->user->lang['SEO_CACHE_UNWRITABLE'] . '</b>' : '');
			$inner_write = $inner_write ? '' : '<br/> <b style="color:red">' . $this->user->lang['SEO_CACHE_INNER_UNWRITABLE'] . '</b>';
			$cache_msg = sprintf($this->user->lang['SEO_CACHE_STATUS'], $cache_dir) . '<br/>' . $exists . $write . $inner_write;

			return '<br/><b>' . $this->user->lang['SEO_CACHE_FILE_TITLE'] . ':</b><br/>' . $cache_msg . '<br/><br/>';
		}
		else
		{
			return ($exists && $write);
		}
	}

	/**
	* write_cache( ) will write the cached file and keep backups.
	*/
	function write_cache($type = 'forum')
	{
		if(!$this->core->cache_config['cache_enable'])
		{
			return false;
		}

		if ($type == 'htaccess')
		{
			// .htaccess case
			$file = $this->core->seo_opt['cache_folder'] . '.htaccess';
			$update = $this->seo_htaccess(false);
		}
		else
		{
			// config
			$file = $this->core->cache_config['file'];
			$update = '<'.'?php' . "\n" . '/**' . "\n" . '* phpBB SEO' . "\n" . '* www.phpBB-SEO.com' . "\n" . '* @package phpBB SEO USU' . "\n" . '*/' . "\n" . 'if (!defined(\'IN_PHPBB\')) {' . "\n\t" . 'exit;' . "\n" . '}' . "\n";
			$update .= '$settings = ' . preg_replace('`[\s]+`', ' ', var_export($this->core->cache_config['settings'], true)) . ';'. "\n";
			$update .= '$forum_urls = ' . preg_replace('`[\s]+`', ' ', var_export($this->core->cache_config['forum_urls'], true)) . ';';
		}

		// Keep a backup of the previous settings
		@copy($file, $file . '.old');
		$handle = @fopen($file, 'wb');
		@fputs($handle, $update);
		@fclose ($handle);
		unset($update);
		@umask(0000);
		phpbb_chmod($file, CHMOD_READ | CHMOD_WRITE);

		// Keep a backup of the current settings
		@copy($file, $file . '.current');

		return true;
	}

	/**
	*  select_multiple($value, $key, $select_ary)
	*/
	function select_multiple($value, $key, $select_ary)
	{
		$size = min(12,count($select_ary));
		$html = '<select multiple="multiple" id="' . $key . '" name="multiple_' . $key . '[]" size="' . $size . '">';

		foreach ($select_ary as $sel_key => $sel_data)
		{
			if (empty($sel_data['disabled']))
			{
				$selected = array_search($sel_key, @$this->new_config[$key]) !== false ? 'selected="selected"' : '';
				$disabled = '';
			}
			else
			{
				$disabled = 'disabled="disabled" class="disabled-option"';
				$selected = '';
			}

			$sel_title = $sel_data['title'];
			$html .= "<option value=\"{$sel_key}\" {$disabled} {$selected}>{$sel_title}</option>";
		}

		return $html . '</select>';
	}

	/**
	*  forum_select() // custom forum select setup
	*/
	function forum_select($ignore_acl = true, $ignore_nonpost = false, $ignore_emptycat = false, $only_acl_post = false)
	{
		$select_ary = make_forum_select(false, false, $ignore_acl, $ignore_nonpost, $ignore_emptycat, $only_acl_post, true);

		foreach($select_ary as $f_id => $f_data)
		{
			$select_ary[$f_id] = array(
				'title'		=> $f_data['padding'] . $f_data['forum_name'],
				'disabled'	=> $f_data['disabled'],
			);
		}

		return $select_ary;
	}

	/**
	* Pick a language, any language ... or no language
	*/
	function language_select($default = '')
	{
		return '<option value="">' . $this->user->lang['DISABLED'] . '</option>' . language_select($default);
	}
}
