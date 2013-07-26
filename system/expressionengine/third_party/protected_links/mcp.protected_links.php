<?php

/*
=====================================================
 Protected Links
-----------------------------------------------------
 http://www.intoeetive.com/
-----------------------------------------------------
 Copyright (c) 2011-2013 Yuri Salimovskiy
=====================================================
 This software is intended for usage with
 ExpressionEngine CMS, version 2.0 or higher
=====================================================
 File: mcp.protected_links.php
-----------------------------------------------------
 Purpose: Encrypt and protect download links
=====================================================
*/

if ( ! defined('BASEPATH'))
{
    exit('Invalid file request');
}

require_once PATH_THIRD.'protected_links/config.php';

class Protected_links_mcp {

    var $version = PROTECTED_LINKS_ADDON_VERSION;
    
    var $settings = array();
    
    var $perpage = 50;
    
    function __construct() { 
        // Make a local reference to the ExpressionEngine super object 
        $this->EE =& get_instance(); 
        
        $this->EE->lang->loadfile('protected_links');  
        
        $query = $this->EE->db->query("SELECT settings FROM exp_modules WHERE module_name='Protected_links' LIMIT 1");
        $this->settings = unserialize($query->row('settings')); 
    } 
    
    function index()
    {
        return $this->links();
    }
    
    
    function files()
    {
    	$this->EE->load->library('table');  
        $this->EE->load->library('javascript');

    	$vars = array();
        /*
        $vars['files'] = array();
        $vars['files'][''] = $this->EE->lang->line('all_files');
        $this->EE->db->select();
        $this->EE->db->from('protected_links_files');
        //$this->EE->db->group_by(array('storage, container, url'));  
        //$this->EE->db->order_by('title', 'desc');
        $query = $this->EE->db->get();
        foreach ($query->result() as $obj)
        {
           $title_arr = explode("/", $obj->url);
           $vars['files'][$obj->file_id] = $title_arr[(count($title_arr)-1)];
        }
        
        $vars['members'] = array();
        $vars['members'][''] = $this->EE->lang->line('all_members');
        $query = $this->EE->db->query("SELECT DISTINCT exp_members.member_id, screen_name FROM exp_members, exp_protected_links_stats WHERE exp_members.member_id=exp_protected_links_stats.member_id");
        foreach ($query->result() as $obj)
        {
           $vars['members'][$obj->member_id] = $obj->screen_name;
        }
        $vars['members'][0] = $this->EE->lang->line('guest');
        
        $vars['sortby'] = array(
                        'dl_date'  =>  $this->EE->lang->line('last_dl_date'),
                        'url'  =>  $this->EE->lang->line('file'),
                        'member_id'  =>  $this->EE->lang->line('member')
                    );
        $vars['order'] = array(
                        'desc'  =>  $this->EE->lang->line('desc'),
                        'asc'  =>  $this->EE->lang->line('asc')
                    );

    	$vars['selected'] = array();
        $vars['selected']['files']=$this->EE->input->get_post('files');
        $vars['selected']['members']=$this->EE->input->get_post('members');
        $vars['selected']['sortby']=($this->EE->input->get_post('sortby')!='' && $this->EE->input->get_post('sortby')!=0)?$this->EE->input->get_post('sortby'):'dl_date';
        $vars['selected']['order']=($this->EE->input->get_post('order')!='' && $this->EE->input->get_post('order')!=0)?$this->EE->input->get_post('order'):'desc';

        */
        
        $vars['selected']['rownum']=($this->EE->input->get_post('rownum')!='')?$this->EE->input->get_post('rownum'):0;
        
        $this->EE->db->select();
        $this->EE->db->from('protected_links_files');
        
        if ($this->EE->input->get_post('search')!==false && strlen($this->EE->input->get_post('search'))>2)
        {
            $vars['selected']['search']=$this->EE->input->get_post('search');
            $this->EE->db->where('url LIKE "%'.$this->EE->db->escape_str($this->EE->input->get_post('search')).'%"');
        }
        else
        {
            $vars['selected']['search']='';
        }
        
        //$this->EE->db->order_by($vars['selected']['sortby'], $vars['selected']['order']);

        $this->EE->db->limit($this->perpage, $vars['selected']['rownum']);

        $query = $this->EE->db->get();
        $vars['total_count'] = $query->num_rows();
        //exit();
        
        $i = $vars['selected']['rownum']+1;
        $vars['table_headings'] = array(
                        $this->EE->lang->line('#'),
                        $this->EE->lang->line('file'),
                        $this->EE->lang->line('dl_count'),
                        $this->EE->lang->line('dl_date'),
                        '',
                        ''
                    );
                    
        
        
              
        foreach ($query->result() as $obj)
        {
           $vars['protected_files'][$i]['count'] = $i;
           
           $url_arr = explode("/", $obj->url);
           $vars['protected_files'][$i]['title'] = $url_arr[(count($url_arr)-1)];
           $vars['protected_files'][$i]['dl_count'] = $obj->dl_count;
           $vars['protected_files'][$i]['dl_date'] = $this->EE->localize->decode_date("%Y-%m-%d %H:%i", $obj->dl_date); 
           
           $vars['protected_files'][$i]['view_stats'] = "<a href=\"".BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=filestats'.AMP.'file_id='.$obj->file_id."\" title=\"".$this->EE->lang->line('view_stats')."\"><img src=\"".$this->EE->config->slash_item('theme_folder_url')."third_party/protected_links/stats.png\" alt=\"".$this->EE->lang->line('view_stats')."\"></a>";
           
           $vars['protected_files'][$i]['delete_file'] = "<a class=\"file_delete_warning\" href=\"".BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=deletefile'.AMP.'file_id='.$obj->file_id."\" title=\"".$this->EE->lang->line('delete_file')."\"><img src=\"".$this->EE->cp->cp_theme_url."images/icon-delete.png\" alt=\"".$this->EE->lang->line('delete_file')."\"></a>";    
           
           $i++;
        }
        
        
        $outputjs = '
				var draft_target = "";

			$("<div id=\"file_delete_warning\">'.$this->EE->lang->line('file_delete_warning').'</div>").dialog({
				autoOpen: false,
				resizable: false,
				title: "'.$this->EE->lang->line('confirm_deleting').'",
				modal: true,
				position: "center",
				minHeight: "0px", 
				buttons: {
					Cancel: function() {
					$(this).dialog("close");
					},
				"'.$this->EE->lang->line('delete_file').'": function() {
					location=draft_target;
				}
				}});

			$(".file_delete_warning").click( function (){
				$("#file_delete_warning").dialog("open");
				draft_target = $(this).attr("href");
				$(".ui-dialog-buttonpane button:eq(2)").focus();	
				return false;
		});';

		$this->EE->javascript->output(str_replace(array("\n", "\t"), '', $outputjs));
        
        
        $this->EE->load->library('pagination');
        
        $this->EE->db->select('COUNT(*) AS count');
        $this->EE->db->from('protected_links_files');

        $base_url = BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=files';
        if ($vars['selected']['search']!='')
        {
           $base_url .= AMP.'search='.$this->EE->input->get_post('search');
           $this->EE->db->where('url LIKE "%'.$this->EE->db->escape_str($this->EE->input->get_post('search')).'%"');
        }
        $q = $this->EE->db->get();
        
        $p_config = $this->_p_config($base_url, $q->row('count'));

		$this->EE->pagination->initialize($p_config);
        
		$vars['pagination'] = $this->EE->pagination->create_links();
        
        $this->EE->cp->set_breadcrumb(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links', lang('protected_links_module_name'));
        $this->EE->cp->set_variable('cp_page_title', lang('protected_links_module_name').' - '.lang('files'));
        
        $this->EE->cp->set_right_nav(array(
		            'generate' => BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=edit')
		        );
        
    	return $this->EE->load->view('files', $vars, TRUE);
	
    }    
    
    
    function links()
    {
        $this->EE->load->helper('form');
    	$this->EE->load->library('table');  
        $this->EE->load->library('javascript');

    	$vars = array();
        
        
        if ($this->EE->input->get_post('search')!==false && strlen($this->EE->input->get_post('search'))>2)
        {
            $vars['selected']['search']=$this->EE->input->get_post('search');
        }
        else
        {
            $vars['selected']['search']='';
        }
        $vars['selected']['rownum']=($this->EE->input->get_post('rownum')!='')?$this->EE->input->get_post('rownum'):0;
        
        $this->EE->db->select('link_id, title, filename, accesskey, link_date, dl_count');
        $this->EE->db->from('protected_links_links');
        if ($vars['selected']['search']!='')
        {
            $this->EE->db->where('filename LIKE "%'.$this->EE->db->escape_str($this->EE->input->get_post('search')).'%"');
        }
        //$this->EE->db->where('file_id', $this->EE->input->get_post('file_id'));
        $this->EE->db->where('cp_generated', 'y');
        $this->EE->db->order_by('link_date', 'desc');

        $this->EE->db->limit($this->perpage, $vars['selected']['rownum']);

        $query = $this->EE->db->get();
        $vars['total_count'] = $query->num_rows();
        //exit();
        
        $i = $vars['selected']['rownum']+1;
        $vars['table_headings'] = array(
                        $this->EE->lang->line('link_id'),
                        $this->EE->lang->line('title'),
                        $this->EE->lang->line('file_name'),
                        $this->EE->lang->line('created'),
                        $this->EE->lang->line('dl_count'),
                        '',
                        '',
                        '',
                        ''
                    );
                    
        $act = $this->EE->db->query("SELECT action_id FROM exp_actions WHERE class='Protected_links' AND method='process'");
              
        foreach ($query->result() as $obj)
        {
           $vars['protected_files'][$i]['link_id'] = $obj->link_id;
           $vars['protected_files'][$i]['title'] = $obj->title;
           $vars['protected_files'][$i]['filename'] = $obj->filename;
           $vars['protected_files'][$i]['link_date'] = $this->EE->localize->decode_date("%Y-%m-%d %H:%i", $obj->link_date); 
           $vars['protected_files'][$i]['dl_count'] = $obj->dl_count;
           $vars['protected_files'][$i]['view_link'] = "<a href=\"".BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=linkstats'.AMP.'link_id='.$obj->link_id."\" title=\"".$this->EE->lang->line('view_stats')."\"><img src=\"".$this->EE->config->slash_item('theme_folder_url')."third_party/protected_links/stats.png\" alt=\"".$this->EE->lang->line('view_stats')."\"></a>";
           $vars['protected_files'][$i]['edit_link'] = "<a href=\"".BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=edit'.AMP.'link_id='.$obj->link_id."\" title=\"".$this->EE->lang->line('edit')."\"><img src=\"".$this->EE->cp->cp_theme_url."images/icon-edit.png\" alt=\"".$this->EE->lang->line('edit')."\"></a>";
           $url = $this->EE->config->item('site_url')."?ACT=".$act->row('action_id')."&key=".$obj->accesskey;

           $vars['protected_files'][$i]['download_file'] = "<a href=\"".$url."\" title=\"".$this->EE->lang->line('download_file')."\"><img src=\"".$this->EE->cp->cp_theme_url."images/icon-download-file.png\" alt=\"".$this->EE->lang->line('download_file')."\"></a>";
           $vars['protected_files'][$i]['delete_link'] = "<a class=\"link_delete_warning\" href=\"".BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=deletelink'.AMP.'link_id='.$obj->link_id."\" title=\"".$this->EE->lang->line('delete_link')."\"><img src=\"".$this->EE->cp->cp_theme_url."images/icon-delete.png\" alt=\"".$this->EE->lang->line('delete_link')."\"></a>";
           
           $i++;
        }
        
        $outputjs = '
				var draft_target = "";

			$("<div id=\"link_delete_warning\">'.$this->EE->lang->line('link_delete_warning').'</div>").dialog({
				autoOpen: false,
				resizable: false,
				title: "'.$this->EE->lang->line('confirm_deleting').'",
				modal: true,
				position: "center",
				minHeight: "0px", 
				buttons: {
					Cancel: function() {
					$(this).dialog("close");
					},
				"'.$this->EE->lang->line('delete_link').'": function() {
					location=draft_target;
				}
				}});

			$(".link_delete_warning").click( function (){
				$("#link_delete_warning").dialog("open");
				draft_target = $(this).attr("href");
				$(".ui-dialog-buttonpane button:eq(2)").focus();	
				return false;
		});';

		$this->EE->javascript->output(str_replace(array("\n", "\t"), '', $outputjs));
        
        $this->EE->load->library('pagination');
        
        $this->EE->db->select('COUNT(*) AS count');
        $this->EE->db->from('protected_links_links');
        if ($vars['selected']['search']!='')
        {
            $this->EE->db->where('filename LIKE "%'.$this->EE->db->escape_str($this->EE->input->get_post('search')).'%"');
        }
        $this->EE->db->where('cp_generated', 'y');
        $q = $this->EE->db->get();
        
        $base_url = BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=links';
        if ($vars['selected']['search']!='')
        {
           $base_url .= AMP.'search='.$this->EE->input->get_post('search');
        }
        $p_config = $this->_p_config($base_url, $q->row('count'));

		$this->EE->pagination->initialize($p_config);
        
		$vars['pagination'] = $this->EE->pagination->create_links();
        
        $this->EE->cp->set_breadcrumb(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links', lang('protected_links_module_name'));
        $this->EE->cp->set_variable('cp_page_title', lang('protected_links_module_name').' - '.lang('links'));
        
        $this->EE->cp->set_right_nav(array(
		            'generate' => BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=edit')
		        );
        
    	return $this->EE->load->view('links', $vars, TRUE);
	
    }    
    
    
    
    function filestats()
    {
        $this->EE->load->helper('form');
    	$this->EE->load->library('table');  
        $this->EE->load->library('javascript');
        
        $js = "date_obj = new Date();
date_obj_hours = date_obj.getHours();
date_obj_mins = date_obj.getMinutes();

date_obj_am_pm = (date_obj_hours < 12) ? 'AM': 'PM';

// This turns midnight into 12 AM, so ignore if it's already 0
if (date_obj_hours != 0) {
    date_obj_hours = ((date_obj_hours + 11) % 12) + 1;
}  

date_obj_time = \"' \"+date_obj_hours+\":\"+date_obj_mins+\" \"+date_obj_am_pm+\"'\";";
        $this->EE->javascript->output($js);

        $this->EE->cp->add_js_script('ui', 'datepicker'); 
        $this->EE->javascript->output(' $("#date_from").datepicker({ dateFormat: $.datepicker.W3C + date_obj_time }); '); 
        $this->EE->javascript->output(' $("#date_to").datepicker({ dateFormat: $.datepicker.W3C + date_obj_time }); '); 
        $this->EE->javascript->compile(); 

    	$vars = array();
        
        if ($this->EE->input->get_post('date_from')!='' && $this->EE->input->get_post('date_from')!=0)
        {
            $vars['selected']['date_from']=$this->EE->input->get_post('date_from');
            $date_from = $this->EE->localize->convert_human_date_to_gmt($this->EE->input->get_post('date_from'));
        }
        else
        {
            $vars['selected']['date_from']='';
        }
        if ($this->EE->input->get_post('date_to')!='' && $this->EE->input->get_post('date_to')!=0)
        {
            $vars['selected']['date_to']=$this->EE->input->get_post('date_to');
            $date_to = $this->EE->localize->convert_human_date_to_gmt($this->EE->input->get_post('date_to'));
        }
        else
        {
            $vars['selected']['date_to']='';
        }
        
        $vars['selected']['rownum']=($this->EE->input->get_post('rownum')!='')?$this->EE->input->get_post('rownum'):0;
        
        $this->EE->db->select('*');
        $this->EE->db->from('protected_links_files');
        $this->EE->db->where('file_id', $this->EE->input->get_post('file_id'));
        $url = $this->EE->db->get();
        $vars['selected']['url'] = $this->EE->lang->line($url->row('storage')).": ".$url->row('container')." ".$url->row('url');
        $vars['selected']['file_id'] = $url->row('file_id');
        
        $this->EE->db->select('exp_protected_links_stats.member_id, exp_protected_links_stats.ip, exp_protected_links_stats.dl_date, exp_members.screen_name');
        $this->EE->db->from('protected_links_stats');
        $this->EE->db->join('members', 'exp_protected_links_stats.member_id=exp_members.member_id', 'left');
        $this->EE->db->where('file_id', $this->EE->input->get_post('file_id'));
        if ($vars['selected']['date_from']!='')
        {
            $this->EE->db->where('dl_date >=', $this->EE->localize->convert_human_date_to_gmt($vars['selected']['date_from']));
        }
        if ($vars['selected']['date_to']!='')
        {
            $this->EE->db->where('dl_date <=', $this->EE->localize->convert_human_date_to_gmt($vars['selected']['date_to']));
        }
        $this->EE->db->order_by('exp_protected_links_stats.dl_date', 'desc');

        $this->EE->db->limit($this->perpage, $vars['selected']['rownum']);

        $query = $this->EE->db->get();
        $vars['total_count'] = $query->num_rows();
        //exit();
        
        $i = 0;
        $vars['table_headings'] = array(
                        $this->EE->lang->line('dl_date'),
                        $this->EE->lang->line('screen_name'),
                        $this->EE->lang->line('ip_address')
                    );
                    
        
        
              
        foreach ($query->result() as $obj)
        {
           //$vars['protected_files'][$i]['count'] = $i;
           $vars['protected_files'][$i]['dl_date'] = $this->EE->localize->decode_date("%Y-%m-%d %H:%i", $obj->dl_date);
           $vars['protected_files'][$i]['screen_name'] = ($obj->member_id!=0)?"<a href=\"".BASE.AMP.'D=cp'.AMP.'C=myaccount'.AMP.'id='.$obj->member_id."\">".$obj->screen_name."</a>":$this->EE->lang->line('guest');
           $vars['protected_files'][$i]['ip_address'] = $obj->ip;
            
           $i++;
        }
        
        
        
        $this->EE->load->library('pagination');
        
        $this->EE->db->select('COUNT(*) AS count');
        $this->EE->db->from('protected_links_stats');
        $this->EE->db->where('file_id', $this->EE->input->get_post('file_id'));
        if ($vars['selected']['date_from']!='')
        {
            $this->EE->db->where('dl_date >=', $this->EE->localize->convert_human_date_to_gmt($vars['selected']['date_from']));
        }
        if ($vars['selected']['date_to']!='')
        {
            $this->EE->db->where('dl_date <=', $this->EE->localize->convert_human_date_to_gmt($vars['selected']['date_to']));
        }
        $query = $this->EE->db->get();
        
        $p_config = $this->_p_config(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=filestats'.AMP.'file_id='.$this->EE->input->get_post('file_id'), $query->row('count'));

		$this->EE->pagination->initialize($p_config);
        
		$vars['pagination'] = $this->EE->pagination->create_links();
        
        $this->EE->cp->set_breadcrumb(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links', lang('protected_links_module_name'));
        $this->EE->cp->set_breadcrumb(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=files', lang('files'));
        $url_arr = explode("/", $url->row('url'));
        $this->EE->cp->set_variable('cp_page_title', $url_arr[(count($url_arr)-1)]);
        
        $this->EE->cp->set_right_nav(array(
		            'generate' => BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=edit')
		        );
        
    	return $this->EE->load->view('filestats', $vars, TRUE);
	
    }    
    
    
    function linkstats()
    {
        $this->EE->load->helper('form');
    	$this->EE->load->library('table');  
        $this->EE->load->library('javascript');
        
        $js = "date_obj = new Date();
date_obj_hours = date_obj.getHours();
date_obj_mins = date_obj.getMinutes();

date_obj_am_pm = (date_obj_hours < 12) ? 'AM': 'PM';

// This turns midnight into 12 AM, so ignore if it's already 0
if (date_obj_hours != 0) {
    date_obj_hours = ((date_obj_hours + 11) % 12) + 1;
}  

date_obj_time = \"' \"+date_obj_hours+\":\"+date_obj_mins+\" \"+date_obj_am_pm+\"'\";";
        $this->EE->javascript->output($js);

        $this->EE->cp->add_js_script('ui', 'datepicker'); 
        $this->EE->javascript->output(' $("#date_from").datepicker({ dateFormat: $.datepicker.W3C + date_obj_time }); '); 
        $this->EE->javascript->output(' $("#date_to").datepicker({ dateFormat: $.datepicker.W3C + date_obj_time }); '); 
        $this->EE->javascript->compile(); 

    	$vars = array();
        
        if ($this->EE->input->get_post('date_from')!='' && $this->EE->input->get_post('date_from')!=0)
        {
            $vars['selected']['date_from']=$this->EE->input->get_post('date_from');
            $date_from = $this->EE->localize->convert_human_date_to_gmt($this->EE->input->get_post('date_from'));
        }
        else
        {
            $vars['selected']['date_from']='';
        }
        if ($this->EE->input->get_post('date_to')!='' && $this->EE->input->get_post('date_to')!=0)
        {
            $vars['selected']['date_to']=$this->EE->input->get_post('date_to');
            $date_to = $this->EE->localize->convert_human_date_to_gmt($this->EE->input->get_post('date_to'));
        }
        else
        {
            $vars['selected']['date_to']='';
        }
        
        $vars['selected']['rownum']=($this->EE->input->get_post('rownum')!='')?$this->EE->input->get_post('rownum'):0;
        
        $this->EE->db->select('*');
        $this->EE->db->from('protected_links_links');
        $this->EE->db->where('link_id', $this->EE->input->get_post('link_id'));
        $url = $this->EE->db->get();
        $vars['selected']['title'] = $url->row('title');
        $vars['selected']['link_id'] = $url->row('link_id');
        
        $this->EE->db->select('exp_protected_links_stats.member_id, exp_protected_links_stats.ip, exp_protected_links_stats.dl_date, exp_members.screen_name');
        $this->EE->db->from('protected_links_stats');
        $this->EE->db->join('members', 'exp_protected_links_stats.member_id=exp_members.member_id', 'left');
        $this->EE->db->where('link_id', $this->EE->input->get_post('link_id'));
        if ($vars['selected']['date_from']!='')
        {
            $this->EE->db->where('dl_date >=', $this->EE->localize->convert_human_date_to_gmt($vars['selected']['date_from']));
        }
        if ($vars['selected']['date_to']!='')
        {
            $this->EE->db->where('dl_date <=', $this->EE->localize->convert_human_date_to_gmt($vars['selected']['date_to']));
        }
        $this->EE->db->order_by('exp_protected_links_stats.dl_date', 'desc');

        $this->EE->db->limit($this->perpage, $vars['selected']['rownum']);

        $query = $this->EE->db->get();
        $vars['total_count'] = $query->num_rows();
        //exit();
        
        $i = 0;
        $vars['table_headings'] = array(
                        $this->EE->lang->line('dl_date'),
                        $this->EE->lang->line('screen_name'),
                        $this->EE->lang->line('ip_address')
                    );
                    
        
        
              
        foreach ($query->result() as $obj)
        {
           //$vars['protected_files'][$i]['count'] = $i;
           $vars['protected_files'][$i]['dl_date'] = $this->EE->localize->decode_date("%Y-%m-%d %H:%i", $obj->dl_date);
           $vars['protected_files'][$i]['screen_name'] = ($obj->member_id!=0)?"<a href=\"".BASE.AMP.'D=cp'.AMP.'C=myaccount'.AMP.'id='.$obj->member_id."\">".$obj->screen_name."</a>":$this->EE->lang->line('guest');
           $vars['protected_files'][$i]['ip_address'] = $obj->ip;
            
           $i++;
        }
        
        
        
        $this->EE->load->library('pagination');
        
        $this->EE->db->select('COUNT(*) AS count');
        $this->EE->db->from('protected_links_stats');
        $this->EE->db->where('link_id', $this->EE->input->get_post('link_id'));
        if ($vars['selected']['date_from']!='')
        {
            $this->EE->db->where('dl_date >=', $this->EE->localize->convert_human_date_to_gmt($vars['selected']['date_from']));
        }
        if ($vars['selected']['date_to']!='')
        {
            $this->EE->db->where('dl_date <=', $this->EE->localize->convert_human_date_to_gmt($vars['selected']['date_to']));
        }
        $query = $this->EE->db->get();
        
        $p_config = $this->_p_config(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=linkstats'.AMP.'link_id='.$this->EE->input->get_post('link_id'), $query->row('count'));

		$this->EE->pagination->initialize($p_config);
        
		$vars['pagination'] = $this->EE->pagination->create_links();
        
        $this->EE->cp->set_breadcrumb(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links', lang('protected_links_module_name'));
        $this->EE->cp->set_breadcrumb(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=links', lang('links'));
        $this->EE->cp->set_variable('cp_page_title', $vars['selected']['title']);
        
        $this->EE->cp->set_right_nav(array(
		            'generate' => BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=edit')
		        );
        
    	return $this->EE->load->view('linkstats', $vars, TRUE);
	
    }    
    
    
    
    function memberstats()
    {
        $this->EE->load->helper('form');
    	$this->EE->load->library('table');  
        $this->EE->load->library('javascript');
        
        $js = "date_obj = new Date();
date_obj_hours = date_obj.getHours();
date_obj_mins = date_obj.getMinutes();

date_obj_am_pm = (date_obj_hours < 12) ? 'AM': 'PM';

// This turns midnight into 12 AM, so ignore if it's already 0
if (date_obj_hours != 0) {
    date_obj_hours = ((date_obj_hours + 11) % 12) + 1;
}  

date_obj_time = \"' \"+date_obj_hours+\":\"+date_obj_mins+\" \"+date_obj_am_pm+\"'\";";
        $this->EE->javascript->output($js);

        $this->EE->cp->add_js_script('ui', 'datepicker'); 
        $this->EE->javascript->output(' $("#date_from").datepicker({ dateFormat: $.datepicker.W3C + date_obj_time }); '); 
        $this->EE->javascript->output(' $("#date_to").datepicker({ dateFormat: $.datepicker.W3C + date_obj_time }); '); 
        $this->EE->javascript->compile(); 

    	$vars = array();
        
        if ($this->EE->input->get_post('date_from')!='' && $this->EE->input->get_post('date_from')!=0)
        {
            $vars['selected']['date_from']=$this->EE->input->get_post('date_from');
            $date_from = $this->EE->localize->convert_human_date_to_gmt($this->EE->input->get_post('date_from'));
        }
        else
        {
            $vars['selected']['date_from']='';
        }
        if ($this->EE->input->get_post('date_to')!='' && $this->EE->input->get_post('date_to')!=0)
        {
            $vars['selected']['date_to']=$this->EE->input->get_post('date_to');
            $date_to = $this->EE->localize->convert_human_date_to_gmt($this->EE->input->get_post('date_to'));
        }
        else
        {
            $vars['selected']['date_to']='';
        }
        
        $vars['selected']['rownum']=($this->EE->input->get_post('rownum')!='')?$this->EE->input->get_post('rownum'):0;
        
        if ($this->EE->input->get_post('member_id')!=0)
        {
            $this->EE->db->select('screen_name');
            $this->EE->db->from('members');
            $this->EE->db->where('member_id', $this->EE->input->get_post('member_id'));
            $user = $this->EE->db->get();
            $vars['selected']['screen_name'] = $user->row('screen_name');
        }
        else
        {
            $vars['selected']['screen_name'] = $this->EE->lang->line('guest');
        }
        $vars['selected']['member_id'] = $this->EE->input->get_post('member_id');
        
        $this->EE->db->select('s.ip, s.dl_date, l.link_id, l.title, l.accesskey, f.file_id, f.storage, f.container, f.url');
        $this->EE->db->from('protected_links_stats AS s');
        $this->EE->db->join('protected_links_links AS l', 's.link_id=l.link_id', 'left');
        $this->EE->db->join('protected_links_files AS f', 's.file_id=f.file_id', 'left');
        $this->EE->db->where('s.member_id', $this->EE->input->get_post('member_id'));

        if ($vars['selected']['date_from']!='')
        {
            $this->EE->db->where('s.dl_date >=', $this->EE->localize->convert_human_date_to_gmt($vars['selected']['date_from']));
        }
        if ($vars['selected']['date_to']!='')
        {
            $this->EE->db->where('s.dl_date <=', $this->EE->localize->convert_human_date_to_gmt($vars['selected']['date_to']));
        }
        $this->EE->db->order_by('s.dl_date', 'desc');

        $this->EE->db->limit($this->perpage, $vars['selected']['rownum']);

        $query = $this->EE->db->get();
        $vars['total_count'] = $query->num_rows();
        //exit();
        
        $i = 0;
        $vars['table_headings'] = array(
                        $this->EE->lang->line('link_title'),
                        $this->EE->lang->line('file'),
                        $this->EE->lang->line('dl_date'),
                        $this->EE->lang->line('ip_address')
                    );
                    
        
       
        $act = $this->EE->db->query("SELECT action_id FROM exp_actions WHERE class='Protected_links' AND method='process'");
        
              
        foreach ($query->result() as $obj)
        {
           //$vars['protected_files'][$i]['count'] = $i;
           
           $url = $this->EE->config->item('site_url')."?ACT=".$act->row('action_id')."&key=".$obj->accesskey;
           $vars['protected_files'][$i]['title'] = $obj->title." <a href=\"".BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=linkstats'.AMP.'link_id='.$obj->link_id."\" title=\"".$this->EE->lang->line('view_stats')."\"><img src=\"".$this->EE->config->slash_item('theme_folder_url')."third_party/protected_links/stats.png\" alt=\"".$this->EE->lang->line('view_stats')."\"></a> <a href=\"".BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=edit'.AMP.'link_id='.$obj->link_id."\" title=\"".$this->EE->lang->line('edit')."\"><img src=\"".$this->EE->cp->cp_theme_url."images/icon-edit.png\" alt=\"".$this->EE->lang->line('edit')."\"></a> <a href=\"".$url."\" title=\"".$this->EE->lang->line('download_file')."\"><img src=\"".$this->EE->cp->cp_theme_url."images/icon-download-file.png\" alt=\"".$this->EE->lang->line('download_file')."\"></a>";
           $url_arr = explode("/", $obj->url);
           $filename = $url_arr[(count($url_arr)-1)];
           $vars['protected_files'][$i]['file'] = "<span title=\"".$this->EE->lang->line($obj->storage).": ".$obj->container." ".$obj->url."\">$filename</span> <a href=\"".BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=filestats'.AMP.'file_id='.$obj->file_id."\" title=\"".$this->EE->lang->line('view_stats')."\"><img src=\"".$this->EE->config->slash_item('theme_folder_url')."third_party/protected_links/stats.png\" alt=\"".$this->EE->lang->line('view_stats')."\"></a>";
           $vars['protected_files'][$i]['url'] = $this->EE->localize->decode_date("%Y-%m-%d %H:%i", $obj->dl_date);
           $vars['protected_files'][$i]['dl_date'] = $obj->ip;
           
            
           $i++;
        }
        
        
        
        $this->EE->load->library('pagination');
        
        $this->EE->db->select('COUNT(*) AS count');
        $this->EE->db->from('protected_links_stats');
        $this->EE->db->where('member_id', $this->EE->input->get_post('member_id'));
        if ($vars['selected']['date_from']!='')
        {
            $this->EE->db->where('dl_date >=', $this->EE->localize->convert_human_date_to_gmt($vars['selected']['date_from']));
        }
        if ($vars['selected']['date_to']!='')
        {
            $this->EE->db->where('dl_date <=', $this->EE->localize->convert_human_date_to_gmt($vars['selected']['date_to']));
        }
        $query = $this->EE->db->get();
        
        $p_config = $this->_p_config(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=memberstats'.AMP.'member_id='.$this->EE->input->get_post('member_id'), $query->row('count'));

		$this->EE->pagination->initialize($p_config);
        
		$vars['pagination'] = $this->EE->pagination->create_links();
        
        $this->EE->cp->set_breadcrumb(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links', lang('protected_links_module_name'));
        $this->EE->cp->set_breadcrumb(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=members', lang('members'));
        $this->EE->cp->set_variable('cp_page_title', $vars['selected']['screen_name']);
        
        $this->EE->cp->set_right_nav(array(
		            'generate' => BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=edit')
		        );
        
    	return $this->EE->load->view('memberstats', $vars, TRUE);
	
    }    
    
    
    
    
    function members()
    {
    	$this->EE->load->library('table');  

    	$vars = array();
        
        $vars['selected']['rownum']=($this->EE->input->get_post('rownum')!='')?$this->EE->input->get_post('rownum'):0;
        
        //$this->EE->db->distinct();
        $this->EE->db->select('exp_protected_links_stats.member_id, screen_name, COUNT(dl_id) AS dl_count');
        $this->EE->db->from('protected_links_stats');
        $this->EE->db->join('members', 'exp_protected_links_stats.member_id=exp_members.member_id', 'left');
        if ($this->EE->input->get_post('search')!==false && strlen($this->EE->input->get_post('search'))>2)
        {
            $vars['selected']['search']=$this->EE->input->get_post('search');
            $this->EE->db->where('username LIKE "%'.$this->EE->db->escape_str($this->EE->input->get_post('search')).'%" OR screen_name LIKE "%'.$this->EE->db->escape_str($this->EE->input->get_post('search')).'%" OR email LIKE "%'.$this->EE->db->escape_str($this->EE->input->get_post('search')).'%" ');
        }
        else
        {
            $vars['selected']['search']='';
        }
        $this->EE->db->order_by('screen_name', 'asc');
        $this->EE->db->group_by('exp_protected_links_stats.member_id');

        $this->EE->db->limit($this->perpage, $vars['selected']['rownum']);

        $query = $this->EE->db->get();
        $vars['total_count'] = ($query->num_rows()==0)?$query->num_rows():$query->row('dl_count');
        //exit();
        
        $i = $vars['selected']['rownum']+1;
        $vars['table_headings'] = array(
                        $this->EE->lang->line('screen_name'),
                        $this->EE->lang->line('dl_count'),
                        ''
                    );
                    
        if ($vars['total_count']>0)
        {      
            foreach ($query->result() as $obj)
            {
               $vars['protected_files'][$i]['screen_name'] = ($obj->member_id!=0)?"<a href=\"".BASE.AMP.'D=cp'.AMP.'C=myaccount'.AMP.'id='.$obj->member_id."\">".$obj->screen_name."</a>":$this->EE->lang->line('guest');
               $vars['protected_files'][$i]['dl_count'] = $obj->dl_count;
               
               $vars['protected_files'][$i]['view_stats'] = "<a href=\"".BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=memberstats'.AMP.'member_id='.$obj->member_id."\" title=\"".$this->EE->lang->line('view_stats')."\"><img src=\"".$this->EE->config->slash_item('theme_folder_url')."third_party/protected_links/stats.png\" alt=\"".$this->EE->lang->line('view_stats')."\"></a>";
               
               $i++;
            }
        }
        
        
        $this->EE->load->library('pagination');
        
        $this->EE->db->select('member_id');
        $this->EE->db->from('exp_protected_links_stats');
        $this->EE->db->group_by('exp_protected_links_stats.member_id');
        
        $base_url = BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=members';
        if ($vars['selected']['search']!='')
        {
           $base_url .= AMP.'search='.$this->EE->input->get_post('search');
           $this->EE->db->join('members', 'exp_protected_links_stats.member_id=exp_members.member_id', 'left');
           $this->EE->db->where('username LIKE "%'.$this->EE->db->escape_str($this->EE->input->get_post('search')).'%" OR screen_name LIKE "%'.$this->EE->db->escape_str($this->EE->input->get_post('search')).'%" OR email LIKE "%'.$this->EE->db->escape_str($this->EE->input->get_post('search')).'%" ');
        }
        $q = $this->EE->db->get();
        $p_config = $this->_p_config($base_url, $q->num_rows());  
            

		$this->EE->pagination->initialize($p_config);
        
		$vars['pagination'] = $this->EE->pagination->create_links();
        
        $this->EE->cp->set_breadcrumb(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links', lang('protected_links_module_name'));
        $this->EE->cp->set_variable('cp_page_title', lang('protected_links_module_name').' - '.lang('members'));
        
        $this->EE->cp->set_right_nav(array(
		            'generate' => BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=edit')
		        );
        
    	return $this->EE->load->view('members', $vars, TRUE);
	
    }    
    
    
    
    
    function edit()
    {
    	$this->EE->load->helper('form');
    	$this->EE->load->library('table');  
        $this->EE->load->library('javascript');
        
        //$date_format_picker = 'yy-mm-dd';
        
        $js = "date_obj = new Date();
date_obj_hours = date_obj.getHours();
date_obj_mins = date_obj.getMinutes();

date_obj_am_pm = (date_obj_hours < 12) ? 'AM': 'PM';

// This turns midnight into 12 AM, so ignore if it's already 0
if (date_obj_hours != 0) {
    date_obj_hours = ((date_obj_hours + 11) % 12) + 1;
}  

date_obj_time = \"' \"+date_obj_hours+\":\"+date_obj_mins+\" \"+date_obj_am_pm+\"'\";";
        $this->EE->javascript->output($js);

        $this->EE->cp->add_js_script('ui', 'datepicker'); 
        $this->EE->javascript->output(' $("input[name=expires]").datepicker({ dateFormat: $.datepicker.W3C + date_obj_time }); '); 
        $this->EE->javascript->compile(); 

    	$vars = array();

        $filetypes = array(
                        'video/x-ms-asf'  =>  'asf',
                        'video/x-msvideo'  =>  'avi',
                        'application/octet-stream'  =>  'exe',
                        'video/quicktime'  =>  'mov',
                        'audio/mpeg'  =>  'mp3',
                        'video/mpeg'  =>  'mpg',
                        'video/mpeg'  =>  'mpeg',
                        'application/pdf'  =>  'pdf',
                        'application/x-rar-compressed'  =>  'rar',
                        'text/plain'  =>  'txt',
                        'text/html'  =>  'html',
                        'audio/wave'  =>  'wav',
                        'audio/x-ms-wma'  =>  'wma',
                        'video/x-ms-wmv'  =>  'wmv',
                        'application/x-zip-compressed'  =>  'zip',
                        'application/force-download'  =>  'Force download'
                    );
                    
        
        $yesno = array(
                                    'y' => $this->EE->lang->line('yes'),
                                    'n' => $this->EE->lang->line('no')
                                );
        $storages = array(
                                    'url' => $this->EE->lang->line('url'),
                                    'local' => $this->EE->lang->line('local'),
                                    's3' => $this->EE->lang->line('s3'),
                                    'rackspace' => $this->EE->lang->line('rackspace')
                                );
        
        $member_groups = array();
        $member_groups[''] = '';
        $this->EE->db->select('group_id, group_title');
        $this->EE->db->where('group_id NOT IN (1,2,3)');
        $q = $this->EE->db->get('member_groups');
        foreach ($q->result() as $obj)
        {
            $member_groups[$obj->group_id] = $obj->group_title;
        }
        
        $custom_fields = array();
        $custom_fields[''] = '';
        $this->EE->db->select('m_field_id, m_field_label');
        $this->EE->db->order_by('m_field_order', 'asc');
        $q = $this->EE->db->get('exp_member_fields');
        foreach ($q->result() as $obj)
        {
            $custom_fields[$obj->m_field_id] = $obj->m_field_label;
        }
        
        $endpoints = array(
			''									=>	lang('us'),
			's3-us-west-1.amazonaws.com'		=>	lang('us_west_1'),
			's3-us-west-2.amazonaws.com'		=>	lang('us_west_2'),
			's3-eu-west-1.amazonaws.com'		=>	lang('eu'),
			's3-ap-southeast-1.amazonaws.com'	=>	lang('ap_southeast_1'),
			's3-ap-southeast-2.amazonaws.com'	=>	lang('ap_southeast_2'),
			's3-ap-northeast-1.amazonaws.com'	=>	lang('ap_northeast_1'),
			's3-sa-east-1.amazonaws.com'		=>	lang('sa_east_1')
		);
 
        $link_id = intval($this->EE->input->get('link_id'));
        if ($link_id!=0)
        {
            $q = $this->EE->db->query("SELECT storage, container, endpoint, url, description, exp_protected_links_links.* FROM exp_protected_links_links LEFT JOIN exp_protected_links_files ON exp_protected_links_links.file_id=exp_protected_links_files.file_id WHERE link_id=$link_id");
            //a trick to make selects 'multiple'
            $group_access = explode("|", $q->row('group_access'));
            //array_push($group_access, array(0,-1));
            $act = $this->EE->db->query("SELECT action_id FROM exp_actions WHERE class='Protected_links' AND method='process'");
            $url = $this->EE->config->item('site_url')."?ACT=".$act->row('action_id')."&key=".$q->row('accesskey');
            if ($q->row('custom_access_rules')!='')
            {
                $custom_access_arr = unserialize($q->row('custom_access_rules'));
                foreach ($custom_access_arr as $field=>$value)
                {
                    $custom_profile_field = $field;
                    $custom_profile_value = $value;
                }
            }
            else
            {
                $custom_profile_field = $custom_profile_value = '';
            }
            $vars['data'] = array(	
                ''	=> form_hidden('link_id', $q->row('link_id')).form_hidden('file_id', $q->row('file_id')).$url,
                'storage'	=> form_dropdown('storage', $storages, $q->row('storage')),
                'container'	=> form_input('container', $q->row('container'), 'style="width: 95%"'),
                'endpoint'	=> form_dropdown('endpoint', $endpoints, $q->row('endpoint')),
                'url_or_path'	=> form_input('url', $q->row('url'), 'style="width: 95%"').$this->_file_select('pl_file_selector'),
                'filename'	=> form_input('filename', $q->row('filename'), 'style="width: 95%"'),
                'title'	=> form_input('title', $q->row('title'), 'style="width: 95%"'),
                'description'	=> form_textarea('description', $q->row('description')),
                'filetype'	=> form_dropdown('type', $filetypes, $q->row('type')),
                'guest_access'	=> form_dropdown('guest_access', $yesno, $q->row('guest_access')),
                'deny_hotlink'	=> form_dropdown('deny_hotlink', $yesno, $q->row('deny_hotlink')),
                'display_inline' => form_dropdown('inline', $yesno, $q->row('inline')),
                'group_access'	=> form_multiselect('group_access[]', $member_groups, $group_access),
                'expires'	=> form_input('expires', $q->row('expires')),
                'member_limit'	=> form_input('member_limit', $q->row('member_limit')),
                'use_backend'	=> form_dropdown('use_backend', $yesno, $q->row('use_backend')),
                'limit_access_by_custom_field'	=> form_dropdown('custom_profile_field', $custom_fields, $custom_profile_field),
                'custom_field_value_to_access'	=> form_input('custom_profile_value', $custom_profile_value)
        		);
        		
      		if (!in_array($q->row('storage'), array('s3', 'rackspace')))
	        {
	        	$this->EE->javascript->output('$("input[name=container]").parent().parent().hide();');
				if ($q->row('storage')!='s3')
				{
					$this->EE->javascript->output('$("select[name=endpoint]").parent().parent().hide();');
				}
	        }
        }
        else
        {
            $group_access = array();
            //array_push($group_access, array(0,0));
            $vars['data'] = array(	
                ''	=> form_hidden('link_id', ''),
                'storage'	=> form_dropdown('storage', $storages, 'url'),
                'container'	=> form_input('container', '', 'style="width: 95%"'),
                'endpoint'	=> form_dropdown('endpoint', $endpoints, ''),
                'url_or_path'	=> form_input('url', '', 'style="width: 95%"').$this->_file_select('pl_file_selector'),
                'filename'	=> form_input('filename', '', 'style="width: 95%"'),
                'title'	=> form_input('title', '', 'style="width: 95%"'),
                'description'	=> form_textarea('description', ''),
                'filetype'	=> form_dropdown('type', $filetypes, 'application/force-download'),
                'guest_access'	=> form_dropdown('guest_access', $yesno, 'y'),
                'deny_hotlink'	=> form_dropdown('deny_hotlink', $yesno, 'n'),
                'display_inline' => form_dropdown('inline', $yesno, 'n'),
                'group_access'	=> form_multiselect('group_access[]', $member_groups, $group_access),
                'expires'	=> form_input('expires', ''),
                'member_limit'	=> form_input('member_limit', ''),
                'use_backend'	=> form_dropdown('use_backend', $yesno, 'y'),
                'limit_access_by_custom_field'	=> form_dropdown('custom_profile_field', $custom_fields, ''),
                'custom_field_value_to_access'	=> form_input('custom_profile_value', '')
        		);
        		
      		$this->EE->javascript->output('$("input[name=container]").parent().parent().hide();');
      		$this->EE->javascript->output('$("select[name=endpoint]").parent().parent().hide();');
        }
        
        $this->EE->javascript->output("
			$('select[name=storage]').change(function() {
				if ($(this).val()=='s3') {
					$('input[name=container]').parent().parent().show();
					$('select[name=endpoint]').parent().parent().show();
				} else {
					if ($(this).val()=='rackspace') {
						$('input[name=container]').parent().parent().show();
					} else {
						$('input[name=container]').parent().parent().hide();
					}
					$('select[name=endpoint]').parent().parent().hide();
				}
			});
		");
        

		$this->EE->javascript->compile();


        $this->EE->cp->set_breadcrumb(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links', lang('protected_links_module_name'));
        if ($link_id!=0)
        {
            $this->EE->cp->set_variable('cp_page_title', lang('edit_link'));
        }
        else
        {
            $this->EE->cp->set_variable('cp_page_title', lang('create_link'));
        }
        
        
        $this->EE->cp->set_right_nav(array(
		            'generate' => BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=edit')
		        );
        
    	return $this->EE->load->view('edit', $vars, TRUE);
	
    }
    
    
    function settings()
    {

        $this->EE->load->helper('form');
    	$this->EE->load->library('table');
 
        $vars['settings'] = array(	
            's3_key_id'	=> form_input('s3_key_id', $this->settings['s3_key_id']),
            's3_key_value'	=> form_input('s3_key_value', $this->settings['s3_key_value']),
            'rackspace_api_login'	=> form_input('rackspace_api_login', $this->settings['rackspace_api_login']),
            'rackspace_api_password'	=> form_input('rackspace_api_password', $this->settings['rackspace_api_password'])
    		);
    	
        $this->EE->cp->set_variable('cp_page_title', lang('protected_links_module_name').' - '.lang('settings'));
        
        $this->EE->cp->set_right_nav(array(
		            'generate' => BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=edit')
		        );
        
    	return $this->EE->load->view('settings', $vars, TRUE);
	
    }    
    
    function save_settings()
    {
        
        $settings['s3_key_id'] = (isset($_POST['s3_key_id']))?$this->EE->security->xss_clean($_POST['s3_key_id']):'';
        $settings['s3_key_value'] = (isset($_POST['s3_key_value']))?$this->EE->security->xss_clean($_POST['s3_key_value']):'';
        $settings['rackspace_api_login'] = (isset($_POST['rackspace_api_login']))?$this->EE->security->xss_clean($_POST['rackspace_api_login']):'';
        $settings['rackspace_api_password'] = (isset($_POST['rackspace_api_password']))?$this->EE->security->xss_clean($_POST['rackspace_api_password']):'';

        $this->EE->db->where('module_name', 'Protected_links');
        $this->EE->db->update('modules', array('settings' => serialize($settings)));
        $this->EE->session->set_flashdata('message_success', $this->EE->lang->line('updated'));
        
        $this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=settings');
    }
    
    
    function save()
    {

        //if (empty($_POST['url']) || strpos($_POST['url'], 'http')!==0)
        if (empty($_POST['url']))
        {
            $this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('missing_url'));
            $this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=index');
            return false;
        }
        
        $filedata['url'] = $this->EE->input->get_post('url');
        if (empty($_POST['link_id']))
        {
            $data['accesskey'] = $this->_generate_key();
        }
        $data['cp_generated'] = 'y';
        
        if (!empty($_POST['filename']))
        {
            $data['filename'] = $this->EE->input->get_post('filename');
        }
        else
        {
            $url_arr = explode("/", $filedata['url']);
            $data['filename'] = $url_arr[(count($url_arr)-1)];
        }
        
        if (!empty($_POST['title']))
        {
            $data['title'] = $this->EE->input->get_post('title');
        }
        else
        {
            $data['title'] = $data['filename'];
        }
        
        if (!empty($_POST['description']))
        {
            $data['description'] = $this->EE->input->get_post('description');
        }
        
        if (!empty($_POST['storage']))
        {
            $filedata['storage'] = $this->EE->input->get_post('storage');
        }
        
        if (!empty($_POST['container']))
        {
            $filedata['container'] = $this->EE->input->get_post('container');
        }
        
        if (!empty($_POST['endpoint']))
        {
            $filedata['endpoint'] = $this->EE->input->get_post('endpoint');
        }
        
        if (!empty($_POST['type']))
        {
            $data['type'] = $this->EE->input->get_post('type');
        }
        
        if (!empty($_POST['guest_access']))
        {
            $data['guest_access'] = $this->EE->input->get_post('guest_access');
        }
        
        if (!empty($_POST['deny_hotlink']))
        {
            $data['deny_hotlink'] = $this->EE->input->get_post('deny_hotlink');
        }
        
        if (!empty($_POST['inline']))
        {
            $data['inline'] = $this->EE->input->get_post('inline');
        }
        
        if (!empty($_POST['group_access']))
        {
            $data['group_access'] = implode("|",$_POST['group_access']);
        }
        else
        {
            $data['group_access'] = '';
        }
        
        if (!empty($_POST['member_limit']))
        {
            $data['member_limit'] = $this->EE->input->get_post('member_limit');
        }
        else
        {
            $data['member_limit'] = NULL;
        }

        
        if (!empty($_POST['expires']))
        {
            $data['expires'] = $this->EE->localize->convert_human_date_to_gmt($this->EE->input->get_post('expires'));
        }
        else
        {
            $data['expires'] = NULL;
        }
        
        if (!empty($_POST['use_backend']))
        {
            $data['use_backend'] = $this->EE->input->get_post('use_backend');
        }
        
        if (!empty($_POST['custom_profile_field']))
        {
            $custom_access = array($this->EE->input->get_post('custom_profile_field')=>$this->EE->input->get_post('custom_profile_value'));
            $data['custom_access_rules'] = serialize($custom_access);
        }
        else
        {
            $data['custom_access_rules'] = '';
        }
        
        if (!empty($_POST['link_id']))
        {
            $this->EE->db->where('file_id', $this->EE->input->post('file_id'));
            $this->EE->db->update('protected_links_files', $filedata);
            $this->EE->db->where('link_id', $this->EE->input->post('link_id'));
            $this->EE->db->update('protected_links_links', $data);
        }
        else
        {
            //does file exist?
            $this->EE->db->select('file_id');
            $this->EE->db->from('protected_links_files');
            if (isset($filedata['storage']))
            {
                $this->EE->db->where('storage', "{$filedata['storage']}");
            }
            if (isset($filedata['container']))
            {
                $this->EE->db->where('container', "{$filedata['container']}");
            }
            $this->EE->db->where('url', "{$filedata['url']}");
            $this->EE->db->limit(1);
            $q = $this->EE->db->get();
            if ($q->num_rows()==0)
            {
                $this->EE->db->insert('protected_links_files', $filedata);
                $data['file_id'] = $this->EE->db->insert_id();
            }
            else
            {
                $data['file_id'] = $q->row('file_id');
            }
            
            $data['link_date'] = $this->EE->localize->now;
            $this->EE->db->insert('protected_links_links', $data);
        }
        
        //var_dump($data);
        
        $this->EE->session->set_flashdata('message_success', $this->EE->lang->line('updated'));        
        $this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=links');
        
        
    }
    
    function deletelink()
    {

        if (!empty($_GET['link_id']))
        {
            $this->EE->db->where('link_id', $this->EE->input->get_post('link_id'));
            $this->EE->db->delete('protected_links_stats');
			
			$this->EE->db->where('link_id', $this->EE->input->get_post('link_id'));
            $this->EE->db->delete('protected_links_links');
            if ($this->EE->db->affected_rows()>0)
            {
                $this->EE->session->set_flashdata('message_success', $this->EE->lang->line('link_deleted')); 
            }
            else
            {
                $this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('no_link_to_delete'));  
            }
            
        }
        else 
        {
            $this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('no_link_to_delete'));  
        }

        $this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=links');
        
        
    }
    
    
    function deletefile()
    {

        if (!empty($_GET['file_id']))
        {
            $this->EE->db->where('file_id', $this->EE->input->get_post('file_id'));
            $this->EE->db->delete('protected_links_stats');
            
            $this->EE->db->where('file_id', $this->EE->input->get_post('file_id'));
            $this->EE->db->delete('protected_links_links');
            
            $this->EE->db->where('file_id', $this->EE->input->get_post('file_id'));
            $this->EE->db->delete('protected_links_files');
            
            if ($this->EE->db->affected_rows()>0)
            {
                $this->EE->session->set_flashdata('message_success', $this->EE->lang->line('file_deleted')); 
            }
            else
            {
                $this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('no_file_to_delete'));  
            }
            
        }
        else 
        {
            $this->EE->session->set_flashdata('message_failure', $this->EE->lang->line('no_file_to_delete'));  
        }

        $this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=protected_links'.AMP.'method=index');
        
        
    }

    
    
    function _p_config($base_url, $total_rows)
    {
        $p_config = array();
        $p_config['base_url'] = $base_url;
        $p_config['total_rows'] = $total_rows;
		$p_config['per_page'] = $this->perpage;
		$p_config['page_query_string'] = TRUE;
		$p_config['query_string_segment'] = 'rownum';
		$p_config['full_tag_open'] = '<p id="paginationLinks">';
		$p_config['full_tag_close'] = '</p>';
		$p_config['prev_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_prev_button.gif" width="13" height="13" alt="&lt;" />';
		$p_config['next_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_next_button.gif" width="13" height="13" alt="&gt;" />';
		$p_config['first_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_first_button.gif" width="13" height="13" alt="&lt; &lt;" />';
		$p_config['last_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_last_button.gif" width="13" height="13" alt="&gt; &gt;" />';
        return $p_config;
    }
    
    
    function _generate_key($length = 16, $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890')
    {
        // Length of character list
        $chars_length = (strlen($chars) - 1);
    
        // Start our string
        $string = $chars[rand(0, $chars_length)];
        
        // Generate random string
        for ($i = 1; $i < $length; $i++)
        {
            // Grab a random character from our list
            $r = $chars[rand(0, $chars_length)];
            
            // Make sure the same two characters don't appear next to each other
            //if ($r != $string{$i - 1}) $string .=  $r;
            $string .=  $r;
        }
        
        $q = $this->EE->db->query("SELECT link_id FROM exp_protected_links_links WHERE `accesskey`='".$string."'");
        if ($q->num_rows>0)
        {
            $string = $this->_generate_key();
        }
        
        // Return the string
        return $string;
    }     
    
    
    function _file_select($field_name)
	{
		$ee_version = '2.'.str_replace('.', '', substr(APP_VER, 2));
		if ($ee_version < 2.20)
		{
			return;
		}
		
		$this->EE->lang->loadfile('fieldtypes');  
	        
        $this->EE->load->model('file_upload_preferences_model');
		
		if ($ee_version < 2.40)
		{
			$upload_directories = $this->EE->file_upload_preferences_model->get_upload_preferences($this->EE->session->userdata('group_id'), '');
		}
		else
		{
			$upload_directories = $this->EE->file_upload_preferences_model->get_file_upload_preferences($this->EE->session->userdata('group_id'));
		}
		
		if (count($upload_directories) == 0) return '';
		
		foreach($upload_directories as $row)
		{
			$upload_dirs[$row['id']] = $row['name'];
		}
        
        if (count($upload_dirs) == 0) return '';
        
        $this->EE->load->library('filemanager');
		
		if ($ee_version < 2.40)
		{
	        $this->EE->filemanager->filebrowser('C=content_publish&M=filemanager_actions');   
		}
		else
		{
			$this->EE->lang->loadfile('content');
			
			// Include dependencies
			$this->EE->cp->add_js_script(array(
				'plugin'    => array('scrollable', 'scrollable.navigator', 'ee_filebrowser', 'ee_fileuploader', 'tmpl', 'ee_table')
			));
			
			$this->EE->load->helper('html');
			
			$this->EE->javascript->set_global(array(
				'lang' => array(
					'resize_image'		=> $this->EE->lang->line('resize_image'),
					'or'				=> $this->EE->lang->line('or'),
					'return_to_publish'	=> $this->EE->lang->line('return_to_publish')
				),
				'filebrowser' => array(
					'endpoint_url'		=> 'C=content_publish&M=filemanager_actions',
					'window_title'		=> lang('file_manager'),
					'next'				=> anchor(
						'#', 
						img(
							$this->EE->cp->cp_theme_url . 'images/pagination_next_button.gif',
							array(
								'alt' => lang('next'),
								'width' => 13,
								'height' => 13
							)
						),
						array(
							'class' => 'next'
						)
					),
					'previous'			=> anchor(
						'#', 
						img(
							$this->EE->cp->cp_theme_url . 'images/pagination_prev_button.gif',
							array(
								'alt' => lang('previous'),
								'width' => 13,
								'height' => 13
							)
						),
						array(
							'class' => 'previous'
						)
					)
				),
				'fileuploader' => array(
					'window_title'		=> lang('file_upload'),
					'delete_url'		=> 'C=content_files&M=delete_files'
				)
			));
		}
	  
		$r = "<a href=\"#\" class=\"choose_file\"\ title=\"".$this->EE->lang->line('select_upload_file')."\"><img src=\"".$this->EE->cp->cp_theme_url."images/icon-create-upload-file.png\" alt=\"".$this->EE->lang->line('select_upload_file')."\"></a>";
        
        $r .= "<script type=\"text/javascript\">
        $(document).ready(function(){
        	var e = !1;
	$.ee_filebrowser();
	$(\"input[name=url]\", \"#protected_links_generate_form\").each(function () {
		var a = $(this).closest(\"td\"),
			b = a.find(\".choose_file\"),
			e = 'all',
			f = 'all',
			e = {
				content_type: e,
				directory: f
			};
		$.ee_filebrowser.add_trigger(b, $(this).attr(\"name\"), e, c);
	});
    function c(a, d) {
		$(\"input[name=url]\").val(a.rel_path);
	}
});
</script>";
		return $r;
		
		
	}
       

}
/* END */
?>